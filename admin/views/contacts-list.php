<?php
/**
 * View da lista de contatos
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
$eixos = CRM_Dev_Helpers::get_eixos_tematicos();
$categorias = CRM_Dev_Helpers::get_categorias_representacao();

// Mensagens
if (isset($_GET['deleted'])) {
    echo '<div class="notice notice-success is-dismissible"><p>' . __('Contato excluído com sucesso!', 'crm-developer') . '</p></div>';
}
?>

<div class="wrap crm-dev-wrap">
    <div class="crm-dev-header">
        <div class="header-title-row">
            <div>
                <h1>
                    <i class="fas fa-address-book"></i>
                    <?php esc_html_e('Contatos', 'crm-developer'); ?>
                </h1>
            </div>
            <div class="header-actions-with-help">
                <a href="<?php echo esc_url(admin_url('admin.php?page=crm-developer&section=contact-new')); ?>" class="button button-primary">
                    <i class="fas fa-plus"></i> <?php esc_html_e('Novo Contato', 'crm-developer'); ?>
                </a>
                <?php crm_dev_render_help_button('contacts'); ?>
            </div>
        </div>
    </div>

    <div class="crm-dev-card">
        <!-- Filtros -->
        <div class="crm-dev-filters">
            <div class="filter-row">
                <div class="filter-group search-group">
                    <label><i class="fas fa-search"></i></label>
                    <input type="text" id="filter-search" placeholder="<?php esc_html_e('Buscar por nome, email, telefone...', 'crm-developer'); ?>">
                </div>

                <div class="filter-group">
                    <select id="filter-estado">
                        <option value=""><?php esc_html_e('Todos os estados', 'crm-developer'); ?></option>
                        <?php foreach ($estados as $uf => $nome) : ?>
                            <option value="<?php echo esc_attr($uf); ?>"><?php echo esc_html($nome); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <select id="filter-regiao">
                        <option value=""><?php esc_html_e('Todas as regiões', 'crm-developer'); ?></option>
                        <?php foreach ($regioes as $regiao) : ?>
                            <option value="<?php echo esc_attr($regiao); ?>"><?php echo esc_html($regiao); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <select id="filter-status">
                        <option value=""><?php esc_html_e('Todos os status', 'crm-developer'); ?></option>
                        <option value="ativo"><?php esc_html_e('Ativo', 'crm-developer'); ?></option>
                        <option value="inativo"><?php esc_html_e('Inativo', 'crm-developer'); ?></option>
                        <option value="pendente"><?php esc_html_e('Pendente', 'crm-developer'); ?></option>
                    </select>
                </div>

                <div class="filter-group">
                    <select id="filter-eixo">
                        <option value=""><?php esc_html_e('Todos os eixos', 'crm-developer'); ?></option>
                        <?php foreach ($eixos as $key => $nome) : ?>
                            <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($nome); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="button" id="btn-filter" class="button">
                    <i class="fas fa-filter"></i> <?php esc_html_e('Filtrar', 'crm-developer'); ?>
                </button>

                <button type="button" id="btn-clear-filters" class="button">
                    <i class="fas fa-times"></i> <?php esc_html_e('Limpar', 'crm-developer'); ?>
                </button>
            </div>
        </div>

        <!-- Ações em massa -->
        <div class="crm-dev-bulk-actions" style="display: none;">
            <span class="selected-count">0 selecionados</span>
            <button type="button" id="btn-bulk-delete" class="button button-link-delete">
                <i class="fas fa-trash"></i> <?php esc_html_e('Excluir', 'crm-developer'); ?>
            </button>
            <button type="button" id="btn-bulk-export" class="button">
                <i class="fas fa-download"></i> <?php esc_html_e('Exportar selecionados', 'crm-developer'); ?>
            </button>
        </div>

        <!-- Tabela -->
        <div class="crm-dev-table-container">
            <table class="crm-dev-table" id="contacts-table">
                <thead>
                    <tr>
                        <th class="check-column">
                            <input type="checkbox" id="select-all">
                        </th>
                        <th class="sortable" data-sort="nome_completo"><?php esc_html_e('Nome', 'crm-developer'); ?></th>
                        <th><?php esc_html_e('Contato', 'crm-developer'); ?></th>
                        <th><?php esc_html_e('Localização', 'crm-developer'); ?></th>
                        <th class="sortable" data-sort="score_engajamento"><?php esc_html_e('Score', 'crm-developer'); ?></th>
                        <th><?php esc_html_e('Status', 'crm-developer'); ?></th>
                        <th class="sortable" data-sort="created_at"><?php esc_html_e('Cadastro', 'crm-developer'); ?></th>
                        <th><?php esc_html_e('Ações', 'crm-developer'); ?></th>
                    </tr>
                </thead>
                <tbody id="contacts-tbody">
                    <tr class="loading-row">
                        <td colspan="8">
                            <div class="crm-dev-loading">
                                <i class="fas fa-spinner fa-spin"></i>
                                <?php esc_html_e('Carregando contatos...', 'crm-developer'); ?>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Paginação -->
        <div class="crm-dev-pagination" id="pagination-container">
            <div class="pagination-info">
                <span id="pagination-info"></span>
            </div>
            <div class="pagination-controls">
                <button type="button" id="btn-prev" class="button" disabled>
                    <i class="fas fa-chevron-left"></i>
                </button>
                <span id="pagination-pages"></span>
                <button type="button" id="btn-next" class="button" disabled>
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            <div class="pagination-per-page">
                <select id="per-page">
                    <option value="10">10</option>
                    <option value="20" selected>20</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                <span><?php esc_html_e('por página', 'crm-developer'); ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Template de linha -->
<script type="text/template" id="contact-row-template">
    <tr data-id="{{id}}">
        <td class="check-column">
            <input type="checkbox" class="contact-checkbox" value="{{id}}">
        </td>
        <td>
            <div class="contact-name">
                <a href="<?php echo esc_url(admin_url('admin.php?page=crm-developer&section=contacts&action=view&id=')); ?>{{id}}">
                    <strong>{{nome_completo}}</strong>
                </a>
                {{#nome_social}}<br><small>{{nome_social}}</small>{{/nome_social}}
            </div>
        </td>
        <td>
            <div class="contact-info">
                {{#email}}<span><i class="fas fa-envelope"></i> {{email}}</span>{{/email}}
                {{#whatsapp}}<br><a href="{{whatsapp_link}}" target="_blank"><i class="fab fa-whatsapp"></i> {{whatsapp_formatted}}</a>{{/whatsapp}}
                {{#telefone}}{{^whatsapp}}<br><span><i class="fas fa-phone"></i> {{telefone_formatted}}</span>{{/whatsapp}}{{/telefone}}
            </div>
        </td>
        <td>
            {{#municipio}}{{municipio}} - {{/municipio}}{{estado}}
            {{#regiao}}<br><small>{{regiao}}</small>{{/regiao}}
        </td>
        <td>
            <div class="score-container">
                <span class="score-badge" style="background-color: {{score_color}}">{{score_engajamento}}</span>
                <span class="score-label">{{score_label}}</span>
            </div>
        </td>
        <td>
            <span class="status-badge status-{{status}}">{{status_label}}</span>
        </td>
        <td>
            {{created_at_formatted}}
        </td>
        <td>
            <div class="row-actions">
                <a href="<?php echo esc_url(admin_url('admin.php?page=crm-developer&section=contacts&action=view&id=')); ?>{{id}}" class="action-btn" title="<?php esc_html_e('Ver', 'crm-developer'); ?>">
                    <i class="fas fa-eye"></i>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=crm-developer&section=contacts&action=edit&id=')); ?>{{id}}" class="action-btn" title="<?php esc_html_e('Editar', 'crm-developer'); ?>">
                    <i class="fas fa-edit"></i>
                </a>
                <a href="#" class="action-btn delete-contact" data-id="{{id}}" title="<?php esc_html_e('Excluir', 'crm-developer'); ?>">
                    <i class="fas fa-trash"></i>
                </a>
            </div>
        </td>
    </tr>
</script>

<script>
jQuery(document).ready(function($) {
    let currentPage = 1;
    let perPage = 20;
    let orderBy = 'created_at';
    let order = 'DESC';
    let selectedIds = [];

    // Carrega contatos inicial
    loadContacts();

    // Filtrar
    $('#btn-filter').on('click', function() {
        currentPage = 1;
        loadContacts();
    });

    // Limpar filtros
    $('#btn-clear-filters').on('click', function() {
        $('#filter-search, #filter-estado, #filter-regiao, #filter-status, #filter-eixo').val('');
        currentPage = 1;
        loadContacts();
    });

    // Busca com Enter
    $('#filter-search').on('keypress', function(e) {
        if (e.which === 13) {
            currentPage = 1;
            loadContacts();
        }
    });

    // Ordenação
    $('.sortable').on('click', function() {
        const newOrderBy = $(this).data('sort');
        if (orderBy === newOrderBy) {
            order = order === 'ASC' ? 'DESC' : 'ASC';
        } else {
            orderBy = newOrderBy;
            order = 'ASC';
        }
        $('.sortable').removeClass('asc desc');
        $(this).addClass(order.toLowerCase());
        loadContacts();
    });

    // Por página
    $('#per-page').on('change', function() {
        perPage = parseInt($(this).val());
        currentPage = 1;
        loadContacts();
    });

    // Paginação
    $('#btn-prev').on('click', function() {
        if (currentPage > 1) {
            currentPage--;
            loadContacts();
        }
    });

    $('#btn-next').on('click', function() {
        currentPage++;
        loadContacts();
    });

    // Seleção
    $('#select-all').on('change', function() {
        const checked = $(this).is(':checked');
        $('.contact-checkbox').prop('checked', checked);
        updateSelection();
    });

    $(document).on('change', '.contact-checkbox', function() {
        updateSelection();
    });

    // Excluir contato
    $(document).on('click', '.delete-contact', function(e) {
        e.preventDefault();
        const id = $(this).data('id');

        if (confirm(crmDevAdmin.strings.confirmDelete)) {
            $.post(crmDevAdmin.ajaxUrl, {
                action: 'crm_dev_delete_contact',
                nonce: crmDevAdmin.nonce,
                id: id
            }, function(response) {
                if (response.success) {
                    loadContacts();
                } else {
                    alert(response.data.message);
                }
            });
        }
    });

    // Excluir em massa
    $('#btn-bulk-delete').on('click', function() {
        if (selectedIds.length === 0) return;

        if (confirm('Tem certeza que deseja excluir ' + selectedIds.length + ' contato(s)?')) {
            // Implementar exclusão em massa
            let deleted = 0;
            selectedIds.forEach(function(id) {
                $.post(crmDevAdmin.ajaxUrl, {
                    action: 'crm_dev_delete_contact',
                    nonce: crmDevAdmin.nonce,
                    id: id
                }, function(response) {
                    deleted++;
                    if (deleted === selectedIds.length) {
                        loadContacts();
                    }
                });
            });
        }
    });

    function loadContacts() {
        $('#contacts-tbody').html('<tr class="loading-row"><td colspan="8"><div class="crm-dev-loading"><i class="fas fa-spinner fa-spin"></i> Carregando...</div></td></tr>');

        $.post(crmDevAdmin.ajaxUrl, {
            action: 'crm_dev_get_contacts',
            nonce: crmDevAdmin.nonce,
            page: currentPage,
            per_page: perPage,
            search: $('#filter-search').val(),
            estado: $('#filter-estado').val(),
            regiao: $('#filter-regiao').val(),
            status: $('#filter-status').val(),
            eixo_tematico: $('#filter-eixo').val(),
            orderby: orderBy,
            order: order
        }, function(response) {
            if (response.success) {
                renderContacts(response.data);
            } else {
                $('#contacts-tbody').html('<tr><td colspan="8" class="crm-dev-error">Erro ao carregar contatos</td></tr>');
            }
        });
    }

    function renderContacts(data) {
        const template = $('#contact-row-template').html();
        let html = '';

        if (data.items.length === 0) {
            html = '<tr><td colspan="8" class="crm-dev-empty">Nenhum contato encontrado</td></tr>';
        } else {
            data.items.forEach(function(contact) {
                let row = template;

                // Processa dados
                contact.score_color = getScoreColor(contact.score_engajamento);
                contact.score_label = getScoreLabel(contact.score_engajamento);
                contact.status_label = contact.status.charAt(0).toUpperCase() + contact.status.slice(1);
                contact.created_at_formatted = formatDate(contact.created_at);
                contact.whatsapp_formatted = formatPhone(contact.whatsapp);
                contact.telefone_formatted = formatPhone(contact.telefone);
                contact.whatsapp_link = contact.whatsapp ? 'https://wa.me/55' + contact.whatsapp.replace(/\D/g, '') : '';

                // Substitui placeholders simples
                Object.keys(contact).forEach(function(key) {
                    const value = contact[key] || '';
                    row = row.replace(new RegExp('{{' + key + '}}', 'g'), value);
                });

                // Processa condicionais (simplificado)
                row = row.replace(/{{#(\w+)}}(.*?){{\/\1}}/gs, function(match, key, content) {
                    return contact[key] ? content : '';
                });
                row = row.replace(/{{\^(\w+)}}(.*?){{\/\1}}/gs, function(match, key, content) {
                    return !contact[key] ? content : '';
                });

                html += row;
            });
        }

        $('#contacts-tbody').html(html);

        // Atualiza paginação
        const start = ((currentPage - 1) * perPage) + 1;
        const end = Math.min(currentPage * perPage, data.total);
        $('#pagination-info').text(start + '-' + end + ' de ' + data.total);
        $('#btn-prev').prop('disabled', currentPage <= 1);
        $('#btn-next').prop('disabled', currentPage >= data.pages);
        $('#pagination-pages').text('Página ' + currentPage + ' de ' + data.pages);

        // Limpa seleção
        selectedIds = [];
        updateSelection();
    }

    function updateSelection() {
        selectedIds = [];
        $('.contact-checkbox:checked').each(function() {
            selectedIds.push($(this).val());
        });

        if (selectedIds.length > 0) {
            $('.crm-dev-bulk-actions').show();
            $('.selected-count').text(selectedIds.length + ' selecionado(s)');
        } else {
            $('.crm-dev-bulk-actions').hide();
        }
    }

    function getScoreColor(score) {
        if (score >= 70) return '#27ae60';
        if (score >= 40) return '#f39c12';
        return '#e74c3c';
    }

    function getScoreLabel(score) {
        if (score >= 70) return 'Alto';
        if (score >= 40) return 'Médio';
        return 'Baixo';
    }

    function formatDate(dateStr) {
        if (!dateStr) return '-';
        const date = new Date(dateStr);
        return date.toLocaleDateString('pt-BR');
    }

    function formatPhone(phone) {
        if (!phone) return '';
        phone = phone.replace(/\D/g, '');
        if (phone.length === 11) {
            return '(' + phone.substr(0, 2) + ') ' + phone.substr(2, 5) + '-' + phone.substr(7);
        }
        return phone;
    }
});
</script>

<?php
// Modal de ajuda
crm_dev_render_help_modal_contacts();
crm_dev_render_help_modal_script();
?>
