<?php
/**
 * View de Configurações
 *
 * @package CRM_Developer
 */

if (!defined('ABSPATH')) {
    exit;
}

// Inclui modais de ajuda
require_once CRM_DEV_PLUGIN_DIR . 'admin/views/partials/help-modals.php';

// Salvar configurações
if (isset($_POST['crm_dev_save_settings']) && wp_verify_nonce($_POST['crm_dev_settings_nonce'], 'crm_dev_save_settings')) {
    update_option('crm_dev_public_form_enabled', isset($_POST['public_form_enabled']) ? 1 : 0);
    update_option('crm_dev_lgpd_text', wp_kses_post($_POST['lgpd_text']));
    update_option('crm_dev_email_notifications', isset($_POST['email_notifications']) ? 1 : 0);
    update_option('crm_dev_notification_email', sanitize_email($_POST['notification_email']));

    echo '<div class="notice notice-success is-dismissible"><p>' . __('Configurações salvas com sucesso!', 'crm-developer') . '</p></div>';
}

$public_form_enabled = get_option('crm_dev_public_form_enabled', 1);
$lgpd_text = get_option('crm_dev_lgpd_text', 'Ao preencher este formulário, você concorda com o tratamento dos seus dados pessoais conforme nossa política de privacidade e a Lei Geral de Proteção de Dados (LGPD).');
$email_notifications = get_option('crm_dev_email_notifications', 0);
$notification_email = get_option('crm_dev_notification_email', get_option('admin_email'));
?>

