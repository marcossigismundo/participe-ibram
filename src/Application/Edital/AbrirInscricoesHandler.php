<?php
/**
 * Handler de abertura de inscrições do edital (publicado → inscricoes_abertas).
 *
 * @package Ibram\ParticipeIbram\Application\Edital
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Edital;

use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Domain\Edital\EditalNotFound;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbEditalRepository;

/**
 * Aplica a transição publicado → inscricoes_abertas e dispara
 * `do_action('pi_inscricoes_abertas', int $id)`.
 */
final class AbrirInscricoesHandler
{
    private WpdbEditalRepository $editaisRepo;

    private AuditLogger $audit;

    public function __construct(WpdbEditalRepository $editaisRepo, AuditLogger $audit)
    {
        $this->editaisRepo = $editaisRepo;
        $this->audit       = $audit;
    }

    /**
     * @throws EditalNotFound
     */
    public function handle(int $editalId, ?int $atorId = null): void
    {
        $edital = $this->editaisRepo->findById($editalId);
        if ($edital === null) {
            throw EditalNotFound::withId($editalId);
        }

        $edital->abrirInscricoes();
        $this->editaisRepo->save($edital);

        if (function_exists('do_action')) {
            do_action('pi_inscricoes_abertas', $editalId);
        }

        $this->audit->log('edital', $editalId, 'abrir_inscricoes', null, ['status' => 'inscricoes_abertas'], $atorId);
    }
}
