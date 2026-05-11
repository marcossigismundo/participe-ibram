<?php
/**
 * DashboardController — renders the admin dashboard page.
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\Controllers
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\Controllers;

use Ibram\ParticipeIbram\Core\Helpers\Json;
use Ibram\ParticipeIbram\Presentation\Admin\MenuRegistry;
use Ibram\ParticipeIbram\Presentation\Admin\Support\DashboardMetricsQuery;

/**
 * Capability gate: pi_listar_cadastros (same as the menu entry).
 * Dashboard shows only aggregated, non-PII data.
 */
final class DashboardController
{
    private DashboardMetricsQuery $query;

    public function __construct(DashboardMetricsQuery $query)
    {
        $this->query = $query;
    }

    /**
     * Render the dashboard template with KPI data.
     */
    public function render(): void
    {
        if (!function_exists('current_user_can')
            || !\current_user_can(MenuRegistry::CAP_LISTAR_CADASTROS)
        ) {
            \wp_die(
                function_exists('esc_html__')
                    ? (string) \esc_html__('Você não tem permissão para acessar esta página.', 'participe-ibram')
                    : 'Acesso negado.',
                403
            );
            return;
        }

        $metrics = $this->query->allMetrics(12);

        // Extracted for template variables (no PII — only integers/floats/arrays of aggregates).
        $cadastros_pendentes  = (int) (($metrics['cadastros_por_status']['submetido'] ?? 0)
            + ($metrics['cadastros_por_status']['em_analise'] ?? 0));
        $cadastros_em_analise = (int) ($metrics['cadastros_por_status']['em_analise'] ?? 0);
        $editais_ativos       = (int) ($metrics['editais_ativos'] ?? 0);
        $solicitacoes_lgpd    = (int) ($metrics['solicitacoes_lgpd'] ?? 0);
        $recursos_vencendo    = (int) ($metrics['recursos_vencendo'] ?? 0);

        $tempo_medio     = $metrics['tempo_medio_analise_dias'];
        $cadastros_tipo  = (array) ($metrics['cadastros_por_tipo'] ?? []);
        $cadastros_status = (array) ($metrics['cadastros_por_status'] ?? []);
        $cadastros_mes   = (array) ($metrics['cadastros_por_mes'] ?? []);
        $cadastros_estado = (array) ($metrics['cadastros_por_estado'] ?? []);

        // Top 10 states by deferidos.
        arsort($cadastros_estado);
        $top10_estados = array_slice($cadastros_estado, 0, 10, true);

        // JSON for JS — safe via Json::encodeForScript (R5 V-05).
        try {
            $dashboard_json = Json::encodeForScript([
                'cadastrosTipo'   => $cadastros_tipo,
                'cadastrosStatus' => $cadastros_status,
                'cadastrosMes'    => $cadastros_mes,
                'top10Estados'    => $top10_estados,
            ]);
        } catch (\RuntimeException $e) {
            $dashboard_json = '{}';
        }

        // Nonce for AJAX refresh (scoped to user).
        $userId          = function_exists('get_current_user_id') ? (int) \get_current_user_id() : 0;
        $refresh_nonce   = function_exists('wp_create_nonce')
            ? \wp_create_nonce('pi_admin_dashboard_metrics_' . $userId)
            : '';

        $template = $this->templatePath('dashboard.php');
        if ($template !== null) {
            include $template;
            return;
        }

        echo '<div class="wrap"><p>' .
            (function_exists('esc_html__')
                ? (string) \esc_html__('Template não encontrado.', 'participe-ibram')
                : 'Template não encontrado.')
            . '</p></div>';
    }

    private function templatePath(string $relative): ?string
    {
        if (\defined('PI_PLUGIN_DIR')) {
            $base = (string) \PI_PLUGIN_DIR;
        } else {
            $base = dirname(__DIR__, 4);
        }
        $candidate = rtrim($base, '/\\') . '/templates/admin/' . ltrim($relative, '/');
        return file_exists($candidate) ? $candidate : null;
    }
}
