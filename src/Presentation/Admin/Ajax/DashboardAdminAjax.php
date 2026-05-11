<?php
/**
 * DashboardAdminAjax — AJAX endpoint for dashboard KPI refresh.
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\Ajax
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\Ajax;

use Ibram\ParticipeIbram\Core\Helpers\RateLimiter;
use Ibram\ParticipeIbram\Presentation\Admin\MenuRegistry;
use Ibram\ParticipeIbram\Presentation\Admin\Support\DashboardMetricsQuery;

/**
 * Action: wp_ajax_pi_admin_dashboard_metrics
 * Capability: pi_listar_cadastros
 * Rate limit: 30 requests/minute per user
 * Cache: 5 min via DashboardMetricsQuery (group pi_dashboard)
 *
 * Response NEVER contains PII — only numeric aggregates.
 */
final class DashboardAdminAjax
{
    private const RATE_MAX    = 30;
    private const RATE_WINDOW = 60; // seconds

    private DashboardMetricsQuery $query;

    public function __construct(DashboardMetricsQuery $query)
    {
        $this->query = $query;
    }

    public function registerHooks(): void
    {
        if (!function_exists('add_action')) {
            return;
        }
        \add_action('wp_ajax_pi_admin_dashboard_metrics', [$this, 'handle']);
    }

    /**
     * AJAX handler: validate nonce + cap + rate limit, return JSON.
     */
    public function handle(): void
    {
        // Authentication.
        if (!function_exists('get_current_user_id')) {
            $this->sendError(401, 'pi_unauthorized', self::tr('Autenticação requerida.'));
            return;
        }
        $userId = (int) \get_current_user_id();
        if ($userId <= 0) {
            $this->sendError(401, 'pi_unauthorized', self::tr('Autenticação requerida.'));
            return;
        }

        // Nonce verification.
        $nonceAction = 'pi_admin_dashboard_metrics_' . $userId;
        $nonce = (string) (
            $_REQUEST['_wpnonce'] ?? // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            ''
        );
        $nonce = function_exists('wp_unslash') ? (string) \wp_unslash($nonce) : $nonce;
        if (!function_exists('wp_verify_nonce') || !\wp_verify_nonce($nonce, $nonceAction)) {
            $this->sendError(403, 'pi_invalid_nonce', self::tr('Nonce inválido ou expirado.'));
            return;
        }

        // Capability check.
        if (!function_exists('current_user_can') || !\current_user_can(MenuRegistry::CAP_LISTAR_CADASTROS)) {
            $this->sendError(403, 'pi_forbidden', self::tr('Permissão negada.'));
            return;
        }

        // Rate limit.
        $key = RateLimiter::keyForUser('dashboard_metrics', $userId);
        if (!RateLimiter::check($key, self::RATE_MAX, self::RATE_WINDOW)) {
            $this->sendError(429, 'pi_rate_limited', self::tr('Muitas requisições.'));
            return;
        }

        // Return aggregated metrics — no PII.
        $metrics = $this->query->allMetrics(12);

        if (function_exists('wp_send_json_success')) {
            \wp_send_json_success($metrics, 200);
            return;
        }
        $this->emitJson(['success' => true, 'data' => $metrics], 200);
    }

    private function sendError(int $status, string $code, string $message): void
    {
        $payload = ['code' => $code, 'message' => $message, 'data' => ['status' => $status]];
        if (function_exists('wp_send_json_error')) {
            \wp_send_json_error($payload, $status);
            return;
        }
        $this->emitJson(['success' => false, 'data' => $payload], $status);
    }

    /** @param array<string,mixed> $payload */
    private function emitJson(array $payload, int $status): void
    {
        if (function_exists('status_header')) {
            \status_header($status);
        } elseif (!headers_sent()) {
            header('HTTP/1.1 ' . $status);
        }
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo (string) json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private static function tr(string $text): string
    {
        return function_exists('__') ? (string) \__($text, 'participe-ibram') : $text;
    }
}
