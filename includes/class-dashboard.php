<?php
/**
 * Classe de Dashboard e análises
 *
 * @package CRM_Developer
 */

if (!defined('ABSPATH')) {
    exit;
}

class CRM_Dev_Dashboard {

    /**
     * Retorna dados completos do dashboard
     */
    public static function get_dashboard_data() {
        $data = array();

        // Estatísticas gerais
        $data['stats'] = CRM_Dev_Contacts::get_stats();

        // Interações recentes
        $data['recent_interactions'] = self::get_recent_interactions(5);

        // Próximas ações
        $data['pending_actions'] = CRM_Dev_Interactions::get_pending_actions(7);
        $data['overdue_actions'] = CRM_Dev_Interactions::get_overdue_actions();

        // Contatos recentes
        $data['recent_contacts'] = self::get_recent_contacts(5);

        // Sugestões de melhoria
        $data['suggestions'] = self::get_improvement_suggestions();

        // Dados para gráficos
        $data['charts'] = self::get_chart_data();

        return $data;
    }

    /**
     * Retorna contatos recentes
     */
    public static function get_recent_contacts($limit = 10) {
        global $wpdb;
        $table = CRM_Dev_Database::get_tables()['contacts'];

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, nome_completo, email, estado, score_engajamento, created_at
                FROM {$table}
                ORDER BY created_at DESC
                LIMIT %d",
                $limit
            ),
            ARRAY_A
        );
    }

    /**
     * Retorna interações recentes
     */
    public static function get_recent_interactions($limit = 10) {
        global $wpdb;
        $tables = CRM_Dev_Database::get_tables();

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT i.*, c.nome_completo, u.display_name as user_name
                FROM {$tables['interactions']} i
                INNER JOIN {$tables['contacts']} c ON i.contact_id = c.id
                LEFT JOIN {$wpdb->users} u ON i.created_by = u.ID
                ORDER BY i.created_at DESC
                LIMIT %d",
                $limit
            ),
            ARRAY_A
        );
    }

    /**
     * Gera sugestões de melhoria baseadas nos dados
     */
    public static function get_improvement_suggestions() {
        global $wpdb;
        $tables = CRM_Dev_Database::get_tables();
        $suggestions = array();

        // Contatos sem email
        $no_email = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$tables['contacts']} WHERE email IS NULL OR email = ''"
        );
        if ($no_email > 0) {
            $suggestions[] = array(
                'type' => 'warning',
                'icon' => 'envelope',
                'title' => 'Contatos sem Email',
                'description' => "{$no_email} contatos não possuem email cadastrado. Isso dificulta a comunicação direta.",
                'action' => 'Atualize os cadastros para incluir emails válidos.',
                'priority' => 'alta',
            );
        }

        // Contatos sem telefone/whatsapp
        $no_phone = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$tables['contacts']} WHERE (telefone IS NULL OR telefone = '') AND (whatsapp IS NULL OR whatsapp = '')"
        );
        if ($no_phone > 0) {
            $suggestions[] = array(
                'type' => 'warning',
                'icon' => 'phone',
                'title' => 'Contatos sem Telefone',
                'description' => "{$no_phone} contatos não possuem telefone ou WhatsApp cadastrado.",
                'action' => 'Complete os dados de contato para facilitar a mobilização.',
                'priority' => 'média',
            );
        }

        // Estados sem representatividade
        $estados_cobertos = $wpdb->get_col(
            "SELECT DISTINCT estado FROM {$tables['contacts']} WHERE estado IS NOT NULL AND estado != ''"
        );
        $todos_estados = array_keys(CRM_Dev_Helpers::get_estados());
        $estados_faltantes = array_diff($todos_estados, $estados_cobertos);

        if (count($estados_faltantes) > 0) {
            $estados_nomes = array();
            $estados_lista = CRM_Dev_Helpers::get_estados();
            foreach (array_slice($estados_faltantes, 0, 5) as $uf) {
                $estados_nomes[] = $estados_lista[$uf];
            }
            $mais = count($estados_faltantes) > 5 ? ' e mais ' . (count($estados_faltantes) - 5) : '';

            $suggestions[] = array(
                'type' => 'info',
                'icon' => 'map-marker-alt',
                'title' => 'Lacunas Territoriais',
                'description' => count($estados_faltantes) . ' estados sem representantes: ' . implode(', ', $estados_nomes) . $mais,
                'action' => 'Busque ampliar a base em regiões não cobertas.',
                'priority' => 'média',
            );
        }

        // Contatos com baixo engajamento
        $low_score = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$tables['contacts']} WHERE score_engajamento < 30"
        );
        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$tables['contacts']}");

        if ($total > 0 && ($low_score / $total) > 0.3) {
            $suggestions[] = array(
                'type' => 'warning',
                'icon' => 'chart-line',
                'title' => 'Alto Índice de Baixo Engajamento',
                'description' => round(($low_score / $total) * 100) . "% dos contatos têm score de engajamento abaixo de 30.",
                'action' => 'Considere ações de reengajamento ou complete dados faltantes.',
                'priority' => 'alta',
            );
        }

        // Contatos de alto potencial sem interações
        $high_potential_no_interaction = $wpdb->get_var(
            "SELECT COUNT(*)
            FROM {$tables['contacts']} c
            LEFT JOIN {$tables['interactions']} i ON c.id = i.contact_id
            WHERE c.score_engajamento >= 70
            AND i.id IS NULL"
        );
        if ($high_potential_no_interaction > 0) {
            $suggestions[] = array(
                'type' => 'success',
                'icon' => 'star',
                'title' => 'Oportunidade de Engajamento',
                'description' => "{$high_potential_no_interaction} contatos de alto potencial ainda não tiveram nenhuma interação registrada.",
                'action' => 'Priorize o contato com esses perfis promissores.',
                'priority' => 'alta',
            );
        }

        // Ações atrasadas
        $overdue = count(CRM_Dev_Interactions::get_overdue_actions());
        if ($overdue > 0) {
            $suggestions[] = array(
                'type' => 'danger',
                'icon' => 'clock',
                'title' => 'Ações Atrasadas',
                'description' => "{$overdue} ações de follow-up estão atrasadas.",
                'action' => 'Revise e execute as ações pendentes.',
                'priority' => 'urgente',
            );
        }

        // Contatos sem consentimento LGPD
        $no_consent = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$tables['contacts']} WHERE consentimento_lgpd != 'sim'"
        );
        if ($no_consent > 0) {
            $suggestions[] = array(
                'type' => 'danger',
                'icon' => 'shield-alt',
                'title' => 'Consentimento LGPD Pendente',
                'description' => "{$no_consent} contatos não possuem consentimento LGPD registrado.",
                'action' => 'Obtenha o consentimento para estar em conformidade com a legislação.',
                'priority' => 'urgente',
            );
        }

        // Ordena por prioridade
        $priority_order = array('urgente' => 0, 'alta' => 1, 'média' => 2, 'baixa' => 3);
        usort($suggestions, function ($a, $b) use ($priority_order) {
            return ($priority_order[$a['priority']] ?? 9) - ($priority_order[$b['priority']] ?? 9);
        });

        return $suggestions;
    }

    /**
     * Retorna dados para gráficos
     */
    public static function get_chart_data() {
        global $wpdb;
        $tables = CRM_Dev_Database::get_tables();
        $charts = array();

        // Gráfico por região
        $charts['by_region'] = $wpdb->get_results(
            "SELECT regiao as label, COUNT(*) as value
            FROM {$tables['contacts']}
            WHERE regiao IS NOT NULL AND regiao != ''
            GROUP BY regiao
            ORDER BY value DESC",
            ARRAY_A
        );

        // Gráfico por estado (top 10)
        $charts['by_state'] = $wpdb->get_results(
            "SELECT estado as label, COUNT(*) as value
            FROM {$tables['contacts']}
            WHERE estado IS NOT NULL AND estado != ''
            GROUP BY estado
            ORDER BY value DESC
            LIMIT 10",
            ARRAY_A
        );

        // Gráfico por gênero
        $charts['by_gender'] = $wpdb->get_results(
            "SELECT genero as label, COUNT(*) as value
            FROM {$tables['contacts']}
            WHERE genero IS NOT NULL AND genero != ''
            GROUP BY genero",
            ARRAY_A
        );

        // Gráfico por raça
        $charts['by_race'] = $wpdb->get_results(
            "SELECT raca_etnia as label, COUNT(*) as value
            FROM {$tables['contacts']}
            WHERE raca_etnia IS NOT NULL AND raca_etnia != ''
            GROUP BY raca_etnia",
            ARRAY_A
        );

        // Gráfico por score
        $charts['by_score'] = array(
            array('label' => 'Alto (70+)', 'value' => $wpdb->get_var("SELECT COUNT(*) FROM {$tables['contacts']} WHERE score_engajamento >= 70")),
            array('label' => 'Médio (40-69)', 'value' => $wpdb->get_var("SELECT COUNT(*) FROM {$tables['contacts']} WHERE score_engajamento >= 40 AND score_engajamento < 70")),
            array('label' => 'Baixo (<40)', 'value' => $wpdb->get_var("SELECT COUNT(*) FROM {$tables['contacts']} WHERE score_engajamento < 40")),
        );

        // Cadastros nos últimos 12 meses
        $charts['monthly_registrations'] = array();
        for ($i = 11; $i >= 0; $i--) {
            $month_start = date('Y-m-01', strtotime("-{$i} months"));
            $month_end = date('Y-m-t', strtotime("-{$i} months"));
            $month_label = date_i18n('M/Y', strtotime("-{$i} months"));

            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$tables['contacts']} WHERE created_at >= %s AND created_at <= %s",
                $month_start,
                $month_end . ' 23:59:59'
            ));

            $charts['monthly_registrations'][] = array(
                'label' => $month_label,
                'value' => intval($count),
            );
        }

        // Interações por tipo
        $charts['interactions_by_type'] = $wpdb->get_results(
            "SELECT tipo as label, COUNT(*) as value
            FROM {$tables['interactions']}
            GROUP BY tipo
            ORDER BY value DESC",
            ARRAY_A
        );

        return $charts;
    }

    /**
     * Retorna dados públicos (anonimizados)
     */
    public static function get_public_dashboard_data() {
        global $wpdb;
        $tables = CRM_Dev_Database::get_tables();
        $data = array();

        // Total geral
        $data['total'] = $wpdb->get_var("SELECT COUNT(*) FROM {$tables['contacts']}");

        // Por região
        $data['by_region'] = $wpdb->get_results(
            "SELECT regiao as label, COUNT(*) as value
            FROM {$tables['contacts']}
            WHERE regiao IS NOT NULL AND regiao != ''
            GROUP BY regiao
            ORDER BY value DESC",
            ARRAY_A
        );

        // Por estado
        $data['by_state'] = $wpdb->get_results(
            "SELECT estado as label, COUNT(*) as value
            FROM {$tables['contacts']}
            WHERE estado IS NOT NULL AND estado != ''
            GROUP BY estado
            ORDER BY value DESC",
            ARRAY_A
        );

        // Por gênero (percentual)
        $data['by_gender'] = $wpdb->get_results(
            "SELECT genero as label, COUNT(*) as value,
            ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM {$tables['contacts']})), 1) as percentage
            FROM {$tables['contacts']}
            WHERE genero IS NOT NULL AND genero != ''
            GROUP BY genero",
            ARRAY_A
        );

        // Por raça (percentual)
        $data['by_race'] = $wpdb->get_results(
            "SELECT raca_etnia as label, COUNT(*) as value,
            ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM {$tables['contacts']})), 1) as percentage
            FROM {$tables['contacts']}
            WHERE raca_etnia IS NOT NULL AND raca_etnia != ''
            GROUP BY raca_etnia",
            ARRAY_A
        );

        // Eixos temáticos (simplificado)
        $data['interests'] = array(
            'mobilizacao' => $wpdb->get_var("SELECT COUNT(*) FROM {$tables['contacts']} WHERE interesse_mobilizacao = 'sim'"),
            'formacao' => $wpdb->get_var("SELECT COUNT(*) FROM {$tables['contacts']} WHERE interesse_formacao = 'sim'"),
            'voluntariado' => $wpdb->get_var("SELECT COUNT(*) FROM {$tables['contacts']} WHERE interesse_voluntariado = 'sim'"),
        );

        return $data;
    }

    /**
     * Handler AJAX - Buscar dados do dashboard
     */
    public static function ajax_get_data() {
        check_ajax_referer('crm_dev_nonce', 'nonce');

        if (!CRM_Dev_Helpers::can_user('view_crm_dashboard')) {
            wp_send_json_error(array('message' => 'Sem permissão'));
        }

        $data = self::get_dashboard_data();
        wp_send_json_success($data);
    }

    /**
     * Handler AJAX - Buscar dados de relatório com filtros
     */
    public static function ajax_get_report_data() {
        check_ajax_referer('crm_dev_nonce', 'nonce');

        if (!CRM_Dev_Helpers::can_user('view_crm_dashboard')) {
            wp_send_json_error(array('message' => 'Sem permissão'));
        }

        $filters = isset($_POST['filters']) ? $_POST['filters'] : array();
        $data = self::get_filtered_report_data($filters);
        wp_send_json_success($data);
    }

    /**
     * Retorna dados filtrados para relatórios
     */
    public static function get_filtered_report_data($filters = array()) {
        global $wpdb;
        $tables = CRM_Dev_Database::get_tables();
        $data = array();

        // Constrói WHERE clause baseado nos filtros
        $where = array('1=1');
        $params = array();

        // Filtro de período
        if (!empty($filters['period']) && $filters['period'] !== 'all') {
            if ($filters['period'] === 'custom') {
                if (!empty($filters['date_from'])) {
                    $where[] = "created_at >= %s";
                    $params[] = $filters['date_from'] . ' 00:00:00';
                }
                if (!empty($filters['date_to'])) {
                    $where[] = "created_at <= %s";
                    $params[] = $filters['date_to'] . ' 23:59:59';
                }
            } else {
                $days = intval($filters['period']);
                $where[] = "created_at >= %s";
                $params[] = date('Y-m-d', strtotime("-{$days} days"));
            }
        }

        // Filtro de região
        if (!empty($filters['regiao'])) {
            $where[] = "regiao = %s";
            $params[] = sanitize_text_field($filters['regiao']);
        }

        // Filtro de estado
        if (!empty($filters['estado'])) {
            $where[] = "estado = %s";
            $params[] = sanitize_text_field($filters['estado']);
        }

        // Filtro de status
        if (!empty($filters['status'])) {
            $where[] = "status = %s";
            $params[] = sanitize_text_field($filters['status']);
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

        // Filtro de gênero
        if (!empty($filters['genero'])) {
            $where[] = "genero = %s";
            $params[] = sanitize_text_field($filters['genero']);
        }

        // Filtro de raça
        if (!empty($filters['raca'])) {
            $where[] = "raca_etnia = %s";
            $params[] = sanitize_text_field($filters['raca']);
        }

        // Filtro de eixo temático
        if (!empty($filters['eixo'])) {
            $where[] = "eixo_tematico LIKE %s";
            $params[] = '%' . sanitize_text_field($filters['eixo']) . '%';
        }

        $where_clause = implode(' AND ', $where);
        $table = $tables['contacts'];

        // Prepara query base
        $base_query = "FROM {$table} WHERE {$where_clause}";
        if (!empty($params)) {
            $base_query = $wpdb->prepare($base_query, ...$params);
        }

        // Resumo
        $data['summary'] = array(
            'total' => intval($wpdb->get_var("SELECT COUNT(*) {$base_query}")),
            'estados' => intval($wpdb->get_var("SELECT COUNT(DISTINCT estado) {$base_query}")),
            'score_medio' => floatval($wpdb->get_var("SELECT AVG(score_engajamento) {$base_query}") ?: 0),
            'lgpd_percent' => 0
        );

        if ($data['summary']['total'] > 0) {
            $lgpd_count = intval($wpdb->get_var("SELECT COUNT(*) {$base_query} AND consentimento_lgpd = 'sim'"));
            $data['summary']['lgpd_percent'] = ($lgpd_count / $data['summary']['total']) * 100;
        }

        // Dados por região
        $data['by_region'] = $wpdb->get_results(
            "SELECT regiao as label, COUNT(*) as value {$base_query} AND regiao IS NOT NULL AND regiao != '' GROUP BY regiao ORDER BY value DESC",
            ARRAY_A
        );

        // Dados por estado
        $data['by_state'] = $wpdb->get_results(
            "SELECT estado as label, COUNT(*) as value {$base_query} AND estado IS NOT NULL AND estado != '' GROUP BY estado ORDER BY value DESC",
            ARRAY_A
        );

        // Estados faltantes
        $estados_cobertos = array_column($data['by_state'], 'label');
        $todos_estados = array_keys(CRM_Dev_Helpers::get_estados());
        $data['missing_states'] = array_values(array_diff($todos_estados, $estados_cobertos));

        // Dados por gênero
        $data['by_gender'] = $wpdb->get_results(
            "SELECT genero as label, COUNT(*) as value {$base_query} AND genero IS NOT NULL AND genero != '' GROUP BY genero",
            ARRAY_A
        );

        // Dados por raça
        $data['by_race'] = $wpdb->get_results(
            "SELECT raca_etnia as label, COUNT(*) as value {$base_query} AND raca_etnia IS NOT NULL AND raca_etnia != '' GROUP BY raca_etnia",
            ARRAY_A
        );

        // Dados por faixa etária
        $data['by_age'] = array(
            array('label' => '18-24', 'value' => intval($wpdb->get_var("SELECT COUNT(*) {$base_query} AND data_nascimento IS NOT NULL AND TIMESTAMPDIFF(YEAR, data_nascimento, CURDATE()) BETWEEN 18 AND 24"))),
            array('label' => '25-34', 'value' => intval($wpdb->get_var("SELECT COUNT(*) {$base_query} AND data_nascimento IS NOT NULL AND TIMESTAMPDIFF(YEAR, data_nascimento, CURDATE()) BETWEEN 25 AND 34"))),
            array('label' => '35-44', 'value' => intval($wpdb->get_var("SELECT COUNT(*) {$base_query} AND data_nascimento IS NOT NULL AND TIMESTAMPDIFF(YEAR, data_nascimento, CURDATE()) BETWEEN 35 AND 44"))),
            array('label' => '45-54', 'value' => intval($wpdb->get_var("SELECT COUNT(*) {$base_query} AND data_nascimento IS NOT NULL AND TIMESTAMPDIFF(YEAR, data_nascimento, CURDATE()) BETWEEN 45 AND 54"))),
            array('label' => '55-64', 'value' => intval($wpdb->get_var("SELECT COUNT(*) {$base_query} AND data_nascimento IS NOT NULL AND TIMESTAMPDIFF(YEAR, data_nascimento, CURDATE()) BETWEEN 55 AND 64"))),
            array('label' => '65+', 'value' => intval($wpdb->get_var("SELECT COUNT(*) {$base_query} AND data_nascimento IS NOT NULL AND TIMESTAMPDIFF(YEAR, data_nascimento, CURDATE()) >= 65"))),
        );

        // Dados de participação - Etapas
        $etapas_raw = $wpdb->get_col("SELECT etapa_participacao {$base_query} AND etapa_participacao IS NOT NULL AND etapa_participacao != ''");
        $etapas_count = array();
        foreach ($etapas_raw as $etapas_str) {
            $etapas_arr = maybe_unserialize($etapas_str);
            if (!is_array($etapas_arr)) $etapas_arr = array($etapas_str);
            foreach ($etapas_arr as $etapa) {
                $etapas_count[$etapa] = ($etapas_count[$etapa] ?? 0) + 1;
            }
        }
        $data['by_etapa'] = array();
        foreach ($etapas_count as $label => $value) {
            $data['by_etapa'][] = array('label' => $label, 'value' => $value);
        }
        usort($data['by_etapa'], fn($a, $b) => $b['value'] - $a['value']);

        // Tipos de participação
        $tipos_raw = $wpdb->get_col("SELECT tipo_participacao {$base_query} AND tipo_participacao IS NOT NULL AND tipo_participacao != ''");
        $tipos_count = array();
        foreach ($tipos_raw as $tipos_str) {
            $tipos_arr = maybe_unserialize($tipos_str);
            if (!is_array($tipos_arr)) $tipos_arr = array($tipos_str);
            foreach ($tipos_arr as $tipo) {
                $tipos_count[$tipo] = ($tipos_count[$tipo] ?? 0) + 1;
            }
        }
        $data['by_tipo_participacao'] = array();
        foreach ($tipos_count as $label => $value) {
            $data['by_tipo_participacao'][] = array('label' => $label, 'value' => $value);
        }
        usort($data['by_tipo_participacao'], fn($a, $b) => $b['value'] - $a['value']);

        // Categorias de representação
        $cats_raw = $wpdb->get_col("SELECT categoria_representacao {$base_query} AND categoria_representacao IS NOT NULL AND categoria_representacao != ''");
        $cats_count = array();
        foreach ($cats_raw as $cats_str) {
            $cats_arr = maybe_unserialize($cats_str);
            if (!is_array($cats_arr)) $cats_arr = array($cats_str);
            foreach ($cats_arr as $cat) {
                $cats_count[$cat] = ($cats_count[$cat] ?? 0) + 1;
            }
        }
        $data['by_categoria'] = array();
        foreach ($cats_count as $label => $value) {
            $data['by_categoria'][] = array('label' => $label, 'value' => $value);
        }
        usort($data['by_categoria'], fn($a, $b) => $b['value'] - $a['value']);

        // Eixos temáticos
        $eixos_raw = $wpdb->get_col("SELECT eixo_tematico {$base_query} AND eixo_tematico IS NOT NULL AND eixo_tematico != ''");
        $eixos_count = array();
        foreach ($eixos_raw as $eixos_str) {
            $eixos_arr = maybe_unserialize($eixos_str);
            if (!is_array($eixos_arr)) $eixos_arr = array($eixos_str);
            foreach ($eixos_arr as $eixo) {
                $eixos_count[$eixo] = ($eixos_count[$eixo] ?? 0) + 1;
            }
        }
        $data['by_eixo'] = array();
        foreach ($eixos_count as $label => $value) {
            $data['by_eixo'][] = array('label' => $label, 'value' => $value);
        }
        usort($data['by_eixo'], fn($a, $b) => $b['value'] - $a['value']);

        // Engajamento
        $data['engagement'] = array(
            'alto' => intval($wpdb->get_var("SELECT COUNT(*) {$base_query} AND score_engajamento >= 70")),
            'medio' => intval($wpdb->get_var("SELECT COUNT(*) {$base_query} AND score_engajamento >= 40 AND score_engajamento < 70")),
            'baixo' => intval($wpdb->get_var("SELECT COUNT(*) {$base_query} AND score_engajamento < 40")),
        );

        // Engajamento por região
        $regioes = CRM_Dev_Helpers::get_regioes();
        $data['engagement_by_region'] = array();
        foreach ($regioes as $regiao) {
            $data['engagement_by_region'][] = array(
                'label' => $regiao,
                'alto' => intval($wpdb->get_var("SELECT COUNT(*) {$base_query} AND regiao = '{$regiao}' AND score_engajamento >= 70")),
                'medio' => intval($wpdb->get_var("SELECT COUNT(*) {$base_query} AND regiao = '{$regiao}' AND score_engajamento >= 40 AND score_engajamento < 70")),
                'baixo' => intval($wpdb->get_var("SELECT COUNT(*) {$base_query} AND regiao = '{$regiao}' AND score_engajamento < 40")),
            );
        }

        // Top contatos por score
        $data['top_contacts'] = $wpdb->get_results(
            "SELECT nome_completo as nome, score_engajamento as score {$base_query} ORDER BY score_engajamento DESC LIMIT 10",
            ARRAY_A
        );

        // Mobilização
        $data['mobilization'] = array(
            'total' => $data['summary']['total'],
            'interesse_formacao' => intval($wpdb->get_var("SELECT COUNT(*) {$base_query} AND interesse_formacao = 'sim'")),
            'interesse_conteudo' => intval($wpdb->get_var("SELECT COUNT(*) {$base_query} AND interesse_conteudo = 'sim'")),
            'interesse_incidencia' => intval($wpdb->get_var("SELECT COUNT(*) {$base_query} AND interesse_incidencia = 'sim'")),
            'interesse_mobilizacao' => intval($wpdb->get_var("SELECT COUNT(*) {$base_query} AND interesse_mobilizacao = 'sim'")),
            'interesse_voluntariado' => intval($wpdb->get_var("SELECT COUNT(*) {$base_query} AND interesse_voluntariado = 'sim'")),
            'interesse_foruns' => intval($wpdb->get_var("SELECT COUNT(*) {$base_query} AND interesse_foruns = 'sim'")),
        );

        // Continuar participando
        $data['continuar_participando'] = $wpdb->get_results(
            "SELECT continuar_participando as label, COUNT(*) as value {$base_query} AND continuar_participando IS NOT NULL AND continuar_participando != '' GROUP BY continuar_participando",
            ARRAY_A
        );

        // Participa de coletivos
        $data['participa_coletivos'] = $wpdb->get_results(
            "SELECT participa_coletivos as label, COUNT(*) as value {$base_query} AND participa_coletivos IS NOT NULL AND participa_coletivos != '' GROUP BY participa_coletivos",
            ARRAY_A
        );

        // Cadastros mensais (últimos 12 meses)
        $data['monthly_registrations'] = array();
        for ($i = 11; $i >= 0; $i--) {
            $month_start = date('Y-m-01', strtotime("-{$i} months"));
            $month_end = date('Y-m-t', strtotime("-{$i} months"));
            $month_label = date_i18n('M/Y', strtotime("-{$i} months"));

            $count = $wpdb->get_var(
                "SELECT COUNT(*) {$base_query} AND created_at >= '{$month_start}' AND created_at <= '{$month_end} 23:59:59'"
            );

            $data['monthly_registrations'][] = array(
                'label' => $month_label,
                'value' => intval($count),
            );
        }

        // Por dia da semana
        $weekdays = array('Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb');
        $data['by_weekday'] = array();
        for ($i = 1; $i <= 7; $i++) {
            $day_num = $i % 7; // MySQL: 1=Dom, 2=Seg, etc.
            $count = $wpdb->get_var(
                "SELECT COUNT(*) {$base_query} AND DAYOFWEEK(created_at) = " . ($i)
            );
            $data['by_weekday'][] = array(
                'label' => $weekdays[$i - 1],
                'value' => intval($count),
            );
        }

        // Por hora do dia
        $data['by_hour'] = array();
        for ($h = 0; $h < 24; $h++) {
            $count = $wpdb->get_var(
                "SELECT COUNT(*) {$base_query} AND HOUR(created_at) = {$h}"
            );
            $data['by_hour'][] = array(
                'label' => str_pad($h, 2, '0', STR_PAD_LEFT),
                'value' => intval($count),
            );
        }

        return $data;
    }
}
