<?php
/**
 * View de Relatórios Avançados
 *
 * @package CRM_Developer
 */

if (!defined('ABSPATH')) {
    exit;
}

// Inclui modais de ajuda
require_once CRM_DEV_PLUGIN_DIR . 'admin/views/partials/help-modals.php';

$estados = CRM_Dev_Helpers::get_estados();
$regioes = CRM_Dev_Helpers::get_regioes();
$generos = CRM_Dev_Helpers::get_generos();
$racas = CRM_Dev_Helpers::get_racas();
$eixos = CRM_Dev_Helpers::get_eixos_tematicos();
$categorias = CRM_Dev_Helpers::get_categorias_representacao();
$etapas = CRM_Dev_Helpers::get_etapas_participacao();
?>

<div class="wrap crm-dev-wrap">
    <div class="crm-dev-header">
        <div class="header-title-row">
            <div>
                <h1>
                    <i class="fas fa-chart-bar"></i>
                    <?php _e('Relatórios e Análises', 'crm-developer'); ?>
                </h1>
                <p class="crm-dev-subtitle"><?php _e('Análise detalhada com filtros avançados e múltiplas visualizações', 'crm-developer'); ?></p>
            </div>
            <?php crm_dev_render_help_button('reports'); ?>
        </div>
    </div>

    <!-- Painel de Filtros Avançados -->
    <div class="crm-dev-card">
        <div class="card-header">
            <h3><i class="fas fa-filter"></i> <?php _e('Filtros Avançados', 'crm-developer'); ?></h3>
            <button type="button" class="button" id="btn-toggle-filters">
                <i class="fas fa-chevron-down"></i>
            </button>
        </div>
        <div class="card-body" id="filters-panel">
            <div class="advanced-filters">
                <div class="filters-row">
                    <div class="filter-group">
                        <label><?php _e('Período', 'crm-developer'); ?></label>
                        <select id="filter-period">
                            <option value="all"><?php _e('Todo o período', 'crm-developer'); ?></option>
                            <option value="7"><?php _e('Últimos 7 dias', 'crm-developer'); ?></option>
                            <option value="30" selected><?php _e('Últimos 30 dias', 'crm-developer'); ?></option>
                            <option value="90"><?php _e('Últimos 90 dias', 'crm-developer'); ?></option>
                            <option value="180"><?php _e('Últimos 6 meses', 'crm-developer'); ?></option>
                            <option value="365"><?php _e('Último ano', 'crm-developer'); ?></option>
                            <option value="custom"><?php _e('Personalizado', 'crm-developer'); ?></option>
                        </select>
                    </div>
                    <div class="filter-group date-range" style="display: none;">
                        <label><?php _e('De', 'crm-developer'); ?></label>
                        <input type="date" id="filter-date-from">
                    </div>
                    <div class="filter-group date-range" style="display: none;">
                        <label><?php _e('Até', 'crm-developer'); ?></label>
                        <input type="date" id="filter-date-to">
                    </div>
                    <div class="filter-group">
                        <label><?php _e('Região', 'crm-developer'); ?></label>
                        <select id="filter-regiao">
                            <option value=""><?php _e('Todas as regiões', 'crm-developer'); ?></option>
                            <?php foreach ($regioes as $regiao) : ?>
                                <option value="<?php echo esc_attr($regiao); ?>"><?php echo esc_html($regiao); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label><?php _e('Estado', 'crm-developer'); ?></label>
                        <select id="filter-estado">
                            <option value=""><?php _e('Todos os estados', 'crm-developer'); ?></option>
                            <?php foreach ($estados as $uf => $nome) : ?>
                                <option value="<?php echo esc_attr($uf); ?>"><?php echo esc_html($nome); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="filters-row">
                    <div class="filter-group">
                        <label><?php _e('Status', 'crm-developer'); ?></label>
                        <select id="filter-status">
                            <option value=""><?php _e('Todos os status', 'crm-developer'); ?></option>
                            <option value="ativo"><?php _e('Ativo', 'crm-developer'); ?></option>
                            <option value="inativo"><?php _e('Inativo', 'crm-developer'); ?></option>
                            <option value="pendente"><?php _e('Pendente', 'crm-developer'); ?></option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label><?php _e('Engajamento', 'crm-developer'); ?></label>
                        <select id="filter-engajamento">
                            <option value=""><?php _e('Todos os níveis', 'crm-developer'); ?></option>
                            <option value="alto"><?php _e('Alto (70+)', 'crm-developer'); ?></option>
                            <option value="medio"><?php _e('Médio (40-69)', 'crm-developer'); ?></option>
                            <option value="baixo"><?php _e('Baixo (<40)', 'crm-developer'); ?></option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label><?php _e('Gênero', 'crm-developer'); ?></label>
                        <select id="filter-genero">
                            <option value=""><?php _e('Todos os gêneros', 'crm-developer'); ?></option>
                            <?php foreach ($generos as $key => $label) : ?>
                                <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label><?php _e('Raça/Etnia', 'crm-developer'); ?></label>
                        <select id="filter-raca">
                            <option value=""><?php _e('Todas', 'crm-developer'); ?></option>
                            <?php foreach ($racas as $key => $label) : ?>
                                <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label><?php _e('Eixo Temático', 'crm-developer'); ?></label>
                        <select id="filter-eixo">
                            <option value=""><?php _e('Todos os eixos', 'crm-developer'); ?></option>
                            <?php foreach ($eixos as $key => $label) : ?>
                                <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="filters-actions">
                    <button type="button" class="button button-primary" id="btn-apply-filters">
                        <i class="fas fa-search"></i> <?php _e('Aplicar Filtros', 'crm-developer'); ?>
                    </button>
                    <button type="button" class="button" id="btn-clear-filters">
                        <i class="fas fa-times"></i> <?php _e('Limpar Filtros', 'crm-developer'); ?>
                    </button>
                    <button type="button" class="button" id="btn-export-report">
                        <i class="fas fa-download"></i> <?php _e('Exportar Relatório', 'crm-developer'); ?>
                    </button>
                    <button type="button" class="button" id="btn-print-report">
                        <i class="fas fa-print"></i> <?php _e('Imprimir', 'crm-developer'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Resumo com Contadores Dinâmicos -->
    <div class="crm-dev-stats-grid" id="stats-summary">
        <div class="crm-dev-stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #059669 0%, #10b981 100%);">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-content">
                <span class="stat-number" id="stat-total">0</span>
                <span class="stat-label"><?php _e('Contatos Filtrados', 'crm-developer'); ?></span>
            </div>
        </div>
        <div class="crm-dev-stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #0d9488 0%, #14b8a6 100%);">
                <i class="fas fa-map-marker-alt"></i>
            </div>
            <div class="stat-content">
                <span class="stat-number" id="stat-estados">0</span>
                <span class="stat-label"><?php _e('Estados', 'crm-developer'); ?></span>
            </div>
        </div>
        <div class="crm-dev-stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #047857 0%, #059669 100%);">
                <i class="fas fa-star"></i>
            </div>
            <div class="stat-content">
                <span class="stat-number" id="stat-engajamento">0%</span>
                <span class="stat-label"><?php _e('Score Médio', 'crm-developer'); ?></span>
            </div>
        </div>
        <div class="crm-dev-stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #065f46 0%, #0d9488 100%);">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <span class="stat-number" id="stat-lgpd">0%</span>
                <span class="stat-label"><?php _e('Com Consentimento LGPD', 'crm-developer'); ?></span>
            </div>
        </div>
    </div>

    <!-- Seletor de Visualização -->
    <div class="crm-dev-card">
        <div class="card-header">
            <h3><i class="fas fa-eye"></i> <?php _e('Tipo de Visualização', 'crm-developer'); ?></h3>
        </div>
        <div class="card-body">
            <div class="visualization-tabs">
                <button type="button" class="viz-tab active" data-viz="geografico">
                    <i class="fas fa-map"></i> <?php _e('Geográfico', 'crm-developer'); ?>
                </button>
                <button type="button" class="viz-tab" data-viz="demografico">
                    <i class="fas fa-users"></i> <?php _e('Demográfico', 'crm-developer'); ?>
                </button>
                <button type="button" class="viz-tab" data-viz="participacao">
                    <i class="fas fa-calendar-check"></i> <?php _e('Participação', 'crm-developer'); ?>
                </button>
                <button type="button" class="viz-tab" data-viz="engajamento">
                    <i class="fas fa-chart-line"></i> <?php _e('Engajamento', 'crm-developer'); ?>
                </button>
                <button type="button" class="viz-tab" data-viz="mobilizacao">
                    <i class="fas fa-bullhorn"></i> <?php _e('Mobilização', 'crm-developer'); ?>
                </button>
                <button type="button" class="viz-tab" data-viz="temporal">
                    <i class="fas fa-clock"></i> <?php _e('Temporal', 'crm-developer'); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Área de Gráficos Dinâmicos -->
    <div id="charts-container">
        <!-- Visualização Geográfica -->
        <div class="viz-panel active" id="viz-geografico">
            <div class="reports-grid">
                <div class="crm-dev-card">
                    <div class="card-header">
                        <h3><i class="fas fa-globe-americas"></i> <?php _e('Distribuição por Região', 'crm-developer'); ?></h3>
                        <div class="chart-type-selector">
                            <button type="button" class="chart-btn active" data-chart="geo-region" data-type="bar"><i class="fas fa-chart-bar"></i></button>
                            <button type="button" class="chart-btn" data-chart="geo-region" data-type="pie"><i class="fas fa-chart-pie"></i></button>
                            <button type="button" class="chart-btn" data-chart="geo-region" data-type="doughnut"><i class="fas fa-circle-notch"></i></button>
                        </div>
                    </div>
                    <div class="card-body">
                        <canvas id="chart-geo-region" height="280"></canvas>
                    </div>
                </div>
                <div class="crm-dev-card">
                    <div class="card-header">
                        <h3><i class="fas fa-map-marked-alt"></i> <?php _e('Top 15 Estados', 'crm-developer'); ?></h3>
                        <div class="chart-type-selector">
                            <button type="button" class="chart-btn active" data-chart="geo-state" data-type="horizontalBar"><i class="fas fa-align-left"></i></button>
                            <button type="button" class="chart-btn" data-chart="geo-state" data-type="bar"><i class="fas fa-chart-bar"></i></button>
                        </div>
                    </div>
                    <div class="card-body">
                        <canvas id="chart-geo-state" height="350"></canvas>
                    </div>
                </div>
            </div>
            <div class="crm-dev-card full-width">
                <div class="card-header">
                    <h3><i class="fas fa-exclamation-triangle"></i> <?php _e('Lacunas Territoriais', 'crm-developer'); ?></h3>
                </div>
                <div class="card-body">
                    <div id="territorial-gaps"></div>
                </div>
            </div>
        </div>

        <!-- Visualização Demográfica -->
        <div class="viz-panel" id="viz-demografico">
            <div class="reports-grid">
                <div class="crm-dev-card">
                    <div class="card-header">
                        <h3><i class="fas fa-venus-mars"></i> <?php _e('Distribuição por Gênero', 'crm-developer'); ?></h3>
                        <div class="chart-type-selector">
                            <button type="button" class="chart-btn active" data-chart="demo-gender" data-type="doughnut"><i class="fas fa-circle-notch"></i></button>
                            <button type="button" class="chart-btn" data-chart="demo-gender" data-type="pie"><i class="fas fa-chart-pie"></i></button>
                            <button type="button" class="chart-btn" data-chart="demo-gender" data-type="bar"><i class="fas fa-chart-bar"></i></button>
                        </div>
                    </div>
                    <div class="card-body">
                        <canvas id="chart-demo-gender" height="280"></canvas>
                    </div>
                </div>
                <div class="crm-dev-card">
                    <div class="card-header">
                        <h3><i class="fas fa-palette"></i> <?php _e('Distribuição por Raça/Etnia', 'crm-developer'); ?></h3>
                        <div class="chart-type-selector">
                            <button type="button" class="chart-btn active" data-chart="demo-race" data-type="doughnut"><i class="fas fa-circle-notch"></i></button>
                            <button type="button" class="chart-btn" data-chart="demo-race" data-type="pie"><i class="fas fa-chart-pie"></i></button>
                            <button type="button" class="chart-btn" data-chart="demo-race" data-type="bar"><i class="fas fa-chart-bar"></i></button>
                        </div>
                    </div>
                    <div class="card-body">
                        <canvas id="chart-demo-race" height="280"></canvas>
                    </div>
                </div>
            </div>
            <div class="crm-dev-card full-width">
                <div class="card-header">
                    <h3><i class="fas fa-birthday-cake"></i> <?php _e('Distribuição por Faixa Etária', 'crm-developer'); ?></h3>
                </div>
                <div class="card-body">
                    <canvas id="chart-demo-age" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Visualização de Participação -->
        <div class="viz-panel" id="viz-participacao">
            <div class="reports-grid">
                <div class="crm-dev-card">
                    <div class="card-header">
                        <h3><i class="fas fa-layer-group"></i> <?php _e('Etapas de Participação', 'crm-developer'); ?></h3>
                    </div>
                    <div class="card-body">
                        <canvas id="chart-part-etapas" height="280"></canvas>
                    </div>
                </div>
                <div class="crm-dev-card">
                    <div class="card-header">
                        <h3><i class="fas fa-user-tag"></i> <?php _e('Tipos de Participação', 'crm-developer'); ?></h3>
                    </div>
                    <div class="card-body">
                        <canvas id="chart-part-tipos" height="280"></canvas>
                    </div>
                </div>
            </div>
            <div class="reports-grid">
                <div class="crm-dev-card">
                    <div class="card-header">
                        <h3><i class="fas fa-sitemap"></i> <?php _e('Categorias de Representação', 'crm-developer'); ?></h3>
                    </div>
                    <div class="card-body">
                        <canvas id="chart-part-categorias" height="280"></canvas>
                    </div>
                </div>
                <div class="crm-dev-card">
                    <div class="card-header">
                        <h3><i class="fas fa-book"></i> <?php _e('Eixos Temáticos', 'crm-developer'); ?></h3>
                    </div>
                    <div class="card-body">
                        <canvas id="chart-part-eixos" height="280"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Visualização de Engajamento -->
        <div class="viz-panel" id="viz-engajamento">
            <div class="crm-dev-card full-width">
                <div class="card-header">
                    <h3><i class="fas fa-thermometer-half"></i> <?php _e('Distribuição de Score de Engajamento', 'crm-developer'); ?></h3>
                </div>
                <div class="card-body">
                    <div class="engagement-distribution">
                        <div class="engagement-bar-large" id="engagement-bar">
                            <!-- Preenchido via JS -->
                        </div>
                        <div class="engagement-legend">
                            <span class="legend-item"><span class="dot high"></span> <?php _e('Alto (70+)', 'crm-developer'); ?> - <span id="eng-alto">0</span></span>
                            <span class="legend-item"><span class="dot medium"></span> <?php _e('Médio (40-69)', 'crm-developer'); ?> - <span id="eng-medio">0</span></span>
                            <span class="legend-item"><span class="dot low"></span> <?php _e('Baixo (<40)', 'crm-developer'); ?> - <span id="eng-baixo">0</span></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="reports-grid">
                <div class="crm-dev-card">
                    <div class="card-header">
                        <h3><i class="fas fa-chart-area"></i> <?php _e('Engajamento por Região', 'crm-developer'); ?></h3>
                    </div>
                    <div class="card-body">
                        <canvas id="chart-eng-region" height="280"></canvas>
                    </div>
                </div>
                <div class="crm-dev-card">
                    <div class="card-header">
                        <h3><i class="fas fa-trophy"></i> <?php _e('Top 10 Contatos por Score', 'crm-developer'); ?></h3>
                    </div>
                    <div class="card-body">
                        <div id="top-contacts-list"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Visualização de Mobilização -->
        <div class="viz-panel" id="viz-mobilizacao">
            <div class="crm-dev-card full-width">
                <div class="card-header">
                    <h3><i class="fas fa-hands-helping"></i> <?php _e('Potencial de Mobilização', 'crm-developer'); ?></h3>
                </div>
                <div class="card-body">
                    <div class="mobilization-grid" id="mobilization-grid">
                        <!-- Preenchido via JS -->
                    </div>
                </div>
            </div>
            <div class="reports-grid">
                <div class="crm-dev-card">
                    <div class="card-header">
                        <h3><i class="fas fa-hand-paper"></i> <?php _e('Deseja Continuar Participando', 'crm-developer'); ?></h3>
                    </div>
                    <div class="card-body">
                        <canvas id="chart-mob-continuar" height="250"></canvas>
                    </div>
                </div>
                <div class="crm-dev-card">
                    <div class="card-header">
                        <h3><i class="fas fa-users-cog"></i> <?php _e('Participação em Coletivos', 'crm-developer'); ?></h3>
                    </div>
                    <div class="card-body">
                        <canvas id="chart-mob-coletivos" height="250"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Visualização Temporal -->
        <div class="viz-panel" id="viz-temporal">
            <div class="crm-dev-card full-width">
                <div class="card-header">
                    <h3><i class="fas fa-calendar-alt"></i> <?php _e('Evolução de Cadastros', 'crm-developer'); ?></h3>
                    <div class="chart-type-selector">
                        <button type="button" class="chart-btn active" data-chart="temp-monthly" data-type="line"><i class="fas fa-chart-line"></i></button>
                        <button type="button" class="chart-btn" data-chart="temp-monthly" data-type="bar"><i class="fas fa-chart-bar"></i></button>
                        <button type="button" class="chart-btn" data-chart="temp-monthly" data-type="area"><i class="fas fa-chart-area"></i></button>
                    </div>
                </div>
                <div class="card-body">
                    <canvas id="chart-temp-monthly" height="250"></canvas>
                </div>
            </div>
            <div class="reports-grid">
                <div class="crm-dev-card">
                    <div class="card-header">
                        <h3><i class="fas fa-calendar-week"></i> <?php _e('Cadastros por Dia da Semana', 'crm-developer'); ?></h3>
                    </div>
                    <div class="card-body">
                        <canvas id="chart-temp-weekday" height="250"></canvas>
                    </div>
                </div>
                <div class="crm-dev-card">
                    <div class="card-header">
                        <h3><i class="fas fa-clock"></i> <?php _e('Cadastros por Hora do Dia', 'crm-developer'); ?></h3>
                    </div>
                    <div class="card-body">
                        <canvas id="chart-temp-hour" height="250"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabela de Dados Detalhados -->
    <div class="crm-dev-card full-width">
        <div class="card-header">
            <h3><i class="fas fa-table"></i> <?php _e('Dados Detalhados', 'crm-developer'); ?></h3>
            <button type="button" class="button" id="btn-export-table">
                <i class="fas fa-file-excel"></i> <?php _e('Exportar Tabela', 'crm-developer'); ?>
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="crm-dev-table" id="report-data-table">
                    <thead>
                        <tr>
                            <th><?php _e('Métrica', 'crm-developer'); ?></th>
                            <th><?php _e('Valor', 'crm-developer'); ?></th>
                            <th><?php _e('Percentual', 'crm-developer'); ?></th>
                            <th><?php _e('Comparativo', 'crm-developer'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="report-data-tbody">
                        <!-- Preenchido via JS -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Configurações
    const colors = [
        'rgba(5, 150, 105, 0.8)',
        'rgba(13, 148, 136, 0.8)',
        'rgba(16, 185, 129, 0.8)',
        'rgba(20, 184, 166, 0.8)',
        'rgba(6, 95, 70, 0.8)',
        'rgba(4, 120, 87, 0.8)',
        'rgba(52, 211, 153, 0.8)',
        'rgba(110, 231, 183, 0.8)',
        'rgba(167, 243, 208, 0.8)',
        'rgba(209, 250, 229, 0.8)'
    ];

    const generos = <?php echo json_encode($generos); ?>;
    const racas = <?php echo json_encode($racas); ?>;
    const eixos = <?php echo json_encode($eixos); ?>;
    const categorias = <?php echo json_encode($categorias); ?>;
    const etapas = <?php echo json_encode($etapas); ?>;
    const estadosMap = <?php echo json_encode($estados); ?>;

    let charts = {};
    let reportData = null;

    // Período personalizado
    $('#filter-period').on('change', function() {
        if ($(this).val() === 'custom') {
            $('.date-range').show();
        } else {
            $('.date-range').hide();
        }
    });

    // Toggle filtros
    $('#btn-toggle-filters').on('click', function() {
        $('#filters-panel').slideToggle();
        $(this).find('i').toggleClass('fa-chevron-down fa-chevron-up');
    });

    // Tabs de visualização
    $('.viz-tab').on('click', function() {
        $('.viz-tab').removeClass('active');
        $(this).addClass('active');
        $('.viz-panel').removeClass('active');
        $('#viz-' + $(this).data('viz')).addClass('active');
    });

    // Seletor de tipo de gráfico
    $(document).on('click', '.chart-btn', function() {
        const chartId = $(this).data('chart');
        const chartType = $(this).data('type');

        $(this).siblings().removeClass('active');
        $(this).addClass('active');

        updateChartType(chartId, chartType);
    });

    // Aplicar filtros
    $('#btn-apply-filters').on('click', loadReportData);

    // Limpar filtros
    $('#btn-clear-filters').on('click', function() {
        $('#filter-period').val('30');
        $('#filter-regiao, #filter-estado, #filter-status, #filter-engajamento, #filter-genero, #filter-raca, #filter-eixo').val('');
        $('.date-range').hide();
        loadReportData();
    });

    // Imprimir
    $('#btn-print-report').on('click', () => window.print());

    // Exportar relatório
    $('#btn-export-report').on('click', exportReport);
    $('#btn-export-table').on('click', exportTable);

    // Carrega dados iniciais
    loadReportData();

    function getFilters() {
        return {
            period: $('#filter-period').val(),
            date_from: $('#filter-date-from').val(),
            date_to: $('#filter-date-to').val(),
            regiao: $('#filter-regiao').val(),
            estado: $('#filter-estado').val(),
            status: $('#filter-status').val(),
            engajamento: $('#filter-engajamento').val(),
            genero: $('#filter-genero').val(),
            raca: $('#filter-raca').val(),
            eixo: $('#filter-eixo').val()
        };
    }

    function loadReportData() {
        $('#stats-summary').addClass('loading');

        $.post(crmDevAdmin.ajaxUrl, {
            action: 'crm_dev_get_report_data',
            nonce: crmDevAdmin.nonce,
            filters: getFilters()
        }, function(response) {
            if (response.success) {
                reportData = response.data;
                updateStats(reportData.summary);
                updateCharts(reportData);
                updateTables(reportData);
            }
            $('#stats-summary').removeClass('loading');
        });
    }

    function updateStats(summary) {
        $('#stat-total').text(summary.total.toLocaleString('pt-BR'));
        $('#stat-estados').text(summary.estados);
        $('#stat-engajamento').text(Math.round(summary.score_medio) + '%');
        $('#stat-lgpd').text(Math.round(summary.lgpd_percent) + '%');
    }

    function updateCharts(data) {
        // Destrói gráficos existentes
        Object.values(charts).forEach(chart => chart.destroy());
        charts = {};

        // Geográfico
        if (data.by_region) {
            charts['geo-region'] = createChart('chart-geo-region', 'bar', {
                labels: data.by_region.map(d => d.label),
                data: data.by_region.map(d => d.value)
            });
        }

        if (data.by_state) {
            charts['geo-state'] = createChart('chart-geo-state', 'horizontalBar', {
                labels: data.by_state.slice(0, 15).map(d => estadosMap[d.label] || d.label),
                data: data.by_state.slice(0, 15).map(d => d.value)
            });
        }

        // Lacunas territoriais
        updateTerritorialGaps(data.missing_states || []);

        // Demográfico
        if (data.by_gender) {
            charts['demo-gender'] = createChart('chart-demo-gender', 'doughnut', {
                labels: data.by_gender.map(d => generos[d.label] || d.label),
                data: data.by_gender.map(d => d.value)
            });
        }

        if (data.by_race) {
            charts['demo-race'] = createChart('chart-demo-race', 'doughnut', {
                labels: data.by_race.map(d => racas[d.label] || d.label),
                data: data.by_race.map(d => d.value)
            });
        }

        if (data.by_age) {
            charts['demo-age'] = createChart('chart-demo-age', 'bar', {
                labels: data.by_age.map(d => d.label),
                data: data.by_age.map(d => d.value)
            });
        }

        // Participação
        if (data.by_etapa) {
            charts['part-etapas'] = createChart('chart-part-etapas', 'bar', {
                labels: data.by_etapa.map(d => etapas[d.label] || d.label),
                data: data.by_etapa.map(d => d.value)
            });
        }

        if (data.by_tipo_participacao) {
            charts['part-tipos'] = createChart('chart-part-tipos', 'bar', {
                labels: data.by_tipo_participacao.map(d => d.label),
                data: data.by_tipo_participacao.map(d => d.value)
            });
        }

        if (data.by_categoria) {
            charts['part-categorias'] = createChart('chart-part-categorias', 'doughnut', {
                labels: data.by_categoria.map(d => categorias[d.label] || d.label),
                data: data.by_categoria.map(d => d.value)
            });
        }

        if (data.by_eixo) {
            charts['part-eixos'] = createChart('chart-part-eixos', 'bar', {
                labels: data.by_eixo.map(d => eixos[d.label] || d.label),
                data: data.by_eixo.map(d => d.value)
            }, { indexAxis: 'y' });
        }

        // Engajamento
        updateEngagementBar(data.engagement);

        if (data.engagement_by_region) {
            charts['eng-region'] = createChart('chart-eng-region', 'bar', {
                labels: data.engagement_by_region.map(d => d.label),
                datasets: [
                    { label: 'Alto', data: data.engagement_by_region.map(d => d.alto), backgroundColor: 'rgba(5, 150, 105, 0.8)' },
                    { label: 'Médio', data: data.engagement_by_region.map(d => d.medio), backgroundColor: 'rgba(245, 158, 11, 0.8)' },
                    { label: 'Baixo', data: data.engagement_by_region.map(d => d.baixo), backgroundColor: 'rgba(239, 68, 68, 0.8)' }
                ]
            }, { stacked: true });
        }

        updateTopContacts(data.top_contacts || []);

        // Mobilização
        updateMobilizationGrid(data.mobilization || {});

        if (data.continuar_participando) {
            charts['mob-continuar'] = createChart('chart-mob-continuar', 'doughnut', {
                labels: data.continuar_participando.map(d => d.label === 'sim' ? 'Sim' : (d.label === 'nao' ? 'Não' : 'Talvez')),
                data: data.continuar_participando.map(d => d.value)
            });
        }

        if (data.participa_coletivos) {
            charts['mob-coletivos'] = createChart('chart-mob-coletivos', 'doughnut', {
                labels: data.participa_coletivos.map(d => d.label === 'sim' ? 'Sim' : 'Não'),
                data: data.participa_coletivos.map(d => d.value)
            });
        }

        // Temporal
        if (data.monthly_registrations) {
            charts['temp-monthly'] = createChart('chart-temp-monthly', 'line', {
                labels: data.monthly_registrations.map(d => d.label),
                data: data.monthly_registrations.map(d => d.value)
            }, { fill: true });
        }

        if (data.by_weekday) {
            charts['temp-weekday'] = createChart('chart-temp-weekday', 'bar', {
                labels: data.by_weekday.map(d => d.label),
                data: data.by_weekday.map(d => d.value)
            });
        }

        if (data.by_hour) {
            charts['temp-hour'] = createChart('chart-temp-hour', 'line', {
                labels: data.by_hour.map(d => d.label + 'h'),
                data: data.by_hour.map(d => d.value)
            });
        }
    }

    function createChart(canvasId, type, chartData, options = {}) {
        const ctx = document.getElementById(canvasId);
        if (!ctx) return null;

        const isHorizontal = type === 'horizontalBar';
        const actualType = isHorizontal ? 'bar' : type;

        const config = {
            type: actualType,
            data: {
                labels: chartData.labels,
                datasets: chartData.datasets || [{
                    data: chartData.data,
                    backgroundColor: colors,
                    borderColor: colors.map(c => c.replace('0.8', '1')),
                    borderWidth: 1,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: isHorizontal ? 'y' : (options.indexAxis || 'x'),
                plugins: {
                    legend: { display: type === 'doughnut' || type === 'pie', position: 'bottom' }
                },
                scales: (type === 'doughnut' || type === 'pie') ? {} : {
                    y: { beginAtZero: true, stacked: options.stacked || false },
                    x: { stacked: options.stacked || false }
                }
            }
        };

        if (options.fill) {
            config.data.datasets[0].fill = true;
            config.data.datasets[0].backgroundColor = 'rgba(5, 150, 105, 0.1)';
            config.data.datasets[0].borderColor = 'rgba(5, 150, 105, 1)';
        }

        return new Chart(ctx, config);
    }

    function updateChartType(chartId, newType) {
        if (!charts[chartId] || !reportData) return;

        const canvas = charts[chartId].canvas;
        const canvasId = canvas.id;
        charts[chartId].destroy();

        // Reconstrói com novo tipo
        const dataKey = chartId.replace('chart-', '').replace(/-/g, '_');
        // Simplificado - na prática, precisaria mapear corretamente
        loadReportData();
    }

    function updateTerritorialGaps(missingStates) {
        const container = $('#territorial-gaps');
        if (missingStates.length === 0) {
            container.html(`
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <p>Excelente! Todos os estados brasileiros estão representados.</p>
                </div>
            `);
        } else {
            let html = `
                <div class="warning-message">
                    <i class="fas fa-map-marked-alt"></i>
                    <p>${missingStates.length} estados sem representantes:</p>
                </div>
                <div class="missing-states">
            `;
            missingStates.forEach(uf => {
                html += `<span class="state-tag">${estadosMap[uf] || uf} (${uf})</span>`;
            });
            html += '</div>';
            container.html(html);
        }
    }

    function updateEngagementBar(data) {
        if (!data) return;
        const total = data.alto + data.medio + data.baixo;
        if (total === 0) return;

        const altoPercent = (data.alto / total * 100).toFixed(1);
        const medioPercent = (data.medio / total * 100).toFixed(1);
        const baixoPercent = (data.baixo / total * 100).toFixed(1);

        $('#engagement-bar').html(`
            <div class="bar-segment high" style="width: ${altoPercent}%"><span>${data.alto}</span></div>
            <div class="bar-segment medium" style="width: ${medioPercent}%"><span>${data.medio}</span></div>
            <div class="bar-segment low" style="width: ${baixoPercent}%"><span>${data.baixo}</span></div>
        `);

        $('#eng-alto').text(data.alto);
        $('#eng-medio').text(data.medio);
        $('#eng-baixo').text(data.baixo);
    }

    function updateTopContacts(contacts) {
        let html = '<div class="top-contacts-list">';
        contacts.forEach((c, i) => {
            const scoreColor = c.score >= 70 ? '#059669' : (c.score >= 40 ? '#f59e0b' : '#ef4444');
            html += `
                <div class="top-contact-item">
                    <span class="position">${i + 1}</span>
                    <span class="name">${c.nome}</span>
                    <span class="score" style="background: ${scoreColor}">${c.score}</span>
                </div>
            `;
        });
        html += '</div>';
        $('#top-contacts-list').html(html);
    }

    function updateMobilizationGrid(data) {
        const interests = {
            'interesse_formacao': { icon: 'graduation-cap', label: 'Formação' },
            'interesse_conteudo': { icon: 'pen', label: 'Produção de Conteúdo' },
            'interesse_incidencia': { icon: 'landmark', label: 'Incidência Política' },
            'interesse_mobilizacao': { icon: 'bullhorn', label: 'Mobilização Territorial' },
            'interesse_voluntariado': { icon: 'hands-helping', label: 'Voluntariado' },
            'interesse_foruns': { icon: 'comments', label: 'Fóruns Temáticos' }
        };

        let html = '';
        for (const [key, info] of Object.entries(interests)) {
            const count = data[key] || 0;
            const percent = data.total > 0 ? Math.round(count / data.total * 100) : 0;
            html += `
                <div class="mobilization-item">
                    <div class="mobilization-icon"><i class="fas fa-${info.icon}"></i></div>
                    <div class="mobilization-info">
                        <span class="mobilization-label">${info.label}</span>
                        <span class="mobilization-count">${count} contatos</span>
                    </div>
                    <div class="mobilization-bar"><div class="bar-fill" style="width: ${percent}%"></div></div>
                    <span class="mobilization-percent">${percent}%</span>
                </div>
            `;
        }
        $('#mobilization-grid').html(html);
    }

    function updateTables(data) {
        let html = '';
        const metrics = [
            { label: 'Total de Contatos', value: data.summary.total, key: 'total' },
            { label: 'Estados Representados', value: data.summary.estados, key: 'estados' },
            { label: 'Score Médio', value: Math.round(data.summary.score_medio) + '%', key: 'score' },
            { label: 'Com Consentimento LGPD', value: Math.round(data.summary.lgpd_percent) + '%', key: 'lgpd' },
            { label: 'Alto Engajamento', value: data.engagement?.alto || 0, key: 'alto' },
            { label: 'Médio Engajamento', value: data.engagement?.medio || 0, key: 'medio' },
            { label: 'Baixo Engajamento', value: data.engagement?.baixo || 0, key: 'baixo' }
        ];

        metrics.forEach(m => {
            const percent = typeof m.value === 'number' && data.summary.total > 0
                ? ((m.value / data.summary.total) * 100).toFixed(1) + '%'
                : '-';
            html += `
                <tr>
                    <td>${m.label}</td>
                    <td><strong>${m.value}</strong></td>
                    <td>${percent}</td>
                    <td>-</td>
                </tr>
            `;
        });

        $('#report-data-tbody').html(html);
    }

    function exportReport() {
        if (!reportData) return;

        // Gera PDF ou imagem dos gráficos
        alert('Exportação de relatório em desenvolvimento. Use a função Imprimir para gerar um PDF.');
    }

    function exportTable() {
        if (!reportData) return;

        const data = [
            ['Métrica', 'Valor', 'Percentual'],
            ['Total de Contatos', reportData.summary.total, '100%'],
            ['Estados Representados', reportData.summary.estados, '-'],
            ['Score Médio', Math.round(reportData.summary.score_medio) + '%', '-'],
            ['Com LGPD', Math.round(reportData.summary.lgpd_percent) + '%', '-']
        ];

        const ws = XLSX.utils.aoa_to_sheet(data);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Relatório');
        XLSX.writeFile(wb, 'relatorio_crm_' + new Date().toISOString().slice(0, 10) + '.xlsx');
    }
});
</script>

<?php
// Modal de ajuda
crm_dev_render_help_modal_reports();
crm_dev_render_help_modal_script();
?>
