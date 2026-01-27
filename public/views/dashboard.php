<?php
/**
 * View do dashboard público
 *
 * @package CRM_Developer
 */

if (!defined('ABSPATH')) {
    exit;
}

$data = CRM_Dev_Dashboard::get_public_dashboard_data();
$generos = CRM_Dev_Helpers::get_generos();
$racas = CRM_Dev_Helpers::get_racas();
?>

<div class="crm-public-dashboard">
    <div class="dashboard-header">
        <h2><?php echo esc_html($atts['titulo']); ?></h2>
        <p>Dados agregados e anonimizados da nossa base de participantes</p>
    </div>

    <div class="dashboard-stats">
        <div class="stat-card main">
            <span class="stat-number"><?php echo number_format_i18n($data['total']); ?></span>
            <span class="stat-label">Participantes Cadastrados</span>
        </div>
    </div>

    <div class="dashboard-grid">
        <!-- Por Região -->
        <div class="dashboard-card">
            <h3>Distribuição por Região</h3>
            <canvas id="pub-chart-region" height="200"></canvas>
        </div>

        <!-- Por Estado -->
        <div class="dashboard-card">
            <h3>Top 10 Estados</h3>
            <div class="state-bars">
                <?php
                $max = !empty($data['by_state']) ? $data['by_state'][0]['value'] : 1;
                foreach (array_slice($data['by_state'], 0, 10) as $item) :
                    $percent = round(($item['value'] / $max) * 100);
                ?>
                    <div class="state-bar-item">
                        <span class="state-name"><?php echo esc_html($item['label']); ?></span>
                        <div class="state-bar">
                            <div class="bar-fill" style="width: <?php echo $percent; ?>%"></div>
                        </div>
                        <span class="state-value"><?php echo number_format_i18n($item['value']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Por Gênero -->
        <div class="dashboard-card">
            <h3>Distribuição por Gênero</h3>
            <canvas id="pub-chart-gender" height="200"></canvas>
        </div>

        <!-- Por Raça -->
        <div class="dashboard-card">
            <h3>Distribuição por Raça/Etnia</h3>
            <canvas id="pub-chart-race" height="200"></canvas>
        </div>
    </div>

    <div class="dashboard-footer">
        <p><small>Dados atualizados automaticamente. Última atualização: <?php echo date_i18n('d/m/Y H:i'); ?></small></p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function() {
    document.addEventListener('DOMContentLoaded', function() {
        const colors = [
            'rgba(5, 150, 105, 0.8)',
            'rgba(13, 148, 136, 0.8)',
            'rgba(16, 185, 129, 0.8)',
            'rgba(20, 184, 166, 0.8)',
            'rgba(6, 95, 70, 0.8)'
        ];

        const generos = <?php echo json_encode($generos); ?>;
        const racas = <?php echo json_encode($racas); ?>;

        // Gráfico Região
        const regionData = <?php echo json_encode($data['by_region']); ?>;
        if (regionData.length > 0) {
            new Chart(document.getElementById('pub-chart-region'), {
                type: 'doughnut',
                data: {
                    labels: regionData.map(d => d.label),
                    datasets: [{
                        data: regionData.map(d => d.value),
                        backgroundColor: colors
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        }

        // Gráfico Gênero
        const genderData = <?php echo json_encode($data['by_gender']); ?>;
        if (genderData.length > 0) {
            new Chart(document.getElementById('pub-chart-gender'), {
                type: 'pie',
                data: {
                    labels: genderData.map(d => generos[d.label] || d.label),
                    datasets: [{
                        data: genderData.map(d => d.value),
                        backgroundColor: colors
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        }

        // Gráfico Raça
        const raceData = <?php echo json_encode($data['by_race']); ?>;
        if (raceData.length > 0) {
            new Chart(document.getElementById('pub-chart-race'), {
                type: 'pie',
                data: {
                    labels: raceData.map(d => racas[d.label] || d.label),
                    datasets: [{
                        data: raceData.map(d => d.value),
                        backgroundColor: colors
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        }
    });
})();
</script>
