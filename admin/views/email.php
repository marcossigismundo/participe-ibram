<?php
/**
 * View de Comunicação por Email
 *
 * @package CRM_Developer
 */

if (!defined('ABSPATH')) {
    exit;
}

$tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'send';
$settings = CRM_Dev_Email::get_settings();
$templates = CRM_Dev_Email::get_templates();

// Dados para filtros
global $wpdb;
$tables = CRM_Dev_Database::get_tables();
$estados = $wpdb->get_col("SELECT DISTINCT estado FROM {$tables['contacts']} WHERE estado IS NOT NULL AND estado != '' ORDER BY estado");
$regioes = $wpdb->get_col("SELECT DISTINCT regiao FROM {$tables['contacts']} WHERE regiao IS NOT NULL AND regiao != '' ORDER BY regiao");
?>

<div class="wrap crm-dev-wrap">
    <div class="crm-dev-header">
        <h1>
            <i class="fas fa-envelope"></i>
            <?php _e('Comunicação por Email', 'crm-developer'); ?>
        </h1>
        <p class="crm-dev-subtitle"><?php _e('Envie emails personalizados para seus contatos', 'crm-developer'); ?></p>
    </div>

    <!-- Navegação por Abas -->
    <div class="crm-dev-tabs">
        <a href="<?php echo admin_url('admin.php?page=crm-developer&section=email&tab=send'); ?>"
           class="tab-item <?php echo $tab === 'send' ? 'active' : ''; ?>">
            <i class="fas fa-paper-plane"></i>
            <?php _e('Enviar Email', 'crm-developer'); ?>
        </a>
        <a href="<?php echo admin_url('admin.php?page=crm-developer&section=email&tab=templates'); ?>"
           class="tab-item <?php echo $tab === 'templates' ? 'active' : ''; ?>">
            <i class="fas fa-file-alt"></i>
            <?php _e('Templates', 'crm-developer'); ?>
        </a>
        <a href="<?php echo admin_url('admin.php?page=crm-developer&section=email&tab=campaigns'); ?>"
           class="tab-item <?php echo $tab === 'campaigns' ? 'active' : ''; ?>">
            <i class="fas fa-bullhorn"></i>
            <?php _e('Campanhas', 'crm-developer'); ?>
        </a>
        <a href="<?php echo admin_url('admin.php?page=crm-developer&section=email&tab=logs'); ?>"
           class="tab-item <?php echo $tab === 'logs' ? 'active' : ''; ?>">
            <i class="fas fa-history"></i>
            <?php _e('Histórico', 'crm-developer'); ?>
        </a>
        <a href="<?php echo admin_url('admin.php?page=crm-developer&section=email&tab=settings'); ?>"
           class="tab-item <?php echo $tab === 'settings' ? 'active' : ''; ?>">
            <i class="fas fa-cog"></i>
            <?php _e('Configurações', 'crm-developer'); ?>
        </a>
    </div>

    <!-- Conteúdo das Abas -->
    <div class="crm-dev-tab-content">
        <?php if ($tab === 'send') : ?>
            <!-- Aba Enviar Email -->
            <div class="crm-dev-email-send">
                <div class="crm-dev-row">
                    <!-- Coluna de Configuração -->
                    <div class="crm-dev-col-8">
                        <div class="crm-dev-card">
                            <div class="card-header">
                                <h3><i class="fas fa-edit"></i> <?php _e('Compor Email', 'crm-developer'); ?></h3>
                            </div>
                            <div class="card-body">
                                <form id="email-send-form">
                                    <div class="form-group">
                                        <label for="email-template"><?php _e('Template', 'crm-developer'); ?></label>
                                        <select id="email-template" name="template_id" class="form-control">
                                            <option value=""><?php _e('-- Selecionar template ou criar novo --', 'crm-developer'); ?></option>
                                            <?php foreach ($templates as $template) : ?>
                                                <option value="<?php echo esc_attr($template['id']); ?>"
                                                        data-subject="<?php echo esc_attr($template['subject']); ?>"
                                                        data-content="<?php echo esc_attr($template['content']); ?>">
                                                    <?php echo esc_html($template['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label for="email-subject"><?php _e('Assunto', 'crm-developer'); ?> *</label>
                                        <input type="text" id="email-subject" name="subject" class="form-control" required>
                                    </div>

                                    <div class="form-group">
                                        <label for="email-content"><?php _e('Conteúdo', 'crm-developer'); ?> *</label>
                                        <div class="editor-toolbar">
                                            <button type="button" class="btn-toolbar" data-command="bold" title="Negrito">
                                                <i class="fas fa-bold"></i>
                                            </button>
                                            <button type="button" class="btn-toolbar" data-command="italic" title="Itálico">
                                                <i class="fas fa-italic"></i>
                                            </button>
                                            <button type="button" class="btn-toolbar" data-command="underline" title="Sublinhado">
                                                <i class="fas fa-underline"></i>
                                            </button>
                                            <span class="toolbar-divider"></span>
                                            <button type="button" class="btn-toolbar" data-command="insertUnorderedList" title="Lista">
                                                <i class="fas fa-list-ul"></i>
                                            </button>
                                            <button type="button" class="btn-toolbar" data-command="insertOrderedList" title="Lista Numerada">
                                                <i class="fas fa-list-ol"></i>
                                            </button>
                                            <span class="toolbar-divider"></span>
                                            <button type="button" class="btn-toolbar" data-command="createLink" title="Link">
                                                <i class="fas fa-link"></i>
                                            </button>
                                            <span class="toolbar-divider"></span>
                                            <div class="dropdown-toolbar">
                                                <button type="button" class="btn-toolbar dropdown-toggle" id="btn-variables">
                                                    <i class="fas fa-code"></i> <?php _e('Variáveis', 'crm-developer'); ?>
                                                </button>
                                                <div class="dropdown-menu" id="variables-menu">
                                                    <a href="#" data-var="{{nome}}"><?php _e('Nome Completo', 'crm-developer'); ?></a>
                                                    <a href="#" data-var="{{primeiro_nome}}"><?php _e('Primeiro Nome', 'crm-developer'); ?></a>
                                                    <a href="#" data-var="{{email}}"><?php _e('Email', 'crm-developer'); ?></a>
                                                    <a href="#" data-var="{{estado}}"><?php _e('Estado', 'crm-developer'); ?></a>
                                                    <a href="#" data-var="{{municipio}}"><?php _e('Município', 'crm-developer'); ?></a>
                                                    <a href="#" data-var="{{regiao}}"><?php _e('Região', 'crm-developer'); ?></a>
                                                    <a href="#" data-var="{{data_cadastro}}"><?php _e('Data de Cadastro', 'crm-developer'); ?></a>
                                                    <a href="#" data-var="{{link_descadastro}}"><?php _e('Link Descadastro', 'crm-developer'); ?></a>
                                                </div>
                                            </div>
                                        </div>
                                        <div id="email-content" class="content-editor" contenteditable="true"></div>
                                        <input type="hidden" name="content" id="email-content-hidden">
                                    </div>

                                    <div class="form-actions">
                                        <button type="button" id="btn-preview-email" class="btn btn-secondary">
                                            <i class="fas fa-eye"></i> <?php _e('Visualizar', 'crm-developer'); ?>
                                        </button>
                                        <button type="button" id="btn-save-template" class="btn btn-outline">
                                            <i class="fas fa-save"></i> <?php _e('Salvar como Template', 'crm-developer'); ?>
                                        </button>
                                        <button type="submit" id="btn-send-email" class="btn btn-primary">
                                            <i class="fas fa-paper-plane"></i> <?php _e('Enviar Emails', 'crm-developer'); ?>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Coluna de Filtros e Destinatários -->
                    <div class="crm-dev-col-4">
                        <div class="crm-dev-card">
                            <div class="card-header">
                                <h3><i class="fas fa-filter"></i> <?php _e('Filtrar Destinatários', 'crm-developer'); ?></h3>
                            </div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="filter-region"><?php _e('Região', 'crm-developer'); ?></label>
                                    <select id="filter-region" name="filter_region" class="form-control filter-recipients">
                                        <option value=""><?php _e('Todas', 'crm-developer'); ?></option>
                                        <?php foreach ($regioes as $regiao) : ?>
                                            <option value="<?php echo esc_attr($regiao); ?>"><?php echo esc_html($regiao); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="filter-state"><?php _e('Estado', 'crm-developer'); ?></label>
                                    <select id="filter-state" name="filter_state" class="form-control filter-recipients">
                                        <option value=""><?php _e('Todos', 'crm-developer'); ?></option>
                                        <?php foreach ($estados as $estado) : ?>
                                            <option value="<?php echo esc_attr($estado); ?>"><?php echo esc_html($estado); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="filter-engagement"><?php _e('Engajamento', 'crm-developer'); ?></label>
                                    <select id="filter-engagement" name="filter_engagement" class="form-control filter-recipients">
                                        <option value=""><?php _e('Todos', 'crm-developer'); ?></option>
                                        <option value="alto"><?php _e('Alto (70-100)', 'crm-developer'); ?></option>
                                        <option value="medio"><?php _e('Médio (40-69)', 'crm-developer'); ?></option>
                                        <option value="baixo"><?php _e('Baixo (0-39)', 'crm-developer'); ?></option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="filter-status"><?php _e('Status', 'crm-developer'); ?></label>
                                    <select id="filter-status" name="filter_status" class="form-control filter-recipients">
                                        <option value=""><?php _e('Todos', 'crm-developer'); ?></option>
                                        <option value="ativo"><?php _e('Ativo', 'crm-developer'); ?></option>
                                        <option value="inativo"><?php _e('Inativo', 'crm-developer'); ?></option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>
                                        <input type="checkbox" id="filter-consent" name="filter_consent" value="1" checked>
                                        <?php _e('Apenas com consentimento LGPD', 'crm-developer'); ?>
                                    </label>
                                </div>

                                <div class="recipients-count">
                                    <div class="count-box">
                                        <span id="recipients-count">0</span>
                                        <label><?php _e('Destinatários', 'crm-developer'); ?></label>
                                    </div>
                                </div>

                                <button type="button" id="btn-refresh-count" class="btn btn-secondary btn-block">
                                    <i class="fas fa-sync-alt"></i> <?php _e('Atualizar Contagem', 'crm-developer'); ?>
                                </button>
                            </div>
                        </div>

                        <!-- Informações de Envio -->
                        <div class="crm-dev-card">
                            <div class="card-header">
                                <h3><i class="fas fa-info-circle"></i> <?php _e('Informações', 'crm-developer'); ?></h3>
                            </div>
                            <div class="card-body">
                                <div class="info-list">
                                    <div class="info-item">
                                        <i class="fas fa-user"></i>
                                        <span><?php _e('Remetente:', 'crm-developer'); ?></span>
                                        <strong><?php echo esc_html($settings['from_name'] ?: get_bloginfo('name')); ?></strong>
                                    </div>
                                    <div class="info-item">
                                        <i class="fas fa-at"></i>
                                        <span><?php _e('Email:', 'crm-developer'); ?></span>
                                        <strong><?php echo esc_html($settings['from_email'] ?: get_option('admin_email')); ?></strong>
                                    </div>
                                    <div class="info-item">
                                        <i class="fas fa-tachometer-alt"></i>
                                        <span><?php _e('Limite:', 'crm-developer'); ?></span>
                                        <strong><?php echo intval($settings['rate_limit'] ?: 50); ?> <?php _e('emails/hora', 'crm-developer'); ?></strong>
                                    </div>
                                </div>
                                <p class="info-note">
                                    <i class="fas fa-shield-alt"></i>
                                    <?php _e('Emails são enviados em lotes para evitar bloqueios.', 'crm-developer'); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($tab === 'templates') : ?>
            <!-- Aba Templates -->
            <div class="crm-dev-email-templates">
                <div class="crm-dev-row">
                    <div class="crm-dev-col-8">
                        <div class="crm-dev-card">
                            <div class="card-header">
                                <h3><i class="fas fa-file-alt"></i> <?php _e('Templates de Email', 'crm-developer'); ?></h3>
                                <button type="button" id="btn-new-template" class="btn btn-primary btn-sm">
                                    <i class="fas fa-plus"></i> <?php _e('Novo Template', 'crm-developer'); ?>
                                </button>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($templates)) : ?>
                                    <table class="crm-dev-table">
                                        <thead>
                                            <tr>
                                                <th><?php _e('Nome', 'crm-developer'); ?></th>
                                                <th><?php _e('Assunto', 'crm-developer'); ?></th>
                                                <th><?php _e('Criado em', 'crm-developer'); ?></th>
                                                <th width="120"><?php _e('Ações', 'crm-developer'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody id="templates-list">
                                            <?php foreach ($templates as $template) : ?>
                                                <tr data-id="<?php echo esc_attr($template['id']); ?>">
                                                    <td><strong><?php echo esc_html($template['name']); ?></strong></td>
                                                    <td><?php echo esc_html($template['subject']); ?></td>
                                                    <td><?php echo CRM_Dev_Helpers::format_datetime($template['created_at'], 'd/m/Y'); ?></td>
                                                    <td>
                                                        <button type="button" class="btn-icon btn-edit-template"
                                                                data-id="<?php echo esc_attr($template['id']); ?>"
                                                                data-name="<?php echo esc_attr($template['name']); ?>"
                                                                data-subject="<?php echo esc_attr($template['subject']); ?>"
                                                                data-content="<?php echo esc_attr($template['content']); ?>"
                                                                title="<?php _e('Editar', 'crm-developer'); ?>">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button type="button" class="btn-icon btn-delete-template"
                                                                data-id="<?php echo esc_attr($template['id']); ?>"
                                                                title="<?php _e('Excluir', 'crm-developer'); ?>">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php else : ?>
                                    <div class="crm-dev-empty">
                                        <i class="fas fa-file-alt"></i>
                                        <p><?php _e('Nenhum template criado ainda.', 'crm-developer'); ?></p>
                                        <button type="button" id="btn-new-template-empty" class="btn btn-primary">
                                            <i class="fas fa-plus"></i> <?php _e('Criar Primeiro Template', 'crm-developer'); ?>
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="crm-dev-col-4">
                        <div class="crm-dev-card">
                            <div class="card-header">
                                <h3><i class="fas fa-code"></i> <?php _e('Variáveis Disponíveis', 'crm-developer'); ?></h3>
                            </div>
                            <div class="card-body">
                                <div class="variables-list">
                                    <div class="variable-item">
                                        <code>{{nome}}</code>
                                        <span><?php _e('Nome completo do contato', 'crm-developer'); ?></span>
                                    </div>
                                    <div class="variable-item">
                                        <code>{{primeiro_nome}}</code>
                                        <span><?php _e('Primeiro nome do contato', 'crm-developer'); ?></span>
                                    </div>
                                    <div class="variable-item">
                                        <code>{{email}}</code>
                                        <span><?php _e('Email do contato', 'crm-developer'); ?></span>
                                    </div>
                                    <div class="variable-item">
                                        <code>{{estado}}</code>
                                        <span><?php _e('Estado do contato', 'crm-developer'); ?></span>
                                    </div>
                                    <div class="variable-item">
                                        <code>{{municipio}}</code>
                                        <span><?php _e('Município do contato', 'crm-developer'); ?></span>
                                    </div>
                                    <div class="variable-item">
                                        <code>{{regiao}}</code>
                                        <span><?php _e('Região do contato', 'crm-developer'); ?></span>
                                    </div>
                                    <div class="variable-item">
                                        <code>{{data_cadastro}}</code>
                                        <span><?php _e('Data de cadastro', 'crm-developer'); ?></span>
                                    </div>
                                    <div class="variable-item">
                                        <code>{{link_descadastro}}</code>
                                        <span><?php _e('Link para cancelar inscrição', 'crm-developer'); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($tab === 'campaigns') : ?>
            <!-- Aba Campanhas -->
            <div class="crm-dev-email-campaigns">
                <div class="crm-dev-card">
                    <div class="card-header">
                        <h3><i class="fas fa-bullhorn"></i> <?php _e('Campanhas de Email', 'crm-developer'); ?></h3>
                    </div>
                    <div class="card-body">
                        <div id="campaigns-list">
                            <p class="loading"><i class="fas fa-spinner fa-spin"></i> <?php _e('Carregando...', 'crm-developer'); ?></p>
                        </div>
                    </div>
                </div>

                <div class="crm-dev-card">
                    <div class="card-header">
                        <h3><i class="fas fa-clock"></i> <?php _e('Fila de Envio', 'crm-developer'); ?></h3>
                        <button type="button" id="btn-refresh-queue" class="btn btn-secondary btn-sm">
                            <i class="fas fa-sync-alt"></i> <?php _e('Atualizar', 'crm-developer'); ?>
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="queue-status">
                            <p class="loading"><i class="fas fa-spinner fa-spin"></i> <?php _e('Carregando...', 'crm-developer'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($tab === 'logs') : ?>
            <!-- Aba Histórico -->
            <div class="crm-dev-email-logs">
                <div class="crm-dev-card">
                    <div class="card-header">
                        <h3><i class="fas fa-history"></i> <?php _e('Histórico de Emails', 'crm-developer'); ?></h3>
                        <div class="header-actions">
                            <select id="log-filter-status" class="form-control form-control-sm">
                                <option value=""><?php _e('Todos os Status', 'crm-developer'); ?></option>
                                <option value="sent"><?php _e('Enviados', 'crm-developer'); ?></option>
                                <option value="failed"><?php _e('Falhas', 'crm-developer'); ?></option>
                                <option value="opened"><?php _e('Abertos', 'crm-developer'); ?></option>
                            </select>
                            <button type="button" id="btn-refresh-logs" class="btn btn-secondary btn-sm">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="email-logs">
                            <p class="loading"><i class="fas fa-spinner fa-spin"></i> <?php _e('Carregando...', 'crm-developer'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($tab === 'settings') : ?>
            <!-- Aba Configurações -->
            <div class="crm-dev-email-settings">
                <form id="email-settings-form">
                    <div class="crm-dev-row">
                        <div class="crm-dev-col-6">
                            <div class="crm-dev-card">
                                <div class="card-header">
                                    <h3><i class="fas fa-user"></i> <?php _e('Remetente', 'crm-developer'); ?></h3>
                                </div>
                                <div class="card-body">
                                    <div class="form-group">
                                        <label for="from_name"><?php _e('Nome do Remetente', 'crm-developer'); ?></label>
                                        <input type="text" id="from_name" name="from_name" class="form-control"
                                               value="<?php echo esc_attr($settings['from_name'] ?: get_bloginfo('name')); ?>">
                                    </div>

                                    <div class="form-group">
                                        <label for="from_email"><?php _e('Email do Remetente', 'crm-developer'); ?></label>
                                        <input type="email" id="from_email" name="from_email" class="form-control"
                                               value="<?php echo esc_attr($settings['from_email'] ?: get_option('admin_email')); ?>">
                                    </div>

                                    <div class="form-group">
                                        <label for="reply_to"><?php _e('Responder Para', 'crm-developer'); ?></label>
                                        <input type="email" id="reply_to" name="reply_to" class="form-control"
                                               value="<?php echo esc_attr($settings['reply_to'] ?? ''); ?>"
                                               placeholder="<?php _e('Deixe vazio para usar o email do remetente', 'crm-developer'); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="crm-dev-card">
                                <div class="card-header">
                                    <h3><i class="fas fa-tachometer-alt"></i> <?php _e('Limites de Envio', 'crm-developer'); ?></h3>
                                </div>
                                <div class="card-body">
                                    <div class="form-group">
                                        <label for="rate_limit"><?php _e('Limite por Hora', 'crm-developer'); ?></label>
                                        <input type="number" id="rate_limit" name="rate_limit" class="form-control"
                                               value="<?php echo intval($settings['rate_limit'] ?: 50); ?>" min="1" max="500">
                                        <p class="form-help"><?php _e('Quantidade máxima de emails enviados por hora.', 'crm-developer'); ?></p>
                                    </div>

                                    <div class="form-group">
                                        <label for="batch_size"><?php _e('Tamanho do Lote', 'crm-developer'); ?></label>
                                        <input type="number" id="batch_size" name="batch_size" class="form-control"
                                               value="<?php echo intval($settings['batch_size'] ?: 10); ?>" min="1" max="50">
                                        <p class="form-help"><?php _e('Quantidade de emails enviados por lote.', 'crm-developer'); ?></p>
                                    </div>

                                    <div class="form-group">
                                        <label for="batch_delay"><?php _e('Intervalo entre Lotes (segundos)', 'crm-developer'); ?></label>
                                        <input type="number" id="batch_delay" name="batch_delay" class="form-control"
                                               value="<?php echo intval($settings['batch_delay'] ?: 60); ?>" min="10" max="300">
                                        <p class="form-help"><?php _e('Tempo de espera entre cada lote de envios.', 'crm-developer'); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="crm-dev-col-6">
                            <div class="crm-dev-card">
                                <div class="card-header">
                                    <h3><i class="fas fa-server"></i> <?php _e('Configuração SMTP', 'crm-developer'); ?></h3>
                                </div>
                                <div class="card-body">
                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" id="smtp_enabled" name="smtp_enabled" value="1"
                                                   <?php checked(!empty($settings['smtp_enabled'])); ?>>
                                            <?php _e('Usar servidor SMTP personalizado', 'crm-developer'); ?>
                                        </label>
                                    </div>

                                    <div id="smtp-settings" style="<?php echo empty($settings['smtp_enabled']) ? 'display:none;' : ''; ?>">
                                        <div class="form-group">
                                            <label for="smtp_host"><?php _e('Servidor SMTP', 'crm-developer'); ?></label>
                                            <input type="text" id="smtp_host" name="smtp_host" class="form-control"
                                                   value="<?php echo esc_attr($settings['smtp_host'] ?? ''); ?>"
                                                   placeholder="smtp.gmail.com">
                                        </div>

                                        <div class="form-row">
                                            <div class="form-group col-6">
                                                <label for="smtp_port"><?php _e('Porta', 'crm-developer'); ?></label>
                                                <input type="number" id="smtp_port" name="smtp_port" class="form-control"
                                                       value="<?php echo intval($settings['smtp_port'] ?? 587); ?>">
                                            </div>

                                            <div class="form-group col-6">
                                                <label for="smtp_secure"><?php _e('Segurança', 'crm-developer'); ?></label>
                                                <select id="smtp_secure" name="smtp_secure" class="form-control">
                                                    <option value="" <?php selected(empty($settings['smtp_secure'])); ?>><?php _e('Nenhum', 'crm-developer'); ?></option>
                                                    <option value="tls" <?php selected(($settings['smtp_secure'] ?? '') === 'tls'); ?>>TLS</option>
                                                    <option value="ssl" <?php selected(($settings['smtp_secure'] ?? '') === 'ssl'); ?>>SSL</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label for="smtp_user"><?php _e('Usuário SMTP', 'crm-developer'); ?></label>
                                            <input type="text" id="smtp_user" name="smtp_user" class="form-control"
                                                   value="<?php echo esc_attr($settings['smtp_user'] ?? ''); ?>">
                                        </div>

                                        <div class="form-group">
                                            <label for="smtp_pass"><?php _e('Senha SMTP', 'crm-developer'); ?></label>
                                            <input type="password" id="smtp_pass" name="smtp_pass" class="form-control"
                                                   value="<?php echo esc_attr($settings['smtp_pass'] ?? ''); ?>"
                                                   placeholder="<?php echo !empty($settings['smtp_pass']) ? '••••••••' : ''; ?>">
                                        </div>

                                        <button type="button" id="btn-test-smtp" class="btn btn-secondary">
                                            <i class="fas fa-vial"></i> <?php _e('Testar Conexão', 'crm-developer'); ?>
                                        </button>
                                        <span id="smtp-test-result"></span>
                                    </div>
                                </div>
                            </div>

                            <div class="crm-dev-card">
                                <div class="card-header">
                                    <h3><i class="fas fa-signature"></i> <?php _e('Rodapé Padrão', 'crm-developer'); ?></h3>
                                </div>
                                <div class="card-body">
                                    <div class="form-group">
                                        <label for="email_footer"><?php _e('Texto do Rodapé', 'crm-developer'); ?></label>
                                        <textarea id="email_footer" name="email_footer" class="form-control" rows="4"
                                                  placeholder="<?php _e('Adicione informações de contato, redes sociais, etc.', 'crm-developer'); ?>"><?php echo esc_textarea($settings['email_footer'] ?? ''); ?></textarea>
                                    </div>

                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" id="include_unsubscribe" name="include_unsubscribe" value="1"
                                                   <?php checked(!isset($settings['include_unsubscribe']) || $settings['include_unsubscribe']); ?>>
                                            <?php _e('Incluir link de descadastro automaticamente', 'crm-developer'); ?>
                                        </label>
                                        <p class="form-help"><?php _e('Recomendado para conformidade com LGPD.', 'crm-developer'); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions-fixed">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save"></i> <?php _e('Salvar Configurações', 'crm-developer'); ?>
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal de Template -->
<div id="modal-template" class="crm-dev-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modal-template-title"><?php _e('Novo Template', 'crm-developer'); ?></h3>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <form id="template-form">
                <input type="hidden" name="template_id" id="template-id">

                <div class="form-group">
                    <label for="template-name"><?php _e('Nome do Template', 'crm-developer'); ?> *</label>
                    <input type="text" id="template-name" name="name" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="template-subject"><?php _e('Assunto', 'crm-developer'); ?> *</label>
                    <input type="text" id="template-subject" name="subject" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="template-content"><?php _e('Conteúdo', 'crm-developer'); ?> *</label>
                    <textarea id="template-content" name="content" class="form-control" rows="10" required></textarea>
                    <p class="form-help"><?php _e('Use as variáveis disponíveis para personalizar o email.', 'crm-developer'); ?></p>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary modal-close-btn"><?php _e('Cancelar', 'crm-developer'); ?></button>
            <button type="button" id="btn-save-template-modal" class="btn btn-primary">
                <i class="fas fa-save"></i> <?php _e('Salvar', 'crm-developer'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Modal de Preview -->
<div id="modal-preview" class="crm-dev-modal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h3><?php _e('Visualização do Email', 'crm-developer'); ?></h3>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div id="email-preview-content"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary modal-close-btn"><?php _e('Fechar', 'crm-developer'); ?></button>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // ========================================
    // ENVIAR EMAIL
    // ========================================

    // Carregar template selecionado
    $('#email-template').on('change', function() {
        const selected = $(this).find(':selected');
        if (selected.val()) {
            $('#email-subject').val(selected.data('subject'));
            $('#email-content').html(selected.data('content'));
        }
    });

    // Toolbar do editor
    $('.btn-toolbar').on('click', function(e) {
        e.preventDefault();
        const command = $(this).data('command');

        if (command === 'createLink') {
            const url = prompt('Digite a URL:');
            if (url) {
                document.execCommand(command, false, url);
            }
        } else {
            document.execCommand(command, false, null);
        }
        $('#email-content').focus();
    });

    // Dropdown de variáveis
    $('#btn-variables').on('click', function(e) {
        e.preventDefault();
        $('#variables-menu').toggleClass('show');
    });

    $('#variables-menu a').on('click', function(e) {
        e.preventDefault();
        const variable = $(this).data('var');
        document.execCommand('insertText', false, variable);
        $('#variables-menu').removeClass('show');
        $('#email-content').focus();
    });

    // Fechar dropdown ao clicar fora
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.dropdown-toolbar').length) {
            $('#variables-menu').removeClass('show');
        }
    });

    // Atualizar contagem de destinatários
    function updateRecipientsCount() {
        const filters = {
            region: $('#filter-region').val(),
            state: $('#filter-state').val(),
            engagement: $('#filter-engagement').val(),
            status: $('#filter-status').val(),
            consent: $('#filter-consent').is(':checked') ? 1 : 0
        };

        $.post(crmDevAdmin.ajaxUrl, {
            action: 'crm_dev_get_contacts',
            nonce: crmDevAdmin.nonce,
            count_only: 1,
            filters: filters
        }, function(response) {
            if (response.success) {
                $('#recipients-count').text(response.data.total || 0);
            }
        });
    }

    $('.filter-recipients').on('change', updateRecipientsCount);
    $('#filter-consent').on('change', updateRecipientsCount);
    $('#btn-refresh-count').on('click', updateRecipientsCount);
    updateRecipientsCount();

    // Preview do email
    $('#btn-preview-email').on('click', function() {
        const subject = $('#email-subject').val();
        const content = $('#email-content').html();

        if (!subject || !content) {
            alert('<?php _e('Preencha o assunto e conteúdo do email.', 'crm-developer'); ?>');
            return;
        }

        $.post(crmDevAdmin.ajaxUrl, {
            action: 'crm_dev_preview_email',
            nonce: crmDevAdmin.nonce,
            subject: subject,
            content: content
        }, function(response) {
            if (response.success) {
                $('#email-preview-content').html(response.data.preview);
                $('#modal-preview').addClass('show');
            }
        });
    });

    // Enviar email
    $('#email-send-form').on('submit', function(e) {
        e.preventDefault();

        const subject = $('#email-subject').val();
        const content = $('#email-content').html();
        $('#email-content-hidden').val(content);

        if (!subject || !content) {
            alert('<?php _e('Preencha todos os campos obrigatórios.', 'crm-developer'); ?>');
            return;
        }

        const recipientsCount = parseInt($('#recipients-count').text());
        if (recipientsCount === 0) {
            alert('<?php _e('Nenhum destinatário encontrado com os filtros selecionados.', 'crm-developer'); ?>');
            return;
        }

        if (!confirm('<?php _e('Tem certeza que deseja enviar o email para', 'crm-developer'); ?> ' + recipientsCount + ' <?php _e('destinatários?', 'crm-developer'); ?>')) {
            return;
        }

        const $btn = $('#btn-send-email');
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> <?php _e('Enviando...', 'crm-developer'); ?>');

        $.post(crmDevAdmin.ajaxUrl, {
            action: 'crm_dev_send_mass_email',
            nonce: crmDevAdmin.nonce,
            subject: subject,
            content: content,
            filters: {
                region: $('#filter-region').val(),
                state: $('#filter-state').val(),
                engagement: $('#filter-engagement').val(),
                status: $('#filter-status').val(),
                consent: $('#filter-consent').is(':checked') ? 1 : 0
            }
        }, function(response) {
            $btn.prop('disabled', false).html('<i class="fas fa-paper-plane"></i> <?php _e('Enviar Emails', 'crm-developer'); ?>');

            if (response.success) {
                alert(response.data.message);
                // Redirecionar para campanhas
                window.location.href = '<?php echo admin_url('admin.php?page=crm-developer&section=email&tab=campaigns'); ?>';
            } else {
                alert(response.data.message || '<?php _e('Erro ao enviar emails.', 'crm-developer'); ?>');
            }
        });
    });

    // ========================================
    // TEMPLATES
    // ========================================

    // Abrir modal de novo template
    $('#btn-new-template, #btn-new-template-empty').on('click', function() {
        $('#modal-template-title').text('<?php _e('Novo Template', 'crm-developer'); ?>');
        $('#template-form')[0].reset();
        $('#template-id').val('');
        $('#modal-template').addClass('show');
    });

    // Editar template
    $(document).on('click', '.btn-edit-template', function() {
        const $btn = $(this);
        $('#modal-template-title').text('<?php _e('Editar Template', 'crm-developer'); ?>');
        $('#template-id').val($btn.data('id'));
        $('#template-name').val($btn.data('name'));
        $('#template-subject').val($btn.data('subject'));
        $('#template-content').val($btn.data('content'));
        $('#modal-template').addClass('show');
    });

    // Salvar template
    $('#btn-save-template-modal').on('click', function() {
        const $form = $('#template-form');

        if (!$form[0].checkValidity()) {
            $form[0].reportValidity();
            return;
        }

        const $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

        $.post(crmDevAdmin.ajaxUrl, {
            action: 'crm_dev_save_email_template',
            nonce: crmDevAdmin.nonce,
            id: $('#template-id').val(),
            name: $('#template-name').val(),
            subject: $('#template-subject').val(),
            content: $('#template-content').val()
        }, function(response) {
            $btn.prop('disabled', false).html('<i class="fas fa-save"></i> <?php _e('Salvar', 'crm-developer'); ?>');

            if (response.success) {
                location.reload();
            } else {
                alert(response.data.message || '<?php _e('Erro ao salvar template.', 'crm-developer'); ?>');
            }
        });
    });

    // Excluir template
    $(document).on('click', '.btn-delete-template', function() {
        if (!confirm('<?php _e('Tem certeza que deseja excluir este template?', 'crm-developer'); ?>')) {
            return;
        }

        const $btn = $(this);
        const id = $btn.data('id');

        $.post(crmDevAdmin.ajaxUrl, {
            action: 'crm_dev_delete_email_template',
            nonce: crmDevAdmin.nonce,
            id: id
        }, function(response) {
            if (response.success) {
                $btn.closest('tr').fadeOut(function() {
                    $(this).remove();
                });
            } else {
                alert(response.data.message || '<?php _e('Erro ao excluir template.', 'crm-developer'); ?>');
            }
        });
    });

    // Salvar como template (da aba enviar)
    $('#btn-save-template').on('click', function() {
        const subject = $('#email-subject').val();
        const content = $('#email-content').html();

        if (!subject || !content) {
            alert('<?php _e('Preencha o assunto e conteúdo antes de salvar.', 'crm-developer'); ?>');
            return;
        }

        const name = prompt('<?php _e('Digite o nome do template:', 'crm-developer'); ?>');
        if (!name) return;

        $.post(crmDevAdmin.ajaxUrl, {
            action: 'crm_dev_save_email_template',
            nonce: crmDevAdmin.nonce,
            name: name,
            subject: subject,
            content: content
        }, function(response) {
            if (response.success) {
                alert('<?php _e('Template salvo com sucesso!', 'crm-developer'); ?>');
            } else {
                alert(response.data.message || '<?php _e('Erro ao salvar template.', 'crm-developer'); ?>');
            }
        });
    });

    // ========================================
    // CAMPANHAS E FILA
    // ========================================

    function loadCampaigns() {
        $.post(crmDevAdmin.ajaxUrl, {
            action: 'crm_dev_get_email_queue',
            nonce: crmDevAdmin.nonce,
            type: 'campaigns'
        }, function(response) {
            if (response.success && response.data.campaigns) {
                let html = '';
                if (response.data.campaigns.length > 0) {
                    html = '<table class="crm-dev-table"><thead><tr>';
                    html += '<th><?php _e('Campanha', 'crm-developer'); ?></th>';
                    html += '<th><?php _e('Status', 'crm-developer'); ?></th>';
                    html += '<th><?php _e('Enviados', 'crm-developer'); ?></th>';
                    html += '<th><?php _e('Falhas', 'crm-developer'); ?></th>';
                    html += '<th><?php _e('Data', 'crm-developer'); ?></th>';
                    html += '</tr></thead><tbody>';

                    response.data.campaigns.forEach(function(c) {
                        html += '<tr>';
                        html += '<td><strong>' + c.name + '</strong></td>';
                        html += '<td><span class="status-badge status-' + c.status + '">' + c.status_label + '</span></td>';
                        html += '<td>' + c.sent_count + '/' + c.total_recipients + '</td>';
                        html += '<td>' + c.failed_count + '</td>';
                        html += '<td>' + c.created_at + '</td>';
                        html += '</tr>';
                    });

                    html += '</tbody></table>';
                } else {
                    html = '<p class="crm-dev-empty"><?php _e('Nenhuma campanha encontrada.', 'crm-developer'); ?></p>';
                }
                $('#campaigns-list').html(html);
            }
        });
    }

    function loadQueue() {
        $.post(crmDevAdmin.ajaxUrl, {
            action: 'crm_dev_get_email_queue',
            nonce: crmDevAdmin.nonce,
            type: 'queue'
        }, function(response) {
            if (response.success) {
                let html = '';
                if (response.data.queue && response.data.queue.pending > 0) {
                    html = '<div class="queue-stats">';
                    html += '<div class="queue-stat"><span class="value">' + response.data.queue.pending + '</span><span class="label"><?php _e('Pendentes', 'crm-developer'); ?></span></div>';
                    html += '<div class="queue-stat"><span class="value">' + response.data.queue.processing + '</span><span class="label"><?php _e('Processando', 'crm-developer'); ?></span></div>';
                    html += '<div class="queue-stat"><span class="value">' + response.data.queue.sent + '</span><span class="label"><?php _e('Enviados', 'crm-developer'); ?></span></div>';
                    html += '<div class="queue-stat"><span class="value">' + response.data.queue.failed + '</span><span class="label"><?php _e('Falhas', 'crm-developer'); ?></span></div>';
                    html += '</div>';
                } else {
                    html = '<p class="crm-dev-empty"><i class="fas fa-check-circle"></i> <?php _e('Fila de envio vazia.', 'crm-developer'); ?></p>';
                }
                $('#queue-status').html(html);
            }
        });
    }

    if ($('#campaigns-list').length) {
        loadCampaigns();
        loadQueue();
    }

    $('#btn-refresh-queue').on('click', function() {
        loadCampaigns();
        loadQueue();
    });

    // ========================================
    // LOGS
    // ========================================

    function loadLogs(status) {
        $.post(crmDevAdmin.ajaxUrl, {
            action: 'crm_dev_get_email_logs',
            nonce: crmDevAdmin.nonce,
            status: status || ''
        }, function(response) {
            if (response.success && response.data.logs) {
                let html = '';
                if (response.data.logs.length > 0) {
                    html = '<table class="crm-dev-table"><thead><tr>';
                    html += '<th><?php _e('Destinatário', 'crm-developer'); ?></th>';
                    html += '<th><?php _e('Assunto', 'crm-developer'); ?></th>';
                    html += '<th><?php _e('Status', 'crm-developer'); ?></th>';
                    html += '<th><?php _e('Data', 'crm-developer'); ?></th>';
                    html += '</tr></thead><tbody>';

                    response.data.logs.forEach(function(log) {
                        html += '<tr>';
                        html += '<td>' + log.to_email + '</td>';
                        html += '<td>' + log.subject + '</td>';
                        html += '<td><span class="status-badge status-' + log.status + '">' + log.status_label + '</span></td>';
                        html += '<td>' + log.sent_at + '</td>';
                        html += '</tr>';
                    });

                    html += '</tbody></table>';
                } else {
                    html = '<p class="crm-dev-empty"><?php _e('Nenhum registro encontrado.', 'crm-developer'); ?></p>';
                }
                $('#email-logs').html(html);
            }
        });
    }

    if ($('#email-logs').length) {
        loadLogs();
    }

    $('#log-filter-status').on('change', function() {
        loadLogs($(this).val());
    });

    $('#btn-refresh-logs').on('click', function() {
        loadLogs($('#log-filter-status').val());
    });

    // ========================================
    // CONFIGURAÇÕES
    // ========================================

    // Toggle SMTP settings
    $('#smtp_enabled').on('change', function() {
        $('#smtp-settings').toggle(this.checked);
    });

    // Testar SMTP
    $('#btn-test-smtp').on('click', function() {
        const $btn = $(this);
        const $result = $('#smtp-test-result');

        $btn.prop('disabled', true);
        $result.html('<i class="fas fa-spinner fa-spin"></i> <?php _e('Testando...', 'crm-developer'); ?>');

        $.post(crmDevAdmin.ajaxUrl, {
            action: 'crm_dev_test_smtp',
            nonce: crmDevAdmin.nonce,
            smtp_host: $('#smtp_host').val(),
            smtp_port: $('#smtp_port').val(),
            smtp_secure: $('#smtp_secure').val(),
            smtp_user: $('#smtp_user').val(),
            smtp_pass: $('#smtp_pass').val()
        }, function(response) {
            $btn.prop('disabled', false);
            if (response.success) {
                $result.html('<span class="text-success"><i class="fas fa-check"></i> <?php _e('Conexão OK!', 'crm-developer'); ?></span>');
            } else {
                $result.html('<span class="text-danger"><i class="fas fa-times"></i> ' + (response.data.message || '<?php _e('Erro na conexão', 'crm-developer'); ?>') + '</span>');
            }
        });
    });

    // Salvar configurações
    $('#email-settings-form').on('submit', function(e) {
        e.preventDefault();

        const $btn = $(this).find('button[type="submit"]');
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> <?php _e('Salvando...', 'crm-developer'); ?>');

        $.post(crmDevAdmin.ajaxUrl, {
            action: 'crm_dev_save_email_settings',
            nonce: crmDevAdmin.nonce,
            settings: $(this).serialize()
        }, function(response) {
            $btn.prop('disabled', false).html('<i class="fas fa-save"></i> <?php _e('Salvar Configurações', 'crm-developer'); ?>');

            if (response.success) {
                alert('<?php _e('Configurações salvas com sucesso!', 'crm-developer'); ?>');
            } else {
                alert(response.data.message || '<?php _e('Erro ao salvar configurações.', 'crm-developer'); ?>');
            }
        });
    });

    // ========================================
    // MODAIS
    // ========================================

    // Fechar modais
    $('.modal-close, .modal-close-btn').on('click', function() {
        $(this).closest('.crm-dev-modal').removeClass('show');
    });

    $('.crm-dev-modal').on('click', function(e) {
        if (e.target === this) {
            $(this).removeClass('show');
        }
    });

    // ESC para fechar modal
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            $('.crm-dev-modal.show').removeClass('show');
        }
    });
});
</script>

