<?php
/**
 * Classe de Gerenciamento de Emails
 *
 * @package CRM_Developer
 */

if (!defined('ABSPATH')) {
    exit;
}

class CRM_Dev_Email {

    /**
     * Variáveis disponíveis para templates
     */
    private static $template_vars = array(
        '{{nome}}' => 'Nome completo do contato',
        '{{nome_social}}' => 'Nome social (se houver)',
        '{{primeiro_nome}}' => 'Primeiro nome do contato',
        '{{email}}' => 'Email do contato',
        '{{estado}}' => 'Estado do contato',
        '{{municipio}}' => 'Município do contato',
        '{{regiao}}' => 'Região do contato',
        '{{data_cadastro}}' => 'Data de cadastro',
        '{{link_descadastro}}' => 'Link para descadastro',
    );

    /**
     * Retorna variáveis de template disponíveis
     */
    public static function get_template_vars() {
        return self::$template_vars;
    }

    /**
     * Retorna configurações de email
     */
    public static function get_settings() {
        $defaults = array(
            'from_name' => get_bloginfo('name'),
            'from_email' => get_option('admin_email'),
            'reply_to' => get_option('admin_email'),
            'smtp_enabled' => false,
            'smtp_host' => '',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'smtp_username' => '',
            'smtp_password' => '',
            'rate_limit' => 50, // emails por hora
            'batch_size' => 10, // emails por lote
            'batch_delay' => 5, // segundos entre lotes
            'footer_text' => 'Você está recebendo este email porque se cadastrou em nossa base de contatos.',
            'unsubscribe_enabled' => true,
        );

        $settings = get_option('crm_dev_email_settings', array());
        return wp_parse_args($settings, $defaults);
    }

    /**
     * Salva configurações de email
     */
    public static function save_settings($settings) {
        $sanitized = array(
            'from_name' => sanitize_text_field($settings['from_name'] ?? ''),
            'from_email' => sanitize_email($settings['from_email'] ?? ''),
            'reply_to' => sanitize_email($settings['reply_to'] ?? ''),
            'smtp_enabled' => !empty($settings['smtp_enabled']),
            'smtp_host' => sanitize_text_field($settings['smtp_host'] ?? ''),
            'smtp_port' => intval($settings['smtp_port'] ?? 587),
            'smtp_encryption' => in_array($settings['smtp_encryption'] ?? '', array('none', 'ssl', 'tls')) ? $settings['smtp_encryption'] : 'tls',
            'smtp_username' => sanitize_text_field($settings['smtp_username'] ?? ''),
            'smtp_password' => $settings['smtp_password'] ?? '', // Não sanitizar para preservar caracteres especiais
            'rate_limit' => max(1, intval($settings['rate_limit'] ?? 50)),
            'batch_size' => max(1, intval($settings['batch_size'] ?? 10)),
            'batch_delay' => max(1, intval($settings['batch_delay'] ?? 5)),
            'footer_text' => wp_kses_post($settings['footer_text'] ?? ''),
            'unsubscribe_enabled' => !empty($settings['unsubscribe_enabled']),
        );

        update_option('crm_dev_email_settings', $sanitized);
        return true;
    }

    /**
     * Retorna todos os templates de email
     */
    public static function get_templates() {
        global $wpdb;
        $table = $wpdb->prefix . 'crm_dev_email_templates';

        // Verifica se a tabela existe
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            self::create_email_tables();
        }

