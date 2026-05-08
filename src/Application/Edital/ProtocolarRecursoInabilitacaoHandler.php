<?php
/**
 * Handler para protocolo de recurso contra inabilitação.
 *
 * @package Ibram\ParticipeIbram\Application\Edital
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Edital;

use DateTimeImmutable;
use DomainException;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Domain\Edital\RecursoInabilitacao;
use Ibram\ParticipeIbram\Domain\Edital\StatusInscricao;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbEditalRepository;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbInscricaoRepository;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbRecursoInabilitacaoRepository;
use InvalidArgumentException;

/**
 * Caso de uso "protocolar recurso de inabilitação".
 *
 * Validações:
 *  1. Inscrição existe e está em status `inabilitado`.
 *  2. Prazo: agora ≤ `prazo_recurso_inabilitacao` do edital.
 *  3. Não há recurso anterior protocolado (1 por inscrição).
 *  4. Cria {@see RecursoInabilitacao}, transiciona inscrição para `em_recurso`.
 *  5. Dispara `do_action('pi_recurso_inabilitacao_protocolado', $recursoId, $inscricaoId)`.
 */
final class ProtocolarRecursoInabilitacaoHandler
{
    private WpdbInscricaoRepository $inscricoesRepo;

    private WpdbEditalRepository $editaisRepo;

    private WpdbRecursoInabilitacaoRepository $recursosRepo;

    private AuditLogger $audit;

    public function __construct(
        WpdbInscricaoRepository $inscricoesRepo,
        WpdbEditalRepository $editaisRepo,
        WpdbRecursoInabilitacaoRepository $recursosRepo,
        AuditLogger $audit
    ) {
        $this->inscricoesRepo = $inscricoesRepo;
        $this->editaisRepo    = $editaisRepo;
        $this->recursosRepo   = $recursosRepo;
        $this->audit          = $audit;
    }

    /**
     * @return int ID do recurso criado.
     *
     * @throws DomainException
     */
    public function handle(int $inscricaoId, string $fundamentacaoMd, int $atorId): int
    {
        $fund = trim($fundamentacaoMd);
        if ($fund === '') {
            throw new InvalidArgumentException(
                __('Fundamentacao do recurso e obrigatoria.', 'participe-ibram')
            );
        }
        if ($atorId <= 0) {
            throw new InvalidArgumentException('Ator invalido.');
        }

        $inscricao = $this->inscricoesRepo->findById($inscricaoId);
        if ($inscricao === null) {
            throw new DomainException(sprintf(
                __('Inscricao %d nao encontrada.', 'participe-ibram'),
                $inscricaoId
            ));
        }
        if ($inscricao->status()->value() !== StatusInscricao::INABILITADO) {
            throw new DomainException(
                __('So inscricoes inabilitadas podem ter recurso protocolado.', 'participe-ibram')
            );
        }

        // Prazo: comparar com `prazo_recurso_inabilitacao` do edital.
        $edital = $this->editaisRepo->findById($inscricao->editalId());
        if ($edital === null) {
            throw new DomainException(sprintf(
                __('Edital %d nao encontrado.', 'participe-ibram'),
                $inscricao->editalId()
            ));
        }
        $prazo = $edital->prazoRecursoInabilitacao();
        if ($prazo === null) {
            throw new DomainException(
                __('Edital sem prazo de recurso de inabilitacao definido.', 'participe-ibram')
            );
        }
        $now = new DateTimeImmutable('now');
        if ($now > $prazo) {
            throw new DomainException(sprintf(
                __('Prazo para recurso de inabilitacao expirou em %s.', 'participe-ibram'),
                $prazo->format('Y-m-d H:i')
            ));
        }

        // Idempotência: 1 recurso por inscrição.
        if ($this->recursosRepo->findByInscricao($inscricaoId) !== null) {
            throw new DomainException(
                __('Ja existe recurso protocolado para esta inscricao.', 'participe-ibram')
            );
        }

        $recurso = RecursoInabilitacao::protocolar($inscricaoId, $fund);
        $recursoId = $this->recursosRepo->save($recurso);

        $inscricao->protocolarRecurso();
        $this->inscricoesRepo->save($inscricao);

        $this->audit->log(
            'recurso_inabilitacao',
            $recursoId,
            'protocolar',
            null,
            ['inscricao_id' => $inscricaoId],
            $atorId
        );

        if (function_exists('do_action')) {
            do_action('pi_recurso_inabilitacao_protocolado', $recursoId, $inscricaoId);
        }

        return $recursoId;
    }
}
