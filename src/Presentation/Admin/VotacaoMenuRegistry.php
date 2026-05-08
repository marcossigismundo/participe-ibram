<?php
/**
 * VotacaoMenuRegistry — submenus de Votação sob "Participe Ibram".
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin;

use Ibram\ParticipeIbram\Presentation\Admin\Controllers\ApuracaoController;
use Ibram\ParticipeIbram\Presentation\Admin\Controllers\VotacaoAuditoriaController;
use Ibram\ParticipeIbram\Presentation\Admin\Controllers\VotacaoListController;

/**
 * Registra os submenus:
 *   participe-ibram_votacoes              cap pi_apurar_votacao        (lista)
 *   participe-ibram_apurar                cap pi_apurar_votacao        (detalhe/apurar)
 *   participe-ibram_votacao_auditoria     cap pi_visualizar_audit_log  (auditoria interna)
 *
 * Capability checks aplicados em DUAS camadas (R5 V-06 defense in depth):
 *   - WP `add_submenu_page($cap)`
 *   - controller `current_user_can()` no início do render.
 */
final class VotacaoMenuRegistry
{
    public const CAP_APURAR     = 'pi_apurar_votacao';
    public const CAP_PUBLICAR   = 'pi_publicar_resultado';
    public const CAP_AUDIT_VIEW = 'pi_visualizar_audit_log';

    public const SLUG_ROOT      = 'participe-ibram';
    public const SLUG_VOTACOES  = 'participe-ibram_votacoes';
    public const SLUG_APURAR    = 'participe-ibram_apurar';
    public const SLUG_AUDITORIA = 'participe-ibram_votacao_auditoria';

    private VotacaoListController $listCtrl;

    private ApuracaoController $apurarCtrl;

    private VotacaoAuditoriaController $auditoriaCtrl;

    public function __construct(
        VotacaoListController $listCtrl,
        ApuracaoController $apurarCtrl,
        VotacaoAuditoriaController $auditoriaCtrl
    ) {
        $this->listCtrl      = $listCtrl;
        $this->apurarCtrl    = $apurarCtrl;
        $this->auditoriaCtrl = $auditoriaCtrl;
    }

    public function registerHooks(): void
    {
        if (!function_exists('add_action')) {
            return;
        }
        \add_action('admin_menu', [$this, 'register']);
    }

    public function register(): void
    {
        if (!function_exists('add_submenu_page')) {
            return;
        }

        \add_submenu_page(
            self::SLUG_ROOT,
            self::tr('Votações — Lista'),
            self::tr('Votações'),
            self::CAP_APURAR,
            self::SLUG_VOTACOES,
            [$this, 'renderList']
        );

        // Página oculta acessada via querystring; cap é re-checada no controller.
        \add_submenu_page(
            'options.php',
            self::tr('Votação — Apurar'),
            self::tr('Votação — Apurar'),
            self::CAP_APURAR,
            self::SLUG_APURAR,
            [$this, 'renderApuracao']
        );

        \add_submenu_page(
            self::SLUG_ROOT,
            self::tr('Votações — Auditoria'),
            self::tr('Auditoria de Votações'),
            self::CAP_AUDIT_VIEW,
            self::SLUG_AUDITORIA,
            [$this, 'renderAuditoria']
        );
    }

    /* ---------------------- Render callbacks ---------------------- */

    public function renderList(): void
    {
        $this->listCtrl->render();
    }

    public function renderApuracao(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $id = isset($_GET['id']) ? (int) \absint(\wp_unslash($_GET['id'])) : 0;
        $this->apurarCtrl->render($id);
    }

    public function renderAuditoria(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $id = isset($_GET['id']) ? (int) \absint(\wp_unslash($_GET['id'])) : 0;
        $this->auditoriaCtrl->render($id);
    }

    /* ---------------------- URL helpers ---------------------- */

    public static function urlVotacoesList(): string
    {
        $base = function_exists('admin_url') ? \admin_url('admin.php') : 'admin.php';
        return $base . '?page=' . self::SLUG_VOTACOES;
    }

    public static function urlApurar(int $votacaoId): string
    {
        $base = function_exists('admin_url') ? \admin_url('admin.php') : 'admin.php';
        return $base . '?page=' . self::SLUG_APURAR . '&id=' . $votacaoId;
    }

    public static function urlAuditoria(int $votacaoId): string
    {
        $base = function_exists('admin_url') ? \admin_url('admin.php') : 'admin.php';
        return $base . '?page=' . self::SLUG_AUDITORIA . '&id=' . $votacaoId;
    }

    private static function tr(string $text): string
    {
        return function_exists('__') ? (string) \__($text, 'participe-ibram') : $text;
    }
}
