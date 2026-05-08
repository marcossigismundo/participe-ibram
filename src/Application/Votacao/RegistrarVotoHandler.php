<?php
/**
 * Handler: registrar um voto.
 *
 * @package Ibram\ParticipeIbram\Application\Votacao
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Votacao;

use DateTimeImmutable;
use DateTimeZone;
use Ibram\ParticipeIbram\Application\Votacao\Ports\AgenteVotanteGateway;
use Ibram\ParticipeIbram\Application\Votacao\Ports\CategoriaConsultaGateway;
use Ibram\ParticipeIbram\Application\Votacao\Ports\InscricaoConsultaGateway;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Core\Helpers\RateLimiter;
use Ibram\ParticipeIbram\Core\Network\IpResolver;
use Ibram\ParticipeIbram\Domain\Votacao\EleitorHasher;
use Ibram\ParticipeIbram\Domain\Votacao\EleitorInelegivel;
use Ibram\ParticipeIbram\Domain\Votacao\Voto;
use Ibram\ParticipeIbram\Domain\Votacao\VotacaoNaoAberta;
use Ibram\ParticipeIbram\Domain\Votacao\VotacaoRepository;
use Ibram\ParticipeIbram\Domain\Votacao\VotoDuplicado;
use Ibram\ParticipeIbram\Domain\Votacao\VotoRepository;
use RuntimeException;

/**
 * Caso de uso: validar e registrar um voto.
 *
 * Fluxo:
 *   1. Carrega Votação. Status precisa ser `aberta`. Janela temporal precisa
 *      conter o "agora".
 *   2. Categoria precisa pertencer ao edital da votação e aceitar o tipo do agente.
 *   3. Agente precisa estar deferido (qualquer das 3 variações).
 *   4. Calcula `eleitor_hash` via {@see EleitorHasher} (HMAC com secret separado).
 *   5. Aplica RateLimiter por `eleitor_hash` (3 req/60s) — proteção contra
 *      flood mesmo se o atacante tentar reidentificar.
 *   6. Candidato precisa ser uma inscrição com status `final_habilitado` na categoria.
 *   7. Repositório verifica não-duplicação (UNIQUE no banco).
 *   8. Cria {@see Voto} (imutável), anexa ipHash e persiste.
 *   9. Audit log SEM `agente_id` — apenas `categoria_id` e `eleitor_hash`
 *      (auditoria SEM rastreabilidade).
 *
 * @phpstan-type ClockFactory callable():DateTimeImmutable
 */
final class RegistrarVotoHandler
{
    private VotacaoRepository $votacaoRepo;

    private VotoRepository $votoRepo;

    private EleitorHasher $eleitorHasher;

    private AgenteVotanteGateway $agenteGateway;

    private CategoriaConsultaGateway $categoriaGateway;

    private InscricaoConsultaGateway $inscricaoGateway;

    private AuditLogger $audit;

    private ?IpResolver $ipResolver;

    /**
     * @var callable():DateTimeImmutable
     */
    private $clock;

