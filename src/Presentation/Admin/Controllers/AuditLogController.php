<?php
/**
 * AuditLogController — renderiza páginas do log de auditoria.
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\Controllers
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\Controllers;

use Ibram\ParticipeIbram\Application\Audit\ExportarAuditLogCommand;
use Ibram\ParticipeIbram\Application\Audit\ExportarAuditLogHandler;
use Ibram\ParticipeIbram\Core\Helpers\RateLimiter;
use Ibram\ParticipeIbram\Core\Helpers\RequestHelper;
use Ibram\ParticipeIbram\Presentation\Admin\AuditMenuRegistry;
use Ibram\ParticipeIbram\Presentation\Admin\ListTables\AuditDecisoesListTable;
use Ibram\ParticipeIbram\Presentation\Admin\ListTables\AuditLogListTable;
use Ibram\ParticipeIbram\Presentation\Admin\ListTables\AuditPiiAccessListTable;
use Ibram\ParticipeIbram\Presentation\Admin\Support\AuditLogQuery;
use Throwable;

/**
 * Renderiza as três páginas do audit log e processa o export POST.
 *
 * Capability: pi_visualizar_audit_log (R5 V-06).
 * Export: nonce + cap + rate limit (5/hora) + proteção DoS (100k linhas).
 */
final class AuditLogController
{
    public const CAP = 'pi_visualizar_audit_log';

    /** Rate limit para export: 5 requests por hora por usuário. */
    private const RATE_EXPORT_MAX    = 5;
    private const RATE_EXPORT_WINDOW = 3600;

    private AuditLogQuery $query;
    private ExportarAuditLogHandler $exportHandler;

    public function __construct(
        AuditLogQuery $query,
        ExportarAuditLogHandler $exportHandler
    ) {
        $this->query         = $query;
        $this->exportHandler = $exportHandler;
    }

    /**
     * Renderiza a página de log geral (slug participe-ibram_audit_log).
     */
    public function render(): void
    {
        if (!self::userCan(self::CAP)) {
            self::wpDie(self::tr('Permissão negada.'));
            return;
        }

        $slug = (string) RequestHelper::get('page', 'sanitize_key', '');

        $listTable = $this->buildListTable($slug);
        $listTable->prepare_items();

        $stats   = $this->buildStats();
        $flash   = $this->consumeFlash();
        $template = $this->templatePath($this->templateNameForSlug($slug));

        if ($template === null) {
            echo '<div class="wrap"><p>' . self::escHtml(self::tr('Template não encontrado.')) . '</p></div>';
            return;
        }

        // phpcs:disable WordPress.PHP.DontExtract
        include $template;
        // phpcs:enable
    }

    /**
     * Processa export POST (invocado via admin_init, antes do render).
     * Gating: nonce + cap + rate limit.
     */
    public function handlePostAction(): void
    {
        $action = (string) RequestHelper::post('pi_audit_action', 'sanitize_key', '');
        if ($action !== 'exportar') {
            return;
        }

        if (!self::userCan(self::CAP)) {
            $this->setFlash('error', self::tr('Permissão negada.'));
            self::redirectBack();
            return;
        }

        $userId = self::currentUserId();
        if ($userId <= 0) {
            $this->setFlash('error', self::tr('Sessão expirada.'));
            self::redirectBack();
            return;
        }

        // Nonce — escopado por usuário
        $nonce = (string) RequestHelper::post('_wpnonce', 'sanitize_text_field', '');
        if (!function_exists('wp_verify_nonce') || !\wp_verify_nonce($nonce, 'pi_audit_export_' . $userId)) {
            $this->setFlash('error', self::tr('Nonce inválido.'));
            self::redirectBack();
            return;
        }

        // Rate limit
        if (!RateLimiter::check(
            RateLimiter::keyForUser('pi_audit_export', $userId),
            self::RATE_EXPORT_MAX,
            self::RATE_EXPORT_WINDOW
        )) {
            $this->setFlash('error', self::tr('Limite de exports atingido. Aguarde antes de tentar novamente.'));
            self::redirectBack();
            return;
        }

        $format  = (string) RequestHelper::post('formato', 'sanitize_key', 'csv');
        $format  = in_array($format, ['csv', 'json'], true) ? $format : 'csv';
        $filters = $this->collectFiltersFromPost();

        try {
            $signedUrl = $this->exportHandler->handle(
                new ExportarAuditLogCommand($filters, $format, $userId)
            );
            $this->setFlash('success', self::tr('Export gerado com sucesso.'));
            // Redireciona para URL assinada (download)
            if (function_exists('wp_safe_redirect')) {
                \wp_safe_redirect($signedUrl);
                exit;
            }
        } catch (Throwable $e) {
            $debug = \defined('WP_DEBUG') && \WP_DEBUG;
            $this->setFlash(
                'error',
                $debug ? $e->getMessage() : self::tr('Falha ao gerar export. Tente filtros mais restritos.')
            );
            self::redirectBack();
        }
    }

