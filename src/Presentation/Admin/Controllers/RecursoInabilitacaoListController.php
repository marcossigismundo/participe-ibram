<?php
/**
 * Controller admin — listagem de Recursos de Inabilitação (W5-C).
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\Controllers
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\Controllers;

use Ibram\ParticipeIbram\Core\Helpers\RequestHelper;
use Ibram\ParticipeIbram\Presentation\Admin\HabilitacaoMenuRegistry;
use Ibram\ParticipeIbram\Presentation\Admin\ListTables\RecursosInabilitacaoListTable;

/**
 * Renderiza a listagem e despacha para o detalhe de decisão.
 */
final class RecursoInabilitacaoListController
{
    private RecursoInabilitacaoDetalhesController $detalhesController;
    private string $templatesDir;

    /** @var \wpdb */
    private $wpdb;

    public function __construct(
        RecursoInabilitacaoDetalhesController $detalhesController,
        $wpdb,
        string $templatesDir
    ) {
        $this->detalhesController = $detalhesController;
        $this->wpdb               = $wpdb;
        $this->templatesDir       = rtrim($templatesDir, '/\\');
    }

    /**
     * Entrypoint do submenu.
     */
    public function dispatch(): void
    {
        $this->guardCap();

        $action    = (string) RequestHelper::get('action', 'sanitize_key', 'list');
        $recursoId = (int) RequestHelper::get('recurso_id', 'absint', 0);

        if ($action === 'view' && $recursoId > 0) {
            $this->detalhesController->render($recursoId);
            return;
        }

        $this->render();
    }

    public function render(): void
    {
        $this->guardCap();
        $listTable = new RecursosInabilitacaoListTable($this->wpdb);
        $listTable->prepare_items();

        $template = $this->templatesDir . '/recursos-inabilitacao/lista.php';
        if (!is_file($template)) {
            return;
        }
        /** @var \Ibram\ParticipeIbram\Presentation\Admin\ListTables\RecursosInabilitacaoListTable $listTable */
        include $template;
    }

    public function handlePostAction(): void
    {
        $page = (string) RequestHelper::get('page', 'sanitize_key', '');
        if ($page !== HabilitacaoMenuRegistry::SLUG_RECURSOS_INABILITACAO) {
            return;
        }
        $action    = (string) RequestHelper::get('action', 'sanitize_key', 'list');
        $recursoId = (int) RequestHelper::get('recurso_id', 'absint', 0);
        if ($action === 'view' && $recursoId > 0) {
            $this->detalhesController->handlePostAction();
        }
    }

    private function guardCap(): void
    {
        if (!function_exists('current_user_can') || !\current_user_can(HabilitacaoMenuRegistry::CAP_HABILITACAO)) {
            if (function_exists('wp_die')) {
                \wp_die(\esc_html__('Permissão negada.', 'participe-ibram'), 403);
            }
            throw new \RuntimeException('forbidden');
        }
    }
}