<style>
/* Estilos específicos para Email */
.crm-dev-tabs {
    display: flex;
    gap: 0;
    margin-bottom: 24px;
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.crm-dev-tabs .tab-item {
    padding: 16px 24px;
    text-decoration: none;
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 8px;
    border-bottom: 3px solid transparent;
    transition: all 0.2s;
}

.crm-dev-tabs .tab-item:hover {
    background: #f1f5f9;
    color: #059669;
}

.crm-dev-tabs .tab-item.active {
    color: #059669;
    border-bottom-color: #059669;
    background: #f0fdf4;
}

.crm-dev-row {
    display: flex;
    gap: 24px;
    flex-wrap: wrap;
}

.crm-dev-col-4 {
    flex: 0 0 calc(33.333% - 16px);
}

.crm-dev-col-6 {
    flex: 0 0 calc(50% - 12px);
}

.crm-dev-col-8 {
    flex: 0 0 calc(66.666% - 8px);
}

@media (max-width: 1024px) {
    .crm-dev-col-4,
    .crm-dev-col-6,
    .crm-dev-col-8 {
        flex: 0 0 100%;
    }
}

/* Editor de Email */
.editor-toolbar {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 8px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-bottom: none;
    border-radius: 8px 8px 0 0;
}

.btn-toolbar {
    padding: 8px 12px;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    cursor: pointer;
    color: #64748b;
    transition: all 0.2s;
}

.btn-toolbar:hover {
    background: #059669;
    color: white;
    border-color: #059669;
}

.toolbar-divider {
    width: 1px;
    height: 24px;
    background: #e2e8f0;
    margin: 0 8px;
}

.dropdown-toolbar {
    position: relative;
}

.dropdown-menu {
    display: none;
    position: absolute;
    top: 100%;
    left: 0;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    z-index: 100;
    min-width: 200px;
    margin-top: 4px;
}

.dropdown-menu.show {
    display: block;
}

.dropdown-menu a {
    display: block;
    padding: 10px 16px;
    color: #334155;
    text-decoration: none;
    transition: background 0.2s;
}

.dropdown-menu a:hover {
    background: #f0fdf4;
    color: #059669;
}

.content-editor {
    min-height: 300px;
    padding: 16px;
    border: 1px solid #e2e8f0;
    border-radius: 0 0 8px 8px;
    background: white;
    outline: none;
}

.content-editor:focus {
    border-color: #059669;
    box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
}

/* Contagem de destinatários */
.recipients-count {
    text-align: center;
    padding: 20px;
    background: #f0fdf4;
    border-radius: 8px;
    margin: 16px 0;
}

.count-box span {
    display: block;
}

.count-box #recipients-count {
    font-size: 48px;
    font-weight: 700;
    color: #059669;
}