        return $wpdb->get_results(
            "SELECT * FROM {$table} ORDER BY nome ASC",
            ARRAY_A
        );
    }

    /**
     * Retorna um template específico
     */
    public static function get_template($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'crm_dev_email_templates';

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id),
            ARRAY_A
        );
    }

    /**
     * Salva template de email
     */
    public static function save_template($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'crm_dev_email_templates';

        $template_data = array(
            'nome' => sanitize_text_field($data['nome'] ?? ''),
            'assunto' => sanitize_text_field($data['assunto'] ?? ''),
            'conteudo' => wp_kses_post($data['conteudo'] ?? ''),
            'tipo' => sanitize_text_field($data['tipo'] ?? 'geral'),
            'updated_at' => current_time('mysql'),
        );

        if (!empty($data['id'])) {
            $wpdb->update($table, $template_data, array('id' => intval($data['id'])));
            return intval($data['id']);
        } else {
            $template_data['created_at'] = current_time('mysql');
            $template_data['created_by'] = get_current_user_id();
            $wpdb->insert($table, $template_data);
            return $wpdb->insert_id;
        }
    }

    /**
     * Exclui template de email
     */
    public static function delete_template($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'crm_dev_email_templates';
        return $wpdb->delete($table, array('id' => intval($id)));
    }

    /**
     * Processa variáveis no template para um contato específico
     */
    public static function process_template($template, $contact) {
        $settings = self::get_settings();

        // Gera token de descadastro
        $unsubscribe_token = wp_hash($contact['id'] . $contact['email'] . 'unsubscribe');
        $unsubscribe_link = add_query_arg(array(
            'crm_unsubscribe' => $contact['id'],
            'token' => $unsubscribe_token,
        ), home_url());

        $replacements = array(
            '{{nome}}' => $contact['nome_completo'] ?? '',
            '{{nome_social}}' => $contact['nome_social'] ?? $contact['nome_completo'] ?? '',
            '{{primeiro_nome}}' => explode(' ', $contact['nome_completo'] ?? '')[0],
            '{{email}}' => $contact['email'] ?? '',
            '{{estado}}' => $contact['estado'] ?? '',
            '{{municipio}}' => $contact['municipio'] ?? '',
            '{{regiao}}' => $contact['regiao'] ?? '',
            '{{data_cadastro}}' => isset($contact['created_at']) ? date_i18n('d/m/Y', strtotime($contact['created_at'])) : '',
            '{{link_descadastro}}' => $unsubscribe_link,
        );

        $processed = str_replace(array_keys($replacements), array_values($replacements), $template);

        return $processed;
    }

    /**
     * Envia email para um contato
     */
    public static function send_email($contact, $subject, $content, $campaign_id = null) {
        $settings = self::get_settings();

        // Verifica se o contato pode receber emails
        if (empty($contact['email']) || !is_email($contact['email'])) {
            return array('success' => false, 'error' => 'Email inválido');
        }

        // Verifica se está na lista de descadastro
        if (self::is_unsubscribed($contact['email'])) {
            return array('success' => false, 'error' => 'Contato descadastrado');
        }

        // Processa variáveis do template
        $processed_subject = self::process_template($subject, $contact);
        $processed_content = self::process_template($content, $contact);

        // Adiciona footer
        if ($settings['unsubscribe_enabled']) {
            $unsubscribe_token = wp_hash($contact['id'] . $contact['email'] . 'unsubscribe');
            $unsubscribe_link = add_query_arg(array(
                'crm_unsubscribe' => $contact['id'],
                'token' => $unsubscribe_token,
            ), home_url());

            $processed_content .= '<br><br><hr><p style="font-size: 12px; color: #666;">' .
                $settings['footer_text'] . '<br>' .
                '<a href="' . esc_url($unsubscribe_link) . '">Clique aqui para não receber mais nossos emails</a></p>';
        }

        // Configura headers
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $settings['from_name'] . ' <' . $settings['from_email'] . '>',
        );

        if (!empty($settings['reply_to'])) {
            $headers[] = 'Reply-To: ' . $settings['reply_to'];
        }

        // Configura SMTP se habilitado
        if ($settings['smtp_enabled']) {
            add_action('phpmailer_init', array(__CLASS__, 'configure_smtp'));
        }

        // Envia email
        $sent = wp_mail($contact['email'], $processed_subject, $processed_content, $headers);

        // Remove configuração SMTP
        if ($settings['smtp_enabled']) {
            remove_action('phpmailer_init', array(__CLASS__, 'configure_smtp'));
        }

        // Registra log
        self::log_email($contact['id'], $campaign_id, $subject, $sent);

        return array('success' => $sent, 'error' => $sent ? null : 'Falha no envio');
    }

    /**
     * Configura SMTP
     */
    public static function configure_smtp($phpmailer) {
        $settings = self::get_settings();

        $phpmailer->isSMTP();
        $phpmailer->Host = $settings['smtp_host'];
        $phpmailer->Port = $settings['smtp_port'];

        if ($settings['smtp_encryption'] !== 'none') {
            $phpmailer->SMTPSecure = $settings['smtp_encryption'];
        }

        if (!empty($settings['smtp_username'])) {
            $phpmailer->SMTPAuth = true;
            $phpmailer->Username = $settings['smtp_username'];
            $phpmailer->Password = $settings['smtp_password'];
        }
    }

    /**
     * Envia emails em massa
     */
    public static function send_mass_email($contacts, $subject, $content, $campaign_name = '') {
        global $wpdb;
        $settings = self::get_settings();

        // Cria campanha
        $table_campaigns = $wpdb->prefix . 'crm_dev_email_campaigns';
        $wpdb->insert($table_campaigns, array(
            'nome' => $campaign_name ?: 'Campanha ' . date('d/m/Y H:i'),
            'assunto' => $subject,
            'conteudo' => $content,
            'total_destinatarios' => count($contacts),
            'enviados' => 0,
            'erros' => 0,
            'status' => 'processing',
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql'),
        ));
        $campaign_id = $wpdb->insert_id;

        // Adiciona à fila de envio
        $table_queue = $wpdb->prefix . 'crm_dev_email_queue';
        foreach ($contacts as $contact) {
            $wpdb->insert($table_queue, array(
                'campaign_id' => $campaign_id,
                'contact_id' => $contact['id'],
                'email' => $contact['email'],
                'status' => 'pending',
                'created_at' => current_time('mysql'),
            ));
        }

        // Agenda processamento da fila
        if (!wp_next_scheduled('crm_dev_process_email_queue')) {
            wp_schedule_single_event(time() + 5, 'crm_dev_process_email_queue');
        }

        return array(
            'success' => true,
            'campaign_id' => $campaign_id,
            'queued' => count($contacts),
        );
    }

    /**
     * Processa fila de emails
     */
    public static function process_email_queue() {
        global $wpdb;
        $settings = self::get_settings();

        $table_queue = $wpdb->prefix . 'crm_dev_email_queue';
        $table_campaigns = $wpdb->prefix . 'crm_dev_email_campaigns';
        $table_contacts = CRM_Dev_Database::get_tables()['contacts'];

        // Verifica rate limit
        $sent_last_hour = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table_queue}
            WHERE status = 'sent' AND processed_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );

        if ($sent_last_hour >= $settings['rate_limit']) {
            // Reagenda para mais tarde
            wp_schedule_single_event(time() + 300, 'crm_dev_process_email_queue');
            return;
        }

        // Pega próximo lote
        $limit = min($settings['batch_size'], $settings['rate_limit'] - $sent_last_hour);
        $pending = $wpdb->get_results($wpdb->prepare(
            "SELECT q.*, c.nome as campaign_name, c.assunto, c.conteudo
            FROM {$table_queue} q
            INNER JOIN {$table_campaigns} c ON q.campaign_id = c.id
            WHERE q.status = 'pending'
            ORDER BY q.id ASC
            LIMIT %d",
            $limit
        ), ARRAY_A);

        if (empty($pending)) {
            // Atualiza campanhas concluídas
            $wpdb->query(
                "UPDATE {$table_campaigns} SET status = 'completed'
                WHERE status = 'processing'
                AND id NOT IN (SELECT campaign_id FROM {$table_queue} WHERE status = 'pending')"
            );
            return;
        }

        foreach ($pending as $item) {
            // Busca dados do contato
            $contact = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM {$table_contacts} WHERE id = %d", $item['contact_id']),
                ARRAY_A
            );

            if (!$contact) {
                $wpdb->update($table_queue, array(
                    'status' => 'error',
                    'error_message' => 'Contato não encontrado',
                    'processed_at' => current_time('mysql'),
                ), array('id' => $item['id']));
                continue;
            }

            // Envia email
            $result = self::send_email($contact, $item['assunto'], $item['conteudo'], $item['campaign_id']);

            // Atualiza status na fila
            $wpdb->update($table_queue, array(
                'status' => $result['success'] ? 'sent' : 'error',
                'error_message' => $result['error'] ?? null,
                'processed_at' => current_time('mysql'),
            ), array('id' => $item['id']));

            // Atualiza contadores da campanha
            if ($result['success']) {
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$table_campaigns} SET enviados = enviados + 1 WHERE id = %d",
                    $item['campaign_id']
                ));
            } else {
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$table_campaigns} SET erros = erros + 1 WHERE id = %d",
                    $item['campaign_id']
                ));
            }

            // Delay entre emails
            if ($settings['batch_delay'] > 0) {
                sleep($settings['batch_delay']);
            }
        }

        // Verifica se há mais emails na fila
        $remaining = $wpdb->get_var("SELECT COUNT(*) FROM {$table_queue} WHERE status = 'pending'");
        if ($remaining > 0) {
            wp_schedule_single_event(time() + 10, 'crm_dev_process_email_queue');
        }
    }

    /**
     * Registra log de email enviado
     */
    public static function log_email($contact_id, $campaign_id, $subject, $success) {
        global $wpdb;
        $table = $wpdb->prefix . 'crm_dev_email_logs';

        $wpdb->insert($table, array(
            'contact_id' => $contact_id,
            'campaign_id' => $campaign_id,
            'assunto' => $subject,
            'status' => $success ? 'sent' : 'error',
            'created_at' => current_time('mysql'),
        ));
    }

    /**
     * Verifica se email está na lista de descadastro
     */
    public static function is_unsubscribed($email) {
        global $wpdb;
        $table = $wpdb->prefix . 'crm_dev_email_unsubscribes';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return false;
        }

        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE email = %s",
            $email
        )) > 0;
    }

    /**
     * Adiciona email à lista de descadastro
     */
    public static function unsubscribe($email, $contact_id = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'crm_dev_email_unsubscribes';

        // Verifica se já está descadastrado
        if (self::is_unsubscribed($email)) {
            return true;
        }

        return $wpdb->insert($table, array(
            'email' => sanitize_email($email),
            'contact_id' => $contact_id,
            'created_at' => current_time('mysql'),
            'ip_address' => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''),
        ));
    }

    /**
     * Retorna logs de email
     */
    public static function get_email_logs($limit = 50, $campaign_id = null) {
        global $wpdb;
        $table_logs = $wpdb->prefix . 'crm_dev_email_logs';
        $table_contacts = CRM_Dev_Database::get_tables()['contacts'];

        $where = '1=1';
        if ($campaign_id) {
            $where .= $wpdb->prepare(" AND l.campaign_id = %d", $campaign_id);
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT l.*, c.nome_completo, c.email
            FROM {$table_logs} l
            LEFT JOIN {$table_contacts} c ON l.contact_id = c.id
            WHERE {$where}
            ORDER BY l.created_at DESC
            LIMIT %d",
            $limit
        ), ARRAY_A);
    }

    /**
     * Retorna campanhas de email
     */
    public static function get_campaigns($limit = 20) {
        global $wpdb;
        $table = $wpdb->prefix . 'crm_dev_email_campaigns';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            self::create_email_tables();
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, u.display_name as user_name
            FROM {$table} c
            LEFT JOIN {$wpdb->users} u ON c.created_by = u.ID
            ORDER BY c.created_at DESC
            LIMIT %d",
            $limit
        ), ARRAY_A);
    }

    /**
     * Cria tabelas de email
     */
    public static function create_email_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Tabela de templates
        $table_templates = $wpdb->prefix . 'crm_dev_email_templates';
        $sql = "CREATE TABLE {$table_templates} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            nome varchar(255) NOT NULL,
            assunto varchar(255) NOT NULL,
            conteudo longtext NOT NULL,
            tipo varchar(50) DEFAULT 'geral',
            created_by bigint(20) UNSIGNED,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) {$charset_collate};";
        dbDelta($sql);

        // Tabela de campanhas
        $table_campaigns = $wpdb->prefix . 'crm_dev_email_campaigns';
        $sql = "CREATE TABLE {$table_campaigns} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            nome varchar(255) NOT NULL,
            assunto varchar(255) NOT NULL,
            conteudo longtext NOT NULL,
            total_destinatarios int(11) DEFAULT 0,
            enviados int(11) DEFAULT 0,
            erros int(11) DEFAULT 0,
            status varchar(50) DEFAULT 'draft',
            created_by bigint(20) UNSIGNED,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) {$charset_collate};";
        dbDelta($sql);

        // Tabela de fila de envio
        $table_queue = $wpdb->prefix . 'crm_dev_email_queue';
        $sql = "CREATE TABLE {$table_queue} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            campaign_id bigint(20) UNSIGNED NOT NULL,
            contact_id bigint(20) UNSIGNED NOT NULL,
            email varchar(255) NOT NULL,
            status varchar(50) DEFAULT 'pending',
            error_message text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            processed_at datetime,
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id),
            KEY status (status)
        ) {$charset_collate};";
        dbDelta($sql);

        // Tabela de logs
        $table_logs = $wpdb->prefix . 'crm_dev_email_logs';
        $sql = "CREATE TABLE {$table_logs} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            contact_id bigint(20) UNSIGNED,
            campaign_id bigint(20) UNSIGNED,
            assunto varchar(255),
            status varchar(50),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY contact_id (contact_id),
            KEY campaign_id (campaign_id)
        ) {$charset_collate};";
        dbDelta($sql);

        // Tabela de descadastros
        $table_unsub = $wpdb->prefix . 'crm_dev_email_unsubscribes';
        $sql = "CREATE TABLE {$table_unsub} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            email varchar(255) NOT NULL,
            contact_id bigint(20) UNSIGNED,
            ip_address varchar(45),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY email (email)
        ) {$charset_collate};";
        dbDelta($sql);
    }

    // ============= AJAX HANDLERS =============

    /**
     * AJAX - Retorna templates
     */
    public static function ajax_get_templates() {
        check_ajax_referer('crm_dev_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Sem permissão'));
        }

        $templates = self::get_templates();
        wp_send_json_success($templates);
    }

    /**
     * AJAX - Salva template
     */
    public static function ajax_save_template() {
        check_ajax_referer('crm_dev_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Sem permissão'));
        }

        $data = isset($_POST['data']) ? $_POST['data'] : array();
        $id = self::save_template($data);

        if ($id) {
            wp_send_json_success(array('id' => $id, 'message' => 'Template salvo com sucesso'));
        } else {
            wp_send_json_error(array('message' => 'Erro ao salvar template'));
        }
    }

    /**
     * AJAX - Exclui template
     */
    public static function ajax_delete_template() {
        check_ajax_referer('crm_dev_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Sem permissão'));
        }

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $deleted = self::delete_template($id);

        if ($deleted) {
            wp_send_json_success(array('message' => 'Template excluído'));
        } else {
            wp_send_json_error(array('message' => 'Erro ao excluir template'));
        }
    }

    /**
     * AJAX - Conta destinatários de email com filtros
     */
    public static function ajax_count_email_recipients() {
        check_ajax_referer('crm_dev_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Sem permissão'));
        }

        $filters = array(
            'estado' => sanitize_text_field($_POST['estado'] ?? ''),
            'regiao' => sanitize_text_field($_POST['regiao'] ?? ''),
            'status' => sanitize_text_field($_POST['status'] ?? ''),
            'engajamento' => sanitize_text_field($_POST['engajamento'] ?? ''),
            'consent' => isset($_POST['consent']) ? intval($_POST['consent']) : 1,
        );

        $contacts = self::get_filtered_contacts($filters);

        wp_send_json_success(array('total' => count($contacts)));
    }

    /**
     * AJAX - Envia email em massa
     */
    public static function ajax_send_mass_email() {
        check_ajax_referer('crm_dev_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Sem permissão'));
        }

        $subject = sanitize_text_field($_POST['subject'] ?? '');
        $content = wp_kses_post($_POST['content'] ?? '');
        $campaign_name = sanitize_text_field($_POST['campaign_name'] ?? '');

        // Filtros diretamente do POST
        $filters = array(
            'estado' => sanitize_text_field($_POST['estado'] ?? ''),
            'regiao' => sanitize_text_field($_POST['regiao'] ?? ''),
            'status' => sanitize_text_field($_POST['status'] ?? ''),
            'engajamento' => sanitize_text_field($_POST['engajamento'] ?? ''),
            'consent' => isset($_POST['consent']) ? intval($_POST['consent']) : 1,
        );

        if (empty($subject) || empty($content)) {
            wp_send_json_error(array('message' => 'Assunto e conteúdo são obrigatórios'));
        }

        // Busca contatos com filtros
        $contacts = self::get_filtered_contacts($filters);

        if (empty($contacts)) {
            wp_send_json_error(array('message' => 'Nenhum contato encontrado com os filtros selecionados'));
        }

        $result = self::send_mass_email($contacts, $subject, $content, $campaign_name);
        wp_send_json_success(array(
            'success' => true,
            'message' => sprintf('Email adicionado à fila para %d destinatários!', count($contacts)),
            'queued' => count($contacts)
        ));
    }

    /**
     * Busca contatos com filtros para envio
     */
    private static function get_filtered_contacts($filters) {
        global $wpdb;
        $table = CRM_Dev_Database::get_tables()['contacts'];

        // Condições base: deve ter email válido
        $where = array("email IS NOT NULL AND email != ''");

        // Verifica consentimento LGPD (por padrão, apenas com consentimento)
        $consent = isset($filters['consent']) ? intval($filters['consent']) : 1;
        if ($consent) {
            $where[] = "consentimento_lgpd = 'sim'";
        }

        // Filtro de estado
        if (!empty($filters['estado'])) {
            $where[] = $wpdb->prepare("estado = %s", $filters['estado']);
        }

        // Filtro de região
        if (!empty($filters['regiao'])) {
            $where[] = $wpdb->prepare("regiao = %s", $filters['regiao']);
        }

        // Filtro de status
        if (!empty($filters['status'])) {
            $where[] = $wpdb->prepare("status = %s", $filters['status']);
        }

        // Filtro de engajamento
        if (!empty($filters['engajamento'])) {
            switch ($filters['engajamento']) {
                case 'alto':
                    $where[] = "score_engajamento >= 70";
                    break;
                case 'medio':
                    $where[] = "score_engajamento >= 40 AND score_engajamento < 70";
                    break;
                case 'baixo':
                    $where[] = "score_engajamento < 40";
                    break;
            }
        }

        // Filtro de interesse (se fornecido)
        if (!empty($filters['interesse'])) {
            $interesse_field = sanitize_key($filters['interesse']);
            $where[] = "{$interesse_field} = 'sim'";
        }

        $where_clause = implode(' AND ', $where);

        return $wpdb->get_results(
            "SELECT id, nome_completo, nome_social, email, estado, municipio, regiao, created_at
            FROM {$table}
            WHERE {$where_clause}",
            ARRAY_A
        );
    }

    /**
     * AJAX - Retorna fila de emails
     */
    public static function ajax_get_queue() {
        check_ajax_referer('crm_dev_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Sem permissão'));
        }

        $campaigns = self::get_campaigns();
        wp_send_json_success(array('campaigns' => $campaigns));
    }

    /**
     * AJAX - Preview de email
     */
    public static function ajax_preview_email() {
        check_ajax_referer('crm_dev_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Sem permissão'));
        }

        $content = wp_kses_post($_POST['content'] ?? '');

        // Dados de exemplo
        $sample_contact = array(
            'id' => 0,
            'nome_completo' => 'João da Silva',
            'nome_social' => '',
            'email' => 'joao@exemplo.com',
            'estado' => 'SP',
            'municipio' => 'São Paulo',
            'regiao' => 'Sudeste',
            'created_at' => date('Y-m-d H:i:s'),
        );

        $processed = self::process_template($content, $sample_contact);

        wp_send_json_success(array('preview' => $processed));
    }

    /**
     * AJAX - Salva configurações
     */
    public static function ajax_save_settings() {
        check_ajax_referer('crm_dev_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Sem permissão'));
        }

        $settings = isset($_POST['settings']) ? $_POST['settings'] : array();
        self::save_settings($settings);

        wp_send_json_success(array('message' => 'Configurações salvas com sucesso'));
    }

    /**
     * AJAX - Testa configuração SMTP
     */
    public static function ajax_test_smtp() {
        check_ajax_referer('crm_dev_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Sem permissão'));
        }

        $settings = self::get_settings();
        $test_email = sanitize_email($_POST['test_email'] ?? $settings['from_email']);

        if (!is_email($test_email)) {
            wp_send_json_error(array('message' => 'Email de teste inválido'));
        }

        // Configura SMTP temporariamente
        if ($settings['smtp_enabled']) {
            add_action('phpmailer_init', array(__CLASS__, 'configure_smtp'));
        }

        $subject = 'Teste de Configuração SMTP - CRM Developer';
        $content = '<p>Este é um email de teste para verificar as configurações SMTP do CRM Developer.</p>';
        $content .= '<p>Se você recebeu este email, as configurações estão funcionando corretamente.</p>';
        $content .= '<p><small>Enviado em: ' . date_i18n('d/m/Y H:i:s') . '</small></p>';

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $settings['from_name'] . ' <' . $settings['from_email'] . '>',
        );

        $sent = wp_mail($test_email, $subject, $content, $headers);

        if ($settings['smtp_enabled']) {
            remove_action('phpmailer_init', array(__CLASS__, 'configure_smtp'));
        }

        if ($sent) {
            wp_send_json_success(array('message' => 'Email de teste enviado com sucesso para ' . $test_email));
        } else {
            wp_send_json_error(array('message' => 'Falha ao enviar email de teste. Verifique as configurações.'));
        }
    }

    /**
     * AJAX - Retorna logs de email
     */
    public static function ajax_get_logs() {
        check_ajax_referer('crm_dev_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Sem permissão'));
        }

        $campaign_id = isset($_POST['campaign_id']) ? intval($_POST['campaign_id']) : null;
        $logs = self::get_email_logs(100, $campaign_id);

        wp_send_json_success(array('logs' => $logs));
    }
}

