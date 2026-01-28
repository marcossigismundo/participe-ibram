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
        <div class="header-title-row">
            <div>
                <h1>
                    <i class="fas fa-envelope"></i>
                    <?php _e('Comunicação por Email', 'crm-developer'); ?>
                </h1>
                <p class="crm-dev-subtitle"><?php _e('Envie emails personalizados para seus contatos', 'crm-developer'); ?></p>
            </div>
            <button type="button" class="btn-help-floating" id="btn-help-<?php echo esc_attr($tab); ?>" title="<?php _e('Ajuda', 'crm-developer'); ?>">
                <i class="fas fa-question-circle"></i>
            </button>
        </div>
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
                                                        data-assunto="<?php echo esc_attr($template['assunto'] ?? ''); ?>"
                                                        data-conteudo="<?php echo esc_attr($template['conteudo'] ?? ''); ?>">
                                                    <?php echo esc_html($template['nome'] ?? ''); ?>
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
                                                    <td><strong><?php echo esc_html($template['nome'] ?? ''); ?></strong></td>
                                                    <td><?php echo esc_html($template['assunto'] ?? ''); ?></td>
                                                    <td><?php echo CRM_Dev_Helpers::format_datetime($template['created_at'], 'd/m/Y'); ?></td>
                                                    <td>
                                                        <button type="button" class="btn-icon btn-edit-template"
                                                                data-id="<?php echo esc_attr($template['id']); ?>"
                                                                data-nome="<?php echo esc_attr($template['nome'] ?? ''); ?>"
                                                                data-assunto="<?php echo esc_attr($template['assunto'] ?? ''); ?>"
                                                                data-conteudo="<?php echo esc_attr($template['conteudo'] ?? ''); ?>"
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
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h3 id="modal-template-title"><?php _e('Novo Template', 'crm-developer'); ?></h3>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div class="template-modal-grid">
                <div class="template-form-area">
                    <form id="template-form">
                        <input type="hidden" name="template_id" id="template-id">

                        <div class="form-group">
                            <label for="template-nome"><?php _e('Nome do Template', 'crm-developer'); ?> *</label>
                            <input type="text" id="template-nome" name="nome" class="form-control" required
                                   placeholder="<?php _e('Ex: Boas-vindas, Newsletter, Convite...', 'crm-developer'); ?>">
                        </div>

                        <div class="form-group">
                            <label for="template-assunto"><?php _e('Assunto do Email', 'crm-developer'); ?> *</label>
                            <input type="text" id="template-assunto" name="assunto" class="form-control" required
                                   placeholder="<?php _e('Ex: Olá {{primeiro_nome}}, temos novidades!', 'crm-developer'); ?>">
                        </div>

                        <div class="form-group">
                            <label for="template-conteudo"><?php _e('Conteúdo', 'crm-developer'); ?> *</label>
                            <textarea id="template-conteudo" name="conteudo" class="form-control" rows="12" required
                                      placeholder="<?php _e('Digite o conteúdo do email. Use as variáveis ao lado para personalizar...', 'crm-developer'); ?>"></textarea>
                        </div>
                    </form>
                </div>
                <div class="template-vars-area">
                    <div class="vars-panel">
                        <h4><i class="fas fa-code"></i> <?php _e('Variáveis Disponíveis', 'crm-developer'); ?></h4>
                        <p class="vars-help"><?php _e('Clique para inserir no conteúdo:', 'crm-developer'); ?></p>
                        <div class="vars-buttons">
                            <button type="button" class="var-btn" data-var="{{nome}}" title="<?php _e('Nome completo', 'crm-developer'); ?>">
                                <i class="fas fa-user"></i> {{nome}}
                            </button>
                            <button type="button" class="var-btn" data-var="{{primeiro_nome}}" title="<?php _e('Primeiro nome', 'crm-developer'); ?>">
                                <i class="fas fa-user-tag"></i> {{primeiro_nome}}
                            </button>
                            <button type="button" class="var-btn" data-var="{{email}}" title="<?php _e('Email', 'crm-developer'); ?>">
                                <i class="fas fa-at"></i> {{email}}
                            </button>
                            <button type="button" class="var-btn" data-var="{{estado}}" title="<?php _e('Estado', 'crm-developer'); ?>">
                                <i class="fas fa-map-marker-alt"></i> {{estado}}
                            </button>
                            <button type="button" class="var-btn" data-var="{{municipio}}" title="<?php _e('Município', 'crm-developer'); ?>">
                                <i class="fas fa-city"></i> {{municipio}}
                            </button>
                            <button type="button" class="var-btn" data-var="{{regiao}}" title="<?php _e('Região', 'crm-developer'); ?>">
                                <i class="fas fa-globe-americas"></i> {{regiao}}
                            </button>
                            <button type="button" class="var-btn" data-var="{{data_cadastro}}" title="<?php _e('Data de cadastro', 'crm-developer'); ?>">
                                <i class="fas fa-calendar"></i> {{data_cadastro}}
                            </button>
                            <button type="button" class="var-btn" data-var="{{link_descadastro}}" title="<?php _e('Link descadastro', 'crm-developer'); ?>">
                                <i class="fas fa-unlink"></i> {{link_descadastro}}
                            </button>
                        </div>
                        <div class="vars-tip">
                            <i class="fas fa-lightbulb"></i>
                            <span><?php _e('As variáveis serão substituídas pelos dados de cada contato no momento do envio.', 'crm-developer'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary modal-close-btn"><?php _e('Cancelar', 'crm-developer'); ?></button>
            <button type="button" id="btn-save-template-modal" class="btn btn-primary">
                <i class="fas fa-save"></i> <?php _e('Salvar Template', 'crm-developer'); ?>
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

<!-- Modal de Ajuda - Enviar Email -->
<div id="modal-help-send" class="crm-dev-modal help-modal">
    <div class="modal-content">
        <div class="modal-header help-header">
            <div class="help-header-icon">
                <i class="fas fa-paper-plane"></i>
            </div>
            <h3><?php _e('Enviar Email', 'crm-developer'); ?></h3>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <div class="modal-body help-body">
            <div class="help-section">
                <div class="help-icon"><i class="fas fa-edit"></i></div>
                <div class="help-content">
                    <h4><?php _e('Compor Email', 'crm-developer'); ?></h4>
                    <p><?php _e('Crie emails personalizados usando templates prontos ou escrevendo do zero. Use o editor visual para formatar seu texto com negrito, itálico, listas e links.', 'crm-developer'); ?></p>
                </div>
            </div>
            <div class="help-section">
                <div class="help-icon"><i class="fas fa-code"></i></div>
                <div class="help-content">
                    <h4><?php _e('Variáveis Personalizadas', 'crm-developer'); ?></h4>
                    <p><?php _e('Insira variáveis como {{nome}}, {{primeiro_nome}} ou {{email}} para personalizar cada mensagem automaticamente. O sistema substituirá pelos dados de cada contato.', 'crm-developer'); ?></p>
                </div>
            </div>
            <div class="help-section">
                <div class="help-icon"><i class="fas fa-filter"></i></div>
                <div class="help-content">
                    <h4><?php _e('Filtrar Destinatários', 'crm-developer'); ?></h4>
                    <p><?php _e('Selecione os contatos por região, estado, nível de engajamento ou status. O sistema mostra quantos destinatários serão atingidos em tempo real.', 'crm-developer'); ?></p>
                </div>
            </div>
            <div class="help-section">
                <div class="help-icon"><i class="fas fa-shield-alt"></i></div>
                <div class="help-content">
                    <h4><?php _e('Conformidade LGPD', 'crm-developer'); ?></h4>
                    <p><?php _e('Por padrão, apenas contatos com consentimento recebem emails. Um link de descadastro é incluído automaticamente em todas as mensagens.', 'crm-developer'); ?></p>
                </div>
            </div>
        </div>
        <div class="modal-footer help-footer">
            <button type="button" class="btn btn-primary modal-close-btn"><?php _e('Entendi!', 'crm-developer'); ?></button>
        </div>
    </div>
</div>

<!-- Modal de Ajuda - Templates -->
<div id="modal-help-templates" class="crm-dev-modal help-modal">
    <div class="modal-content">
        <div class="modal-header help-header">
            <div class="help-header-icon">
                <i class="fas fa-file-alt"></i>
            </div>
            <h3><?php _e('Templates de Email', 'crm-developer'); ?></h3>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <div class="modal-body help-body">
            <div class="help-section">
                <div class="help-icon"><i class="fas fa-save"></i></div>
                <div class="help-content">
                    <h4><?php _e('O que são Templates?', 'crm-developer'); ?></h4>
                    <p><?php _e('Templates são modelos de email pré-formatados que você pode reutilizar. Crie uma vez e use sempre que precisar enviar mensagens similares.', 'crm-developer'); ?></p>
                </div>
            </div>
            <div class="help-section">
                <div class="help-icon"><i class="fas fa-plus-circle"></i></div>
                <div class="help-content">
                    <h4><?php _e('Criar Templates', 'crm-developer'); ?></h4>
                    <p><?php _e('Clique em "Novo Template" para criar um modelo. Dê um nome descritivo, defina o assunto e escreva o conteúdo com as variáveis desejadas.', 'crm-developer'); ?></p>
                </div>
            </div>
            <div class="help-section">
                <div class="help-icon"><i class="fas fa-magic"></i></div>
                <div class="help-content">
                    <h4><?php _e('Dicas de Uso', 'crm-developer'); ?></h4>
                    <p><?php _e('Use {{primeiro_nome}} para saudações pessoais. Mantenha templates organizados por tipo: boas-vindas, newsletters, convites, etc.', 'crm-developer'); ?></p>
                </div>
            </div>
        </div>
        <div class="modal-footer help-footer">
            <button type="button" class="btn btn-primary modal-close-btn"><?php _e('Entendi!', 'crm-developer'); ?></button>
        </div>
    </div>
</div>

<!-- Modal de Ajuda - Campanhas -->
<div id="modal-help-campaigns" class="crm-dev-modal help-modal">
    <div class="modal-content">
        <div class="modal-header help-header">
            <div class="help-header-icon">
                <i class="fas fa-bullhorn"></i>
            </div>
            <h3><?php _e('Campanhas de Email', 'crm-developer'); ?></h3>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <div class="modal-body help-body">
            <div class="help-section">
                <div class="help-icon"><i class="fas fa-list-alt"></i></div>
                <div class="help-content">
                    <h4><?php _e('Acompanhar Campanhas', 'crm-developer'); ?></h4>
                    <p><?php _e('Visualize todas as campanhas de email criadas, com estatísticas de envio, quantidade de destinatários e status atual.', 'crm-developer'); ?></p>
                </div>
            </div>
            <div class="help-section">
                <div class="help-icon"><i class="fas fa-clock"></i></div>
                <div class="help-content">
                    <h4><?php _e('Fila de Envio', 'crm-developer'); ?></h4>
                    <p><?php _e('Os emails são enviados em lotes para evitar bloqueios de spam. Acompanhe em tempo real quantos estão pendentes, processando, enviados ou com falha.', 'crm-developer'); ?></p>
                </div>
            </div>
            <div class="help-section">
                <div class="help-icon"><i class="fas fa-tachometer-alt"></i></div>
                <div class="help-content">
                    <h4><?php _e('Limites de Envio', 'crm-developer'); ?></h4>
                    <p><?php _e('O sistema respeita limites configuráveis por hora para garantir boa reputação do seu servidor e evitar que emails caiam em spam.', 'crm-developer'); ?></p>
                </div>
            </div>
        </div>
        <div class="modal-footer help-footer">
            <button type="button" class="btn btn-primary modal-close-btn"><?php _e('Entendi!', 'crm-developer'); ?></button>
        </div>
    </div>
</div>

<!-- Modal de Ajuda - Histórico -->
<div id="modal-help-logs" class="crm-dev-modal help-modal">
    <div class="modal-content">
        <div class="modal-header help-header">
            <div class="help-header-icon">
                <i class="fas fa-history"></i>
            </div>
            <h3><?php _e('Histórico de Emails', 'crm-developer'); ?></h3>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <div class="modal-body help-body">
            <div class="help-section">
                <div class="help-icon"><i class="fas fa-search"></i></div>
                <div class="help-content">
                    <h4><?php _e('Rastrear Envios', 'crm-developer'); ?></h4>
                    <p><?php _e('Consulte o histórico completo de todos os emails enviados, incluindo destinatário, assunto, data e status de entrega.', 'crm-developer'); ?></p>
                </div>
            </div>
            <div class="help-section">
                <div class="help-icon"><i class="fas fa-filter"></i></div>
                <div class="help-content">
                    <h4><?php _e('Filtrar por Status', 'crm-developer'); ?></h4>
                    <p><?php _e('Use os filtros para visualizar apenas emails enviados com sucesso, falhas de envio ou mensagens abertas pelo destinatário.', 'crm-developer'); ?></p>
                </div>
            </div>
            <div class="help-section">
                <div class="help-icon"><i class="fas fa-bug"></i></div>
                <div class="help-content">
                    <h4><?php _e('Identificar Problemas', 'crm-developer'); ?></h4>
                    <p><?php _e('Analise as falhas de envio para identificar emails inválidos ou problemas de configuração do servidor.', 'crm-developer'); ?></p>
                </div>
            </div>
        </div>
        <div class="modal-footer help-footer">
            <button type="button" class="btn btn-primary modal-close-btn"><?php _e('Entendi!', 'crm-developer'); ?></button>
        </div>
    </div>
</div>

<!-- Modal de Ajuda - Configurações -->
<div id="modal-help-settings" class="crm-dev-modal help-modal">
    <div class="modal-content">
        <div class="modal-header help-header">
            <div class="help-header-icon">
                <i class="fas fa-cog"></i>
            </div>
            <h3><?php _e('Configurações de Email', 'crm-developer'); ?></h3>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <div class="modal-body help-body">
            <div class="help-section">
                <div class="help-icon"><i class="fas fa-user"></i></div>
                <div class="help-content">
                    <h4><?php _e('Remetente', 'crm-developer'); ?></h4>
                    <p><?php _e('Defina o nome e email que aparecerão como remetente das mensagens. Use um email válido do seu domínio para melhor entregabilidade.', 'crm-developer'); ?></p>
                </div>
            </div>
            <div class="help-section">
                <div class="help-icon"><i class="fas fa-server"></i></div>
                <div class="help-content">
                    <h4><?php _e('Configuração SMTP', 'crm-developer'); ?></h4>
                    <p><?php _e('Para melhor confiabilidade, configure um servidor SMTP externo (Gmail, SendGrid, etc). Isso melhora a taxa de entrega e evita bloqueios.', 'crm-developer'); ?></p>
                </div>
            </div>
            <div class="help-section">
                <div class="help-icon"><i class="fas fa-tachometer-alt"></i></div>
                <div class="help-content">
                    <h4><?php _e('Limites de Envio', 'crm-developer'); ?></h4>
                    <p><?php _e('Configure a quantidade máxima de emails por hora e o tamanho dos lotes. Valores menores são mais seguros, valores maiores são mais rápidos.', 'crm-developer'); ?></p>
                </div>
            </div>
            <div class="help-section">
                <div class="help-icon"><i class="fas fa-signature"></i></div>
                <div class="help-content">
                    <h4><?php _e('Rodapé Padrão', 'crm-developer'); ?></h4>
                    <p><?php _e('Adicione informações de contato, redes sociais ou avisos legais que serão incluídos automaticamente em todos os emails.', 'crm-developer'); ?></p>
                </div>
            </div>
        </div>
        <div class="modal-footer help-footer">
            <button type="button" class="btn btn-primary modal-close-btn"><?php _e('Entendi!', 'crm-developer'); ?></button>
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
            $('#email-subject').val(selected.data('assunto'));
            $('#email-content').html(selected.data('conteudo'));
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
        $('#template-nome').val('');
        $('#template-assunto').val('');
        $('#template-conteudo').val('');
        $('#modal-template').addClass('show');
    });

    // Inserir variável no campo de conteúdo do template
    $(document).on('click', '.var-btn', function() {
        const variable = $(this).data('var');
        const $textarea = $('#template-conteudo');
        const cursorPos = $textarea[0].selectionStart;
        const textBefore = $textarea.val().substring(0, cursorPos);
        const textAfter = $textarea.val().substring(cursorPos);
        $textarea.val(textBefore + variable + textAfter);
        $textarea.focus();
        $textarea[0].setSelectionRange(cursorPos + variable.length, cursorPos + variable.length);
    });

    // Editar template
    $(document).on('click', '.btn-edit-template', function() {
        const $btn = $(this);
        $('#modal-template-title').text('<?php _e('Editar Template', 'crm-developer'); ?>');
        $('#template-id').val($btn.data('id'));
        $('#template-nome').val($btn.data('nome'));
        $('#template-assunto').val($btn.data('assunto'));
        $('#template-conteudo').val($btn.data('conteudo'));
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
            data: {
                id: $('#template-id').val(),
                nome: $('#template-nome').val(),
                assunto: $('#template-assunto').val(),
                conteudo: $('#template-conteudo').val()
            }
        }, function(response) {
            $btn.prop('disabled', false).html('<i class="fas fa-save"></i> <?php _e('Salvar Template', 'crm-developer'); ?>');

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
        const assunto = $('#email-subject').val();
        const conteudo = $('#email-content').html();

        if (!assunto || !conteudo) {
            alert('<?php _e('Preencha o assunto e conteúdo antes de salvar.', 'crm-developer'); ?>');
            return;
        }

        const nome = prompt('<?php _e('Digite o nome do template:', 'crm-developer'); ?>');
        if (!nome) return;

        $.post(crmDevAdmin.ajaxUrl, {
            action: 'crm_dev_save_email_template',
            nonce: crmDevAdmin.nonce,
            data: {
                nome: nome,
                assunto: assunto,
                conteudo: conteudo
            }
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

    // ========================================
    // MODAIS DE AJUDA
    // ========================================

    // Abrir modal de ajuda correspondente à aba atual
    $('[id^="btn-help-"]').on('click', function() {
        const tab = $(this).attr('id').replace('btn-help-', '');
        $('#modal-help-' + tab).addClass('show');
    });
});
</script>

<style>
/* ========================================
   ESTILOS DE BOTÕES - DESIGN MODERNO
   ======================================== */

/* Header com botão de ajuda */
.header-title-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
}

/* Botão de ajuda flutuante */
.btn-help-floating {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: linear-gradient(135deg, #059669 0%, #10b981 100%);
    border: none;
    color: white;
    font-size: 20px;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(5, 150, 105, 0.4);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.btn-help-floating:hover {
    transform: translateY(-3px) scale(1.05);
    box-shadow: 0 8px 25px rgba(5, 150, 105, 0.5);
}

.btn-help-floating:active {
    transform: translateY(-1px) scale(1.02);
}

/* Botões Base */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 24px;
    font-size: 14px;
    font-weight: 600;
    border-radius: 10px;
    border: none;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    text-decoration: none;
    white-space: nowrap;
    position: relative;
    overflow: hidden;
}

.btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(180deg, rgba(255,255,255,0.15) 0%, transparent 50%);
    pointer-events: none;
}

/* Botão Primário */
.btn-primary {
    background: linear-gradient(135deg, #059669 0%, #10b981 100%);
    color: white;
    box-shadow: 0 4px 15px rgba(5, 150, 105, 0.35);
}

.btn-primary:hover {
    background: linear-gradient(135deg, #047857 0%, #059669 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(5, 150, 105, 0.45);
    color: white;
}

.btn-primary:active {
    transform: translateY(0);
    box-shadow: 0 2px 10px rgba(5, 150, 105, 0.35);
}

/* Botão Secundário */
.btn-secondary {
    background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
    color: #475569;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.btn-secondary:hover {
    background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
    color: #334155;
}

.btn-secondary:active {
    transform: translateY(0);
}

/* Botão Outline */
.btn-outline {
    background: transparent;
    color: #059669;
    border: 2px solid #059669;
    box-shadow: none;
}

.btn-outline::before {
    display: none;
}

.btn-outline:hover {
    background: #059669;
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(5, 150, 105, 0.35);
}

/* Botão Block */
.btn-block {
    width: 100%;
}

/* Botão Large */
.btn-lg {
    padding: 16px 32px;
    font-size: 16px;
    border-radius: 12px;
}

/* Botão Small */
.btn-sm {
    padding: 8px 16px;
    font-size: 13px;
    border-radius: 8px;
}

/* Botão Ícone */
.btn-icon {
    width: 40px;
    height: 40px;
    padding: 0;
    border-radius: 10px;
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border: 1px solid #e2e8f0;
    color: #64748b;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.btn-icon:hover {
    background: linear-gradient(135deg, #059669 0%, #10b981 100%);
    color: white;
    border-color: transparent;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
}

.btn-icon.btn-delete-template:hover {
    background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
}

/* Botão desabilitado */
.btn:disabled,
.btn.disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none !important;
    box-shadow: none !important;
}

/* ========================================
   ESTILOS DE TABS
   ======================================== */

.crm-dev-tabs {
    display: flex;
    gap: 0;
    margin-bottom: 24px;
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
}

.crm-dev-tabs .tab-item {
    padding: 18px 28px;
    text-decoration: none;
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 10px;
    border-bottom: 3px solid transparent;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    font-weight: 500;
}

.crm-dev-tabs .tab-item:hover {
    background: linear-gradient(180deg, #f0fdf4 0%, #ecfdf5 100%);
    color: #059669;
}

.crm-dev-tabs .tab-item.active {
    color: #059669;
    border-bottom-color: #059669;
    background: linear-gradient(180deg, #f0fdf4 0%, white 100%);
}

.crm-dev-tabs .tab-item i {
    font-size: 16px;
}

/* ========================================
   LAYOUT
   ======================================== */

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

/* ========================================
   EDITOR DE EMAIL
   ======================================== */

.editor-toolbar {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 12px;
    background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
    border: 1px solid #e2e8f0;
    border-bottom: none;
    border-radius: 12px 12px 0 0;
}

.btn-toolbar {
    padding: 10px 14px;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    cursor: pointer;
    color: #64748b;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    font-size: 14px;
}

.btn-toolbar:hover {
    background: linear-gradient(135deg, #059669 0%, #10b981 100%);
    color: white;
    border-color: transparent;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(5, 150, 105, 0.3);
}

.toolbar-divider {
    width: 1px;
    height: 28px;
    background: linear-gradient(180deg, transparent, #cbd5e1, transparent);
    margin: 0 10px;
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
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
    z-index: 100;
    min-width: 220px;
    margin-top: 8px;
    overflow: hidden;
}

.dropdown-menu.show {
    display: block;
    animation: dropdownFadeIn 0.2s ease-out;
}

@keyframes dropdownFadeIn {
    from { opacity: 0; transform: translateY(-8px); }
    to { opacity: 1; transform: translateY(0); }
}

.dropdown-menu a {
    display: block;
    padding: 12px 18px;
    color: #334155;
    text-decoration: none;
    transition: all 0.2s;
    border-bottom: 1px solid #f1f5f9;
}

.dropdown-menu a:last-child {
    border-bottom: none;
}

.dropdown-menu a:hover {
    background: linear-gradient(90deg, #f0fdf4 0%, white 100%);
    color: #059669;
    padding-left: 22px;
}

.content-editor {
    min-height: 300px;
    padding: 20px;
    border: 1px solid #e2e8f0;
    border-radius: 0 0 12px 12px;
    background: white;
    outline: none;
    transition: all 0.3s;
}

.content-editor:focus {
    border-color: #059669;
    box-shadow: 0 0 0 4px rgba(5, 150, 105, 0.1);
}

/* ========================================
   CONTAGEM DE DESTINATÁRIOS
   ======================================== */

.recipients-count {
    text-align: center;
    padding: 24px;
    background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%);
    border-radius: 16px;
    margin: 20px 0;
    border: 1px solid #d1fae5;
}

.count-box span {
    display: block;
}

.count-box #recipients-count {
    font-size: 56px;
    font-weight: 800;
    background: linear-gradient(135deg, #059669 0%, #10b981 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    line-height: 1.1;
}

.count-box label {
    color: #64748b;
    font-size: 14px;
    font-weight: 500;
    margin-top: 4px;
}

/* ========================================
   INFO LIST
   ======================================== */

.info-list {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.info-item {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 14px;
    padding: 8px 0;
    border-bottom: 1px solid #f1f5f9;
}

.info-item:last-child {
    border-bottom: none;
}

.info-item i {
    color: #059669;
    width: 20px;
    text-align: center;
}

.info-item span {
    color: #64748b;
}

.info-item strong {
    color: #334155;
    margin-left: auto;
    font-weight: 600;
}

.info-note {
    margin-top: 20px;
    padding: 14px 16px;
    background: linear-gradient(135deg, #fef3c7 0%, #fef9c3 100%);
    border-radius: 12px;
    font-size: 13px;
    color: #92400e;
    border: 1px solid #fde68a;
}

.info-note i {
    margin-right: 10px;
}

/* ========================================
   VARIÁVEIS
   ======================================== */

.variables-list {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.variable-item {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 10px 0;
    border-bottom: 1px solid #f1f5f9;
}

.variable-item:last-child {
    border-bottom: none;
}

.variable-item code {
    background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%);
    color: #059669;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 13px;
    display: inline-block;
    font-weight: 600;
    border: 1px solid #d1fae5;
}

.variable-item span {
    color: #64748b;
    font-size: 13px;
}

/* ========================================
   QUEUE STATS
   ======================================== */

.queue-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
}

.queue-stat {
    text-align: center;
    padding: 20px 16px;
    background: linear-gradient(180deg, #f8fafc 0%, white 100%);
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    transition: all 0.3s;
}

.queue-stat:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.queue-stat .value {
    display: block;
    font-size: 28px;
    font-weight: 700;
    color: #334155;
}

.queue-stat .label {
    color: #64748b;
    font-size: 12px;
    font-weight: 500;
    margin-top: 4px;
}

/* ========================================
   STATUS BADGES
   ======================================== */

.status-badge {
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.status-pending, .status-queued {
    background: linear-gradient(135deg, #fef3c7 0%, #fef9c3 100%);
    color: #92400e;
    border: 1px solid #fde68a;
}

.status-processing, .status-sending {
    background: linear-gradient(135deg, #dbeafe 0%, #e0e7ff 100%);
    color: #1e40af;
    border: 1px solid #bfdbfe;
}

.status-sent, .status-completed {
    background: linear-gradient(135deg, #dcfce7 0%, #d1fae5 100%);
    color: #166534;
    border: 1px solid #bbf7d0;
}

.status-failed {
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    color: #991b1b;
    border: 1px solid #fecaca;
}

.status-opened {
    background: linear-gradient(135deg, #e0e7ff 0%, #ddd6fe 100%);
    color: #3730a3;
    border: 1px solid #c7d2fe;
}

/* ========================================
   FORM ACTIONS FIXED
   ======================================== */

.form-actions-fixed {
    position: sticky;
    bottom: 0;
    background: linear-gradient(180deg, rgba(255,255,255,0.95) 0%, white 100%);
    padding: 20px 24px;
    margin: 24px -24px -24px;
    border-top: 1px solid #e2e8f0;
    text-align: right;
    backdrop-filter: blur(8px);
}

.form-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    flex-wrap: wrap;
}

/* ========================================
   SMTP FORM
   ======================================== */

.form-row {
    display: flex;
    gap: 16px;
}

.form-row .col-6 {
    flex: 1;
}

#smtp-test-result {
    margin-left: 16px;
    font-weight: 500;
}

.text-success {
    color: #059669;
}

.text-danger {
    color: #dc2626;
}

/* ========================================
   MODAIS
   ======================================== */

.crm-dev-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(4px);
    z-index: 100000;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.crm-dev-modal.show {
    display: flex;
    animation: modalFadeIn 0.3s ease-out;
}

@keyframes modalFadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.crm-dev-modal.show .modal-content {
    animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
    from { opacity: 0; transform: translateY(-20px) scale(0.95); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

.modal-content {
    background: white;
    border-radius: 20px;
    width: 100%;
    max-width: 600px;
    max-height: 90vh;
    overflow: auto;
    box-shadow: 0 25px 60px rgba(0, 0, 0, 0.25);
}

.modal-content.modal-lg {
    max-width: 900px;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 24px 28px;
    border-bottom: 1px solid #e2e8f0;
}

.modal-header h3 {
    margin: 0;
    font-size: 20px;
    font-weight: 700;
    color: #1e293b;
}

.modal-close {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f1f5f9;
    border: none;
    border-radius: 10px;
    font-size: 20px;
    cursor: pointer;
    color: #64748b;
    transition: all 0.2s;
}

.modal-close:hover {
    background: #fee2e2;
    color: #dc2626;
}

.modal-body {
    padding: 28px;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding: 20px 28px;
    border-top: 1px solid #e2e8f0;
    background: linear-gradient(180deg, #f8fafc 0%, white 100%);
    border-radius: 0 0 20px 20px;
}

/* ========================================
   MODAIS DE AJUDA - DESIGN MODERNO
   ======================================== */

.help-modal .modal-content {
    max-width: 560px;
}

.help-header {
    background: linear-gradient(135deg, #059669 0%, #10b981 100%);
    border-bottom: none;
    border-radius: 20px 20px 0 0;
    padding: 28px;
    position: relative;
}

.help-header-icon {
    width: 60px;
    height: 60px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    color: white;
    margin-bottom: 16px;
    backdrop-filter: blur(8px);
}

.help-header h3 {
    color: white;
    font-size: 24px;
    font-weight: 700;
}

.help-header .modal-close {
    position: absolute;
    top: 20px;
    right: 20px;
    background: rgba(255, 255, 255, 0.2);
    color: white;
    backdrop-filter: blur(8px);
}

.help-header .modal-close:hover {
    background: rgba(255, 255, 255, 0.3);
    color: white;
}

.help-body {
    padding: 28px;
}

.help-section {
    display: flex;
    gap: 16px;
    padding: 20px 0;
    border-bottom: 1px solid #f1f5f9;
}

.help-section:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.help-section:first-child {
    padding-top: 0;
}

.help-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #059669;
    font-size: 20px;
    flex-shrink: 0;
    border: 1px solid #d1fae5;
}

.help-content h4 {
    margin: 0 0 8px 0;
    font-size: 16px;
    font-weight: 600;
    color: #1e293b;
}

.help-content p {
    margin: 0;
    font-size: 14px;
    color: #64748b;
    line-height: 1.6;
}

.help-footer {
    background: linear-gradient(180deg, #f0fdf4 0%, white 100%);
    justify-content: center;
    padding: 24px;
}

.help-footer .btn-primary {
    padding: 14px 40px;
    font-size: 15px;
}

/* ========================================
   EMAIL PREVIEW
   ======================================== */

#email-preview-content {
    background: linear-gradient(180deg, #f8fafc 0%, white 100%);
    padding: 28px;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
}

/* ========================================
   HEADER ACTIONS
   ======================================== */

.header-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}

.form-control-sm {
    padding: 8px 14px;
    font-size: 14px;
    border-radius: 8px;
}

/* ========================================
   LOADING
   ======================================== */

.loading {
    text-align: center;
    padding: 50px;
    color: #64748b;
}

.loading i {
    margin-right: 10px;
    font-size: 18px;
}

/* ========================================
   EMPTY STATE
   ======================================== */

.crm-dev-empty {
    text-align: center;
    padding: 48px 24px;
    color: #64748b;
}

.crm-dev-empty i {
    font-size: 48px;
    color: #cbd5e1;
    margin-bottom: 16px;
    display: block;
}

.crm-dev-empty p {
    margin-bottom: 20px;
    font-size: 15px;
}

/* ========================================
   TEMPLATE MODAL STYLES
   ======================================== */

.template-modal-grid {
    display: grid;
    grid-template-columns: 1fr 280px;
    gap: 24px;
}

@media (max-width: 768px) {
    .template-modal-grid {
        grid-template-columns: 1fr;
    }
}

.template-form-area .form-group {
    margin-bottom: 20px;
}

.template-form-area textarea {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    resize: vertical;
}

.vars-panel {
    background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%);
    border: 1px solid #d1fae5;
    border-radius: 12px;
    padding: 20px;
    position: sticky;
    top: 20px;
}

.vars-panel h4 {
    margin: 0 0 8px 0;
    color: #059669;
    font-size: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.vars-help {
    color: #64748b;
    font-size: 13px;
    margin: 0 0 16px 0;
}

.vars-buttons {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.var-btn {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    background: white;
    border: 1px solid #d1fae5;
    border-radius: 8px;
    font-size: 13px;
    color: #334155;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    text-align: left;
}

.var-btn:hover {
    background: linear-gradient(135deg, #059669 0%, #10b981 100%);
    color: white;
    border-color: transparent;
    transform: translateX(4px);
    box-shadow: 0 2px 8px rgba(5, 150, 105, 0.3);
}

.var-btn i {
    width: 16px;
    text-align: center;
    opacity: 0.7;
}

.var-btn:hover i {
    opacity: 1;
}

.vars-tip {
    margin-top: 16px;
    padding: 12px;
    background: white;
    border-radius: 8px;
    display: flex;
    align-items: flex-start;
    gap: 10px;
    font-size: 12px;
    color: #64748b;
}

.vars-tip i {
    color: #f59e0b;
    margin-top: 2px;
}
</style>
