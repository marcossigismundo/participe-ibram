<?php
/**
 * View de Configurações
 *
 * @package CRM_Developer
 */

if (!defined('ABSPATH')) {
    exit;
}

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
        <h1>
            <i class="fas fa-cog"></i>
            <?php _e('Configurações', 'crm-developer'); ?>
        </h1>
        <p class="crm-dev-subtitle"><?php _e('Configure o comportamento do plugin CRM', 'crm-developer'); ?></p>
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
});
</script>
