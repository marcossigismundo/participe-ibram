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
                    <?php esc_html_e('Relatórios e Análises', 'crm-developer'); ?>
                </h1>
                <p class="crm-dev-subtitle"><?php esc_html_e('Análise detalhada com filtros avançados e múltiplas visualizações', 'crm-developer'); ?></p>
            </div>
            <?php crm_dev_render_help_button('reports'); ?>
        </div>
    </div>

    <!-- Painel de Filtros Avançados -->
    <div class="crm-dev-card">
        <div class="card-header">
            <h3><i class="fas fa-filter"></i> <?php esc_html_e('Filtros Avançados', 'crm-developer'); ?></h3>
            <button type="button" class="button" id="btn-toggle-filters">
                <i class="fas fa-chevron-down"></i>
            </button>
        </div>
        <div class="card-body" id="filters-panel">
            <div class="advanced-filters">
                <div class="filters-row">
                    <div class="filter-group">
                        <label><?php esc_html_e('Período', 'crm-developer'); ?></label>
                        <select id="filter-period">
                            <option value="all"><?php esc_html_e('Todo o período', 'crm-developer'); ?></option>
                            <option value="7"><?php esc_html_e('Últimos 7 dias', 'crm-developer'); ?></option>
                            <option value="30" selected><?php esc_html_e('Últimos 30 dias', 'crm-developer'); ?></option>
                            <option value="90"><?php esc_html_e('Últimos 90 dias', 'crm-developer'); ?></option>
                            <option value="180"><?php esc_html_e('Últimos 6 meses', 'crm-developer'); ?></option>
                            <option value="365"><?php esc_html_e('Último ano', 'crm-developer'); ?></option>
                            <option value="custom"><?php esc_html_e('Personalizado', 'crm-developer'); ?></option>
                        </select>
                    </div>
                    <div class="filter-group date-range" style="display: none;">
                        <label><?php esc_html_e('De', 'crm-developer'); ?></label>
                        <input type="date" id="filter-date-from">
                    </div>
                    <div class="filter-group date-range" style="display: none;">
                        <label><?php esc_html_e('Até', 'crm-developer'); ?></label>
                        <input type="date" id="filter-date-to">
                    </div>
                    <div class="filter-group">
                        <label><?php esc_html_e('Região', 'crm-developer'); ?></label>
                        <select id="filter-regiao">
                            <option value=""><?php esc_html_e('Todas as regiões', 'crm-developer'); ?></option>
                            <?php foreach ($regioes as $regiao) : ?>
                                <option value="<?php echo esc_attr($regiao); ?>"><?php echo esc_html($regiao); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label><?php esc_html_e('Estado', 'crm-developer'); ?></label>
                        <select id="filter-estado">
                            <option value=""><?php esc_html_e('Todos os estados', 'crm-developer'); ?></option>
                            <?php foreach ($estados as $uf => $nome) : ?>
                                <option value="<?php echo esc_attr($uf); ?>"><?php echo esc_html($nome); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="filters-row">
                    <div class="filter-group">
                        <label><?php esc_html_e('Status', 'crm-developer'); ?></label>
                        <select id="filter-status">
                            <option value=""><?php esc_html_e('Todos os status', 'crm-developer'); ?></option>
                            <option value="ativo"><?php esc_html_e('Ativo', 'crm-developer'); ?></option>
                            <option value="inativo"><?php esc_html_e('Inativo', 'crm-developer'); ?></option>
                            <option value="pendente"><?php esc_html_e('Pendente', 'crm-developer'); ?></option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label><?php esc_html_e('Engajamento', 'crm-developer'); ?></label>
                        <select id="filter-engajamento">
                            <option value=""><?php esc_html_e('Todos os níveis', 'crm-developer'); ?></option>
                            <option value="alto"><?php esc_html_e('Alto (70+)', 'crm-developer'); ?></option>
                            <option value="medio"><?php esc_html_e('Médio (40-69)', 'crm-developer'); ?></option>
                            <option value="baixo"><?php esc_html_e('Baixo (<40)', 'crm-developer'); ?></option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label><?php esc_html_e('Gênero', 'crm-developer'); ?></label>
                        <select id="filter-genero">
                            <option value=""><?php esc_html_e('Todos os gêneros', 'crm-developer'); ?></option>
                            <?php foreach ($generos as $key => $label) : ?>
                                <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label><?php esc_html_e('Raça/Etnia', 'crm-developer'); ?></label>
                        <select id="filter-raca">
                            <option value=""><?php esc_html_e('Todas', 'crm-developer'); ?></option>
                            <?php foreach ($racas as $key => $label) : ?>
                                <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label><?php esc_html_e('Eixo Temático', 'crm-developer'); ?></label>
                        <select id="filter-eixo">
                            <option value=""><?php esc_html_e('Todos os eixos', 'crm-developer'); ?></option>
                            <?php foreach ($eixos as $key => $label) : ?>
                                <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="filters-actions">
                    <button type="button" class="button button-primary" id="btn-apply-filters">
                        <i class="fas fa-search"></i> <?php esc_html_e('Aplicar Filtros', 'crm-developer'); ?>
                    </button>
                    <button type="button" class="button" id="btn-clear-filters">
                        <i class="fas fa-times"></i> <?php esc_html_e('Limpar Filtros', 'crm-developer'); ?>
                    </button>
                    <button type="button" class="button" id="btn-export-report">
                        <i class="fas fa-download"></i> <?php esc_html_e('Exportar Relatório', 'crm-developer'); ?>
                    </button>
                    <button type="button" class="button" id="btn-print-report">
                        <i class="fas fa-print"></i> <?php esc_html_e('Imprimir', 'crm-developer'); ?>
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
                <span class="stat-label"><?php esc_html_e('Contatos Filtrados', 'crm-developer'); ?></span>
            </div>
        </div>
        <div class="crm-dev-stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #0d9488 0%, #14b8a6 100%);">
                <i class="fas fa-map-marker-alt"></i>
            </div>
            <div class="stat-content">
                <span class="stat-number" id="stat-estados">0</span>
                <span class="stat-label"><?php esc_html_e('Estados', 'crm-developer'); ?></span>
            </div>
        </div>
        <div class="crm-dev-stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #047857 0%, #059669 100%);">
                <i class="fas fa-star"></i>
            </div>
            <div class="stat-content">
                <span class="stat-number" id="stat-engajamento">0%</span>
                <span class="stat-label"><?php esc_html_e('Score Médio', 'crm-developer'); ?></span>
            </div>
        </div>
        <div class="crm-dev-stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #065f46 0%, #0d9488 100%);">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <span class="stat-number" id="stat-lgpd">0%</span>
                <span class="stat-label"><?php esc_html_e('Com Consentimento LGPD', 'crm-developer'); ?></span>
            </div>
        </div>
    </div>

    <!-- Seletor de Visualização -->
    <div class="crm-dev-card">
        <div class="card-header">
            <h3><i class="fas fa-eye"></i> <?php esc_html_e('Tipo de Visualização', 'crm-developer'); ?></h3>
        </div>
        <div class="card-body">
            <div class="visualization-tabs">
                <button type="button" class="viz-tab active" data-viz="geografico">
                    <i class="fas fa-map"></i> <?php esc_html_e('Geográfico', 'crm-developer'); ?>
                </button>
                <button type="button" class="viz-tab" data-viz="demografico">
                    <i class="fas fa-users"></i> <?php esc_html_e('Demográfico', 'crm-developer'); ?>
                </button>
                <button type="button" class="viz-tab" data-viz="participacao">
                    <i class="fas fa-calendar-check"></i> <?php esc_html_e('Participação', 'crm-developer'); ?>
                </button>
                <button type="button" class="viz-tab" data-viz="engajamento">
                    <i class="fas fa-chart-line"></i> <?php esc_html_e('Engajamento', 'crm-developer'); ?>
                </button>
                <button type="button" class="viz-tab" data-viz="mobilizacao">
                    <i class="fas fa-bullhorn"></i> <?php esc_html_e('Mobilização', 'crm-developer'); ?>
                </button>
                <button type="button" class="viz-tab" data-viz="temporal">
                    <i class="fas fa-clock"></i> <?php esc_html_e('Temporal', 'crm-developer'); ?>
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
                        <h3><i class="fas fa-globe-americas"></i> <?php esc_html_e('Distribuição por Região', 'crm-developer'); ?></h3>
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
                        <h3><i class="fas fa-map-marked-alt"></i> <?php esc_html_e('Top 15 Estados', 'crm-developer'); ?></h3>
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
                    <h3><i class="fas fa-exclamation-triangle"></i> <?php esc_html_e('Lacunas Territoriais', 'crm-developer'); ?></h3>
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
                        <h3><i class="fas fa-venus-mars"></i> <?php esc_html_e('Distribuição por Gênero', 'crm-developer'); ?></h3>
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
                        <h3><i class="fas fa-palette"></i> <?php esc_html_e('Distribuição por Raça/Etnia', 'crm-developer'); ?></h3>
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
                    <h3><i class="fas fa-birthday-cake"></i> <?php esc_html_e('Distribuição por Faixa Etária', 'crm-developer'); ?></h3>
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
                        <h3><i class="fas fa-layer-group"></i> <?php esc_html_e('Etapas de Participação', 'crm-developer'); ?></h3>
                    </div>
                    <div class="card-body">
                        <canvas id="chart-part-etapas" height="280"></canvas>
                    </div>
                </div>
                <div class="crm-dev-card">
                    <div class="card-header">
                        <h3><i class="fas fa-user-tag"></i> <?php esc_html_e('Tipos de Participação', 'crm-developer'); ?></h3>
                    </div>
                    <div class="card-body">
                        <canvas id="chart-part-tipos" height="280"></canvas>
                    </div>
                </div>
            </div>
            <div class="reports-grid">
                <div class="crm-dev-card">
                    <div class="card-header">
                        <h3><i class="fas fa-sitemap"></i> <?php esc_html_e('Categorias de Representação', 'crm-developer'); ?></h3>
                    </div>
                    <div class="card-body">
                        <canvas id="chart-part-categorias" height="280"></canvas>
                    </div>
                </div>
                <div class="crm-dev-card">
                    <div class="card-header">
                        <h3><i class="fas fa-book"></i> <?php esc_html_e('Eixos Temáticos', 'crm-developer'); ?></h3>
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
                    <h3><i class="fas fa-thermometer-half"></i> <?php esc_html_e('Distribuição de Score de Engajamento', 'crm-developer'); ?></h3>
                </div>
                <div class="card-body">
                    <div class="engagement-distribution">
                        <div class="engagement-bar-large" id="engagement-bar">
                            <!-- Preenchido via JS -->
                        </div>
                        <div class="engagement-legend">
                            <span class="legend-item"><span class="dot high"></span> <?php esc_html_e('Alto (70+)', 'crm-developer'); ?> - <span id="eng-alto">0</span></span>
                            <span class="legend-item"><span class="dot medium"></span> <?php esc_html_e('Médio (40-69)', 'crm-developer'); ?> - <span id="eng-medio">0</span></span>
                            <span class="legend-item"><span class="dot low"></span> <?php esc_html_e('Baixo (<40)', 'crm-developer'); ?> - <span id="eng-baixo">0</span></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="reports-grid">
                <div class="crm-dev-card">
                    <div class="card-header">
                        <h3><i class="fas fa-chart-area"></i> <?php esc_html_e('Engajamento por Região', 'crm-developer'); ?></h3>
                    </div>
                    <div class="card-body">
                        <canvas id="chart-eng-region" height="280"></canvas>
                    </div>
                </div>
                <div class="crm-dev-card">
                    <div class="card-header">
                        <h3><i class="fas fa-trophy"></i> <?php esc_html_e('Top 10 Contatos por Score', 'crm-developer'); ?></h3>
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
                    <h3><i class="fas fa-hands-helping"></i> <?php esc_html_e('Potencial de Mobilização', 'crm-developer'); ?></h3>
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
                        <h3><i class="fas fa-hand-paper"></i> <?php esc_html_e('Deseja Continuar Participando', 'crm-developer'); ?></h3>
                    </div>
                    <div class="card-body">
                        <canvas id="chart-mob-continuar" height="250"></canvas>
                    </div>
                </div>
                <div class="crm-dev-card">
                    <div class="card-header">
                        <h3><i class="fas fa-users-cog"></i> <?php esc_html_e('Participação em Coletivos', 'crm-developer'); ?></h3>
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
                    <h3><i class="fas fa-calendar-alt"></i> <?php esc_html_e('Evolução de Cadastros', 'crm-developer'); ?></h3>
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
                        <h3><i class="fas fa-calendar-week"></i> <?php esc_html_e('Cadastros por Dia da Semana', 'crm-developer'); ?></h3>
                    </div>
                    <div class="card-body">
                        <canvas id="chart-temp-weekday" height="250"></canvas>
                    </div>
                </div>
                <div class="crm-dev-card">
                    <div class="card-header">
                        <h3><i class="fas fa-clock"></i> <?php esc_html_e('Cadastros por Hora do Dia', 'crm-developer'); ?></h3>
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
            <h3><i class="fas fa-table"></i> <?php esc_html_e('Dados Detalhados', 'crm-developer'); ?></h3>
            <button type="button" class="button" id="btn-export-table">
                <i class="fas fa-file-excel"></i> <?php esc_html_e('Exportar Tabela', 'crm-developer'); ?>
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="crm-dev-table" id="report-data-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Métrica', 'crm-developer'); ?></th>
                            <th><?php esc_html_e('Valor', 'crm-developer'); ?></th>
                            <th><?php esc_html_e('Percentual', 'crm-developer'); ?></th>
                            <th><?php esc_html_e('Comparativo', 'crm-developer'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="report-data-tbody">
                        <!-- Preenchido via JS -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Lista de Contatos Filtrados -->
    <div class="crm-dev-card full-width" id="filtered-contacts-card">
        <div class="card-header">
            <h3><i class="fas fa-users"></i> <?php esc_html_e('Contatos Filtrados', 'crm-developer'); ?></h3>
            <span class="badge" id="filtered-count">0</span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="crm-dev-table" id="filtered-contacts-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Nome', 'crm-developer'); ?></th>
                            <th><?php esc_html_e('Email', 'crm-developer'); ?></th>
                            <th><?php esc_html_e('Telefone', 'crm-developer'); ?></th>
                            <th><?php esc_html_e('Estado', 'crm-developer'); ?></th>
                            <th><?php esc_html_e('Score', 'crm-developer'); ?></th>
                            <th><?php esc_html_e('Status', 'crm-developer'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="filtered-contacts-tbody">
                        <!-- Preenchido via JS -->
                    </tbody>
                </table>
            </div>
            <div id="contacts-pagination" class="pagination-container"></div>
        </div>
    </div>
</div>

<!-- Modal de Exportação -->
<div class="crm-dev-modal" id="export-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-download"></i> <?php esc_html_e('Exportar Relatório', 'crm-developer'); ?></h3>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <p class="export-info" style="margin: 0 0 20px; padding: 12px; background: var(--crm-primary-bg); border-radius: 8px; font-size: 13px; color: var(--crm-primary-dark);">
                <i class="fas fa-info-circle"></i>
                <?php esc_html_e('A exportação respeitará os filtros aplicados atualmente.', 'crm-developer'); ?>
            </p>
            <div class="form-group">
                <label><?php esc_html_e('Formato de Exportação', 'crm-developer'); ?></label>
                <div class="export-format-options">
                    <label class="export-option">
                        <input type="radio" name="export_format" value="xlsx" checked>
                        <span class="option-content">
                            <i class="fas fa-file-excel"></i>
                            <span>Excel (XLSX)</span>
                        </span>
                    </label>
                    <label class="export-option">
                        <input type="radio" name="export_format" value="csv">
                        <span class="option-content">
                            <i class="fas fa-file-csv"></i>
                            <span>CSV</span>
                        </span>
                    </label>
                </div>
            </div>
            <div class="form-group">
                <label><?php esc_html_e('Campos para Exportar', 'crm-developer'); ?></label>
                <div class="export-fields">
                    <?php
                    $export_fields = CRM_Dev_Import_Export::get_available_fields();
                    $default_fields = array('nome_completo', 'email', 'telefone', 'whatsapp', 'estado', 'municipio', 'score_engajamento', 'status');
                    foreach ($export_fields as $field => $label) :
                        $checked = in_array($field, $default_fields) ? 'checked' : '';
                    ?>
                        <label class="checkbox-item">
                            <input type="checkbox" name="export_field[]" value="<?php echo esc_attr($field); ?>" <?php echo $checked; ?>>
                            <span><?php echo esc_html($label); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="button" onclick="closeExportModal()"><?php esc_html_e('Cancelar', 'crm-developer'); ?></button>
            <button type="button" class="button button-primary" id="btn-do-export">
                <i class="fas fa-download"></i> <?php esc_html_e('Exportar', 'crm-developer'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Área de Impressão -->
<div id="print-area" style="display: none;">
    <div class="print-header">
        <h1><?php esc_html_e('CRM Developer', 'crm-developer'); ?></h1>
        <h2><?php esc_html_e('Relatório de Contatos', 'crm-developer'); ?></h2>
        <p class="print-date"><?php esc_html_e('Gerado em:', 'crm-developer'); ?> <span id="print-date"></span></p>
        <p class="print-filters" id="print-filters"></p>
    </div>
    <div class="print-summary" id="print-summary"></div>
    <table class="print-table">
        <thead>
            <tr>
                <th><?php esc_html_e('Nome', 'crm-developer'); ?></th>
                <th><?php esc_html_e('Email', 'crm-developer'); ?></th>
                <th><?php esc_html_e('Telefone', 'crm-developer'); ?></th>
                <th><?php esc_html_e('Estado', 'crm-developer'); ?></th>
                <th><?php esc_html_e('Score', 'crm-developer'); ?></th>
            </tr>
        </thead>
        <tbody id="print-contacts-tbody"></tbody>
    </table>
    <div class="print-footer">
        <p><?php esc_html_e('Total de contatos:', 'crm-developer'); ?> <span id="print-total"></span></p>
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
    let filteredContacts = [];
    let currentPage = 1;
    const perPage = 20;

    // ========== DEFINIÇÃO DE FUNÇÕES PRIMEIRO ==========

    // Função para abrir modal de exportação
    function openExportModal() {
        $('#export-modal').addClass('show');
    }

    // Função para fechar modal de exportação
    function closeExportModal() {
        $('#export-modal').removeClass('show');
        // Reset do botão de exportação
        $('#btn-do-export').prop('disabled', false).html('<i class="fas fa-download"></i> Exportar');
    }

    // Expõe a função globalmente para os onclick inline
    window.closeExportModal = closeExportModal;
    window.openExportModal = openExportModal;

    // ========== EVENT HANDLERS ==========

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
    $('#btn-apply-filters').on('click', function() {
        currentPage = 1;
        loadReportData();
    });

    // Limpar filtros
    $('#btn-clear-filters').on('click', function() {
        $('#filter-period').val('30');
        $('#filter-regiao, #filter-estado, #filter-status, #filter-engajamento, #filter-genero, #filter-raca, #filter-eixo').val('');
        $('.date-range').hide();
        currentPage = 1;
        loadReportData();
    });

    // Imprimir - agora imprime apenas a lista filtrada
    $('#btn-print-report').on('click', printFilteredContacts);

    // Exportar relatório - abre modal
    $('#btn-export-report').on('click', openExportModal);
    $('#btn-export-table').on('click', exportSummaryTable);

    // Botão de executar exportação
    $('#btn-do-export').on('click', doExport);

    // Fechar modal - usando a função já definida
    $('.modal-close').on('click', function(e) {
        e.preventDefault();
        closeExportModal();
    });

    $('#export-modal').on('click', function(e) {
        if (e.target === this) closeExportModal();
    });

    // Tecla ESC para fechar modal
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('#export-modal').hasClass('show')) {
            closeExportModal();
        }
    });

    // Esconde área de impressão após imprimir
    window.addEventListener('afterprint', function() {
        $('#print-area').hide();
    });

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
        $('#filtered-contacts-tbody').html('<tr><td colspan="6" class="loading-row"><i class="fas fa-spinner fa-spin"></i> Carregando...</td></tr>');

        const filters = getFilters();

        // Carrega dados do relatório
        $.post(crmDevAdmin.ajaxUrl, {
            action: 'crm_dev_get_report_data',
            nonce: crmDevAdmin.nonce,
            filters: filters
        }, function(response) {
            if (response.success) {
                reportData = response.data;
                updateStats(reportData.summary);
                updateCharts(reportData);
                updateTables(reportData);
            }
            $('#stats-summary').removeClass('loading');
        });

        // Carrega lista de contatos filtrados
        loadFilteredContacts(filters);
    }

    function loadFilteredContacts(filters) {
        $.post(crmDevAdmin.ajaxUrl, {
            action: 'crm_dev_get_contacts',
            nonce: crmDevAdmin.nonce,
            page: currentPage,
            per_page: perPage,
            search: '',
            status: filters.status || '',
            estado: filters.estado || '',
            regiao: filters.regiao || '',
            engajamento: filters.engajamento || '',
            genero: filters.genero || '',
            raca: filters.raca || '',
            eixo: filters.eixo || '',
            period: filters.period || '',
            date_from: filters.date_from || '',
            date_to: filters.date_to || ''
        }, function(response) {
            if (response.success) {
                filteredContacts = response.data.items || [];
                const total = response.data.total || 0;
                const totalPages = response.data.pages || 1;

                $('#filtered-count').text(total);
                renderContactsTable(filteredContacts);
                renderPagination(total, totalPages);
            }
        });
    }

    function renderContactsTable(contacts) {
        let html = '';
        if (contacts.length === 0) {
            html = '<tr><td colspan="6" class="empty-row">Nenhum contato encontrado com os filtros aplicados.</td></tr>';
        } else {
            contacts.forEach(c => {
                const scoreColor = c.score_engajamento >= 70 ? '#059669' : (c.score_engajamento >= 40 ? '#f59e0b' : '#ef4444');
                const statusClass = c.status === 'ativo' ? 'badge-success' : (c.status === 'inativo' ? 'badge-danger' : 'badge-warning');
                html += `
                    <tr>
                        <td><strong>${c.nome_completo || '-'}</strong></td>
                        <td>${c.email || '-'}</td>
                        <td>${c.telefone || c.whatsapp || '-'}</td>
                        <td>${c.estado ? (estadosMap[c.estado] || c.estado) : '-'}</td>
                        <td><span class="score-badge" style="background: ${scoreColor}">${c.score_engajamento || 0}</span></td>
                        <td><span class="badge ${statusClass}">${c.status || 'ativo'}</span></td>
                    </tr>
                `;
            });
        }
        $('#filtered-contacts-tbody').html(html);
    }

    function renderPagination(total, totalPages) {
        if (totalPages <= 1) {
            $('#contacts-pagination').html('');
            return;
        }

        let html = '<div class="pagination">';
        html += `<button class="btn-page" ${currentPage === 1 ? 'disabled' : ''} onclick="goToPage(${currentPage - 1})"><i class="fas fa-chevron-left"></i></button>`;

        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
                html += `<button class="btn-page ${i === currentPage ? 'active' : ''}" onclick="goToPage(${i})">${i}</button>`;
            } else if (i === currentPage - 3 || i === currentPage + 3) {
                html += '<span class="page-dots">...</span>';
            }
        }

        html += `<button class="btn-page" ${currentPage === totalPages ? 'disabled' : ''} onclick="goToPage(${currentPage + 1})"><i class="fas fa-chevron-right"></i></button>`;
        html += '</div>';

        $('#contacts-pagination').html(html);
    }

    window.goToPage = function(page) {
        currentPage = page;
        loadFilteredContacts(getFilters());
    };

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

    function doExport() {
        const format = $('input[name="export_format"]:checked').val();
        const fields = [];
        $('input[name="export_field[]"]:checked').each(function() {
            fields.push($(this).val());
        });

        if (fields.length === 0) {
            alert('Selecione pelo menos um campo para exportar.');
            return;
        }

        const filters = getFilters();
        const $btn = $('#btn-do-export');
        const originalHtml = $btn.html();

        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Exportando...');

        $.ajax({
            url: crmDevAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'crm_dev_export_contacts',
                nonce: crmDevAdmin.nonce,
                format: format,
                fields: fields,
                filters: filters
            },
            success: function(response) {
                if (response.success) {
                    // A estrutura pode ser response.data.data ou response.data diretamente
                    const exportData = response.data.data || response.data;

                    if (Array.isArray(exportData) && exportData.length > 0) {
                        if (format === 'xlsx') {
                            exportToXLSX(exportData);
                        } else {
                            exportToCSV(exportData);
                        }
                        closeExportModal();
                    } else {
                        alert('Nenhum dado encontrado para exportar.');
                    }
                } else {
                    alert(response.data?.message || 'Erro ao exportar dados.');
                }
            },
            error: function(xhr, status, error) {
                console.error('Erro na exportação:', error);
                alert('Erro ao exportar dados. Verifique o console para detalhes.');
            },
            complete: function() {
                // Sempre reseta o botão, independente do resultado
                $btn.prop('disabled', false).html('<i class="fas fa-download"></i> Exportar');
            }
        });
    }

    function exportToXLSX(data) {
        if (typeof XLSX === 'undefined') {
            alert('Biblioteca XLSX não carregada. Tente exportar em CSV.');
            return;
        }

        const ws = XLSX.utils.aoa_to_sheet(data);

        // Ajusta largura das colunas
        const colWidths = data[0].map((header, i) => {
            let maxLen = header.length;
            data.forEach(row => {
                if (row[i] && String(row[i]).length > maxLen) {
                    maxLen = String(row[i]).length;
                }
            });
            return { wch: Math.min(maxLen + 2, 50) };
        });
        ws['!cols'] = colWidths;

        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Contatos');
        XLSX.writeFile(wb, 'contatos_crm_' + new Date().toISOString().slice(0, 10) + '.xlsx');
    }

    function exportToCSV(data) {
        let csv = '\uFEFF'; // BOM para UTF-8

        data.forEach(row => {
            const escapedRow = row.map(cell => {
                if (cell === null || cell === undefined) return '';
                const str = String(cell);
                if (str.includes(';') || str.includes('"') || str.includes('\n')) {
                    return '"' + str.replace(/"/g, '""') + '"';
                }
                return str;
            });
            csv += escapedRow.join(';') + '\n';
        });

        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'contatos_crm_' + new Date().toISOString().slice(0, 10) + '.csv';
        link.click();
        URL.revokeObjectURL(link.href);
    }

    function exportSummaryTable() {
        if (!reportData) return;

        const data = [
            ['Métrica', 'Valor', 'Percentual'],
            ['Total de Contatos', reportData.summary.total, '100%'],
            ['Estados Representados', reportData.summary.estados, '-'],
            ['Score Médio', Math.round(reportData.summary.score_medio) + '%', '-'],
            ['Com Consentimento LGPD', Math.round(reportData.summary.lgpd_percent) + '%', '-'],
            ['Alto Engajamento (70+)', reportData.engagement?.alto || 0, reportData.summary.total > 0 ? ((reportData.engagement?.alto || 0) / reportData.summary.total * 100).toFixed(1) + '%' : '0%'],
            ['Médio Engajamento (40-69)', reportData.engagement?.medio || 0, reportData.summary.total > 0 ? ((reportData.engagement?.medio || 0) / reportData.summary.total * 100).toFixed(1) + '%' : '0%'],
            ['Baixo Engajamento (<40)', reportData.engagement?.baixo || 0, reportData.summary.total > 0 ? ((reportData.engagement?.baixo || 0) / reportData.summary.total * 100).toFixed(1) + '%' : '0%']
        ];

        if (typeof XLSX !== 'undefined') {
            const ws = XLSX.utils.aoa_to_sheet(data);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Resumo');
            XLSX.writeFile(wb, 'resumo_crm_' + new Date().toISOString().slice(0, 10) + '.xlsx');
        } else {
            exportToCSV(data);
        }
    }

    function printFilteredContacts() {
        // Prepara área de impressão
        const now = new Date();
        $('#print-date').text(now.toLocaleDateString('pt-BR') + ' ' + now.toLocaleTimeString('pt-BR'));

        // Mostra filtros aplicados
        const filters = getFilters();
        let filterText = [];
        if (filters.period && filters.period !== 'all') {
            if (filters.period === 'custom') {
                filterText.push('Período: ' + (filters.date_from || '?') + ' a ' + (filters.date_to || '?'));
            } else {
                filterText.push('Período: últimos ' + filters.period + ' dias');
            }
        }
        if (filters.regiao) filterText.push('Região: ' + filters.regiao);
        if (filters.estado) filterText.push('Estado: ' + (estadosMap[filters.estado] || filters.estado));
        if (filters.status) filterText.push('Status: ' + filters.status);
        if (filters.engajamento) filterText.push('Engajamento: ' + filters.engajamento);
        if (filters.genero) filterText.push('Gênero: ' + (generos[filters.genero] || filters.genero));
        if (filters.raca) filterText.push('Raça/Etnia: ' + (racas[filters.raca] || filters.raca));

        $('#print-filters').text(filterText.length > 0 ? 'Filtros: ' + filterText.join(' | ') : 'Sem filtros aplicados');

        // Resumo
        if (reportData) {
            $('#print-summary').html(`
                <p><strong>Total:</strong> ${reportData.summary.total} contatos |
                <strong>Estados:</strong> ${reportData.summary.estados} |
                <strong>Score Médio:</strong> ${Math.round(reportData.summary.score_medio)}%</p>
            `);
        }

        // Busca todos os contatos filtrados para impressão
        $.post(crmDevAdmin.ajaxUrl, {
            action: 'crm_dev_get_contacts',
            nonce: crmDevAdmin.nonce,
            page: 1,
            per_page: 9999,
            status: filters.status || '',
            estado: filters.estado || '',
            regiao: filters.regiao || '',
            engajamento: filters.engajamento || '',
            genero: filters.genero || '',
            raca: filters.raca || '',
            period: filters.period || '',
            date_from: filters.date_from || '',
            date_to: filters.date_to || ''
        }, function(response) {
            if (response.success) {
                const contacts = response.data.items || [];
                let html = '';

                if (contacts.length === 0) {
                    html = '<tr><td colspan="5" style="text-align: center; padding: 20px;">Nenhum contato encontrado com os filtros aplicados.</td></tr>';
                } else {
                    contacts.forEach(c => {
                        html += `
                            <tr>
                                <td>${c.nome_completo || '-'}</td>
                                <td>${c.email || '-'}</td>
                                <td>${c.telefone || c.whatsapp || '-'}</td>
                                <td>${c.estado || '-'}</td>
                                <td>${c.score_engajamento || 0}</td>
                            </tr>
                        `;
                    });
                }

                $('#print-contacts-tbody').html(html);
                $('#print-total').text(contacts.length);

                // Mostra a área de impressão temporariamente
                $('#print-area').show();

                // Pequeno delay para garantir que o DOM foi atualizado
                setTimeout(function() {
                    window.print();

                    // Esconde a área após fechar o diálogo de impressão
                    setTimeout(function() {
                        $('#print-area').hide();
                    }, 500);
                }, 100);
            } else {
                alert('Erro ao carregar contatos para impressão.');
            }
        }).fail(function() {
            alert('Erro de conexão ao carregar contatos para impressão.');
        });
    }
});
</script>

<?php
// Modal de ajuda
crm_dev_render_help_modal_reports();
crm_dev_render_help_modal_script();
?>
