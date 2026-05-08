<?php
/**
 * MenuRegistry — registers the Participe Ibram WordPress admin menu.
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin;

use Ibram\ParticipeIbram\Presentation\Admin\Controllers\AgenteDetalhesController;
use Ibram\ParticipeIbram\Presentation\Admin\Controllers\FilaAnaliseController;
use Ibram\ParticipeIbram\Presentation\Admin\Controllers\TodosAgentesController;

/**
 * Registers the top-level "Participe Ibram" menu and its submenus. Each
 * submenu maps to a controller render method.
 *
 * Capability gating happens at WordPress level via add_menu_page /
 * add_submenu_page, AND inside each controller (R5 V-06 defense in depth).
 */
final class MenuRegistry
{
    public const CAP_LISTAR_CADASTROS    = 'pi_listar_cadastros';
    public const CAP_GERENCIAR_VOCABULARIOS = 'pi_gerenciar_vocabularios';

    public const SLUG_ROOT     = 'participe-ibram';
    public const SLUG_CADASTROS = 'participe-ibram_cadastros';
    public const SLUG_AGENTES   = 'participe-ibram_agentes';
    public const SLUG_AGENTE    = 'participe-ibram_agente';

    private FilaAnaliseController $filaAnalise;
    private TodosAgentesController $todosAgentes;
    private AgenteDetalhesController $agenteDetalhes;

    public function __construct(
        FilaAnaliseController $filaAnalise,
        TodosAgentesController $todosAgentes,
        AgenteDetalhesController $agenteDetalhes
    ) {
        $this->filaAnalise    = $filaAnalise;
        $this->todosAgentes   = $todosAgentes;
        $this->agenteDetalhes = $agenteDetalhes;
    }

    /**
     * Register the `admin_menu` hook. Idempotent.
     */
    public function registerHooks(): void
    {
        if (!function_exists('add_action')) {
            return;
        }
        \add_action('admin_menu', [$this, 'register']);
    }

    /**
     * Build the menu tree. Called by WordPress.
     */
    public function register(): void
    {
        if (!function_exists('add_menu_page')) {
            return;
        }

        \add_menu_page(
            self::translate('Participe Ibram'),
            self::translate('Participe Ibram'),
            self::CAP_LISTAR_CADASTROS,
            self::SLUG_ROOT,
            [$this, 'renderDashboard'],
            'dashicons-groups',
            26
        );

        \add_submenu_page(
            self::SLUG_ROOT,
            self::translate('Painel'),
            self::translate('Painel'),
            self::CAP_LISTAR_CADASTROS,
            self::SLUG_ROOT,
            [$this, 'renderDashboard']
        );

        \add_submenu_page(
            self::SLUG_ROOT,
            self::translate('Fila de Análise'),
            self::translate('Fila de Análise'),
            self::CAP_LISTAR_CADASTROS,
            self::SLUG_CADASTROS,
            [$this, 'renderFilaAnalise']
        );

        \add_submenu_page(
            self::SLUG_ROOT,
            self::translate('Todos os agentes'),
            self::translate('Todos os agentes'),
            self::CAP_LISTAR_CADASTROS,
            self::SLUG_AGENTES,
            [$this, 'renderTodosAgentes']
        );

        // Detalhe é acessada via querystring; é registrada como submenu oculta
        // (parent != self::SLUG_ROOT) para que add_submenu_page resolva o slug
        // como página válida sem aparecer na navegação. WordPress permite isto
        // passando o mesmo $parent_slug e depois removendo via remove_submenu_page,
        // mas a abordagem mais simples e portable é registrar como submenu cujo
        // título não é exibido (null parent).
        \add_submenu_page(
            'options.php',
            self::translate('Detalhes do agente'),
            self::translate('Detalhes do agente'),
            self::CAP_LISTAR_CADASTROS,
            self::SLUG_AGENTE,
            [$this, 'renderAgenteDetalhes']
        );
    }

    /**
     * Dashboard render: keeps the legacy template until Wave 5 dashboard work.
     */
    public function renderDashboard(): void
    {
        $template = self::templatePath('dashboard.php');
        if ($template !== null) {
            include $template;
            return;
        }
        echo '<div class="wrap"><h1>' . self::escHtml(self::translate('Participe Ibram')) . '</h1></div>';
    }

    public function renderFilaAnalise(): void
    {
        $this->filaAnalise->render();
    }

    public function renderTodosAgentes(): void
    {
        $this->todosAgentes->render();
    }

    public function renderAgenteDetalhes(): void
    {
        $this->agenteDetalhes->render();
    }

    /**
     * URL for the agente detalhes page given an id.
     */
    public static function urlAgenteDetalhes(int $agenteId): string
    {
        $base = function_exists('admin_url')
            ? \admin_url('admin.php')
            : 'admin.php';

        $args = http_build_query([
            'page' => self::SLUG_AGENTE,
            'id'   => $agenteId,
        ]);

        return $base . '?' . $args;
    }

    public static function urlFilaAnalise(): string
    {
        $base = function_exists('admin_url') ? \admin_url('admin.php') : 'admin.php';
        return $base . '?page=' . self::SLUG_CADASTROS;
    }

    public static function urlTodosAgentes(): string
    {
        $base = function_exists('admin_url') ? \admin_url('admin.php') : 'admin.php';
        return $base . '?page=' . self::SLUG_AGENTES;
    }

    private static function templatePath(string $relative): ?string
    {
        if (\defined('PI_PLUGIN_DIR')) {
            $base = (string) \PI_PLUGIN_DIR;
        } else {
            $base = dirname(__DIR__, 3);
        }
        $candidate = rtrim($base, '/\\') . '/templates/admin/' . ltrim($relative, '/');
        return file_exists($candidate) ? $candidate : null;
    }

    private static function translate(string $text): string
    {
        if (function_exists('__')) {
            return (string) \__($text, 'participe-ibram');
        }
        return $text;
    }

    private static function escHtml(string $text): string
    {
        if (function_exists('esc_html')) {
            return (string) \esc_html($text);
        }
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
