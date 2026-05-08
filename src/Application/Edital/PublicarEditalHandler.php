<?php
/**
 * Handler de publicação de edital (rascunho → publicado).
 *
 * @package Ibram\ParticipeIbram\Application\Edital
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Edital;

use DomainException;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Domain\Edital\EditalNotFound;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbCategoriaRepository;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbEditalRepository;

/**
 * Orquestra o caso de uso "publicar edital":
 *  1. Carrega o edital pelo id.
 *  2. Valida que existe ≥ 1 categoria associada.
 *  3. Valida que a programação completa de datas está preenchida (delegado à entidade).
 *  4. Aplica a transição rascunho → publicado e persiste.
 *  5. Dispara `do_action('pi_edital_publicado', int $id)` para listeners.
 *  6. Auditoria é coberta pelo {@see WpdbEditalRepository::save()}.
 */
final class PublicarEditalHandler
{
    private WpdbEditalRepository $editaisRepo;

    private WpdbCategoriaRepository $categoriasRepo;

    private AuditLogger $audit;

    public function __construct(
        WpdbEditalRepository $editaisRepo,
        WpdbCategoriaRepository $categoriasRepo,
        AuditLogger $audit
    ) {
        $this->editaisRepo    = $editaisRepo;
        $this->categoriasRepo = $categoriasRepo;
        $this->audit          = $audit;
    }

    /**
     * @throws EditalNotFound
     * @throws DomainException
     */
    public function handle(int $editalId, ?int $atorId = null): void
    {
        $edital = $this->editaisRepo->findById($editalId);
        if ($edital === null) {
            throw EditalNotFound::withId($editalId);
        }

        $categorias = $this->categoriasRepo->findByEdital($editalId);
        if (count($categorias) < 1) {
            throw new DomainException(
                __('Edital nao pode ser publicado sem ao menos uma categoria.', 'participe-ibram')
            );
        }

        // Entidade valida internamente que datas estão completas.
        $edital->publicar();
        $this->editaisRepo->save($edital);

        if (function_exists('do_action')) {
            do_action('pi_edital_publicado', $editalId);
        }

        $this->audit->log('edital', $editalId, 'publicar', null, ['status' => 'publicado'], $atorId);
    }
}
