<?php
/**
 * Classe de Alertas por Email
 *
 * @package CRM_Developer
 */

if (!defined('ABSPATH')) {
    exit;
}

class CRM_Dev_Alerts {

    /**
     * Tipos de alertas disponíveis
     */
    public static function get_alert_types() {
        return array(
            'new_registration' => array(
                'label' => __('Novo Cadastro', 'crm-developer'),
                'description' => __('Envia alerta quando um novo contato é cadastrado pelo formulário público', 'crm-developer'),
                'icon' => 'fa-user-plus',
                'default_subject' => __('[CRM] Novo cadastro: {{nome}}', 'crm-developer'),
                'default_message' => __("Um novo contato foi cadastrado no CRM:\n\nNome: {{nome}}\nEmail: {{email}}\nTelefone: {{telefone}}\nEstado: {{estado}}\n\nData/Hora: {{data_hora}}", 'crm-developer'),
            ),
            'scheduled_interaction' => array(
                'label' => __('Interação Agendada', 'crm-developer'),
                'description' => __('Envia alerta quando a data de uma próxima ação chega', 'crm-developer'),
                'icon' => 'fa-calendar-check',
                'default_subject' => __('[CRM] Ação agendada para hoje: {{titulo}}', 'crm-developer'),
                'default_message' => __("Você tem uma ação agendada para hoje:\n\nContato: {{nome}}\nAção: {{proxima_acao}}\nTipo: {{tipo}}\n\nVisualize o contato no CRM.", 'crm-developer'),
            ),
            'email_send_error' => array(
                'label' => __('Erro de Envio de Email', 'crm-developer'),
                'description' => __('Envia alerta quando ocorre erro ao enviar email em massa', 'crm-developer'),
                'icon' => 'fa-exclamation-triangle',
                'default_subject' => __('[CRM] Erro no envio de email', 'crm-developer'),
                'default_message' => __("Ocorreu um erro ao enviar email:\n\nDestinatário: {{email}}\nAssunto: {{assunto}}\nErro: {{erro}}\n\nVerifique as configurações de SMTP.", 'crm-developer'),
            ),
            'low_engagement' => array(
                'label' => __('Baixo Engajamento', 'crm-developer'),
                'description' => __('Envia alerta diário com contatos sem interação há mais de 30 dias', 'crm-developer'),
                'icon' => 'fa-chart-line',
                'default_subject' => __('[CRM] Contatos precisam de atenção', 'crm-developer'),
                'default_message' => __("Os seguintes contatos não têm interação há mais de 30 dias:\n\n{{lista_contatos}}\n\nConsidere entrar em contato para manter o engajamento.", 'crm-developer'),
            ),
        );
    }

    /**
     * Retorna configurações dos alertas
     */
    public static function get_settings() {
        $defaults = array();
        $types = self::get_alert_types();

        foreach ($types as $key => $type) {
            $defaults[$key] = array(
                'enabled' => false,
                'recipients' => get_option('admin_email'),
                'subject' => $type['default_subject'],
                'message' => $type['default_message'],
            );
        }

        return get_option('crm_dev_alert_settings', $defaults);
    }

    /**
     * Salva configurações dos alertas
     */
    public static function save_settings($settings) {
        return update_option('crm_dev_alert_settings', $settings);
    }

    /**
     * Envia alerta
     */
    public static function send_alert($type, $data = array()) {
        $settings = self::get_settings();

        if (!isset($settings[$type]) || !$settings[$type]['enabled']) {
            return false;
        }

        $alert = $settings[$type];
        $recipients = array_map('trim', explode(',', $alert['recipients']));
        $subject = self::replace_variables($alert['subject'], $data);
        $message = self::replace_variables($alert['message'], $data);

        // Envia para cada destinatário
        foreach ($recipients as $email) {
            if (is_email($email)) {
                wp_mail($email, $subject, $message);
            }
        }

        // Log do alerta
        self::log_alert($type, $data, $recipients);

        return true;
    }

    /**
     * Substitui variáveis no texto
     */
    private static function replace_variables($text, $data) {
        foreach ($data as $key => $value) {
            $text = str_replace('{{' . $key . '}}', $value, $text);
        }

        // Variáveis de sistema
        $text = str_replace('{{data_hora}}', current_time('d/m/Y H:i'), $text);
        $text = str_replace('{{site_name}}', get_bloginfo('name'), $text);
        $text = str_replace('{{admin_url}}', admin_url('admin.php?page=crm-developer'), $text);

        return $text;
    }

    /**
     * Registra log de alerta
     */
    private static function log_alert($type, $data, $recipients) {
        $logs = get_option('crm_dev_alert_logs', array());

        // Mantém apenas últimos 100 logs
        if (count($logs) >= 100) {
            array_shift($logs);
        }

        $logs[] = array(
            'type' => $type,
            'data' => $data,
            'recipients' => $recipients,
            'sent_at' => current_time('mysql'),
        );

        update_option('crm_dev_alert_logs', $logs);
    }

    /**
     * Retorna logs de alertas
     */
    public static function get_logs($limit = 50) {
        $logs = get_option('crm_dev_alert_logs', array());
        $logs = array_reverse($logs);

        return array_slice($logs, 0, $limit);
    }

    /**
     * Limpa logs de alertas
     */
    public static function clear_logs() {
        return delete_option('crm_dev_alert_logs');
    }

