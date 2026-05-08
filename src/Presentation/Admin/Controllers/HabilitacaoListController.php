<?php
/**
 * Controller admin — listagem e detalhe de habilitação (W5-C).
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\Controllers
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\Controllers;

use Ibram\ParticipeIbram\Core\Helpers\RequestHelper;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbInscricaoRepository;
use Ibram\ParticipeIbram\Presentation\Admin\HabilitacaoMenuRegistry;
use Ibram\ParticipeIbram\Presentation\Admin\ListTables\InscricoesHabilitacaoListTable;

/**
 * Renderiza a listagem de inscrições em fase de habilitação.
 * O detalhe individual é tratado por {@see InscricaoDetalhesController}.
 */
final class HabilitacaoListController
{
    private InscricaoDetalhesController $detalhesController;
    private WpdbInscricaoRepository $inscricoesRepo;
    private string $templatesDir;

    /** @var \wpdb */
    private $wpdb;

    public function __construct(
        InscricaoDetalhesController $detalhesController,
        WpdbInscricaoRepository $inscricoesRepo,
        $wpdb,
        string $templatesDir
    ) {
        $this->detalhesController = $detalhesController;
        $this->inscricoesRepo     = $inscricoesRepo;
        $this->wpdb               = $wpdb;
        $this->templatesDir       = rtrim($templatesDir, '/\\');
    }

    /**
     * Entrypoint do submenu — despacha lista vs. detalhe.
     */
    public function dispatch(): void
    {
        $this->guardCap();

        $action       = (string) RequestHelper::get('action', 'sanitize_key', 'list');
        $inscricaoId  = (int) RequestHelper::get('inscricao_id', 'absint', 0);

        if ($action === 'view' && $inscricaoId > 0) {
            $this->detalhesController->render($inscricaoId);
            return;
        }

        $this->render();
    }

    public function render(): void
    {
        $this->guardCap();
        $listTable = new InscricoesHabilitacaoListTable($this->wpdb);
        $listTable->prepare_items();

        $template = $this->templatesDir . '/habilitacoes/lista.php';
        if (!is_file($template)) {
            return;
        }
        /** @var \Ibram\ParticipeIbram\Presentation\Admin\ListTables\InscricoesHabilitacaoListTable $listTable */
        include $template;
    }

    /**
     * Captura POST actions nesta tela (nenhuma bulk atualmente; extensível).
     */
    public function handlePostAction(): void
    {
        // Nenhuma bulk action registrada nesta tela. POST é tratado via AJAX.
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