    /**
     * @param ClockFactory|null $clock
     */
    public function __construct(
        VotacaoRepository $votacaoRepo,
        VotoRepository $votoRepo,
        EleitorHasher $eleitorHasher,
        AgenteVotanteGateway $agenteGateway,
        CategoriaConsultaGateway $categoriaGateway,
        InscricaoConsultaGateway $inscricaoGateway,
        AuditLogger $audit,
        ?IpResolver $ipResolver = null,
        ?callable $clock = null
    ) {
        $this->votacaoRepo      = $votacaoRepo;
        $this->votoRepo         = $votoRepo;
        $this->eleitorHasher    = $eleitorHasher;
        $this->agenteGateway    = $agenteGateway;
        $this->categoriaGateway = $categoriaGateway;
        $this->inscricaoGateway = $inscricaoGateway;
        $this->audit            = $audit;
        $this->ipResolver       = $ipResolver;
        $this->clock            = $clock ?? static fn (): DateTimeImmutable
            => new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    /**
     * @throws VotacaoNaoAberta
     * @throws EleitorInelegivel
     * @throws VotoDuplicado
     * @throws RuntimeException Em rate limit excedido.
     */
    public function handle(RegistrarVotoCommand $command): Voto
    {
        // 1. Votação aberta na janela.
        $votacao = $this->votacaoRepo->findById($command->votacaoId());
        if (!$votacao->status()->isAberta()) {
            throw VotacaoNaoAberta::paraVotacao(
                $command->votacaoId(),
                $votacao->status()->value()
            );
        }
        $now = ($this->clock)();
        if (!$votacao->dentroDaJanela($now)) {
            throw VotacaoNaoAberta::foraDaJanela($command->votacaoId());
        }

        // 2. Categoria pertence ao edital da votação.
        $editalDaCategoria = $this->categoriaGateway->editalIdDaCategoria($command->categoriaId());
        if ($editalDaCategoria === null || $editalDaCategoria !== $votacao->editalId()) {
            throw EleitorInelegivel::categoriaForaDoEdital(
                $command->votacaoId(),
                $command->categoriaId()
            );
        }

        // 3. Agente deferido + tipo aceito pela categoria.
        if (!$this->agenteGateway->estaDeferido($command->agenteId())) {
            throw EleitorInelegivel::naoDeferido($command->agenteId());
        }
        $tipoAgente = $this->agenteGateway->tipoAgente($command->agenteId());
        if ($tipoAgente === null) {
            throw EleitorInelegivel::naoDeferido($command->agenteId());
        }
        if (!$this->categoriaGateway->aceitaTipoAgente($command->categoriaId(), $tipoAgente)) {
            throw EleitorInelegivel::tipoNaoAdmitidoNaCategoria(
                $command->categoriaId(),
                $tipoAgente
            );
        }

        // 4. eleitor_hash canônico.
        $eleitorHash = $this->eleitorHasher->hash(
            $command->agenteId(),
            $command->votacaoId()
        );

        // 5. Rate limit por eleitor_hash (3 requisições / 60s).
        // Limite acima de 1 dá margem para retries por instabilidade de rede;
        // a UNIQUE no banco continua sendo o gatekeeper definitivo.
        $rateOk = RateLimiter::check(
            'pi_voto_' . $eleitorHash,
            3,
            60
        );
        if (!$rateOk) {
            throw new RuntimeException('Rate limit excedido para registro de voto.');
        }

        // 6. Candidato é inscrição final_habilitada na categoria.
        if (!$this->inscricaoGateway->isCandidatoFinalHabilitado(
            $command->candidatoInscricaoId(),
            $command->categoriaId()
        )) {
            throw new EleitorInelegivel(
                'Candidato nao esta com inscricao final_habilitado nesta categoria.'
            );
        }

        // 7. Não duplicado (consulta defensiva — UNIQUE protege a integridade).
        if ($this->votoRepo->existeVoto(
            $command->votacaoId(),
            $command->categoriaId(),
            $eleitorHash
        )) {
            throw VotoDuplicado::paraVotacaoCategoria(
                $command->votacaoId(),
                $command->categoriaId()
            );
        }

        // 8. Cria entidade imutável e anexa ipHash.
        $ipHash = $this->ipResolver !== null ? $this->ipResolver->resolveHash() : null;
        $voto   = new Voto(
            null,
            $command->votacaoId(),
            $command->categoriaId(),
            $eleitorHash,
            $command->candidatoInscricaoId(),
            $now,
            $ipHash
        );

        $newId = $this->votoRepo->salvarVoto($voto);
        $voto  = $voto->withId($newId);

        // 9. Audit log SEM agente_id — apenas dados anônimos.
        // CRITICAL: agente_id NUNCA aparece nos `dados_*` deste log; isso
        // garante que mesmo um leitor do audit_log com privilégios não consiga
        // associar votos a eleitores. eleitor_hash é mantido no log para que
        // auditores reconstruam a trilha consultando ESTE handler com o agente_id
        // — só quem tem o secret consegue.
        $this->audit->log(
            'voto',
            $newId,
            'voto_registrado',
            null,
            [
                'votacao_id'             => $command->votacaoId(),
                'categoria_id'           => $command->categoriaId(),
                'eleitor_hash'           => $eleitorHash,
                'candidato_inscricao_id' => $command->candidatoInscricaoId(),
                'votado_em'              => $now->format('Y-m-d H:i:s'),
            ],
            null  // ator_id null — não logar identidade do eleitor.
        );

        if (function_exists('do_action')) {
            do_action('pi_voto_registrado', $newId, $command->votacaoId(), $command->categoriaId());
        }

        return $voto;
    }
}