.count-box label {
    color: #64748b;
    font-size: 14px;
}

/* Info list */
.info-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.info-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
}

.info-item i {
    color: #059669;
    width: 20px;
}

.info-item span {
    color: #64748b;
}

.info-item strong {
    color: #334155;
    margin-left: auto;
}

.info-note {
    margin-top: 16px;
    padding: 12px;
    background: #fef3c7;
    border-radius: 8px;
    font-size: 13px;
    color: #92400e;
}

.info-note i {
    margin-right: 8px;
}

/* Variáveis */
.variables-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.variable-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.variable-item code {
    background: #f0fdf4;
    color: #059669;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 13px;
    display: inline-block;
}

.variable-item span {
    color: #64748b;
    font-size: 13px;
}

/* Queue stats */
.queue-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
}

.queue-stat {
    text-align: center;
    padding: 16px;
    background: #f8fafc;
    border-radius: 8px;
}

.queue-stat .value {
    display: block;
    font-size: 24px;
    font-weight: 700;
    color: #334155;
}

.queue-stat .label {
    color: #64748b;
    font-size: 12px;
}

/* Status badges */
.status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.status-pending, .status-queued {
    background: #fef3c7;
    color: #92400e;
}

.status-processing, .status-sending {
    background: #dbeafe;
    color: #1e40af;
}