    /* ====================== privados ======================= */

    private function buildListTable(string $slug): AuditLogListTable
    {
        switch ($slug) {
            case AuditMenuRegistry::SLUG_PII:
                return new AuditPiiAccessListTable($this->query);
            case AuditMenuRegistry::SLUG_DECISOES:
                return new AuditDecisoesListTable($this->query);
            default:
                return new AuditLogListTable($this->query);
        }
    }

    private function templateNameForSlug(string $slug): string
    {
        switch ($slug) {
            case AuditMenuRegistry::SLUG_PII:
                return 'audit/pii-access.php';
            case AuditMenuRegistry::SLUG_DECISOES:
                return 'audit/decisoes.php';
            default:
                return 'audit/log.php';
        }
    }

    /**
     * @return array<string,int>
     */
    private function buildStats(): array
    {
        $today     = gmdate('Y-m-d');
        $yesterday = gmdate('Y-m-d', strtotime('-1 day'));
        $monthAgo  = gmdate('Y-m-d', strtotime('-30 days'));

        return [
            'hoje'      => $this->query->count(['data_de' => $today, 'data_ate' => $today]),
            'ontem'     => $this->query->count(['data_de' => $yesterday, 'data_ate' => $yesterday]),
            'mes'       => $this->query->count(['data_de' => $monthAgo, 'data_ate' => $today]),
        ];
    }

    /**
     * Coleta filtros do POST para o export.
     *
     * @return array<string,mixed>
     */
    private function collectFiltersFromPost(): array
    {
        $entidade  = (string) RequestHelper::post('entidade', 'sanitize_text_field', '');
        $acao      = (string) RequestHelper::post('acao', 'sanitize_text_field', '');
        $dataDe    = (string) RequestHelper::post('data_de', 'sanitize_text_field', '');
        $dataAte   = (string) RequestHelper::post('data_ate', 'sanitize_text_field', '');
        $atorId    = (int) RequestHelper::post('ator_id', 'absint', 0);

        return [
            'entidade'  => $entidade !== '' ? $entidade : null,
            'acao'      => $acao !== '' ? $acao : null,
            'data_de'   => $dataDe !== '' ? $dataDe : null,
            'data_ate'  => $dataAte !== '' ? $dataAte : null,
            'ator_id'   => $atorId > 0 ? $atorId : null,
        ];
    }

    private static function currentUserId(): int
    {
        return function_exists('get_current_user_id') ? (int) \get_current_user_id() : 0;
    }

    private static function userCan(string $cap): bool
    {
        return function_exists('current_user_can') && (bool) \current_user_can($cap);
    }

    private function setFlash(string $type, string $message): void
    {
        if (!function_exists('set_transient') || !function_exists('get_current_user_id')) {
            return;
        }
        $userId = (int) \get_current_user_id();
        if ($userId <= 0) {
            return;
        }
        \set_transient('pi_admin_flash_' . $userId, ['type' => $type, 'message' => $message], 60);
    }

    /**
     * @return array{type:string,message:string}|null
     */
    private function consumeFlash(): ?array
    {
        if (!function_exists('get_transient') || !function_exists('get_current_user_id')) {
            return null;
        }
        $userId = (int) \get_current_user_id();
        if ($userId <= 0) {
            return null;
        }
        $key  = 'pi_admin_flash_' . $userId;
        $data = \get_transient($key);
        if (!is_array($data) || !isset($data['type'], $data['message'])) {
            return null;
        }
        if (function_exists('delete_transient')) {
            \delete_transient($key);
        }
        return ['type' => (string) $data['type'], 'message' => (string) $data['message']];
    }

    private static function redirectBack(): void
    {
        $url = function_exists('admin_url')
            ? \admin_url('admin.php?page=' . AuditMenuRegistry::SLUG_LOG)
            : 'admin.php?page=' . AuditMenuRegistry::SLUG_LOG;
        if (function_exists('wp_safe_redirect')) {
            \wp_safe_redirect($url);
            exit;
        }
    }

    private static function templatePath(string $relative): ?string
    {
        $base = \defined('PI_PLUGIN_DIR') ? (string) \PI_PLUGIN_DIR : dirname(__DIR__, 4);
        $candidate = rtrim($base, '/\\') . '/templates/admin/' . ltrim($relative, '/');
        return file_exists($candidate) ? $candidate : null;
    }

    private static function tr(string $text): string
    {
        return function_exists('__') ? (string) \__($text, 'participe-ibram') : $text;
    }

    private static function escHtml(string $text): string
    {
        return function_exists('esc_html') ? (string) \esc_html($text) : htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    private static function wpDie(string $message): void
    {
        if (function_exists('wp_die')) {
            \wp_die(self::escHtml($message));
        } else {
            echo $message;
            exit;
        }
    }
}
