<?php
/**
 * EditalMenuRegistry — registers Editais submenus under "Participe Ibram".
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin;

use Ibram\ParticipeIbram\Presentation\Admin\Controllers\EditalFormController;
use Ibram\ParticipeIbram\Presentation\Admin\Controllers\EditalListController;
use Ibram\ParticipeIbram\Presentation\Admin\Controllers\EditalDetalhesController;
use Ibram\ParticipeIbram\Presentation\Admin\Controllers\CategoriaController;

/**
 * Registers Editais menu items under the existing top-level "Participe Ibram"
 * menu.  Keeps capability checks here (R5 V-06 defense-in-depth) AND in each
 * controller.
 *
 * Slugs:
 *  - participe-ibram_editais      (list + detalhes)
 *  - participe-ibram_edital_novo  (create form)
 *  - participe-ibram_edital       (hidden — detalhes)
 *  - participe-ibram_categoria    (hidden — categoria form)
 */
final class EditalMenuRegistry
{
    public const CAP_LISTAR   = 'pi_listar_cadastros';
    public const CAP_CRIAR    = 'pi_criar_edital';
    public const CAP_EDITAR   = 'pi_editar_edital';
    public const CAP_PUBLICAR = 'pi_publicar_edital';

    public const SLUG_ROOT      = 'participe-ibram';
    public const SLUG_EDITAIS   = 'participe-ibram_editais';
    public const SLUG_NOVO      = 'participe-ibram_edital_novo';
    public const SLUG_EDITAL    = 'participe-ibram_edital';
    public const SLUG_CATEGORIA = 'participe-ibram_categoria';

    private EditalListController $listCtrl;
    private EditalFormController $formCtrl;
    private EditalDetalhesController $detalhesCtrl;
    private CategoriaController $categoriaCtrl;

    public function __construct(
        EditalListController $listCtrl,
        EditalFormController $formCtrl,
        EditalDetalhesController $detalhesCtrl,
        CategoriaController $categoriaCtrl
    ) {
        $this->listCtrl      = $listCtrl;
        $this->formCtrl      = $formCtrl;
        $this->detalhesCtrl  = $detalhesCtrl;
        $this->categoriaCtrl = $categoriaCtrl;
    }

    /**
     * Register WordPress hooks. Idempotent.
     */
    public function registerHooks(): void
    {
        if (!function_exists('add_action')) {
            return;
        }
        // W11-A IA: prioridade 20 — abre o grupo "Editais & habilitações".
        // Ver docs/refactor/W11-IA.md.
        \add_action('admin_menu', [$this, 'register'], 20);
        \add_action('admin_init', [$this, 'routePostActions']);
    }

    /**
     * Build the menu tree under the existing top-level menu.
     */
    public function register(): void
    {
        if (!function_exists('add_submenu_page')) {
            return;
        }

        // W11-A IA: grupo "Editais & habilitações", posições 20–21.
        // Ver docs/refactor/W11-IA.md.
        \add_submenu_page(
            self::SLUG_ROOT,
            self::tr('Editais — Lista'),
            self::tr('Editais — Lista'),
            self::CAP_LISTAR,
            self::SLUG_EDITAIS,
            [$this, 'renderList'],
            20
        );

        \add_submenu_page(
            self::SLUG_ROOT,
            self::tr('Editais — Novo edital'),
            self::tr('Editais — Novo edital'),
            self::CAP_CRIAR,
            self::SLUG_NOVO,
            [$this, 'renderNovo'],
            21
        );

        // Hidden pages (registered under options.php so they don't appear in nav).
        \add_submenu_page(
            'options.php',
            self::tr('Edital — Detalhes'),
            self::tr('Edital — Detalhes'),
            self::CAP_LISTAR,
            self::SLUG_EDITAL,
            [$this, 'renderDetalhes']
        );

        \add_submenu_page(
            'options.php',
            self::tr('Categoria do Edital'),
            self::tr('Categoria do Edital'),
            self::CAP_EDITAR,
            self::SLUG_CATEGORIA,
            [$this, 'renderCategoria']
        );
    }

    /**
     * Routes POST form submissions to the appropriate controller (PRG pattern).
     * Only fires when the current screen is one of our pages.
     */
    public function routePostActions(): void
    {
        if (!isset($_SERVER['REQUEST_METHOD'])) {
            return;
        }
        $method = strtoupper((string) \wp_unslash($_SERVER['REQUEST_METHOD']));
        if ($method !== 'POST') {
            return;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $page = isset($_POST['page']) ? (string) \sanitize_key(\wp_unslash($_POST['page'])) : '';
        if ($page === self::SLUG_EDITAIS || $page === self::SLUG_NOVO || $page === self::SLUG_EDITAL) {
            $this->formCtrl->handlePostAction();
        }
        if ($page === self::SLUG_CATEGORIA) {
            $this->categoriaCtrl->handlePostAction();
        }
    }

    /* ---------------------- Render callbacks ---------------------- */

    public function renderList(): void
    {
        $this->listCtrl->render();
    }

    public function renderNovo(): void
    {
        $this->formCtrl->renderCreate();
    }

    public function renderDetalhes(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $page = isset($_GET['page']) ? (string) \sanitize_key(\wp_unslash($_GET['page'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $id = isset($_GET['id']) ? (int) \absint(\wp_unslash($_GET['id'])) : 0;
        if ($id > 0 && $page === self::SLUG_EDITAL) {
            $this->detalhesCtrl->render($id);
            return;
        }
        if ($page === self::SLUG_EDITAL && isset($_GET['edit'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $editId = (int) \absint(\wp_unslash($_GET['edit']));
            if ($editId > 0) {
                $this->formCtrl->renderEdit($editId);
                return;
            }
        }
        $this->listCtrl->render();
    }

    public function renderCategoria(): void
    {
        $this->categoriaCtrl->render();
    }

    /* ---------------------- URL helpers ---------------------- */

    public static function urlEditaisList(): string
    {
        $base = function_exists('admin_url') ? \admin_url('admin.php') : 'admin.php';
        return $base . '?page=' . self::SLUG_EDITAIS;
    }

    public static function urlEditalDetalhes(int $editalId): string
    {
        $base = function_exists('admin_url') ? \admin_url('admin.php') : 'admin.php';
        return $base . '?page=' . self::SLUG_EDITAL . '&id=' . $editalId;
    }

    public static function urlEditalEdit(int $editalId): string
    {
        $base = function_exists('admin_url') ? \admin_url('admin.php') : 'admin.php';
        return $base . '?page=' . self::SLUG_EDITAL . '&edit=' . $editalId;
    }

    public static function urlNovo(): string
    {
        $base = function_exists('admin_url') ? \admin_url('admin.php') : 'admin.php';
        return $base . '?page=' . self::SLUG_NOVO;
    }

    public static function urlCategoria(int $editalId, ?int $categoriaId = null): string
    {
        $base = function_exists('admin_url') ? \admin_url('admin.php') : 'admin.php';
        $url  = $base . '?page=' . self::SLUG_CATEGORIA . '&edital_id=' . $editalId;
        if ($categoriaId !== null) {
            $url .= '&categoria_id=' . $categoriaId;
        }
        return $url;
    }

    /* ---------------------- Internals ---------------------- */

    private static function tr(string $text): string
    {
        return function_exists('__') ? (string) \__($text, 'participe-ibram') : $text;
    }
}
