<?php
/**
 * Registra os submenus admin de Habilitação e Recursos de Inabilitação (W5-C).
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin;

use Ibram\ParticipeIbram\Core\Helpers\RequestHelper;
use Ibram\ParticipeIbram\Presentation\Admin\Controllers\HabilitacaoListController;
use Ibram\ParticipeIbram\Presentation\Admin\Controllers\RecursoInabilitacaoListController;

/**
 * Submenus administrativos de habilitação (W5-C):
 *
 *  - `participe-ibram_habilitacoes` (cap `pi_decidir_habilitacao`).
 *  - `participe-ibram_recursos_inabilitacao` (cap `pi_decidir_habilitacao`).
 *
 * Arquivo separado do W4-B (RecursoMenuRegistry) para evitar conflitos de merge.
 */
final class HabilitacaoMenuRegistry
{
    public const PARENT_SLUG = 'participe-ibram';

    public const SLUG_HABILITACOES          = 'participe-ibram_habilitacoes';
    public const SLUG_RECURSOS_INABILITACAO = 'participe-ibram_recursos_inabilitacao';

    public const CAP_HABILITACAO = 'pi_decidir_habilitacao';

    private HabilitacaoListController $habilitacaoController;
    private RecursoInabilitacaoListController $recursoInabilitacaoController;

    public function __construct(
        HabilitacaoListController $habilitacaoController,
        RecursoInabilitacaoListController $recursoInabilitacaoController
    ) {
        $this->habilitacaoController          = $habilitacaoController;
        $this->recursoInabilitacaoController  = $recursoInabilitacaoController;
    }

    /**
     * Registra hooks WP. Idempotente.
     */
    public function registerHooks(): void
    {
        if (!function_exists('add_action')) {
            return;
        }
        \add_action('admin_menu', [$this, 'registerMenu'], 25);
        \add_action('admin_init', [$this, 'maybeHandlePost']);
    }

    /**
     * Registra os submenus (executar apenas em `admin_menu`).
     */
    public function registerMenu(): void
    {
        if (!function_exists('add_submenu_page')) {
            return;
        }

        \add_submenu_page(
            self::PARENT_SLUG,
            \__('Habilitações — Pendentes', 'participe-ibram'),
            \__('Habilitações — Pendentes', 'participe-ibram'),
            self::CAP_HABILITACAO,
            self::SLUG_HABILITACOES,
            [$this->habilitacaoController, 'dispatch']
        );

        \add_submenu_page(
            self::PARENT_SLUG,
            \__('Recursos de Inabilitação', 'participe-ibram'),
            \__('Recursos de Inabilitação', 'participe-ibram'),
            self::CAP_HABILITACAO,
            self::SLUG_RECURSOS_INABILITACAO,
            [$this->recursoInabilitacaoController, 'dispatch']
        );
    }

    /**
     * Em `admin_init` captura POST actions para os controllers de habilitação.
     */
    public function maybeHandlePost(): void
    {
        if (!isset($_SERVER['REQUEST_METHOD']) || strtoupper((string) $_SERVER['REQUEST_METHOD']) !== 'POST') {
            return;
        }
        $page = (string) RequestHelper::get('page', 'sanitize_key', '');
        if ($page === '') {
            return;
        }
        if ($page === self::SLUG_HABILITACOES) {
            $this->habilitacaoController->handlePostAction();
            return;
        }
        if ($page === self::SLUG_RECURSOS_INABILITACAO) {
            $this->recursoInabilitacaoController->handlePostAction();
        }
    }
}