    /**
     * Dispara alerta de novo cadastro
     */
    public static function trigger_new_registration($contact) {
        self::send_alert('new_registration', array(
            'nome' => $contact['nome_completo'],
            'email' => $contact['email'] ?? '-',
            'telefone' => $contact['telefone'] ?? $contact['whatsapp'] ?? '-',
            'estado' => $contact['estado'] ?? '-',
            'municipio' => $contact['municipio'] ?? '-',
        ));
    }

    /**
     * Dispara alerta de interação agendada
     */
    public static function trigger_scheduled_interaction($interaction, $contact) {
        self::send_alert('scheduled_interaction', array(
            'nome' => $contact['nome_completo'],
            'email' => $contact['email'] ?? '-',
            'titulo' => $interaction['titulo'],
            'tipo' => $interaction['tipo'],
            'proxima_acao' => $interaction['proxima_acao'],
            'data_proxima_acao' => $interaction['data_proxima_acao'],
        ));
    }

    /**
     * Dispara alerta de erro de envio
     */
    public static function trigger_email_error($email, $subject, $error) {
        self::send_alert('email_send_error', array(
            'email' => $email,
            'assunto' => $subject,
            'erro' => $error,
        ));
    }

    /**
     * Dispara alerta de baixo engajamento (cron diário)
     */
    public static function trigger_low_engagement() {
        global $wpdb;
        $tables = CRM_Dev_Database::get_tables();

        // Busca contatos sem interação há mais de 30 dias
        $contacts = $wpdb->get_results(
            "SELECT nome_completo, email, ultima_interacao
            FROM {$tables['contacts']}
            WHERE status = 'ativo'
            AND (ultima_interacao IS NULL OR ultima_interacao < DATE_SUB(NOW(), INTERVAL 30 DAY))
            ORDER BY ultima_interacao ASC
            LIMIT 20",
            ARRAY_A
        );

        if (empty($contacts)) {
            return;
        }

        $lista = '';
        foreach ($contacts as $c) {
            $ultima = $c['ultima_interacao'] ? date('d/m/Y', strtotime($c['ultima_interacao'])) : 'Nunca';
            $lista .= "- {$c['nome_completo']} ({$c['email']}) - Última interação: {$ultima}\n";
        }

        self::send_alert('low_engagement', array(
            'lista_contatos' => $lista,
            'total' => count($contacts),
        ));
    }

    /**
     * Verifica e dispara alertas de interações agendadas para hoje
     */
    public static function check_scheduled_interactions() {
        $interactions = CRM_Dev_Interactions::get_pending_actions(0); // Ações para hoje

        foreach ($interactions as $interaction) {
            if ($interaction['data_proxima_acao'] === date('Y-m-d')) {
                $contact = array(
                    'nome_completo' => $interaction['nome_completo'],
                    'email' => $interaction['email'],
                );
                self::trigger_scheduled_interaction($interaction, $contact);
            }
        }
    }

    /**
     * Handler AJAX - Salvar configurações de alertas
     */
    public static function ajax_save_settings() {
        check_ajax_referer('crm_dev_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Sem permissão'));
        }

        $settings = isset($_POST['settings']) ? $_POST['settings'] : array();

        // Sanitização
        $sanitized = array();
        $types = self::get_alert_types();

        foreach ($types as $key => $type) {
            if (isset($settings[$key])) {
                $sanitized[$key] = array(
                    'enabled' => !empty($settings[$key]['enabled']),
                    'recipients' => sanitize_text_field($settings[$key]['recipients'] ?? get_option('admin_email')),
                    'subject' => sanitize_text_field($settings[$key]['subject'] ?? $type['default_subject']),
                    'message' => sanitize_textarea_field($settings[$key]['message'] ?? $type['default_message']),
                );
            }
        }

        if (self::save_settings($sanitized)) {
            wp_send_json_success(array('message' => 'Configurações de alertas salvas!'));
        } else {
            wp_send_json_error(array('message' => 'Erro ao salvar configurações'));
        }
    }

    /**
     * Handler AJAX - Obter configurações de alertas
     */
    public static function ajax_get_settings() {
        check_ajax_referer('crm_dev_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Sem permissão'));
        }

        wp_send_json_success(array(
            'settings' => self::get_settings(),
            'types' => self::get_alert_types(),
        ));
    }

    /**
     * Handler AJAX - Testar alerta
     */
    public static function ajax_test_alert() {
        check_ajax_referer('crm_dev_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Sem permissão'));
        }

        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
        $types = self::get_alert_types();

        if (!isset($types[$type])) {
            wp_send_json_error(array('message' => 'Tipo de alerta inválido'));
        }

        // Dados de teste
        $test_data = array(
            'nome' => 'João da Silva (Teste)',
            'email' => 'teste@exemplo.com',
            'telefone' => '(11) 99999-9999',
            'estado' => 'SP',
            'municipio' => 'São Paulo',
            'titulo' => 'Reunião de Acompanhamento',
            'tipo' => 'reuniao',
            'proxima_acao' => 'Agendar próxima reunião',
            'data_proxima_acao' => date('Y-m-d'),
            'assunto' => 'Teste de Email',
            'erro' => 'SMTP connection failed',
            'lista_contatos' => "- Maria Silva (maria@teste.com) - Última interação: 01/01/2024\n- Pedro Santos (pedro@teste.com) - Última interação: Nunca",
            'total' => 2,
        );

        $result = self::send_alert($type, $test_data);

        if ($result) {
            wp_send_json_success(array('message' => 'Alerta de teste enviado!'));
        } else {
            wp_send_json_error(array('message' => 'Alerta está desativado ou não há destinatários configurados'));
        }
    }
}