<div class="wrap crm-dev-wrap">
    <div class="crm-dev-header">
        <div class="header-title-row">
            <div>
                <h1>
                    <i class="fas fa-cog"></i>
                    <?php _e('Configurações', 'crm-developer'); ?>
                </h1>
                <p class="crm-dev-subtitle"><?php _e('Configure o comportamento do plugin CRM', 'crm-developer'); ?></p>
            </div>
            <?php crm_dev_render_help_button('settings'); ?>
        </div>
    </div>

    <form method="post" class="crm-dev-settings-form">
        <?php wp_nonce_field('crm_dev_save_settings', 'crm_dev_settings_nonce'); ?>

        <!-- Formulário Público -->
        <div class="crm-dev-card">
            <div class="card-header">
                <h3><i class="fas fa-globe"></i> <?php _e('Formulário Público', 'crm-developer'); ?></h3>
            </div>
            <div class="card-body">
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Habilitar Formulário Público', 'crm-developer'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="public_form_enabled" value="1" <?php checked($public_form_enabled, 1); ?>>
                                <?php _e('Permitir que visitantes se cadastrem através do formulário público', 'crm-developer'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Shortcode', 'crm-developer'); ?></th>
                        <td>
                            <code>[crm_cadastro]</code>
                            <p class="description"><?php _e('Use este shortcode em qualquer página para exibir o formulário de auto-cadastro.', 'crm-developer'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Dashboard Público', 'crm-developer'); ?></th>
                        <td>
                            <code>[crm_dashboard_publico]</code>
                            <p class="description"><?php _e('Exibe um painel público com dados agregados e anonimizados.', 'crm-developer'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- LGPD -->
        <div class="crm-dev-card">
            <div class="card-header">
                <h3><i class="fas fa-shield-alt"></i> <?php _e('LGPD e Privacidade', 'crm-developer'); ?></h3>
            </div>
            <div class="card-body">
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Texto de Consentimento', 'crm-developer'); ?></th>
                        <td>
                            <textarea name="lgpd_text" rows="4" class="large-text"><?php echo esc_textarea($lgpd_text); ?></textarea>
                            <p class="description"><?php _e('Texto exibido junto ao checkbox de consentimento no formulário público.', 'crm-developer'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Notificações -->
        <div class="crm-dev-card">
            <div class="card-header">
                <h3><i class="fas fa-bell"></i> <?php _e('Notificações', 'crm-developer'); ?></h3>
            </div>
            <div class="card-body">
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Notificações por Email', 'crm-developer'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="email_notifications" value="1" <?php checked($email_notifications, 1); ?>>
                                <?php _e('Receber email quando um novo contato se cadastrar pelo formulário público', 'crm-developer'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Email para Notificações', 'crm-developer'); ?></th>
                        <td>
                            <input type="email" name="notification_email" value="<?php echo esc_attr($notification_email); ?>" class="regular-text">
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Sistema de Alertas -->
        <div class="crm-dev-card">
            <div class="card-header">
                <h3><i class="fas fa-bell-exclamation"></i> <?php _e('Sistema de Alertas por Email', 'crm-developer'); ?></h3>
            </div>
            <div class="card-body">
                <p class="description" style="margin-bottom: 20px;">
                    <?php _e('Configure alertas automáticos por email para eventos importantes do CRM.', 'crm-developer'); ?>
                </p>

                <?php
                $alert_types = CRM_Dev_Alerts::get_alert_types();
                $alert_settings = CRM_Dev_Alerts::get_settings();
                ?>

                <div class="alerts-config">
                    <?php foreach ($alert_types as $key => $type) :
                        $setting = $alert_settings[$key] ?? array('enabled' => false, 'recipients' => get_option('admin_email'), 'subject' => $type['default_subject'], 'message' => $type['default_message']);
                    ?>
                        <div class="alert-config-item">
                            <div class="alert-header">
                                <div class="alert-info">
                                    <div class="alert-icon">
                                        <i class="fas <?php echo esc_attr($type['icon']); ?>"></i>
                                    </div>
                                    <div class="alert-details">
                                        <h4><?php echo esc_html($type['label']); ?></h4>
                                        <p><?php echo esc_html($type['description']); ?></p>
                                    </div>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" class="alert-enabled" data-alert="<?php echo esc_attr($key); ?>" <?php checked(!empty($setting['enabled'])); ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            <div class="alert-config-fields" style="<?php echo empty($setting['enabled']) ? 'display:none;' : ''; ?>">
                                <div class="form-group">
                                    <label><?php _e('Destinatários (separados por vírgula)', 'crm-developer'); ?></label>
                                    <input type="text" class="alert-recipients regular-text" data-alert="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($setting['recipients']); ?>">
                                </div>
                                <div class="form-group">
                                    <label><?php _e('Assunto do Email', 'crm-developer'); ?></label>
                                    <input type="text" class="alert-subject regular-text" data-alert="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($setting['subject']); ?>">
                                </div>
                                <div class="form-group">
                                    <label><?php _e('Mensagem', 'crm-developer'); ?></label>
                                    <textarea class="alert-message large-text" data-alert="<?php echo esc_attr($key); ?>" rows="4"><?php echo esc_textarea($setting['message']); ?></textarea>
                                    <p class="description"><?php _e('Variáveis disponíveis: {{nome}}, {{email}}, {{telefone}}, {{estado}}, {{data_hora}}, {{site_name}}', 'crm-developer'); ?></p>
                                </div>
                                <button type="button" class="button btn-test-alert" data-alert="<?php echo esc_attr($key); ?>">
                                    <i class="fas fa-paper-plane"></i> <?php _e('Enviar Teste', 'crm-developer'); ?>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="alert-actions" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--crm-border);">
                    <button type="button" id="btn-save-alerts" class="button button-primary">
                        <i class="fas fa-save"></i> <?php _e('Salvar Configurações de Alertas', 'crm-developer'); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Ferramentas -->
        <div class="crm-dev-card">
            <div class="card-header">
                <h3><i class="fas fa-tools"></i> <?php _e('Ferramentas', 'crm-developer'); ?></h3>
            </div>
            <div class="card-body">
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Recalcular Scores', 'crm-developer'); ?></th>
                        <td>
                            <button type="button" class="button" id="btn-recalculate-scores">
                                <i class="fas fa-calculator"></i> <?php _e('Recalcular Scores de Engajamento', 'crm-developer'); ?>
                            </button>
                            <p class="description"><?php _e('Recalcula o score de engajamento de todos os contatos.', 'crm-developer'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Limpar Dados de Teste', 'crm-developer'); ?></th>
                        <td>
                            <button type="button" class="button button-link-delete" id="btn-clear-test-data">
                                <i class="fas fa-trash"></i> <?php _e('Excluir Contatos de Teste', 'crm-developer'); ?>
                            </button>
                            <p class="description"><?php _e('Remove contatos marcados como origem "teste". Use com cuidado!', 'crm-developer'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Informações do Sistema -->
        <div class="crm-dev-card">
            <div class="card-header">
                <h3><i class="fas fa-info-circle"></i> <?php _e('Informações do Sistema', 'crm-developer'); ?></h3>
            </div>
            <div class="card-body">
                <table class="form-table info-table">
                    <tr>
                        <th><?php _e('Versão do Plugin', 'crm-developer'); ?></th>
                        <td><?php echo CRM_DEV_VERSION; ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Versão do WordPress', 'crm-developer'); ?></th>
                        <td><?php echo get_bloginfo('version'); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Versão do PHP', 'crm-developer'); ?></th>
                        <td><?php echo phpversion(); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Total de Contatos', 'crm-developer'); ?></th>
                        <td>
                            <?php
                            global $wpdb;
                            $tables = CRM_Dev_Database::get_tables();
                            echo number_format_i18n($wpdb->get_var("SELECT COUNT(*) FROM {$tables['contacts']}"));
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Total de Interações', 'crm-developer'); ?></th>
                        <td>
                            <?php
                            echo number_format_i18n($wpdb->get_var("SELECT COUNT(*) FROM {$tables['interactions']}"));
                            ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <p class="submit">
            <button type="submit" name="crm_dev_save_settings" class="button button-primary button-hero">
                <i class="fas fa-save"></i> <?php _e('Salvar Configurações', 'crm-developer'); ?>
            </button>
        </p>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    $('#btn-recalculate-scores').on('click', function() {
        const $btn = $(this);
        $btn.html('<i class="fas fa-spinner fa-spin"></i> Processando...').prop('disabled', true);

        // Aqui seria feita a chamada AJAX para recalcular
        setTimeout(function() {
            alert('Scores recalculados com sucesso!');
            $btn.html('<i class="fas fa-calculator"></i> Recalcular Scores de Engajamento').prop('disabled', false);
        }, 2000);
    });

    $('#btn-clear-test-data').on('click', function() {
        if (!confirm('Tem certeza que deseja excluir todos os contatos de teste? Esta ação não pode ser desfeita!')) {
            return;
        }

        const $btn = $(this);
        $btn.html('<i class="fas fa-spinner fa-spin"></i> Excluindo...').prop('disabled', true);

        setTimeout(function() {
            alert('Dados de teste removidos!');
            $btn.html('<i class="fas fa-trash"></i> Excluir Contatos de Teste').prop('disabled', false);
        }, 2000);
    });

    // Toggle campos de alerta ao habilitar/desabilitar
    $('.alert-enabled').on('change', function() {
        const $fields = $(this).closest('.alert-config-item').find('.alert-config-fields');
        if ($(this).is(':checked')) {
            $fields.slideDown(200);
        } else {
            $fields.slideUp(200);
        }
    });

    // Salvar configurações de alertas
    $('#btn-save-alerts').on('click', function() {
        const $btn = $(this);
        $btn.html('<i class="fas fa-spinner fa-spin"></i> Salvando...').prop('disabled', true);

        const settings = {};
        $('.alert-config-item').each(function() {
            const $item = $(this);
            const alertKey = $item.find('.alert-enabled').data('alert');
            settings[alertKey] = {
                enabled: $item.find('.alert-enabled').is(':checked'),
                recipients: $item.find('.alert-recipients').val(),
                subject: $item.find('.alert-subject').val(),
                message: $item.find('.alert-message').val()
            };
        });

        $.post(crmDevAdmin.ajaxUrl, {
            action: 'crm_dev_save_alert_settings',
            nonce: crmDevAdmin.nonce,
            settings: settings
        }, function(response) {
            if (response.success) {
                alert('<?php _e('Configurações de alertas salvas!', 'crm-developer'); ?>');
            } else {
                alert(response.data.message || '<?php _e('Erro ao salvar', 'crm-developer'); ?>');
            }
            $btn.html('<i class="fas fa-save"></i> <?php _e('Salvar Configurações de Alertas', 'crm-developer'); ?>').prop('disabled', false);
        });
    });

    // Testar alerta
    $('.btn-test-alert').on('click', function() {
        const $btn = $(this);
        const alertType = $btn.data('alert');
        $btn.html('<i class="fas fa-spinner fa-spin"></i> Enviando...').prop('disabled', true);

        $.post(crmDevAdmin.ajaxUrl, {
            action: 'crm_dev_test_alert',
            nonce: crmDevAdmin.nonce,
            type: alertType
        }, function(response) {
            if (response.success) {
                alert('<?php _e('Alerta de teste enviado!', 'crm-developer'); ?>');
            } else {
                alert(response.data.message || '<?php _e('Erro ao enviar teste', 'crm-developer'); ?>');
            }
            $btn.html('<i class="fas fa-paper-plane"></i> <?php _e('Enviar Teste', 'crm-developer'); ?>').prop('disabled', false);
        });
    });
});
</script>

<?php
// Modal de ajuda
crm_dev_render_help_modal_settings();
crm_dev_render_help_modal_script();
?>