.status-sent, .status-completed {
    background: #dcfce7;
    color: #166534;
}

.status-failed {
    background: #fee2e2;
    color: #991b1b;
}

.status-opened {
    background: #e0e7ff;
    color: #3730a3;
}

/* Form actions fixed */
.form-actions-fixed {
    position: sticky;
    bottom: 0;
    background: white;
    padding: 20px;
    margin: 24px -24px -24px;
    border-top: 1px solid #e2e8f0;
    text-align: right;
}

/* SMTP form */
.form-row {
    display: flex;
    gap: 16px;
}

.form-row .col-6 {
    flex: 1;
}

#smtp-test-result {
    margin-left: 16px;
}

.text-success {
    color: #059669;
}

.text-danger {
    color: #dc2626;
}

/* Modal */
.crm-dev-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 100000;
    align-items: center;
    justify-content: center;
}

.crm-dev-modal.show {
    display: flex;
}

.modal-content {
    background: white;
    border-radius: 12px;
    width: 100%;
    max-width: 600px;
    max-height: 90vh;
    overflow: auto;
    box-shadow: 0 20px 40px rgba(0,0,0,0.2);
}

.modal-content.modal-lg {
    max-width: 900px;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid #e2e8f0;
}

.modal-header h3 {
    margin: 0;
    font-size: 18px;
    color: #334155;
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #64748b;
    padding: 0;
    line-height: 1;
}

.modal-close:hover {
    color: #334155;
}

.modal-body {
    padding: 24px;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding: 16px 24px;
    border-top: 1px solid #e2e8f0;
    background: #f8fafc;
}

/* Email preview */
#email-preview-content {
    background: #f8fafc;
    padding: 24px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

/* Header actions */
.header-actions {
    display: flex;
    gap: 8px;
    align-items: center;
}

.form-control-sm {
    padding: 6px 12px;
    font-size: 14px;
}

/* Loading */
.loading {
    text-align: center;
    padding: 40px;
    color: #64748b;
}

.loading i {
    margin-right: 8px;
}
</style>
