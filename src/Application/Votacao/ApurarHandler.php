<?php
/**
 * Handler: apurar a votação (encerrada → apurada) e gerar Resultado[].
 *
 * @package Ibram\ParticipeIbram\Application\Votacao
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Votacao;

use DateTimeImmutable;
use DateTimeZone;
use Ibram\ParticipeIbram\Application\Votacao\Ports\CategoriaConsultaGateway;
use Ibram\ParticipeIbram\Application\Votacao\Ports\InscricaoConsultaGateway;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Domain\Votacao\IllegalStateTransition;
use Ibram\ParticipeIbram\Domain\Votacao\Resultado;
use Ibram\ParticipeIbram\Domain\Votacao\ResultadoRepository;
use Ibram\ParticipeIbram\Domain\Votacao\Votacao;
use Ibram\ParticipeIbram\Domain\Votacao\VotacaoRepository;
use Ibram\ParticipeIbram\Domain\Votacao\VotoRepository;

/**
 * Caso de uso: apurar votação.
 *
 * Pré-condição: status = `encerrada`. Caso contrário, {@see Votacao::apurar()}
 * lança {@see IllegalStateTransition}.
 *
 * Algoritmo:
 *  1. Para cada categoria do edital da votação:
 *     a. Conta votos por candidato via {@see VotoRepository::contarPorCandidato()}.
 *     b. Ordena candidatos por:
 *        - `total_votos` DESC (mais votos primeiro);
 *        - **tie-break**: `inscrito_em` ASC (quem se inscreveu primeiro tem
 *          posição melhor em caso de empate). Justificativa: critério objetivo,
 *          documentado, reproduzível, não-aleatório, e alinhado ao princípio de
 *          "diligência" em concursos públicos federais. NÃO usamos sorteio nem
 *          ID interno de inscrição (que é volátil entre ambientes de migração).
 *        - tie-break secundário: `candidato_inscricao_id` ASC (estabilizador
 *          determinístico para o caso, improvável, de empates de `inscrito_em`).
 *     c. Marca os primeiros `numVagas` como eleitos; os próximos `numSuplentes`
 *        como suplentes; demais com flags falsas (mas com posição registrada
 *        para histórico).
 *  2. Persiste todos os Resultado[] em transação.
 *  3. Transiciona votação para `apurada`.
 *  4. Audit log.
 *
 * Tie-break — Documentação canônica:
 *  > Em caso de empate de `total_votos`, prevalece a ordem cronológica de
 *  > inscrição (inscrito_em ASC). Empate residual em inscrito_em é desempatado
 *  > por `candidato_inscricao_id` ASC. Esta regra é fixa e auditável.
 */
final class ApurarHandler
{
    private VotacaoRepository $votacaoRepo;

    private VotoRepository $votoRepo;

    private ResultadoRepository $resultadoRepo;

    private CategoriaConsultaGateway $categoriaGateway;

    private InscricaoConsultaGateway $inscricaoGateway;

    private AuditLogger $audit;

    /**
     * @var callable():DateTimeImmutable
     */
    private $clock;

    public function __construct(
        VotacaoRepository $votacaoRepo,
        VotoRepository $votoRepo,
        ResultadoRepository $resultadoRepo,
        CategoriaConsultaGateway $categoriaGateway,
        InscricaoConsultaGateway $inscricaoGateway,
        AuditLogger $audit,
        ?callable $clock = null
    ) {
        $this->votacaoRepo      = $votacaoRepo;
        $this->votoRepo         = $votoRepo;
        $this->resultadoRepo    = $resultadoRepo;
        $this->categoriaGateway = $categoriaGateway;
        $this->inscricaoGateway = $inscricaoGateway;
        $this->audit            = $audit;
        $this->clock            = $clock ?? static fn (): DateTimeImmutable
            => new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    /**
     * @return list<Resultado>
     *
     * @throws IllegalStateTransition
     */
    public function handle(ApurarCommand $command): array
    {
        $votacao = $this->votacaoRepo->findById($command->votacaoId());

        if (!$votacao->status()->isEncerrada()) {
            throw new IllegalStateTransition(
                'Votacao precisa estar com status "encerrada" para ser apurada.'
            );
        }

        $apuradoEm   = ($this->clock)();
        $categorias  = $this->categoriaGateway->listarCategoriasDoEdital($votacao->editalId());
        $resultados  = [];

        foreach ($categorias as $categoriaId) {
            $resultadosCategoria = $this->apurarCategoria(
                $votacao,
                $categoriaId,
                $apuradoEm
            );
            foreach ($resultadosCategoria as $r) {
                $resultados[] = $r;
            }
        }

        // Persiste em transação.
        $this->resultadoRepo->salvarResultados($votacao->id() ?? 0, $resultados);

        // Estado final.
        $votacao->apurar();
        $this->votacaoRepo->save($votacao);

        $this->audit->log(
            'votacao',
            $votacao->id(),
            'apurar',
            null,
            [
                'votacao_id'      => $votacao->id(),
                'qtd_categorias'  => count($categorias),
                'qtd_resultados'  => count($resultados),
                'apurado_em'      => $apuradoEm->format('Y-m-d H:i:s'),
            ],
            $command->atorId()
        );

        return $resultados;
    }

    /**
     * Apura uma categoria com tie-break documentado.
     *
     * @return list<Resultado>
     */
    private function apurarCategoria(
        Votacao $votacao,
        int $categoriaId,
        DateTimeImmutable $apuradoEm
    ): array {
        $contagem = $this->votoRepo->contarPorCandidato(
            (int) $votacao->id(),
            $categoriaId
        );
        if ($contagem === []) {
            return [];
        }

        // Constroi tuplas para ordenação canônica determinística.
        $tuplas = [];
        foreach ($contagem as $candidatoId => $total) {
            $inscritoEm   = $this->inscricaoGateway->inscritoEm($candidatoId);
            $tsDesempate  = $inscritoEm !== null
                ? $inscritoEm->getTimestamp()
                : PHP_INT_MAX;  // sem data: vai para o fim do desempate.
            $tuplas[] = [
                'candidato_id' => $candidatoId,
                'total'        => $total,
                'tsDesempate'  => $tsDesempate,
            ];
        }

        // Ordenação:
        //   1) total_votos DESC
        //   2) tsDesempate ASC (inscrito_em ASC — tie-break documentado)
        //   3) candidato_id ASC (estabilizador determinístico)
        usort($tuplas, static function (array $a, array $b): int {
            if ($a['total'] !== $b['total']) {
                return $b['total'] <=> $a['total'];
            }
            if ($a['tsDesempate'] !== $b['tsDesempate']) {
                return $a['tsDesempate'] <=> $b['tsDesempate'];
            }
            return $a['candidato_id'] <=> $b['candidato_id'];
        });

        $numVagas     = max(0, $this->categoriaGateway->numVagas($categoriaId));
        $numSuplentes = max(0, $this->categoriaGateway->numSuplentes($categoriaId));

        $resultados = [];
        $posicao    = 1;
        foreach ($tuplas as $tupla) {
            $eleito   = $posicao <= $numVagas;
            $suplente = !$eleito && $posicao <= ($numVagas + $numSuplentes);

            $resultados[] = new Resultado(
                null,
                (int) $votacao->id(),
                $categoriaId,
                $tupla['candidato_id'],
                $tupla['total'],
                $posicao,
                $eleito,
                $suplente,
                $apuradoEm
            );

            $posicao++;
        }

        return $resultados;
    }
}
