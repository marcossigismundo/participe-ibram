<?php
/**
 * Classe de gerenciamento de interações
 *
 * @package CRM_Developer
 */

if (!defined('ABSPATH')) {
    exit;
}

class CRM_Dev_Interactions {

    /**
     * Retorna a tabela de interações
     */
    private static function get_table() {
        $tables = CRM_Dev_Database::get_tables();
        return $tables['interactions'];
    }

    /**
     * Busca interações de um contato
     */
    public static function get_interactions($contact_id, $args = array()) {
        global $wpdb;
        $table = self::get_table();

        $defaults = array(
            'per_page' => 50,
            'page' => 1,
            'tipo' => '',
            'orderby' => 'created_at',
            'order' => 'DESC',
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('contact_id = %d');
        $values = array($contact_id);

        if (!empty($args['tipo'])) {
            $where[] = 'tipo = %s';
            $values[] = $args['tipo'];
        }

        $where_clause = implode(' AND ', $where);
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        if (!$orderby) {
            $orderby = 'created_at DESC';
        }

        $per_page = max(1, intval($args['per_page']));
        $page = max(1, intval($args['page']));
        $offset = ($page - 1) * $per_page;

        $values[] = $per_page;
        $values[] = $offset;

        $query = $wpdb->prepare(
            "SELECT i.*, u.display_name as user_name
            FROM {$table} i
            LEFT JOIN {$wpdb->users} u ON i.created_by = u.ID
            WHERE {$where_clause}
            ORDER BY {$orderby}
            LIMIT %d OFFSET %d",
            $values
        );

        return $wpdb->get_results($query, ARRAY_A);
    }

    /**
     * Busca uma interação por ID
     */
    public static function get_interaction($id) {
        global $wpdb;
        $table = self::get_table();

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id),
            ARRAY_A
        );
    }

    /**
     * Salva uma interação
     */
    public static function save_interaction($data, $id = null) {
        global $wpdb;
        $table = self::get_table();
        $tables = CRM_Dev_Database::get_tables();

        // Processar anexos
        $anexos = null;
        if (isset($data['anexos'])) {
            if (is_string($data['anexos']) && !empty($data['anexos'])) {
                // Verificar se é JSON válido
                $decoded = json_decode($data['anexos'], true);
                if (is_array($decoded)) {
                    // Sanitizar IDs
                    $decoded = array_map('intval', $decoded);
                    $decoded = array_filter($decoded);
                    $anexos = !empty($decoded) ? json_encode($decoded) : null;
                }
            } elseif (is_array($data['anexos'])) {
                $decoded = array_map('intval', $data['anexos']);
                $decoded = array_filter($decoded);
                $anexos = !empty($decoded) ? json_encode($decoded) : null;
            }
        }

        $sanitized = array(
            'contact_id' => intval($data['contact_id']),
            'tipo' => sanitize_text_field($data['tipo']),
            'titulo' => sanitize_text_field($data['titulo']),
            'descricao' => sanitize_textarea_field($data['descricao'] ?? ''),
            'resultado' => sanitize_text_field($data['resultado'] ?? ''),
            'proxima_acao' => sanitize_textarea_field($data['proxima_acao'] ?? ''),
            'data_proxima_acao' => !empty($data['data_proxima_acao']) ? sanitize_text_field($data['data_proxima_acao']) : null,
            'anexos' => $anexos,
        );

        if ($id) {
            $result = $wpdb->update(
                $table,
                $sanitized,
                array('id' => $id),
                array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s'),
                array('%d')
            );
        } else {
            $sanitized['created_by'] = get_current_user_id();
            $sanitized['created_at'] = current_time('mysql');

            $result = $wpdb->insert(
                $table,
                $sanitized,
                array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s')
            );

            if ($result) {
                $id = $wpdb->insert_id;

                // Atualiza última interação do contato
                $wpdb->update(
                    $tables['contacts'],
                    array('ultima_interacao' => current_time('mysql')),
                    array('id' => $sanitized['contact_id']),
                    array('%s'),
                    array('%d')
                );
            }
        }

        return $id ?? false;
    }

    /**
     * Exclui uma interação
     */
    public static function delete_interaction($id) {
        global $wpdb;
        $table = self::get_table();

        return $wpdb->delete($table, array('id' => $id), array('%d'));
    }

    /**
     * Retorna próximas ações pendentes
     */
    public static function get_pending_actions($days = 7) {
        global $wpdb;
        $table = self::get_table();
        $contacts_table = CRM_Dev_Database::get_tables()['contacts'];

        $date_limit = date('Y-m-d', strtotime("+{$days} days"));

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT i.*, c.nome_completo, c.email, c.telefone
                FROM {$table} i
                INNER JOIN {$contacts_table} c ON i.contact_id = c.id
                WHERE i.data_proxima_acao IS NOT NULL
                AND i.data_proxima_acao <= %s
                AND i.data_proxima_acao >= CURDATE()
                ORDER BY i.data_proxima_acao ASC",
                $date_limit
            ),
            ARRAY_A
        );
    }

    /**
     * Retorna ações atrasadas
     */
    public static function get_overdue_actions() {
        global $wpdb;
        $table = self::get_table();
        $contacts_table = CRM_Dev_Database::get_tables()['contacts'];

        return $wpdb->get_results(
            "SELECT i.*, c.nome_completo, c.email, c.telefone
            FROM {$table} i
            INNER JOIN {$contacts_table} c ON i.contact_id = c.id
            WHERE i.data_proxima_acao IS NOT NULL
            AND i.data_proxima_acao < CURDATE()
            ORDER BY i.data_proxima_acao ASC",
            ARRAY_A
        );
    }

    /**
     * Conta interações por tipo
     */
    public static function count_by_type($contact_id = null) {
        global $wpdb;
        $table = self::get_table();

        $where = '';
        if ($contact_id) {
            $where = $wpdb->prepare(" WHERE contact_id = %d", $contact_id);
        }

        return $wpdb->get_results(
            "SELECT tipo, COUNT(*) as total FROM {$table} {$where} GROUP BY tipo ORDER BY total DESC",
            ARRAY_A
        );
    }

    /**
     * Handler AJAX - Buscar interações
     */
    public static function ajax_get_interactions() {
        check_ajax_referer('crm_dev_nonce', 'nonce');

        if (!CRM_Dev_Helpers::can_user('edit_crm_contacts')) {
            wp_send_json_error(array('message' => 'Sem permissão'));
        }

        $contact_id = isset($_POST['contact_id']) ? intval($_POST['contact_id']) : 0;

        if (!$contact_id) {
            wp_send_json_error(array('message' => 'ID do contato inválido'));
        }

        $interactions = self::get_interactions($contact_id);

        wp_send_json_success(array('interactions' => $interactions));
    }

    /**
     * Handler AJAX - Salvar interação
     */
    public static function ajax_save_interaction() {
        check_ajax_referer('crm_dev_nonce', 'nonce');

        if (!CRM_Dev_Helpers::can_user('edit_crm_contacts')) {
            wp_send_json_error(array('message' => 'Sem permissão'));
        }

        $data = isset($_POST['data']) ? $_POST['data'] : array();

        if (empty($data['contact_id']) || empty($data['tipo']) || empty($data['titulo'])) {
            wp_send_json_error(array('message' => 'Dados obrigatórios não informados'));
        }

        $id = isset($data['id']) && !empty($data['id']) ? intval($data['id']) : null;
        $result = self::save_interaction($data, $id);

        if ($result) {
            wp_send_json_success(array(
                'id' => $result,
                'message' => $id ? 'Interação atualizada com sucesso!' : 'Interação salva com sucesso!'
            ));
        } else {
            wp_send_json_error(array('message' => 'Erro ao salvar interação'));
        }
    }

    /**
     * Handler AJAX - Excluir interação
     */
    public static function ajax_delete_interaction() {
        check_ajax_referer('crm_dev_nonce', 'nonce');

        if (!CRM_Dev_Helpers::can_user('edit_crm_contacts')) {
            wp_send_json_error(array('message' => 'Sem permissão'));
        }

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

        if (!$id) {
            wp_send_json_error(array('message' => 'ID da interação inválido'));
        }

        $result = self::delete_interaction($id);

        if ($result) {
            wp_send_json_success(array('message' => 'Interação excluída com sucesso!'));
        } else {
            wp_send_json_error(array('message' => 'Erro ao excluir interação'));
        }
    }

    /**
     * Handler AJAX - Obter informações de anexo
     */
    public static function ajax_get_attachment_info() {
        check_ajax_referer('crm_dev_nonce', 'nonce');

        if (!CRM_Dev_Helpers::can_user('edit_crm_contacts')) {
            wp_send_json_error(array('message' => 'Sem permissão'));
        }

        $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;

        if (!$attachment_id) {
            wp_send_json_error(array('message' => 'ID do anexo inválido'));
        }

        $attachment = get_post($attachment_id);

        if (!$attachment) {
            wp_send_json_error(array('message' => 'Anexo não encontrado'));
        }

        $thumb_url = wp_get_attachment_thumb_url($attachment_id);
        $url = wp_get_attachment_url($attachment_id);
        $type = get_post_mime_type($attachment_id);

        wp_send_json_success(array(
            'id' => $attachment_id,
            'title' => get_the_title($attachment_id),
            'url' => $url,
            'thumb_url' => $thumb_url ?: $url,
            'type' => $type
        ));
    }
}