// Registra hook para processar fila de emails
add_action('crm_dev_process_email_queue', array('CRM_Dev_Email', 'process_email_queue'));

// Handler para descadastro
add_action('init', function () {
    if (isset($_GET['crm_unsubscribe']) && isset($_GET['token'])) {
        $contact_id = intval($_GET['crm_unsubscribe']);
        $token = sanitize_text_field($_GET['token']);

        global $wpdb;
        $table = CRM_Dev_Database::get_tables()['contacts'];
        $contact = $wpdb->get_row($wpdb->prepare(
            "SELECT id, email FROM {$table} WHERE id = %d",
            $contact_id
        ), ARRAY_A);

        if ($contact) {
            $expected_token = wp_hash($contact['id'] . $contact['email'] . 'unsubscribe');
            if (hash_equals($expected_token, $token)) {
                CRM_Dev_Email::unsubscribe($contact['email'], $contact['id']);

                // Mostra página de confirmação
                wp_die(
                    '<h2>Descadastro Confirmado</h2>' .
                    '<p>Você foi removido da nossa lista de emails.</p>' .
                    '<p><a href="' . esc_url(home_url()) . '">Voltar ao site</a></p>',
                    'Descadastro Confirmado',
                    array('response' => 200)
                );
            }
        }

        wp_die('Link inválido ou expirado.', 'Erro', array('response' => 400));
    }
});
