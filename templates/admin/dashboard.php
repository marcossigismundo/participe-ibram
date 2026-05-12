<?php
/**
 * Template — Admin Dashboard Participe Ibram (legado — Wave 7).
 *
 * W11-C: migrado para PageLayout chrome. Notice::warning adicionado indicando
 *        tela legada. Inline <style> e breadcrumb manual removidos.
 *        Esta tela é mantida funcional mas marcada como legada; use o Painel
 *        principal (painel.php) como entrada preferencial.
 *
 * Variables injected by DashboardController::render():
 *  - int    $cadastros_pendentes
 *  - int    $cadastros_em_analise
 *  - int    $editais_ativos
 *  - int    $solicitacoes_lgpd
 *  - int    $recursos_vencendo
 *  - float|null $tempo_medio
 *  - array  $cadastros_tipo    ['PF'=>N,'OR'=>N,'SM'=>N]
 *  - array  $cadastros_status  ['status'=>N,...]
 *  - array  $cadastros_mes     [['mes'=>'YYYY-MM','total'=>N],...]
 *  - array  $top10_estados     ['UF'=>N,...]
 *  - string $dashboard_json    — JSON blob for JS (already escaped for script context)
 *  - string $refresh_nonce
 *
 * @package Ibram\ParticipeIbram\Templates\Admin
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use Ibram\ParticipeIbram\Presentation\Admin\Support\Notice;
use Ibram\ParticipeIbram\Presentation\Admin\Support\PageLayout;

// Safe defaults.
$cadastros_pendentes  = isset($cadastros_pendentes)  ? (int) $cadastros_pendentes  : 0;
$cadastros_em_analise = isset($cadastros_em_analise) ? (int) $cadastros_em_analise : 0;
$editais_ativos       = isset($editais_ativos)       ? (int) $editais_ativos       : 0;
$solicitacoes_lgpd    = isset($solicitacoes_lgpd)    ? (int) $solicitacoes_lgpd    : 0;
$recursos_vencendo    = isset($recursos_vencendo)    ? (int) $recursos_vencendo    : 0;
$tempo_medio          = isset($tempo_medio) && is_numeric($tempo_medio) ? round((float) $tempo_medio, 1) : null;
$cadastros_tipo       = isset($cadastros_tipo)  && is_array($cadastros_tipo)  ? $cadastros_tipo  : [];
$cadastros_status     = isset($cadastros_status) && is_array($cadastros_status) ? $cadastros_status : [];
$cadastros_mes        = isset($cadastros_mes)   && is_array($cadastros_mes)   ? $cadastros_mes   : [];
$top10_estados        = isset($top10_estados)   && is_array($top10_estados)   ? $top10_estados   : [];
$dashboard_json       = isset($dashboard_json)  ? (string) $dashboard_json  : '{}';
$refresh_nonce        = isset($refresh_nonce)   ? (string) $refresh_nonce   : '';

$ajax_url = function_exists('admin_url') ? esc_url(admin_url('admin-ajax.php')) : '';

PageLayout::open(
    __('Dashboard (legado) — Participe Ibram', 'participe-ibram'),
    [
        ['label' => __('Início', 'participe-ibram'), 'url' => admin_url()],
        ['label' => __('Visão Geral (legado)', 'participe-ibram')],
    ]
);
?>

<a class="pi-skip-link" href="#pi-dash-main"><?php esc_html_e('Pular para o conteúdo', 'participe-ibram'); ?></a>

<?php
Notice::warning(
    __('Esta tela é legada e será desativada em breve — use o Painel principal para a visão geral atualizada.', 'participe-ibram'),
    true
);
?>

<div class="pi-dash__header-actions" style="margin-bottom:1rem;">
    <button type="button"
            id="pi-dash-refresh"
            class="pi-button pi-button--secondary"
            data-nonce="<?php echo esc_attr($refresh_nonce); ?>"
            data-ajaxurl="<?php echo esc_attr($ajax_url); ?>"
            aria-controls="pi-dash-main">
        <span aria-hidden="true">&#8635;</span>
        <?php esc_html_e('Atualizar dados', 'participe-ibram'); ?>
    </button>
</div>

<main id="pi-dash-main" tabindex="-1">

    <?php /* ── Status bar: live region ────────────────────────────── */ ?>
    <div class="pi-dash__status-bar"
         role="status"
         aria-live="polite"
         aria-atomic="true"
         id="pi-dash-status">
    </div>

    <?php /* ── Row 1: KPI Cards ─────────────────────────────────────── */ ?>
    <h2 class="pi-sr-only"><?php esc_html_e('Indicadores principais', 'participe-ibram'); ?></h2>
    <div class="pi-kpi-grid" aria-live="polite" aria-atomic="false">

        <article class="pi-card pi-card--kpi" aria-labelledby="kpi-pendentes">
            <header class="pi-card__header">
                <h3 id="kpi-pendentes" class="pi-card__title"><?php esc_html_e('Cadastros pendentes', 'participe-ibram'); ?></h3>
            </header>
            <div class="pi-card__body">
                <span class="pi-kpi__value" data-pi-metric="cadastros_pendentes">
                    <?php echo esc_html((string) $cadastros_pendentes); ?>
                </span>
                <span class="pi-kpi__label"><?php esc_html_e('aguardando análise', 'participe-ibram'); ?></span>
            </div>
            <footer class="pi-card__footer">
                <a class="pi-button pi-button--sm pi-button--secondary"
                   href="<?php echo esc_url(admin_url('admin.php?page=participe-ibram_cadastros')); ?>">
                    <?php esc_html_e('Ver fila', 'participe-ibram'); ?>
                </a>
            </footer>
        </article>

        <article class="pi-card pi-card--kpi" aria-labelledby="kpi-analise">
            <header class="pi-card__header">
                <h3 id="kpi-analise" class="pi-card__title"><?php esc_html_e('Em análise', 'participe-ibram'); ?></h3>
            </header>
            <div class="pi-card__body">
                <span class="pi-kpi__value" data-pi-metric="cadastros_em_analise">
                    <?php echo esc_html((string) $cadastros_em_analise); ?>
                </span>
                <span class="pi-kpi__label"><?php esc_html_e('em andamento', 'participe-ibram'); ?></span>
            </div>
            <footer class="pi-card__footer">
                <?php if ($tempo_medio !== null) : ?>
                    <span class="pi-kpi__aux">
                        <?php
                        printf(
                            /* translators: %s: number of days (decimal) */
                            esc_html__('Tempo médio: %s dias', 'participe-ibram'),
                            esc_html((string) $tempo_medio)
                        );
                        ?>
                    </span>
                <?php endif; ?>
            </footer>
        </article>

        <article class="pi-card pi-card--kpi" aria-labelledby="kpi-editais">
            <header class="pi-card__header">
                <h3 id="kpi-editais" class="pi-card__title"><?php esc_html_e('Editais ativos', 'participe-ibram'); ?></h3>
            </header>
            <div class="pi-card__body">
                <span class="pi-kpi__value" data-pi-metric="editais_ativos">
                    <?php echo esc_html((string) $editais_ativos); ?>
                </span>
                <span class="pi-kpi__label"><?php esc_html_e('em andamento', 'participe-ibram'); ?></span>
            </div>
            <footer class="pi-card__footer">
                <a class="pi-button pi-button--sm pi-button--secondary"
                   href="<?php echo esc_url(admin_url('admin.php?page=participe-ibram_editais')); ?>">
                    <?php esc_html_e('Ver editais', 'participe-ibram'); ?>
                </a>
            </footer>
        </article>

        <article class="pi-card pi-card--kpi" aria-labelledby="kpi-lgpd">
            <header class="pi-card__header">
                <h3 id="kpi-lgpd" class="pi-card__title"><?php esc_html_e('Solicitações LGPD', 'participe-ibram'); ?></h3>
            </header>
            <div class="pi-card__body">
                <span class="pi-kpi__value" data-pi-metric="solicitacoes_lgpd">
                    <?php echo esc_html((string) $solicitacoes_lgpd); ?>
                </span>
                <span class="pi-kpi__label"><?php esc_html_e('pendentes de resposta', 'participe-ibram'); ?></span>
            </div>
            <footer class="pi-card__footer">
                <?php if ($recursos_vencendo > 0) : ?>
                    <span class="pi-badge pi-badge--warning" role="img"
                          aria-label="<?php
                          printf(
                              /* translators: %d: count */
                              esc_attr__('%d recurso(s) com prazo próximo', 'participe-ibram'),
                              $recursos_vencendo
                          );
                          ?>">
                        <?php echo esc_html((string) $recursos_vencendo); ?> <?php esc_html_e('recurso(s) vencendo', 'participe-ibram'); ?>
                    </span>
                <?php endif; ?>
            </footer>
        </article>

    </div><!-- .pi-kpi-grid -->

    <?php /* ── Row 2: Pie + Bar charts ──────────────────────────────── */ ?>
    <div class="pi-charts-row pi-charts-row--2col">

        <?php /* Pie: Cadastros por tipo */ ?>
        <section class="pi-chart-card" aria-labelledby="chart-tipo-title">
            <h2 id="chart-tipo-title" class="pi-chart-card__title">
                <?php esc_html_e('Cadastros por tipo (PF / OR / SM)', 'participe-ibram'); ?>
            </h2>
            <figure role="img" aria-labelledby="chart-tipo-figcap">
                <figcaption id="chart-tipo-figcap" class="pi-sr-only">
                    <?php esc_html_e('Gráfico de pizza: distribuição de cadastros por tipologia de agente cultural.', 'participe-ibram'); ?>
                </figcaption>
                <div class="pi-chart-canvas" id="pi-chart-tipo" aria-hidden="true"></div>
            </figure>
            <div class="pi-chart-alt-wrapper">
                <button type="button"
                        class="pi-chart-alt-toggle"
                        aria-expanded="false"
                        aria-controls="pi-chart-tipo-table">
                    <?php esc_html_e('Ver dados em tabela', 'participe-ibram'); ?>
                </button>
                <div id="pi-chart-tipo-table" class="pi-chart-alt-table" hidden>
                    <table class="pi-table pi-table--compact">
                        <caption><?php esc_html_e('Cadastros por tipo', 'participe-ibram'); ?></caption>
                        <thead>
                            <tr>
                                <th scope="col"><?php esc_html_e('Tipo', 'participe-ibram'); ?></th>
                                <th scope="col"><?php esc_html_e('Total', 'participe-ibram'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cadastros_tipo as $tipo => $total) : ?>
                            <tr>
                                <td><?php echo esc_html((string) $tipo); ?></td>
                                <td><?php echo esc_html((string) (int) $total); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($cadastros_tipo)) : ?>
                            <tr><td colspan="2"><?php esc_html_e('Sem dados', 'participe-ibram'); ?></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <?php /* Bar: Cadastros por status */ ?>
        <section class="pi-chart-card" aria-labelledby="chart-status-title">
            <h2 id="chart-status-title" class="pi-chart-card__title">
                <?php esc_html_e('Cadastros por status', 'participe-ibram'); ?>
            </h2>
            <figure role="img" aria-labelledby="chart-status-figcap">
                <figcaption id="chart-status-figcap" class="pi-sr-only">
                    <?php esc_html_e('Gráfico de barras: contagem de cadastros agrupados por status de análise.', 'participe-ibram'); ?>
                </figcaption>
                <div class="pi-chart-canvas" id="pi-chart-status" aria-hidden="true"></div>
            </figure>
            <div class="pi-chart-alt-wrapper">
                <button type="button"
                        class="pi-chart-alt-toggle"
                        aria-expanded="false"
                        aria-controls="pi-chart-status-table">
                    <?php esc_html_e('Ver dados em tabela', 'participe-ibram'); ?>
                </button>
                <div id="pi-chart-status-table" class="pi-chart-alt-table" hidden>
                    <table class="pi-table pi-table--compact">
                        <caption><?php esc_html_e('Cadastros por status', 'participe-ibram'); ?></caption>
                        <thead>
                            <tr>
                                <th scope="col"><?php esc_html_e('Status', 'participe-ibram'); ?></th>
                                <th scope="col"><?php esc_html_e('Total', 'participe-ibram'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cadastros_status as $status => $total) : ?>
                            <tr>
                                <td><?php echo esc_html((string) $status); ?></td>
                                <td><?php echo esc_html((string) (int) $total); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($cadastros_status)) : ?>
                            <tr><td colspan="2"><?php esc_html_e('Sem dados', 'participe-ibram'); ?></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

    </div><!-- .pi-charts-row--2col -->

    <?php /* ── Row 3: Line chart — cadastros por mês ─────────────────── */ ?>
    <section class="pi-chart-card pi-chart-card--wide" aria-labelledby="chart-mes-title">
        <h2 id="chart-mes-title" class="pi-chart-card__title">
            <?php esc_html_e('Cadastros submetidos — últimos 12 meses', 'participe-ibram'); ?>
        </h2>
        <figure role="img" aria-labelledby="chart-mes-figcap">
            <figcaption id="chart-mes-figcap" class="pi-sr-only">
                <?php esc_html_e('Gráfico de linha: evolução mensal de cadastros submetidos nos últimos 12 meses.', 'participe-ibram'); ?>
            </figcaption>
            <div class="pi-chart-canvas pi-chart-canvas--tall" id="pi-chart-mes" aria-hidden="true"></div>
        </figure>
        <div class="pi-chart-alt-wrapper">
            <button type="button"
                    class="pi-chart-alt-toggle"
                    aria-expanded="false"
                    aria-controls="pi-chart-mes-table">
                <?php esc_html_e('Ver dados em tabela', 'participe-ibram'); ?>
            </button>
            <div id="pi-chart-mes-table" class="pi-chart-alt-table" hidden>
                <table class="pi-table pi-table--compact">
                    <caption><?php esc_html_e('Cadastros submetidos por mês', 'participe-ibram'); ?></caption>
                    <thead>
                        <tr>
                            <th scope="col"><?php esc_html_e('Mês', 'participe-ibram'); ?></th>
                            <th scope="col"><?php esc_html_e('Total', 'participe-ibram'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cadastros_mes as $row) : ?>
                        <tr>
                            <td><?php echo esc_html((string) $row['mes']); ?></td>
                            <td><?php echo esc_html((string) (int) $row['total']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($cadastros_mes)) : ?>
                        <tr><td colspan="2"><?php esc_html_e('Sem dados', 'participe-ibram'); ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <?php /* ── Row 4: Top 10 estados ──────────────────────────────────── */ ?>
    <section class="pi-chart-card" aria-labelledby="chart-estados-title">
        <h2 id="chart-estados-title" class="pi-chart-card__title">
            <?php esc_html_e('Top 10 estados — cadastros deferidos', 'participe-ibram'); ?>
        </h2>
        <div class="pi-top10-bar" id="pi-chart-estados" aria-hidden="true"></div>
        <div class="pi-chart-alt-wrapper">
            <button type="button"
                    class="pi-chart-alt-toggle"
                    aria-expanded="true"
                    aria-controls="pi-chart-estados-table">
                <?php esc_html_e('Ver dados em tabela', 'participe-ibram'); ?>
            </button>
            <div id="pi-chart-estados-table" class="pi-chart-alt-table">
                <table class="pi-table pi-table--compact">
                    <caption><?php esc_html_e('Top 10 estados com mais cadastros deferidos', 'participe-ibram'); ?></caption>
                    <thead>
                        <tr>
                            <th scope="col"><?php esc_html_e('UF', 'participe-ibram'); ?></th>
                            <th scope="col"><?php esc_html_e('Deferidos', 'participe-ibram'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top10_estados as $uf => $total) : ?>
                        <tr>
                            <td><?php echo esc_html((string) $uf); ?></td>
                            <td><?php echo esc_html((string) (int) $total); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($top10_estados)) : ?>
                        <tr><td colspan="2"><?php esc_html_e('Sem dados', 'participe-ibram'); ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

</main>

<?php /* JSON data island — script-context safe encoding used by DashboardController */ ?>
<script type="application/json" id="pi-dashboard-data">
<?php echo $dashboard_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — already encoded via Json::encodeForScript ?>
</script>
<?php
if (function_exists('wp_enqueue_script')) {
    $assetBase = \defined('PI_PLUGIN_URL') ? (string) \PI_PLUGIN_URL : plugin_dir_url(dirname(__DIR__, 2) . '/crm-developer.php');
    $assetBase = rtrim($assetBase, '/');
    wp_enqueue_script(
        'pi-admin-dashboard',
        $assetBase . '/assets/dist/js/admin/dashboard.js',
        [],
        '7.0.0',
        true
    );
    wp_enqueue_style(
        'pi-admin-dashboard',
        $assetBase . '/assets/dist/css/admin-dashboard.css',
        [],
        '7.0.0'
    );
}
?>

<?php PageLayout::close(); ?>
