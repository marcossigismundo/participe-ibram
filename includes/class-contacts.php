<?php
/**
 * Classe de gerenciamento de contatos
 *
 * @package CRM_Developer
 */

if (!defined('ABSPATH')) {
    exit;
}

class CRM_Dev_Contacts {

    /**
     * Retorna a tabela de contatos
     */
    private static function get_table() {
        $tables = CRM_Dev_Database::get_tables();
        return $tables['contacts'];
    }

    /**
     * Busca contatos com filtros e paginação
     */
    public static function get_contacts($args = array()) {
        global $wpdb;
        $table = self::get_table();

        $defaults = array(
            'per_page' => 20,
            'page' => 1,
            'search' => '',
            'estado' => '',
            'regiao' => '',
            'status' => '',
            'eixo_tematico' => '',
            'categoria_representacao' => '',
            'score_min' => '',
            'score_max' => '',
            'orderby' => 'created_at',
            'order' => 'DESC',
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');
        $values = array();

        // Busca textual
        if (!empty($args['search'])) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = "(nome_completo LIKE %s OR nome_social LIKE %s OR email LIKE %s OR telefone LIKE %s OR whatsapp LIKE %s OR municipio LIKE %s)";
            $values = array_merge($values, array($search, $search, $search, $search, $search, $search));
        }

        // Filtro por estado
        if (!empty($args['estado'])) {
            $where[] = "estado = %s";
            $values[] = $args['estado'];
        }

        // Filtro por região
        if (!empty($args['regiao'])) {
            $where[] = "regiao = %s";
            $values[] = $args['regiao'];
        }

        // Filtro por status
        if (!empty($args['status'])) {
            $where[] = "status = %s";
            $values[] = $args['status'];
        }

        // Filtro por eixo temático
        if (!empty($args['eixo_tematico'])) {
            $where[] = "eixo_tematico LIKE %s";
            $values[] = '%' . $wpdb->esc_like($args['eixo_tematico']) . '%';
        }

        // Filtro por categoria de representação
        if (!empty($args['categoria_representacao'])) {
            $where[] = "categoria_representacao LIKE %s";
            $values[] = '%' . $wpdb->esc_like($args['categoria_representacao']) . '%';
        }

        // Filtro por score
        if ($args['score_min'] !== '') {
            $where[] = "score_engajamento >= %d";
            $values[] = intval($args['score_min']);
        }
        if ($args['score_max'] !== '') {
            $where[] = "score_engajamento <= %d";
            $values[] = intval($args['score_max']);
        }

        $where_clause = implode(' AND ', $where);

        // Total de registros
        $count_query = "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}";
        if (!empty($values)) {
            $count_query = $wpdb->prepare($count_query, $values);
        }
        $total = $wpdb->get_var($count_query);

        // Ordenação
        $allowed_orderby = array('id', 'nome_completo', 'email', 'estado', 'score_engajamento', 'created_at', 'updated_at');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        // Paginação
        $per_page = max(1, intval($args['per_page']));
        $page = max(1, intval($args['page']));
        $offset = ($page - 1) * $per_page;

        // Query principal
        $query = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $values[] = $per_page;
        $values[] = $offset;

        $results = $wpdb->get_results($wpdb->prepare($query, $values), ARRAY_A);

        return array(
            'items' => $results,
            'total' => intval($total),
            'pages' => ceil($total / $per_page),
            'page' => $page,
            'per_page' => $per_page,
        );
    }

