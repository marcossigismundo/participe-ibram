<?php
/**
 * Classe pública do CRM
 *
 * @package CRM_Developer
 */

if (!defined('ABSPATH')) {
    exit;
}

class CRM_Dev_Public {

    /**
     * Shortcode do formulário de cadastro
     */
    public static function shortcode_form($atts) {
        if (!get_option('crm_dev_public_form_enabled', 1)) {
            return '<p>' . __('O formulário de cadastro está temporariamente indisponível.', 'crm-developer') . '</p>';
        }

        $atts = shortcode_atts(array(
            'titulo' => __('Cadastre-se', 'crm-developer'),
            'subtitulo' => __('Preencha o formulário abaixo para fazer parte da nossa rede.', 'crm-developer'),
        ), $atts);

        ob_start();
        include CRM_DEV_PLUGIN_DIR . 'public/views/form.php';
        return ob_get_clean();
    }

    /**
     * Shortcode do dashboard público
     */
    public static function shortcode_dashboard($atts) {
        $atts = shortcode_atts(array(
            'titulo' => __('Estatísticas', 'crm-developer'),
        ), $atts);

        ob_start();
        include CRM_DEV_PLUGIN_DIR . 'public/views/dashboard.php';
        return ob_get_clean();
    }

    /**
     * Handler AJAX - Registro público
     */
    public static function ajax_register() {
        check_ajax_referer('crm_dev_public_nonce', 'nonce');

        $raw_data = isset($_POST['data']) ? $_POST['data'] : '';

        // Decodifica JSON se for string
        if (is_string($raw_data)) {
            $data = json_decode(stripslashes($raw_data), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $data = array();
            }
        } else {
            $data = $raw_data;
        }

        // Validações
        if (empty($data['nome_completo'])) {
            wp_send_json_error(array('message' => __('Nome completo é obrigatório.', 'crm-developer')));
        }

        if (empty($data['email'])) {
            wp_send_json_error(array('message' => __('Email é obrigatório.', 'crm-developer')));
        }

        if (!is_email($data['email'])) {
            wp_send_json_error(array('message' => __('Email inválido.', 'crm-developer')));
        }

        if (empty($data['consentimento_lgpd']) || $data['consentimento_lgpd'] !== 'sim') {
            wp_send_json_error(array('message' => __('É necessário aceitar os termos de privacidade.', 'crm-developer')));
        }

        // Verifica se email já existe
        $existing = CRM_Dev_Contacts::get_contact_by_email($data['email']);
        if ($existing) {
            wp_send_json_error(array('message' => __('Este email já está cadastrado em nossa base.', 'crm-developer')));
        }

        // Define origem
        $data['origem'] = 'formulario_publico';
        $data['status'] = 'ativo';
        $data['consentimento_lgpd'] = 'sim';

        // Garante que as tabelas existem
        global $wpdb;
        $tables = CRM_Dev_Database::get_tables();
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$tables['contacts']}'");
        if (!$table_exists) {
            CRM_Dev_Database::create_tables();
        }

        // Salva contato
        $contact_id = CRM_Dev_Contacts::save_contact($data);

        if (!$contact_id) {
            $error_msg = __('Erro ao realizar o cadastro. Tente novamente.', 'crm-developer');
            if (defined('WP_DEBUG') && WP_DEBUG && !empty($wpdb->last_error)) {
                $error_msg .= ' Debug: ' . $wpdb->last_error;
            }
            wp_send_json_error(array('message' => $error_msg));
        }

        $wpdb->insert(
            $tables['consent_logs'],
            array(
                'contact_id' => $contact_id,
                'tipo' => 'cadastro_publico',
                'ip_address' => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''),
                'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
                'dados_consentidos' => 'Cadastro público com consentimento LGPD',
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s')
        );

        // Envia notificação por email se configurado
        if (get_option('crm_dev_email_notifications', 0)) {
            $to = get_option('crm_dev_notification_email', get_option('admin_email'));
            $subject = sprintf(__('[CRM] Novo cadastro: %s', 'crm-developer'), $data['nome_completo']);
            $message = sprintf(
                __("Um novo contato se cadastrou:\n\nNome: %s\nEmail: %s\nEstado: %s\n\nAcesse o painel para ver mais detalhes.", 'crm-developer'),
                $data['nome_completo'],
                $data['email'],
                $data['estado'] ?? '-'
            );
            wp_mail($to, $subject, $message);
        }

        wp_send_json_success(array(
            'message' => __('Cadastro realizado com sucesso! Obrigado por se juntar a nós.', 'crm-developer')
        ));
    }
}
