<?php
/**
 * AuditMenuRegistry — registra submenus de Auditoria sob "Participe Ibram".
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin;

use Ibram\ParticipeIbram\Presentation\Admin\Controllers\AuditDetalheController;
use Ibram\ParticipeIbram\Presentation\Admin\Controllers\AuditLogController;

/**
 * Registra três submenus de auditoria com priority=30 (depois Ondas 4-6).
 *
 *  - participe-ibram_audit_log      → Log de eventos (geral)
 *  - participe-ibram_audit_pii      → Acessos a PII
 *  - participe-ibram_audit_decisoes → Decisões
 *
 * Capability: pi_visualizar_audit_log (R5 V-06 defense-in-depth).
 * Hook admin_init: router POST para export.
 */
final class AuditMenuRegistry
{
    public const CAP = 'pi_visualizar_audit_log';

    public const SLUG_ROOT    = 'participe-ibram';
    public const SLUG_LOG     = 'participe-ibram_audit_log';
    public const SLUG_PII     = 'participe-ibram_audit_pii';
    public const SLUG_DECISOES = 'participe-ibram_audit_decisoes';

    private AuditLogController $logCtrl;
    private AuditDetalheController $detalheCtrl;

    public function __construct(
        AuditLogController $logCtrl,
        AuditDetalheController $detalheCtrl
    ) {
        $this->logCtrl     = $logCtrl;
        $this->detalheCtrl = $detalheCtrl;
    }

    /**
     * Registra hooks WordPress. Idempotente.
     */
    public function registerHooks(): void
    {
        if (!function_exists('add_action')) {
            return;
        }
        // W11-A IA: prioridade 40 — grupo "Conformidade & LGPD",
        // depois de Votações. Ver docs/refactor/W11-IA.md.
        \add_action('admin_menu', [$this, 'register'], 40);
        \add_action('admin_init', [$this, 'routePostAction']);
    }

    /**
     * Constrói os submenus. Chamado pelo WordPress.
     */
    public function register(): void
    {
        if (!function_exists('add_submenu_page')) {
            return;
        }

        // W11-A IA: grupo "Conformidade & LGPD", posições 40–42.
        // Ver docs/refactor/W11-IA.md.
        \add_submenu_page(
            self::SLUG_ROOT,
            self::tr('Auditoria — Log de eventos'),
            self::tr('Auditoria — Log de eventos'),
            self::CAP,
            self::SLUG_LOG,
            [$this, 'renderLog'],
            40
        );

        \add_submenu_page(
            self::SLUG_ROOT,
            self::tr('Auditoria — Acessos a PII'),
            self::tr('Auditoria — Acessos a PII'),
            self::CAP,
            self::SLUG_PII,
            [$this, 'renderPii'],
            41
        );

        \add_submenu_page(
            self::SLUG_ROOT,
            self::tr('Auditoria — Decisões'),
            self::tr('Auditoria — Decisões'),
            self::CAP,
            self::SLUG_DECISOES,
            [$this, 'renderDecisoes'],
            42
        );

        // Detalhe: submenu oculto (parent options.php — não aparece na nav)
        \add_submenu_page(
            'options.php',
            self::tr('Detalhe do registro de auditoria'),
            self::tr('Detalhe de auditoria'),
            self::CAP,
            self::SLUG_LOG . '_detalhe',
            [$this, 'renderDetalhe']
        );
    }

    /**
     * Router POST para export — invocado via admin_init (antes do render).
     * Gating completo dentro de AuditLogController::handlePostAction.
     */
    public function routePostAction(): void
    {
        $action = '';
        if (isset($_POST['pi_audit_action'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $action = (string) \sanitize_key(\wp_unslash($_POST['pi_audit_action'])); // phpcs:ignore
        }
        if ($action !== 'exportar') {
            return;
        }
        $this->logCtrl->handlePostAction();
    }

    public function renderLog(): void
    {
        $this->logCtrl->render();
    }

    public function renderPii(): void
    {
        $this->logCtrl->render();
    }

    public function renderDecisoes(): void
    {
        $this->logCtrl->render();
    }

    public function renderDetalhe(): void
    {
        $this->detalheCtrl->render();
    }

    /* ====================== URL builders ====================== */

    public static function urlLog(): string
    {
        $base = function_exists('admin_url') ? \admin_url('admin.php') : 'admin.php';
        return $base . '?page=' . self::SLUG_LOG;
    }

    public static function urlPii(): string
    {
        $base = function_exists('admin_url') ? \admin_url('admin.php') : 'admin.php';
        return $base . '?page=' . self::SLUG_PII;
    }

    public static function urlDecisoes(): string
    {
        $base = function_exists('admin_url') ? \admin_url('admin.php') : 'admin.php';
        return $base . '?page=' . self::SLUG_DECISOES;
    }

    public static function urlDetalhe(int $id): string
    {
        $base = function_exists('admin_url') ? \admin_url('admin.php') : 'admin.php';
        return $base . '?' . http_build_query([
            'page'   => self::SLUG_LOG,
            'action' => 'view',
            'id'     => $id,
        ]);
    }

    private static function tr(string $text): string
    {
        return function_exists('__') ? (string) \__($text, 'participe-ibram') : $text;
    }
}
