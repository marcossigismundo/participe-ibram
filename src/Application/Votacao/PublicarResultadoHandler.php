<?php
/**
 * Handler: publicar resultado da apuração.
 *
 * @package Ibram\ParticipeIbram\Application\Votacao
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Votacao;

use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Domain\Votacao\IllegalStateTransition;
use Ibram\ParticipeIbram\Domain\Votacao\Resultado;
use Ibram\ParticipeIbram\Domain\Votacao\ResultadoRepository;
use Ibram\ParticipeIbram\Domain\Votacao\VotacaoRepository;

/**
 * Caso de uso: publicar resultado de votação já apurada.
 *
 * Não altera dados — apenas:
 *  1. Verifica que votação está com status `apurada`.
 *  2. Monta estrutura de relatório em array (Presentation renderiza).
 *  3. Audit log.
 *  4. Hook `pi_resultado_publicado` para fila de e-mails (TD-13) e snapshot
 *     no site Ibram (TD-05/TD-06).
 *
 * @phpstan-type RelatorioCategoria array{
 *   categoria_id: int,
 *   total_votos_categoria: int,
 *   eleitos: list<array{candidato_inscricao_id:int,total_votos:int,posicao:int}>,
 *   suplentes: list<array{candidato_inscricao_id:int,total_votos:int,posicao:int}>,
 *   demais: list<array{candidato_inscricao_id:int,total_votos:int,posicao:int}>
 * }
 */
final class PublicarResultadoHandler
{
    private VotacaoRepository $votacaoRepo;

    private ResultadoRepository $resultadoRepo;

    private AuditLogger $audit;

    public function __construct(
        VotacaoRepository $votacaoRepo,
        ResultadoRepository $resultadoRepo,
        AuditLogger $audit
    ) {
        $this->votacaoRepo   = $votacaoRepo;
        $this->resultadoRepo = $resultadoRepo;
        $this->audit         = $audit;
    }

    /**
     * @return array{
     *   votacao_id: int,
     *   edital_id: int,
     *   apurado_em: ?string,
     *   hash_pre_apuracao: ?string,
     *   categorias: list<array<string,mixed>>
     * }
     */
    public function handle(PublicarResultadoCommand $command): array
    {
        $votacao = $this->votacaoRepo->findById($command->votacaoId());

        if (!$votacao->status()->isApurada()) {
            throw new IllegalStateTransition(
                'Resultado so pode ser publicado para votacoes apuradas.'
            );
        }

        $resultados = $this->resultadoRepo->findByVotacao($command->votacaoId());

        // Agrupa por categoria.
        $porCategoria = [];
        foreach ($resultados as $r) {
            $catId = $r->categoriaId();
            if (!isset($porCategoria[$catId])) {
                $porCategoria[$catId] = [
                    'eleitos'   => [],
                    'suplentes' => [],
                    'demais'    => [],
                    'total_votos_categoria' => 0,
                ];
            }

            $entry = [
                'candidato_inscricao_id' => $r->candidatoInscricaoId(),
                'total_votos'            => $r->totalVotos(),
                'posicao'                => $r->posicao(),
            ];

            if ($r->eleito()) {
                $porCategoria[$catId]['eleitos'][] = $entry;
            } elseif ($r->suplente()) {
                $porCategoria[$catId]['suplentes'][] = $entry;
            } else {
                $porCategoria[$catId]['demais'][] = $entry;
            }
            $porCategoria[$catId]['total_votos_categoria'] += $r->totalVotos();
        }

        $categorias = [];
        foreach ($porCategoria as $catId => $bloco) {
            $categorias[] = [
                'categoria_id'         => $catId,
                'total_votos_categoria' => $bloco['total_votos_categoria'],
                'eleitos'              => $bloco['eleitos'],
                'suplentes'            => $bloco['suplentes'],
                'demais'               => $bloco['demais'],
            ];
        }

        $relatorio = [
            'votacao_id'        => (int) $votacao->id(),
            'edital_id'         => $votacao->editalId(),
            'apurado_em'        => $votacao->apuradoEm() !== null
                ? $votacao->apuradoEm()->format(\DateTimeInterface::ATOM)
                : null,
            'hash_pre_apuracao' => $votacao->hashPreApuracao(),
            'categorias'        => $categorias,
        ];

        $this->audit->log(
            'resultado',
            $votacao->id(),
            'publicar_resultado',
            null,
            [
                'votacao_id'    => $votacao->id(),
                'qtd_categorias' => count($categorias),
            ],
            $command->atorId()
        );

        if (function_exists('do_action')) {
            do_action('pi_resultado_publicado', $votacao->id(), $relatorio);
        }

        return $relatorio;
    }
}
