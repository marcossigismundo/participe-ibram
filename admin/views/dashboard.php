<?php
/**
 * View do Dashboard
 *
 * @package CRM_Developer
 */

if (!defined('ABSPATH')) {
    exit;
}

// Inclui modais de ajuda
require_once CRM_DEV_PLUGIN_DIR . 'admin/views/partials/help-modals.php';

$dashboard_data = CRM_Dev_Dashboard::get_dashboard_data();
$stats = $dashboard_data['stats'];
$suggestions = $dashboard_data['suggestions'];
$charts = $dashboard_data['charts'];
?>

<div class="wrap crm-dev-wrap">
    <div class="crm-dev-header">
        <div class="header-title-row">
            <div>
                <h1>
                    <i class="fas fa-chart-pie"></i>
                    <?php esc_html_e('Dashboard CRM', 'crm-developer'); ?>
                </h1>
                <p class="crm-dev-subtitle"><?php esc_html_e('Visão geral da sua base de contatos e indicadores de gestão', 'crm-developer'); ?></p>
            </div>
            <?php crm_dev_render_help_button('dashboard'); ?>
        </div>
    </div>

    <!-- Cards de Estatísticas -->
    <div class="crm-dev-stats-grid">
        <div class="crm-dev-stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #059669 0%, #10b981 100%);">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-content">
                <span class="stat-number"><?php echo esc_html(number_format_i18n($stats['total'])); ?></span>
                <span class="stat-label"><?php esc_html_e('Total de Contatos', 'crm-developer'); ?></span>
            </div>
        </div>

        <div class="crm-dev-stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #0d9488 0%, #14b8a6 100%);">
                <i class="fas fa-user-plus"></i>
            </div>
            <div class="stat-content">
                <span class="stat-number"><?php echo esc_html(number_format_i18n($stats['novos_mes'])); ?></span>
                <span class="stat-label"><?php esc_html_e('Novos este mês', 'crm-developer'); ?></span>
            </div>
        </div>

        <div class="crm-dev-stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #047857 0%, #059669 100%);">
                <i class="fas fa-star"></i>
            </div>
            <div class="stat-content">
                <span class="stat-number"><?php echo esc_html(number_format_i18n($stats['score_alto'])); ?></span>
                <span class="stat-label"><?php esc_html_e('Alto Engajamento', 'crm-developer'); ?></span>
            </div>
        </div>

        <div class="crm-dev-stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #065f46 0%, #0d9488 100%);">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-content">
                <span class="stat-number"><?php echo esc_html(round($stats['score_medio_valor'] ?? 0)); ?></span>
                <span class="stat-label"><?php esc_html_e('Score Médio', 'crm-developer'); ?></span>
            </div>
        </div>
    </div>

    <div class="crm-dev-dashboard-grid">
        <!-- Coluna Principal -->
        <div class="crm-dev-main-column">
            <!-- Gráfico de Cadastros Mensais -->
            <div class="crm-dev-card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-area"></i> <?php esc_html_e('Cadastros por Mês', 'crm-developer'); ?></h3>
                </div>
                <div class="card-body">
                    <canvas id="chart-monthly" height="100"></canvas>
                </div>
            </div>

            <!-- Gráficos lado a lado -->
            <div class="crm-dev-charts-row">
                <div class="crm-dev-card">
                    <div class="card-header">
                        <h3><i class="fas fa-map-marked-alt"></i> <?php esc_html_e('Por Região', 'crm-developer'); ?></h3>
                    </div>
                    <div class="card-body">
                        <canvas id="chart-region" height="200"></canvas>
                    </div>
                </div>

                <div class="crm-dev-card">
                    <div class="card-header">
                        <h3><i class="fas fa-thermometer-half"></i> <?php esc_html_e('Por Engajamento', 'crm-developer'); ?></h3>
                    </div>
                    <div class="card-body">
                        <canvas id="chart-score" height="200"></canvas>
                    </div>
                </div>
            </div>

            <!-- Contatos Recentes -->
            <div class="crm-dev-card">
                <div class="card-header">
                    <h3><i class="fas fa-clock"></i> <?php esc_html_e('Contatos Recentes', 'crm-developer'); ?></h3>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=crm-developer&section=contacts')); ?>" class="btn-link">
                        <?php esc_html_e('Ver todos', 'crm-developer'); ?> <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
                <div class="card-body">
                    <?php if (!empty($dashboard_data['recent_contacts'])) : ?>
                        <table class="crm-dev-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Nome', 'crm-developer'); ?></th>
                                    <th><?php esc_html_e('Email', 'crm-developer'); ?></th>
                                    <th><?php esc_html_e('Estado', 'crm-developer'); ?></th>
                                    <th><?php esc_html_e('Score', 'crm-developer'); ?></th>
                                    <th><?php esc_html_e('Data', 'crm-developer'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dashboard_data['recent_contacts'] as $contact) : ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo esc_url(admin_url('admin.php?page=crm-developer&section=contacts&action=view&id=' . $contact['id'])); ?>">
                                                <?php echo esc_html($contact['nome_completo']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo esc_html($contact['email'] ?: '-'); ?></td>
                                        <td><?php echo esc_html($contact['estado'] ?: '-'); ?></td>
                                        <td>
                                            <span class="score-badge" style="background-color: <?php echo esc_attr(CRM_Dev_Helpers::get_score_color($contact['score_engajamento'])); ?>">
                                                <?php echo esc_html($contact['score_engajamento']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo esc_html(CRM_Dev_Helpers::format_datetime($contact['created_at'], 'd/m/Y')); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <p class="crm-dev-empty"><?php esc_html_e('Nenhum contato cadastrado ainda.', 'crm-developer'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Coluna Lateral -->
        <div class="crm-dev-side-column">
            <!-- Ações Rápidas -->
            <div class="crm-dev-card">
                <div class="card-header">
                    <h3><i class="fas fa-bolt"></i> <?php esc_html_e('Ações Rápidas', 'crm-developer'); ?></h3>
                </div>
                <div class="card-body">
                    <div class="quick-actions">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=crm-developer&section=contact-new')); ?>" class="quick-action-btn primary">
                            <i class="fas fa-user-plus"></i>
                            <?php esc_html_e('Novo Contato', 'crm-developer'); ?>
                        </a>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=crm-developer&section=import-export')); ?>" class="quick-action-btn">
                            <i class="fas fa-file-import"></i>
                            <?php esc_html_e('Importar', 'crm-developer'); ?>
                        </a>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=crm-developer&section=import-export&tab=export')); ?>" class="quick-action-btn">
                            <i class="fas fa-file-export"></i>
                            <?php esc_html_e('Exportar', 'crm-developer'); ?>
                        </a>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=crm-developer&section=reports')); ?>" class="quick-action-btn">
                            <i class="fas fa-chart-bar"></i>
                            <?php esc_html_e('Relatórios', 'crm-developer'); ?>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Sugestões de Melhoria -->
            <div class="crm-dev-card">
                <div class="card-header">
                    <h3><i class="fas fa-lightbulb"></i> <?php esc_html_e('Sugestões Inteligentes', 'crm-developer'); ?></h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($suggestions)) : ?>
                        <div class="suggestions-list">
                            <?php foreach (array_slice($suggestions, 0, 5) as $suggestion) : ?>
                                <div class="suggestion-item <?php echo esc_attr($suggestion['type']); ?>">
                                    <div class="suggestion-icon">
                                        <i class="fas fa-<?php echo esc_attr($suggestion['icon']); ?>"></i>
                                    </div>
                                    <div class="suggestion-content">
                                        <strong><?php echo esc_html($suggestion['title']); ?></strong>
                                        <p><?php echo esc_html($suggestion['description']); ?></p>
                                        <span class="suggestion-action"><?php echo esc_html($suggestion['action']); ?></span>
                                    </div>
                                    <span class="priority-badge <?php echo esc_attr($suggestion['priority']); ?>">
                                        <?php echo esc_html(ucfirst($suggestion['priority'])); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else : ?>
                        <p class="crm-dev-success">
                            <i class="fas fa-check-circle"></i>
                            <?php esc_html_e('Ótimo! Sua base está bem estruturada.', 'crm-developer'); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Próximas Ações -->
            <?php if (!empty($dashboard_data['pending_actions']) || !empty($dashboard_data['overdue_actions'])) : ?>
                <div class="crm-dev-card">
                    <div class="card-header">
                        <h3><i class="fas fa-tasks"></i> <?php esc_html_e('Próximas Ações', 'crm-developer'); ?></h3>
                    </div>
                    <div class="card-body">
                        <div class="actions-list">
                            <?php
                            $all_actions = array_merge(
                                array_map(function ($a) {
                                    $a['overdue'] = true;
                                    return $a;
                                }, $dashboard_data['overdue_actions']),
                                $dashboard_data['pending_actions']
                            );
                            foreach (array_slice($all_actions, 0, 5) as $action) :
                                $is_overdue = !empty($action['overdue']);
                            ?>
                                <div class="action-item <?php echo esc_attr($is_overdue ? 'overdue' : ''); ?>">
                                    <div class="action-date">
                                        <?php echo esc_html(CRM_Dev_Helpers::format_date($action['data_proxima_acao'])); ?>
                                        <?php if ($is_overdue) : ?>
                                            <span class="overdue-badge"><?php esc_html_e('Atrasado', 'crm-developer'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="action-content">
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=crm-developer&section=contacts&action=view&id=' . $action['contact_id'])); ?>">
                                            <?php echo esc_html($action['nome_completo']); ?>
                                        </a>
                                        <p><?php echo esc_html(wp_trim_words($action['proxima_acao'], 10)); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Dados para gráficos
    const chartData = <?php echo json_encode($charts); ?>;

    // Cores padrão (tons de verde)
    const colors = [
        'rgba(5, 150, 105, 0.8)',
        'rgba(13, 148, 136, 0.8)',
        'rgba(16, 185, 129, 0.8)',
        'rgba(20, 184, 166, 0.8)',
        'rgba(6, 95, 70, 0.8)',
        'rgba(4, 120, 87, 0.8)',
        'rgba(52, 211, 153, 0.8)'
    ];

    // Gráfico Mensal
    if (chartData.monthly_registrations && chartData.monthly_registrations.length > 0) {
        new Chart(document.getElementById('chart-monthly'), {
            type: 'line',
            data: {
                labels: chartData.monthly_registrations.map(d => d.label),
                datasets: [{
                    label: 'Cadastros',
                    data: chartData.monthly_registrations.map(d => d.value),
                    borderColor: 'rgba(5, 150, 105, 1)',
                    backgroundColor: 'rgba(5, 150, 105, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }

    // Gráfico por Região
    if (chartData.by_region && chartData.by_region.length > 0) {
        new Chart(document.getElementById('chart-region'), {
            type: 'doughnut',
            data: {
                labels: chartData.by_region.map(d => d.label),
                datasets: [{
                    data: chartData.by_region.map(d => d.value),
                    backgroundColor: colors
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    }

    // Gráfico por Score
    if (chartData.by_score && chartData.by_score.length > 0) {
        new Chart(document.getElementById('chart-score'), {
            type: 'doughnut',
            data: {
                labels: chartData.by_score.map(d => d.label),
                datasets: [{
                    data: chartData.by_score.map(d => d.value),
                    backgroundColor: ['#27ae60', '#f39c12', '#e74c3c']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    }
});
</script>

<?php
// Modal de ajuda
crm_dev_render_help_modal_dashboard();
crm_dev_render_help_modal_script();
?>