    /**
     * Busca um contato por ID
     */
    public static function get_contact($id) {
        global $wpdb;
        $table = self::get_table();

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id),
            ARRAY_A
        );
    }

    /**
     * Busca contato por email
     */
    public static function get_contact_by_email($email) {
        global $wpdb;
        $table = self::get_table();

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE email = %s", $email),
            ARRAY_A
        );
    }

    /**
     * Salva um contato (insert ou update)
     */
    public static function save_contact($data, $id = null) {
        global $wpdb;
        $table = self::get_table();

        // Verifica se a tabela existe, se não, cria
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
        if (!$table_exists) {
            CRM_Dev_Database::create_tables();
        }

        // Sanitização dos dados
        $sanitized = self::sanitize_contact_data($data);

        // Calcula região se não informada
        if (!empty($sanitized['estado']) && empty($sanitized['regiao'])) {
            $sanitized['regiao'] = CRM_Dev_Helpers::get_regiao_by_estado($sanitized['estado']);
        }

        // Calcula score de engajamento
        $sanitized['score_engajamento'] = CRM_Dev_Helpers::calculate_engagement_score($sanitized);

        if ($id) {
            // Update
            $sanitized['atualizado_por'] = get_current_user_id();
            $sanitized['updated_at'] = current_time('mysql');

            $result = $wpdb->update(
                $table,
                $sanitized,
                array('id' => $id),
                self::get_data_formats($sanitized),
                array('%d')
            );

            if ($result !== false) {
                return $id;
            }
        } else {
            // Insert
            $sanitized['criado_por'] = get_current_user_id();
            $sanitized['created_at'] = current_time('mysql');
            $sanitized['updated_at'] = current_time('mysql');

            // Garante que nome_completo existe
            if (empty($sanitized['nome_completo'])) {
                return false;
            }

            $result = $wpdb->insert(
                $table,
                $sanitized,
                self::get_data_formats($sanitized)
            );

            if ($result) {
                return $wpdb->insert_id;
            }

            // Log do erro para debug
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('CRM Dev Insert Error: ' . $wpdb->last_error);
            }
        }

        return false;
    }

    /**
     * Exclui um contato
     */
    public static function delete_contact($id) {
        global $wpdb;
        $table = self::get_table();
        $tables = CRM_Dev_Database::get_tables();

        // Remove interações associadas
        $wpdb->delete($tables['interactions'], array('contact_id' => $id), array('%d'));

        // Remove tags associadas
        $wpdb->delete($tables['contact_tags'], array('contact_id' => $id), array('%d'));

        // Remove logs de consentimento
        $wpdb->delete($tables['consent_logs'], array('contact_id' => $id), array('%d'));

        // Remove o contato
        return $wpdb->delete($table, array('id' => $id), array('%d'));
    }

    /**
     * Exclui múltiplos contatos
     */
    public static function delete_contacts($ids) {
        if (!is_array($ids) || empty($ids)) {
            return false;
        }

        $deleted = 0;
        foreach ($ids as $id) {
            if (self::delete_contact(intval($id))) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Sanitiza dados do contato
     */
    private static function sanitize_contact_data($data) {
        $sanitized = array();

        // Foto ID (inteiro ou null)
        if (isset($data['foto_id'])) {
            $sanitized['foto_id'] = !empty($data['foto_id']) ? intval($data['foto_id']) : null;
        }

        // Campos de texto
        $text_fields = array(
            'nome_completo', 'nome_social', 'municipio', 'comunidade_territorio',
            'coletivos_descricao', 'tempo_atuacao_ambiental', 'lideranca_descricao',
            'cargo_publico', 'vinculacao_institucional', 'observacoes', 'origem'
        );
        foreach ($text_fields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = sanitize_text_field($data[$field]);
            }
        }

        // Email
        if (isset($data['email'])) {
            $sanitized['email'] = sanitize_email($data['email']);
        }

        // Telefones
        if (isset($data['telefone'])) {
            $sanitized['telefone'] = CRM_Dev_Helpers::sanitize_phone($data['telefone']);
        }
        if (isset($data['whatsapp'])) {
            $sanitized['whatsapp'] = CRM_Dev_Helpers::sanitize_phone($data['whatsapp']);
        }

        // Data de nascimento
        if (isset($data['data_nascimento']) && !empty($data['data_nascimento'])) {
            $sanitized['data_nascimento'] = sanitize_text_field($data['data_nascimento']);
        }

        // Estado (2 letras maiúsculas)
        if (isset($data['estado'])) {
            $sanitized['estado'] = strtoupper(substr(sanitize_text_field($data['estado']), 0, 2));
        }

        // Região
        if (isset($data['regiao'])) {
            $sanitized['regiao'] = sanitize_text_field($data['regiao']);
        }

        // Campos enum
        $enum_fields = array(
            'genero' => array('feminino', 'masculino', 'nao_binario', 'outro', 'prefiro_nao_informar'),
            'raca_etnia' => array('branca', 'preta', 'parda', 'amarela', 'indigena', 'prefiro_nao_informar'),
            'pessoa_deficiencia' => array('sim', 'nao'),
            'participa_coletivos' => array('sim', 'nao'),
            'atua_justica_climatica' => array('sim', 'nao'),
            'papel_lideranca' => array('sim', 'nao'),
            'continuar_participando' => array('sim', 'nao', 'talvez'),
            'interesse_formacao' => array('sim', 'nao'),
            'interesse_conteudo' => array('sim', 'nao'),
            'interesse_incidencia' => array('sim', 'nao'),
            'interesse_mobilizacao' => array('sim', 'nao'),
            'interesse_voluntariado' => array('sim', 'nao'),
            'interesse_foruns' => array('sim', 'nao'),
            'consentimento_lgpd' => array('sim', 'nao'),
            'status' => array('ativo', 'inativo', 'pendente'),
        );

        foreach ($enum_fields as $field => $allowed) {
            if (isset($data[$field])) {
                $value = sanitize_text_field($data[$field]);
                if (in_array($value, $allowed)) {
                    $sanitized[$field] = $value;
                }
            }
        }

        // Campos de texto com descrição
        if (isset($data['deficiencia_descricao'])) {
            $sanitized['deficiencia_descricao'] = sanitize_textarea_field($data['deficiencia_descricao']);
        }

        // Campos serializados (arrays)
        $array_fields = array('etapa_participacao', 'tipo_participacao', 'categoria_representacao', 'eixo_tematico');
        foreach ($array_fields as $field) {
            if (isset($data[$field])) {
                if (is_array($data[$field])) {
                    $sanitized[$field] = maybe_serialize(array_map('sanitize_text_field', $data[$field]));
                } else {
                    $sanitized[$field] = sanitize_text_field($data[$field]);
                }
            }
        }

        // Data de consentimento
        if (isset($data['consentimento_lgpd']) && $data['consentimento_lgpd'] === 'sim') {
            if (!isset($data['data_consentimento']) || empty($data['data_consentimento'])) {
                $sanitized['data_consentimento'] = current_time('mysql');
            }
        }

        return $sanitized;
    }

    /**
     * Retorna formatos para wpdb
     */
    private static function get_data_formats($data) {
        $formats = array();
        $int_fields = array('score_engajamento', 'criado_por', 'atualizado_por');

        foreach ($data as $key => $value) {
            if (in_array($key, $int_fields)) {
                $formats[] = '%d';
            } else {
                $formats[] = '%s';
            }
        }

        return $formats;
    }

    /**
     * Retorna estatísticas gerais
     */
    public static function get_stats() {
        global $wpdb;
        $table = self::get_table();

        $stats = array();

        // Total de contatos
        $stats['total'] = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");

        // Por status
        $stats['por_status'] = $wpdb->get_results(
            "SELECT status, COUNT(*) as total FROM {$table} GROUP BY status",
            OBJECT_K
        );

        // Por região
        $stats['por_regiao'] = $wpdb->get_results(
            "SELECT regiao, COUNT(*) as total FROM {$table} WHERE regiao IS NOT NULL AND regiao != '' GROUP BY regiao ORDER BY total DESC",
            ARRAY_A
        );

        // Por estado
        $stats['por_estado'] = $wpdb->get_results(
            "SELECT estado, COUNT(*) as total FROM {$table} WHERE estado IS NOT NULL AND estado != '' GROUP BY estado ORDER BY total DESC",
            ARRAY_A
        );

        // Por score
        $stats['score_alto'] = $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE score_engajamento >= 70");
        $stats['score_medio'] = $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE score_engajamento >= 40 AND score_engajamento < 70");
        $stats['score_baixo'] = $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE score_engajamento < 40");

        // Média de score
        $stats['score_medio_valor'] = $wpdb->get_var("SELECT AVG(score_engajamento) FROM {$table}");

        // Novos este mês
        $stats['novos_mes'] = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE created_at >= %s",
                date('Y-m-01 00:00:00')
            )
        );

        // Por gênero
        $stats['por_genero'] = $wpdb->get_results(
            "SELECT genero, COUNT(*) as total FROM {$table} WHERE genero IS NOT NULL AND genero != '' GROUP BY genero",
            ARRAY_A
        );

        // Por raça/etnia
        $stats['por_raca'] = $wpdb->get_results(
            "SELECT raca_etnia, COUNT(*) as total FROM {$table} WHERE raca_etnia IS NOT NULL AND raca_etnia != '' GROUP BY raca_etnia",
            ARRAY_A
        );

        return $stats;
    }

    /**
     * Handler AJAX - Buscar contatos
     */
    public static function ajax_get_contacts() {
        check_ajax_referer('crm_dev_nonce', 'nonce');

        if (!CRM_Dev_Helpers::can_user('edit_crm_contacts')) {
            wp_send_json_error(array('message' => 'Sem permissão'));
        }

        $args = array(
            'per_page' => isset($_POST['per_page']) ? intval($_POST['per_page']) : 20,
            'page' => isset($_POST['page']) ? intval($_POST['page']) : 1,
            'search' => isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '',
            'estado' => isset($_POST['estado']) ? sanitize_text_field($_POST['estado']) : '',
            'regiao' => isset($_POST['regiao']) ? sanitize_text_field($_POST['regiao']) : '',
            'status' => isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '',
            'eixo_tematico' => isset($_POST['eixo_tematico']) ? sanitize_text_field($_POST['eixo_tematico']) : '',
            'categoria_representacao' => isset($_POST['categoria_representacao']) ? sanitize_text_field($_POST['categoria_representacao']) : '',
            'orderby' => isset($_POST['orderby']) ? sanitize_text_field($_POST['orderby']) : 'created_at',
            'order' => isset($_POST['order']) ? sanitize_text_field($_POST['order']) : 'DESC',
        );

        $result = self::get_contacts($args);
        wp_send_json_success($result);
    }

    /**
     * Handler AJAX - Buscar contato
     */
    public static function ajax_get_contact() {
        check_ajax_referer('crm_dev_nonce', 'nonce');

        if (!CRM_Dev_Helpers::can_user('edit_crm_contacts')) {
            wp_send_json_error(array('message' => 'Sem permissão'));
        }

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

        if (!$id) {
            wp_send_json_error(array('message' => 'ID inválido'));
        }

        $contact = self::get_contact($id);

        if (!$contact) {
            wp_send_json_error(array('message' => 'Contato não encontrado'));
        }

        // Deserializa campos array
        $array_fields = array('etapa_participacao', 'tipo_participacao', 'categoria_representacao', 'eixo_tematico');
        foreach ($array_fields as $field) {
            if (!empty($contact[$field])) {
                $unserialized = maybe_unserialize($contact[$field]);
                $contact[$field] = is_array($unserialized) ? $unserialized : array($unserialized);
            }
        }

        wp_send_json_success($contact);
    }

    /**
     * Handler AJAX - Salvar contato
     */
    public static function ajax_save_contact() {
        check_ajax_referer('crm_dev_nonce', 'nonce');

        if (!CRM_Dev_Helpers::can_user('edit_crm_contacts')) {
            wp_send_json_error(array('message' => 'Sem permissão'));
        }

        $id = isset($_POST['id']) ? intval($_POST['id']) : null;
        $data = isset($_POST['data']) ? $_POST['data'] : array();

        if (empty($data['nome_completo'])) {
            wp_send_json_error(array('message' => 'Nome completo é obrigatório'));
        }

        // Verifica email duplicado
        if (!empty($data['email'])) {
            $existing = self::get_contact_by_email($data['email']);
            if ($existing && (!$id || $existing['id'] != $id)) {
                wp_send_json_error(array('message' => 'Este email já está cadastrado'));
            }
        }

        $result = self::save_contact($data, $id);

        if ($result) {
            wp_send_json_success(array(
                'id' => $result,
                'message' => $id ? 'Contato atualizado com sucesso!' : 'Contato criado com sucesso!'
            ));
        } else {
            wp_send_json_error(array('message' => 'Erro ao salvar contato'));
        }
    }

    /**
     * Handler AJAX - Excluir contato
     */
    public static function ajax_delete_contact() {
        check_ajax_referer('crm_dev_nonce', 'nonce');

        if (!CRM_Dev_Helpers::can_user('delete_crm_contacts')) {
            wp_send_json_error(array('message' => 'Sem permissão'));
        }

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

        if (!$id) {
            wp_send_json_error(array('message' => 'ID inválido'));
        }

        if (self::delete_contact($id)) {
            wp_send_json_success(array('message' => 'Contato excluído com sucesso!'));
        } else {
            wp_send_json_error(array('message' => 'Erro ao excluir contato'));
        }
    }
}
