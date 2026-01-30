<?php
/**
 * View de Importação e Exportação
 *
 * @package CRM_Developer
 */

if (!defined('ABSPATH')) {
    exit;
}

// Inclui modais de ajuda
require_once CRM_DEV_PLUGIN_DIR . 'admin/views/partials/help-modals.php';

$tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'import';
$available_fields = CRM_Dev_Import_Export::get_available_fields();
$import_history = CRM_Dev_Import_Export::get_import_history(10);
?>

<div class="wrap crm-dev-wrap">
    <div class="crm-dev-header">
        <div class="header-title-row">
            <div>
                <h1>
                    <i class="fas fa-exchange-alt"></i>
                    <?php esc_html_e('Importar / Exportar', 'crm-developer'); ?>
                </h1>
                <p class="crm-dev-subtitle"><?php esc_html_e('Gerencie a importação e exportação de contatos', 'crm-developer'); ?></p>
            </div>
            <?php crm_dev_render_help_button('import-export'); ?>
        </div>
    </div>

    <!-- Tabs -->
    <nav class="crm-dev-tabs">
        <a href="<?php echo esc_url(admin_url('admin.php?page=crm-developer&section=import-export&tab=import')); ?>" class="tab <?php echo esc_attr($tab === 'import' ? 'active' : ''); ?>">
            <i class="fas fa-file-import"></i> <?php esc_html_e('Importar', 'crm-developer'); ?>
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=crm-developer&section=import-export&tab=export')); ?>" class="tab <?php echo esc_attr($tab === 'export' ? 'active' : ''); ?>">
            <i class="fas fa-file-export"></i> <?php esc_html_e('Exportar', 'crm-developer'); ?>
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=crm-developer&section=import-export&tab=history')); ?>" class="tab <?php echo esc_attr($tab === 'history' ? 'active' : ''); ?>">
            <i class="fas fa-history"></i> <?php esc_html_e('Histórico', 'crm-developer'); ?>
        </a>
    </nav>

    <?php if ($tab === 'import') : ?>
        <!-- Importação -->
        <div class="crm-dev-card">
            <div class="card-header">
                <h3><i class="fas fa-file-import"></i> <?php esc_html_e('Importar Contatos', 'crm-developer'); ?></h3>
            </div>
            <div class="card-body">
                <div class="import-steps">
                    <!-- Etapa 1: Upload do Arquivo -->
                    <div class="import-step active" id="step-upload">
                        <div class="step-header">
                            <span class="step-number">1</span>
                            <h4><?php esc_html_e('Selecione o arquivo', 'crm-developer'); ?></h4>
                        </div>
                        <div class="step-content">
                            <p class="step-description">
                                <?php esc_html_e('Selecione um arquivo CSV ou Excel (.xlsx) com os dados dos contatos. O arquivo deve ter uma linha de cabeçalho com os nomes das colunas.', 'crm-developer'); ?>
                            </p>

                            <div class="file-upload-area" id="drop-zone">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p><?php esc_html_e('Arraste o arquivo aqui ou clique para selecionar', 'crm-developer'); ?></p>
                                <span><?php esc_html_e('Formatos aceitos: CSV, XLSX', 'crm-developer'); ?></span>
                                <input type="file" id="import-file" accept=".csv,.xlsx,.xls" style="display: none;">
                            </div>

                            <div class="file-info" id="file-info" style="display: none;">
                                <i class="fas fa-file-alt"></i>
                                <span id="file-name"></span>
                                <button type="button" class="button-link" id="btn-remove-file">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>

                            <div class="import-template">
                                <p><i class="fas fa-info-circle"></i> <?php esc_html_e('Baixe o modelo de importação:', 'crm-developer'); ?></p>
                                <button type="button" class="button" id="btn-download-template">
                                    <i class="fas fa-download"></i> <?php esc_html_e('Baixar Modelo CSV', 'crm-developer'); ?>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Etapa 2: Mapeamento de Colunas -->
                    <div class="import-step" id="step-mapping" style="display: none;">
                        <div class="step-header">
                            <span class="step-number">2</span>
                            <h4><?php esc_html_e('Mapeamento de Colunas', 'crm-developer'); ?></h4>
                        </div>
                        <div class="step-content">
                            <p class="step-description">
                                <?php esc_html_e('Relacione as colunas do seu arquivo com os campos do CRM. Colunas com fundo verde foram mapeadas automaticamente.', 'crm-developer'); ?>
                            </p>

                            <div class="preview-stats">
                                <span><i class="fas fa-file-alt"></i> <span id="total-rows">0</span> <?php esc_html_e('linhas encontradas', 'crm-developer'); ?></span>
                                <span><i class="fas fa-columns"></i> <span id="total-cols">0</span> <?php esc_html_e('colunas', 'crm-developer'); ?></span>
                            </div>

                            <table class="crm-dev-table" id="mapping-table">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Coluna do Arquivo', 'crm-developer'); ?></th>
                                        <th><?php esc_html_e('Campo do CRM', 'crm-developer'); ?></th>
                                        <th><?php esc_html_e('Exemplo', 'crm-developer'); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="mapping-tbody">
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Etapa 3: Opções e Confirmação -->
                    <div class="import-step" id="step-options" style="display: none;">
                        <div class="step-header">
                            <span class="step-number">3</span>
                            <h4><?php esc_html_e('Opções de Importação', 'crm-developer'); ?></h4>
                        </div>
                        <div class="step-content">
                            <div class="import-options">
                                <label class="checkbox-option">
                                    <input type="checkbox" id="opt-skip-duplicates" checked>
                                    <span><?php esc_html_e('Ignorar contatos duplicados (email, telefone, WhatsApp ou nome+estado)', 'crm-developer'); ?></span>
                                </label>
                                <label class="checkbox-option">
                                    <input type="checkbox" id="opt-update-existing">
                                    <span><?php esc_html_e('Atualizar contatos existentes ao invés de ignorar', 'crm-developer'); ?></span>
                                </label>
                            </div>

                            <div class="import-info-box">
                                <i class="fas fa-info-circle"></i>
                                <div>
                                    <strong><?php esc_html_e('Detecção de Duplicatas', 'crm-developer'); ?></strong>
                                    <p><?php esc_html_e('O sistema verifica duplicatas por: Email, WhatsApp, Telefone e combinação Nome + Estado. Se qualquer um desses dados já existir, o contato será considerado duplicado.', 'crm-developer'); ?></p>
                                </div>
                            </div>

                            <div class="import-summary">
                                <h5><?php esc_html_e('Resumo da Importação', 'crm-developer'); ?></h5>
                                <ul>
                                    <li><strong id="summary-total">0</strong> <?php esc_html_e('registros serão processados', 'crm-developer'); ?></li>
                                    <li><strong id="summary-mapped">0</strong> <?php esc_html_e('campos mapeados', 'crm-developer'); ?></li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Etapa 4: Resultado -->
                    <div class="import-step" id="step-result" style="display: none;">
                        <div class="step-header">
                            <span class="step-number">4</span>
                            <h4><?php esc_html_e('Resultado da Importação', 'crm-developer'); ?></h4>
                        </div>
                        <div class="step-content">
                            <div class="import-result" id="import-result">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Botões de Navegação -->
                <div class="import-navigation">
                    <button type="button" class="button" id="btn-import-prev" style="display: none;">
                        <i class="fas fa-arrow-left"></i> <?php esc_html_e('Voltar', 'crm-developer'); ?>
                    </button>
                    <button type="button" class="button button-primary" id="btn-import-next" disabled>
                        <?php esc_html_e('Próximo', 'crm-developer'); ?> <i class="fas fa-arrow-right"></i>
                    </button>
                    <button type="button" class="button button-primary" id="btn-import-start" style="display: none;">
                        <i class="fas fa-play"></i> <?php esc_html_e('Iniciar Importação', 'crm-developer'); ?>
                    </button>
                    <button type="button" class="button button-primary" id="btn-import-new" style="display: none;">
                        <i class="fas fa-plus"></i> <?php esc_html_e('Nova Importação', 'crm-developer'); ?>
                    </button>
                </div>
            </div>
        </div>

    <?php elseif ($tab === 'export') : ?>
        <!-- Exportação -->
        <div class="crm-dev-card">
            <div class="card-header">
                <h3><i class="fas fa-file-export"></i> <?php esc_html_e('Exportar Contatos', 'crm-developer'); ?></h3>
            </div>
            <div class="card-body">
                <div class="export-options">
                    <h4><?php esc_html_e('Formato de Exportação', 'crm-developer'); ?></h4>
                    <div class="format-options">
                        <label class="format-option selected">
                            <input type="radio" name="export_format" value="xlsx" checked>
                            <i class="fas fa-file-excel"></i>
                            <span>Excel (XLSX)</span>
                        </label>
                        <label class="format-option">
                            <input type="radio" name="export_format" value="csv">
                            <i class="fas fa-file-csv"></i>
                            <span>CSV</span>
                        </label>
                    </div>

                    <h4><?php esc_html_e('Campos para Exportar', 'crm-developer'); ?></h4>
                    <div class="export-field-controls">
                        <button type="button" class="button" id="btn-select-all-fields"><?php esc_html_e('Selecionar Todos', 'crm-developer'); ?></button>
                        <button type="button" class="button" id="btn-deselect-all-fields"><?php esc_html_e('Desmarcar Todos', 'crm-developer'); ?></button>
                    </div>
                    <div class="export-fields">
                        <?php foreach ($available_fields as $key => $label) : ?>
                            <label class="field-checkbox">
                                <input type="checkbox" name="export_fields[]" value="<?php echo esc_attr($key); ?>" checked>
                                <?php echo esc_html($label); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <h4><?php esc_html_e('Filtros (opcional)', 'crm-developer'); ?></h4>
                    <div class="export-filters">
                        <div class="filter-group">
                            <label><?php esc_html_e('Estado', 'crm-developer'); ?></label>
                            <select id="export-estado">
                                <option value=""><?php esc_html_e('Todos', 'crm-developer'); ?></option>
                                <?php foreach (CRM_Dev_Helpers::get_estados() as $uf => $nome) : ?>
                                    <option value="<?php echo esc_attr($uf); ?>"><?php echo esc_html($nome); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label><?php esc_html_e('Região', 'crm-developer'); ?></label>
                            <select id="export-regiao">
                                <option value=""><?php esc_html_e('Todas', 'crm-developer'); ?></option>
                                <?php foreach (CRM_Dev_Helpers::get_regioes() as $regiao) : ?>
                                    <option value="<?php echo esc_attr($regiao); ?>"><?php echo esc_html($regiao); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label><?php esc_html_e('Status', 'crm-developer'); ?></label>
                            <select id="export-status">
                                <option value=""><?php esc_html_e('Todos', 'crm-developer'); ?></option>
                                <option value="ativo"><?php esc_html_e('Ativo', 'crm-developer'); ?></option>
                                <option value="inativo"><?php esc_html_e('Inativo', 'crm-developer'); ?></option>
                                <option value="pendente"><?php esc_html_e('Pendente', 'crm-developer'); ?></option>
                            </select>
                        </div>
                    </div>

                    <div class="export-actions">
                        <button type="button" class="button button-primary button-hero" id="btn-export">
                            <i class="fas fa-download"></i> <?php esc_html_e('Exportar Contatos', 'crm-developer'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>

    <?php else : ?>
        <!-- Histórico -->
        <div class="crm-dev-card">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> <?php esc_html_e('Histórico de Importações', 'crm-developer'); ?></h3>
            </div>
            <div class="card-body">
                <?php if (!empty($import_history)) : ?>
                    <table class="crm-dev-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Data', 'crm-developer'); ?></th>
                                <th><?php esc_html_e('Arquivo', 'crm-developer'); ?></th>
                                <th><?php esc_html_e('Total', 'crm-developer'); ?></th>
                                <th><?php esc_html_e('Importados', 'crm-developer'); ?></th>
                                <th><?php esc_html_e('Erros', 'crm-developer'); ?></th>
                                <th><?php esc_html_e('Usuário', 'crm-developer'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($import_history as $log) : ?>
                                <tr>
                                    <td><?php echo esc_html(CRM_Dev_Helpers::format_datetime($log['created_at'])); ?></td>
                                    <td><?php echo esc_html($log['arquivo']); ?></td>
                                    <td><?php echo esc_html(number_format_i18n($log['total_linhas'])); ?></td>
                                    <td class="text-success"><?php echo esc_html(number_format_i18n($log['importados'])); ?></td>
                                    <td class="text-danger"><?php echo esc_html(number_format_i18n($log['erros'])); ?></td>
                                    <td><?php echo esc_html($log['user_name']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p class="crm-dev-empty"><?php esc_html_e('Nenhuma importação realizada ainda.', 'crm-developer'); ?></p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    // Variáveis de importação
    let importData = [];
    let importMapping = {};
    let currentImportStep = 1;

    const availableFields = <?php echo json_encode($available_fields); ?>;

    // ========== IMPORTAÇÃO ==========

    // Drag and drop
    const dropZone = document.getElementById('drop-zone');
    const fileInput = document.getElementById('import-file');

    if (dropZone) {
        dropZone.addEventListener('click', () => fileInput.click());

        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('drag-over');
        });

        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('drag-over');
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('drag-over');
            const file = e.dataTransfer.files[0];
            if (file) handleFile(file);
        });

        fileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) handleFile(file);
        });
    }

    function handleFile(file) {
        const validTypes = ['text/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
        const ext = file.name.split('.').pop().toLowerCase();

        if (!['csv', 'xlsx', 'xls'].includes(ext)) {
            alert('Formato de arquivo não suportado. Use CSV ou XLSX.');
            return;
        }

        $('#file-info').show();
        $('#file-name').text(file.name);
        $('#drop-zone').hide();
        $('#btn-import-next').prop('disabled', false);

        // Lê o arquivo
        const reader = new FileReader();

        if (ext === 'csv') {
            reader.onload = function(e) {
                parseCSV(e.target.result);
            };
            reader.readAsText(file);
        } else {
            reader.onload = function(e) {
                parseXLSX(e.target.result);
            };
            reader.readAsArrayBuffer(file);
        }
    }

    function parseCSV(content) {
        // Remove BOM
        content = content.replace(/^\uFEFF/, '');

        // Detecta delimitador
        const firstLine = content.split('\n')[0];
        let delimiter = ';';
        if ((firstLine.match(/,/g) || []).length > (firstLine.match(/;/g) || []).length) {
            delimiter = ',';
        }

        const lines = content.split('\n').filter(line => line.trim());
        importData = lines.map(line => {
            // Parse CSV básico
            const regex = new RegExp(`(?:^|${delimiter})("(?:[^"]|"")*"|[^${delimiter}]*)`, 'g');
            const row = [];
            let match;
            while ((match = regex.exec(line)) !== null) {
                let value = match[1] || '';
                value = value.replace(/^"|"$/g, '').replace(/""/g, '"').trim();
                row.push(value);
            }
            return row;
        });

        updateMappingTable();
    }

    function parseXLSX(data) {
        const workbook = XLSX.read(data, { type: 'array' });
        const sheetName = workbook.SheetNames[0];
        const sheet = workbook.Sheets[sheetName];
        importData = XLSX.utils.sheet_to_json(sheet, { header: 1, defval: '' });
        updateMappingTable();
    }

    function updateMappingTable() {
        if (importData.length < 2) {
            alert('Arquivo deve conter pelo menos cabeçalho e uma linha de dados.');
            return;
        }

        const header = importData[0];
        const sampleRow = importData[1] || [];

        $('#total-rows').text(importData.length - 1);
        $('#total-cols').text(header.length);

        // Auto-mapeamento
        importMapping = autoMapFields(header);

        let html = '';
        header.forEach((col, index) => {
            const mappedField = importMapping[index] || '';
            const isAutoMapped = mappedField !== '';

            html += `<tr class="${isAutoMapped ? 'auto-mapped' : ''}">
                <td><strong>${escapeHtml(col)}</strong></td>
                <td>
                    <select class="mapping-select" data-index="${index}">
                        <option value="">-- Ignorar --</option>
                        ${Object.entries(availableFields).map(([key, label]) =>
                            `<option value="${key}" ${key === mappedField ? 'selected' : ''}>${label}</option>`
                        ).join('')}
                    </select>
                </td>
                <td><span class="sample-value">${escapeHtml(sampleRow[index] || '-')}</span></td>
            </tr>`;
        });

        $('#mapping-tbody').html(html);

        // Listener para mudanças no mapeamento
        $('.mapping-select').on('change', function() {
            const index = $(this).data('index');
            importMapping[index] = $(this).val();
            updateSummary();
        });

        updateSummary();
    }

    function autoMapFields(header) {
        const mapping = {};
        const synonyms = {
            'nome_completo': ['nome', 'name', 'nome completo', 'full name', 'nome_completo'],
            'nome_social': ['nome social', 'social name', 'nome_social'],
            'email': ['email', 'e-mail', 'mail'],
            'telefone': ['telefone', 'phone', 'tel', 'fone'],
            'whatsapp': ['whatsapp', 'wpp', 'zap', 'whats'],
            'municipio': ['municipio', 'cidade', 'city', 'município'],
            'estado': ['estado', 'uf', 'state'],
            'data_nascimento': ['nascimento', 'birth', 'data nascimento', 'data_nascimento'],
            'genero': ['genero', 'gender', 'sexo', 'gênero'],
            'raca_etnia': ['raca', 'etnia', 'race', 'raça', 'cor', 'raca_etnia']
        };

        header.forEach((col, index) => {
            const colLower = col.toLowerCase().trim().replace(/[^a-z0-9]/g, '');

            for (const [field, syns] of Object.entries(synonyms)) {
                for (const syn of syns) {
                    if (colLower === syn.replace(/[^a-z0-9]/g, '')) {
                        mapping[index] = field;
                        break;
                    }
                }
                if (mapping[index]) break;
            }
        });

        return mapping;
    }

    function updateSummary() {
        const mappedCount = Object.values(importMapping).filter(v => v).length;
        $('#summary-total').text(importData.length - 1);
        $('#summary-mapped').text(mappedCount);
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // Navegação de etapas
    $('#btn-import-next').on('click', function() {
        if (currentImportStep === 1) {
            $('#step-upload').hide();
            $('#step-mapping').show();
            $('#btn-import-prev').show();
            currentImportStep = 2;
        } else if (currentImportStep === 2) {
            $('#step-mapping').hide();
            $('#step-options').show();
            $(this).hide();
            $('#btn-import-start').show();
            currentImportStep = 3;
        }
    });

    $('#btn-import-prev').on('click', function() {
        if (currentImportStep === 2) {
            $('#step-mapping').hide();
            $('#step-upload').show();
            $(this).hide();
            currentImportStep = 1;
        } else if (currentImportStep === 3) {
            $('#step-options').hide();
            $('#step-mapping').show();
            $('#btn-import-start').hide();
            $('#btn-import-next').show();
            currentImportStep = 2;
        }
    });

    $('#btn-import-start').on('click', function() {
        $(this).html('<i class="fas fa-spinner fa-spin"></i> Importando...').prop('disabled', true);
        $('#btn-import-prev').prop('disabled', true);

        // Envia dados como JSON string para evitar limite de max_input_vars do PHP
        $.post(crmDevAdmin.ajaxUrl, {
            action: 'crm_dev_import_contacts',
            nonce: crmDevAdmin.nonce,
            data_json: JSON.stringify(importData),
            mapping_json: JSON.stringify(importMapping),
            options: {
                skip_duplicates: $('#opt-skip-duplicates').is(':checked'),
                update_existing: $('#opt-update-existing').is(':checked')
            }
        }, function(response) {
            $('#step-options').hide();
            $('#step-result').show();
            $('#btn-import-start').hide();
            $('#btn-import-prev').hide();
            $('#btn-import-new').show();

            if (response.success) {
                const r = response.data;
                const sr = r.skip_reasons || {};

                let skipDetails = '';
                if (r.skipped > 0) {
                    skipDetails = `
                        <div class="skip-reasons">
                            <h5><i class="fas fa-filter"></i> Detalhes dos ${r.skipped} registros ignorados:</h5>
                            <div class="reasons-grid">
                                ${sr.email_duplicado > 0 ? `<div class="reason-item"><i class="fas fa-envelope"></i> <strong>${sr.email_duplicado}</strong> Email duplicado</div>` : ''}
                                ${sr.whatsapp_duplicado > 0 ? `<div class="reason-item"><i class="fab fa-whatsapp"></i> <strong>${sr.whatsapp_duplicado}</strong> WhatsApp duplicado</div>` : ''}
                                ${sr.telefone_duplicado > 0 ? `<div class="reason-item"><i class="fas fa-phone"></i> <strong>${sr.telefone_duplicado}</strong> Telefone duplicado</div>` : ''}
                                ${sr.nome_estado_duplicado > 0 ? `<div class="reason-item"><i class="fas fa-user"></i> <strong>${sr.nome_estado_duplicado}</strong> Nome+Estado duplicado</div>` : ''}
                                ${sr.nome_vazio > 0 ? `<div class="reason-item"><i class="fas fa-exclamation-triangle"></i> <strong>${sr.nome_vazio}</strong> Nome vazio</div>` : ''}
                                ${sr.linha_vazia > 0 ? `<div class="reason-item"><i class="fas fa-minus"></i> <strong>${sr.linha_vazia}</strong> Linhas vazias</div>` : ''}
                                ${sr.erro_insercao > 0 ? `<div class="reason-item error"><i class="fas fa-times-circle"></i> <strong>${sr.erro_insercao}</strong> Erros de inserção</div>` : ''}
                            </div>
                        </div>
                    `;
                }

                let errorsList = '';
                if (r.errors && r.errors.length > 0) {
                    const maxErrors = 20;
                    const errorsToShow = r.errors.slice(0, maxErrors);
                    const moreErrors = r.errors.length > maxErrors ? `<li class="more-errors">... e mais ${r.errors.length - maxErrors} registros</li>` : '';

                    errorsList = `
                        <div class="result-errors">
                            <h5><i class="fas fa-list"></i> Detalhes por linha (primeiros ${maxErrors}):</h5>
                            <div class="errors-scroll">
                                <ul>${errorsToShow.map(e => `<li>${escapeHtml(e)}</li>`).join('')}${moreErrors}</ul>
                            </div>
                        </div>
                    `;
                }

                $('#import-result').html(`
                    <div class="result-success">
                        <i class="fas fa-check-circle"></i>
                        <h4>Importação Concluída!</h4>
                        <div class="result-stats">
                            <div class="stat success"><i class="fas fa-plus-circle"></i> <strong>${r.imported}</strong> novos importados</div>
                            <div class="stat updated"><i class="fas fa-sync"></i> <strong>${r.updated}</strong> atualizados</div>
                            <div class="stat skipped"><i class="fas fa-forward"></i> <strong>${r.skipped}</strong> ignorados</div>
                        </div>
                        <div class="result-summary">
                            <p><strong>Total processado:</strong> ${r.total} linhas</p>
                            <p><strong>Taxa de sucesso:</strong> ${r.total > 0 ? Math.round(((r.imported + r.updated) / r.total) * 100) : 0}%</p>
                        </div>
                        ${skipDetails}
                        ${errorsList}
                    </div>
                `);
            } else {
                $('#import-result').html(`
                    <div class="result-error">
                        <i class="fas fa-times-circle"></i>
                        <h4>Erro na Importação</h4>
                        <p>${response.data.message}</p>
                    </div>
                `);
            }
        });
    });

    $('#btn-import-new').on('click', function() {
        location.reload();
    });

    $('#btn-remove-file').on('click', function() {
        $('#file-info').hide();
        $('#drop-zone').show();
        $('#import-file').val('');
        $('#btn-import-next').prop('disabled', true);
        importData = [];
        importMapping = {};
    });

    // Download template
    $('#btn-download-template').on('click', function() {
        const headers = Object.values(availableFields);
        const csv = headers.join(';') + '\n';
        const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'modelo_importacao_crm.csv';
        link.click();
    });

    // ========== EXPORTAÇÃO ==========

    $('.format-option').on('click', function() {
        $('.format-option').removeClass('selected');
        $(this).addClass('selected');
    });

    $('#btn-select-all-fields').on('click', function() {
        $('input[name="export_fields[]"]').prop('checked', true);
    });

    $('#btn-deselect-all-fields').on('click', function() {
        $('input[name="export_fields[]"]').prop('checked', false);
    });

    $('#btn-export').on('click', function() {
        const format = $('input[name="export_format"]:checked').val();
        const fields = $('input[name="export_fields[]"]:checked').map(function() {
            return $(this).val();
        }).get();

        if (fields.length === 0) {
            alert('Selecione pelo menos um campo para exportar.');
            return;
        }

        const $btn = $(this);
        $btn.html('<i class="fas fa-spinner fa-spin"></i> Exportando...').prop('disabled', true);

        $.post(crmDevAdmin.ajaxUrl, {
            action: 'crm_dev_export_contacts',
            nonce: crmDevAdmin.nonce,
            format: format,
            fields: fields,
            filters: {
                estado: $('#export-estado').val(),
                regiao: $('#export-regiao').val(),
                status: $('#export-status').val()
            }
        }, function(response) {
            if (response.success) {
                const data = response.data.data;

                if (format === 'xlsx') {
                    const ws = XLSX.utils.aoa_to_sheet(data);
                    const wb = XLSX.utils.book_new();
                    XLSX.utils.book_append_sheet(wb, ws, 'Contatos');
                    XLSX.writeFile(wb, 'contatos_crm_' + new Date().toISOString().slice(0, 10) + '.xlsx');
                } else {
                    let csv = data.map(row => row.map(cell => `"${String(cell || '').replace(/"/g, '""')}"`).join(';')).join('\n');
                    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
                    const link = document.createElement('a');
                    link.href = URL.createObjectURL(blob);
                    link.download = 'contatos_crm_' + new Date().toISOString().slice(0, 10) + '.csv';
                    link.click();
                }
            } else {
                alert(response.data.message || 'Erro na exportação');
            }

            $btn.html('<i class="fas fa-download"></i> Exportar Contatos').prop('disabled', false);
        });
    });
});
</script>

<?php
// Modal de ajuda
crm_dev_render_help_modal_import_export();
crm_dev_render_help_modal_script();
?>
