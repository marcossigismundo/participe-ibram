<?php
/**
 * Classe de importação e exportação
 *
 * @package CRM_Developer
 */

if (!defined('ABSPATH')) {
    exit;
}

class CRM_Dev_Import_Export {

    /**
     * Campos disponíveis para exportação/importação
     */
    public static function get_available_fields() {
        return array(
            'nome_completo' => 'Nome Completo',
            'nome_social' => 'Nome Social',
            'data_nascimento' => 'Data de Nascimento',
            'genero' => 'Gênero',
            'raca_etnia' => 'Raça/Etnia',
            'pessoa_deficiencia' => 'Pessoa com Deficiência',
            'deficiencia_descricao' => 'Descrição Deficiência',
            'email' => 'Email',
            'telefone' => 'Telefone',
            'whatsapp' => 'WhatsApp',
            'municipio' => 'Município',
            'estado' => 'Estado',
            'regiao' => 'Região',
            'etapa_participacao' => 'Etapa de Participação',
            'tipo_participacao' => 'Tipo de Participação',
            'categoria_representacao' => 'Categoria de Representação',
            'eixo_tematico' => 'Eixo Temático',
            'comunidade_territorio' => 'Comunidade/Território',
            'participa_coletivos' => 'Participa de Coletivos',
            'coletivos_descricao' => 'Descrição Coletivos',
            'tempo_atuacao_ambiental' => 'Tempo de Atuação Ambiental',
            'atua_justica_climatica' => 'Atua com Justiça Climática',
            'papel_lideranca' => 'Papel de Liderança',
            'lideranca_descricao' => 'Descrição Liderança',
            'continuar_participando' => 'Continuar Participando',
            'interesse_formacao' => 'Interesse em Formação',
            'interesse_conteudo' => 'Interesse em Conteúdo',
            'interesse_incidencia' => 'Interesse em Incidência',
            'interesse_mobilizacao' => 'Interesse em Mobilização',
            'interesse_voluntariado' => 'Interesse em Voluntariado',
            'interesse_foruns' => 'Interesse em Fóruns',
            'cargo_publico' => 'Cargo Público',
            'vinculacao_institucional' => 'Vinculação Institucional',
            'observacoes' => 'Observações',
            'status' => 'Status',
            'score_engajamento' => 'Score de Engajamento',
            'created_at' => 'Data de Cadastro',
        );
    }

    /**
     * Mapeamento de sinônimos para campos
     */
    public static function get_field_synonyms() {
        return array(
            'nome_completo' => array('nome', 'name', 'nome completo', 'full name'),
            'nome_social' => array('nome social', 'social name'),
            'data_nascimento' => array('nascimento', 'birth', 'data nascimento', 'birthdate', 'data de nascimento'),
            'genero' => array('genero', 'gender', 'sexo', 'gênero'),
            'raca_etnia' => array('raca', 'etnia', 'race', 'raça', 'cor'),
            'email' => array('email', 'e-mail', 'mail'),
            'telefone' => array('telefone', 'phone', 'tel', 'fone'),
            'whatsapp' => array('whatsapp', 'wpp', 'zap', 'whats'),
            'municipio' => array('municipio', 'cidade', 'city', 'município'),
            'estado' => array('estado', 'uf', 'state'),
            'regiao' => array('regiao', 'região', 'region'),
        );
    }

    /**
     * Exporta contatos para CSV
     */
    public static function export_csv($args = array(), $fields = array()) {
        $contacts_data = CRM_Dev_Contacts::get_contacts(array_merge($args, array('per_page' => 999999)));
        $contacts = $contacts_data['items'];

        if (empty($contacts)) {
            return false;
        }

        $available = self::get_available_fields();

        if (empty($fields)) {
            $fields = array_keys($available);
        }

        // Prepara cabeçalho
        $header = array();
        foreach ($fields as $field) {
            $header[] = isset($available[$field]) ? $available[$field] : $field;
        }

        // Prepara dados
        $rows = array();
        $rows[] = $header;

        foreach ($contacts as $contact) {
            $row = array();
            foreach ($fields as $field) {
                $value = isset($contact[$field]) ? $contact[$field] : '';

                // Deserializa arrays
                if (is_serialized($value)) {
                    $value = maybe_unserialize($value);
                    if (is_array($value)) {
                        $value = implode(', ', $value);
                    }
                }

                $row[] = $value;
            }
            $rows[] = $row;
        }

        // Gera CSV
        $output = fopen('php://temp', 'r+');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8

        foreach ($rows as $row) {
            fputcsv($output, $row, ';');
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * Exporta contatos para XLSX (via JavaScript)
     */
    public static function get_export_data($filters = array(), $fields = array()) {
        // Monta os argumentos para buscar contatos com filtros
        $args = array(
            'per_page' => 999999,
            'page' => 1,
        );

        // Aplica filtros
        if (!empty($filters['estado'])) {
            $args['estado'] = $filters['estado'];
        }
        if (!empty($filters['regiao'])) {
            $args['regiao'] = $filters['regiao'];
        }
        if (!empty($filters['status'])) {
            $args['status'] = $filters['status'];
        }
        if (!empty($filters['genero'])) {
            $args['genero'] = $filters['genero'];
        }
        if (!empty($filters['raca'])) {
            $args['raca'] = $filters['raca'];
        }
        if (!empty($filters['engajamento'])) {
            $args['engajamento'] = $filters['engajamento'];
        }
        if (!empty($filters['eixo_tematico'])) {
            $args['eixo_tematico'] = $filters['eixo_tematico'];
        }
        if (!empty($filters['period'])) {
            $args['period'] = $filters['period'];
        }
        if (!empty($filters['date_from'])) {
            $args['date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $args['date_to'] = $filters['date_to'];
        }

        $contacts_data = CRM_Dev_Contacts::get_contacts($args);
        $contacts = $contacts_data['items'];

        $available = self::get_available_fields();

        if (empty($fields)) {
            $fields = array_keys($available);
        }

        $header = array();
        foreach ($fields as $field) {
            $header[] = isset($available[$field]) ? $available[$field] : $field;
        }

        $data = array();
        $data[] = $header;

        foreach ($contacts as $contact) {
            $row = array();
            foreach ($fields as $field) {
                $value = isset($contact[$field]) ? $contact[$field] : '';

                if (is_serialized($value)) {
                    $value = maybe_unserialize($value);
                    if (is_array($value)) {
                        $value = implode(', ', $value);
                    }
                }

                $row[] = $value;
            }
            $data[] = $row;
        }

        return $data;
    }

    /**
     * Processa arquivo de importação
     */
    public static function process_import($file_path, $mapping = array(), $options = array()) {
        $defaults = array(
            'update_existing' => false,
            'skip_duplicates' => true,
            'dry_run' => false,
        );
        $options = wp_parse_args($options, $defaults);

        // Detecta tipo de arquivo
        $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

        if ($extension === 'csv') {
            $data = self::parse_csv($file_path);
        } elseif (in_array($extension, array('xlsx', 'xls'))) {
            // XLSX é processado no frontend via SheetJS
            return array('error' => 'Use a importação via interface para arquivos Excel');
        } else {
            return array('error' => 'Formato de arquivo não suportado');
        }

        if (empty($data)) {
            return array('error' => 'Arquivo vazio ou inválido');
        }

        return self::import_data($data, $mapping, $options);
    }

    /**
     * Faz parse de CSV
     */
    public static function parse_csv($file_path) {
        $data = array();
        $handle = fopen($file_path, 'r');

        if (!$handle) {
            return false;
        }

        // Detecta delimitador
        $first_line = fgets($handle);
        rewind($handle);

        $delimiters = array(';', ',', "\t");
        $delimiter = ';';
        $max_count = 0;

        foreach ($delimiters as $d) {
            $count = substr_count($first_line, $d);
            if ($count > $max_count) {
                $max_count = $count;
                $delimiter = $d;
            }
        }

        // Lê dados
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            // Remove BOM se existir
            if (!empty($row[0])) {
                $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', $row[0]);
            }
            $data[] = $row;
        }

        fclose($handle);

        return $data;
    }

    /**
     * Importa dados com detecção avançada de duplicatas
     */
    public static function import_data($data, $mapping = array(), $options = array()) {
        global $wpdb;
        $tables = CRM_Dev_Database::get_tables();

        // Aumenta limites para importações grandes
        @set_time_limit(600); // 10 minutos
        @ini_set('memory_limit', '512M');

        $defaults = array(
            'update_existing' => false,
            'skip_duplicates' => true,
            'dry_run' => false,
        );
        $options = wp_parse_args($options, $defaults);

        if (count($data) < 2) {
            return array('error' => 'Arquivo precisa ter pelo menos cabeçalho e uma linha de dados');
        }

        $header = array_shift($data);
        $header = array_map('trim', $header);
        $header = array_map('strtolower', $header);

        // Auto-mapeamento se não fornecido
        if (empty($mapping)) {
            $mapping = self::auto_map_fields($header);
        }

        // Resultados detalhados
        $results = array(
            'total' => count($data),
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => array(),
            'skip_reasons' => array(
                'nome_vazio' => 0,
                'email_duplicado' => 0,
                'telefone_duplicado' => 0,
                'whatsapp_duplicado' => 0,
                'nome_estado_duplicado' => 0,
                'linha_vazia' => 0,
                'erro_insercao' => 0,
            ),
            'details' => array(), // Detalhes de cada linha
        );

        // Carrega índices de duplicatas existentes para performance
        $existing_emails = self::load_existing_emails();
        $existing_phones = self::load_existing_phones();
        $existing_whatsapps = self::load_existing_whatsapps();
        $existing_names_states = self::load_existing_names_states();

        // Arrays para controlar duplicatas dentro da própria importação
        $import_emails = array();
        $import_phones = array();
        $import_whatsapps = array();
        $import_names_states = array();

        // Processa em lotes para arquivos grandes
        $batch_size = 100;
        $total_rows = count($data);

        foreach ($data as $line_num => $row) {
            $line = $line_num + 2; // +2 porque removemos header e arrays começam em 0

            // Verifica se linha está vazia
            $row_content = array_filter($row, function($v) { return trim($v) !== ''; });
            if (empty($row_content)) {
                $results['skip_reasons']['linha_vazia']++;
                $results['skipped']++;
                continue;
            }

            // Monta dados do contato
            $contact_data = array();
            $contact_data['origem'] = 'importacao';

            foreach ($mapping as $col_index => $field) {
                if ($field && isset($row[$col_index])) {
                    $value = trim($row[$col_index]);

                    // Conversões especiais
                    if ($field === 'estado' && strlen($value) > 2) {
                        $estados = array_flip(CRM_Dev_Helpers::get_estados());
                        $value = isset($estados[$value]) ? $estados[$value] : strtoupper(substr($value, 0, 2));
                    }

                    // Normaliza telefones
                    if (in_array($field, array('telefone', 'whatsapp'))) {
                        $value = self::normalize_phone($value);
                    }

                    // Normaliza email
                    if ($field === 'email') {
                        $value = strtolower(trim($value));
                    }

                    $contact_data[$field] = $value;
                }
            }

            // Validação: Nome completo obrigatório
            if (empty($contact_data['nome_completo'])) {
                $results['errors'][] = "Linha {$line}: Nome completo não informado (campo obrigatório)";
                $results['skip_reasons']['nome_vazio']++;
                $results['skipped']++;
                continue;
            }

            // Normaliza nome para comparação
            $nome_normalizado = self::normalize_name($contact_data['nome_completo']);
            $estado = isset($contact_data['estado']) ? strtoupper($contact_data['estado']) : '';
            $nome_estado_key = $nome_normalizado . '|' . $estado;

            // ========== VERIFICAÇÃO DE DUPLICATAS ==========
            $duplicate_found = false;
            $duplicate_reason = '';
            $existing_id = null;

            // 1. Verifica duplicata por email (prioridade máxima)
            if (!empty($contact_data['email'])) {
                $email_lower = strtolower($contact_data['email']);

                // Verifica no banco
                if (isset($existing_emails[$email_lower])) {
                    $duplicate_found = true;
                    $duplicate_reason = "Email '{$contact_data['email']}' já existe no sistema";
                    $existing_id = $existing_emails[$email_lower];
                    $results['skip_reasons']['email_duplicado']++;
                }
                // Verifica na própria importação
                elseif (isset($import_emails[$email_lower])) {
                    $duplicate_found = true;
                    $duplicate_reason = "Email '{$contact_data['email']}' já foi importado neste arquivo (linha {$import_emails[$email_lower]})";
                    $results['skip_reasons']['email_duplicado']++;
                }
            }

            // 2. Verifica duplicata por WhatsApp
            if (!$duplicate_found && !empty($contact_data['whatsapp'])) {
                $whatsapp_norm = self::normalize_phone($contact_data['whatsapp']);
                if (!empty($whatsapp_norm)) {
                    // Verifica no banco
                    if (isset($existing_whatsapps[$whatsapp_norm])) {
                        $duplicate_found = true;
                        $duplicate_reason = "WhatsApp '{$contact_data['whatsapp']}' já existe no sistema";
                        $existing_id = $existing_whatsapps[$whatsapp_norm];
                        $results['skip_reasons']['whatsapp_duplicado']++;
                    }
                    // Verifica na própria importação
                    elseif (isset($import_whatsapps[$whatsapp_norm])) {
                        $duplicate_found = true;
                        $duplicate_reason = "WhatsApp '{$contact_data['whatsapp']}' já foi importado neste arquivo (linha {$import_whatsapps[$whatsapp_norm]})";
                        $results['skip_reasons']['whatsapp_duplicado']++;
                    }
                }
            }

            // 3. Verifica duplicata por telefone
            if (!$duplicate_found && !empty($contact_data['telefone'])) {
                $telefone_norm = self::normalize_phone($contact_data['telefone']);
                if (!empty($telefone_norm)) {
                    // Verifica no banco
                    if (isset($existing_phones[$telefone_norm])) {
                        $duplicate_found = true;
                        $duplicate_reason = "Telefone '{$contact_data['telefone']}' já existe no sistema";
                        $existing_id = $existing_phones[$telefone_norm];
                        $results['skip_reasons']['telefone_duplicado']++;
                    }
                    // Verifica na própria importação
                    elseif (isset($import_phones[$telefone_norm])) {
                        $duplicate_found = true;
                        $duplicate_reason = "Telefone '{$contact_data['telefone']}' já foi importado neste arquivo (linha {$import_phones[$telefone_norm]})";
                        $results['skip_reasons']['telefone_duplicado']++;
                    }
                }
            }

            // 4. Verifica duplicata por nome + estado (menos restritivo)
            if (!$duplicate_found && !empty($nome_normalizado) && !empty($estado)) {
                // Verifica no banco
                if (isset($existing_names_states[$nome_estado_key])) {
                    $duplicate_found = true;
                    $duplicate_reason = "Contato '{$contact_data['nome_completo']}' do estado '{$estado}' já existe no sistema";
                    $existing_id = $existing_names_states[$nome_estado_key];
                    $results['skip_reasons']['nome_estado_duplicado']++;
                }
                // Verifica na própria importação
                elseif (isset($import_names_states[$nome_estado_key])) {
                    $duplicate_found = true;
                    $duplicate_reason = "Contato '{$contact_data['nome_completo']}' do estado '{$estado}' já foi importado neste arquivo (linha {$import_names_states[$nome_estado_key]})";
                    $results['skip_reasons']['nome_estado_duplicado']++;
                }
            }

            // ========== PROCESSA O CONTATO ==========
            if ($duplicate_found) {
                if ($options['skip_duplicates'] && !$options['update_existing']) {
                    $results['errors'][] = "Linha {$line}: {$duplicate_reason} - IGNORADO";
                    $results['skipped']++;
                    continue;
                }

                if ($options['update_existing'] && $existing_id && !$options['dry_run']) {
                    $result = CRM_Dev_Contacts::save_contact($contact_data, $existing_id);
                    if ($result) {
                        $results['updated']++;
                        $results['details'][] = array(
                            'line' => $line,
                            'action' => 'updated',
                            'name' => $contact_data['nome_completo'],
                            'reason' => $duplicate_reason . ' - ATUALIZADO'
                        );
                    } else {
                        $results['errors'][] = "Linha {$line}: Erro ao atualizar contato '{$contact_data['nome_completo']}'";
                        $results['skip_reasons']['erro_insercao']++;
                    }
                    continue;
                }

                // Se chegou aqui, é duplicata mas não vai atualizar
                $results['errors'][] = "Linha {$line}: {$duplicate_reason} - IGNORADO";
                $results['skipped']++;
                continue;
            }

            // Insere novo contato
            if (!$options['dry_run']) {
                $result = CRM_Dev_Contacts::save_contact($contact_data);
                if ($result) {
                    $results['imported']++;

                    // Adiciona aos índices de controle da importação
                    if (!empty($contact_data['email'])) {
                        $import_emails[strtolower($contact_data['email'])] = $line;
                        $existing_emails[strtolower($contact_data['email'])] = $result;
                    }
                    if (!empty($contact_data['telefone'])) {
                        $phone_norm = self::normalize_phone($contact_data['telefone']);
                        if ($phone_norm) {
                            $import_phones[$phone_norm] = $line;
                            $existing_phones[$phone_norm] = $result;
                        }
                    }
                    if (!empty($contact_data['whatsapp'])) {
                        $whats_norm = self::normalize_phone($contact_data['whatsapp']);
                        if ($whats_norm) {
                            $import_whatsapps[$whats_norm] = $line;
                            $existing_whatsapps[$whats_norm] = $result;
                        }
                    }
                    if (!empty($nome_estado_key)) {
                        $import_names_states[$nome_estado_key] = $line;
                        $existing_names_states[$nome_estado_key] = $result;
                    }
                } else {
                    $results['errors'][] = "Linha {$line}: Erro ao inserir contato '{$contact_data['nome_completo']}' - verifique os dados";
                    $results['skip_reasons']['erro_insercao']++;
                    $results['skipped']++;
                }
            } else {
                $results['imported']++;
            }
        }

        // Salva log de importação
        if (!$options['dry_run']) {
            $log_details = array(
                'errors' => array_slice($results['errors'], 0, 100), // Limita a 100 erros no log
                'skip_reasons' => $results['skip_reasons'],
            );

            $wpdb->insert(
                $tables['import_logs'],
                array(
                    'arquivo' => 'import_' . date('Y-m-d_H-i-s'),
                    'total_linhas' => $results['total'],
                    'importados' => $results['imported'] + $results['updated'],
                    'erros' => $results['skipped'],
                    'detalhes_erros' => json_encode($log_details, JSON_UNESCAPED_UNICODE),
                    'user_id' => get_current_user_id(),
                    'created_at' => current_time('mysql'),
                ),
                array('%s', '%d', '%d', '%d', '%s', '%d', '%s')
            );
        }

        return $results;
    }

    /**
     * Normaliza número de telefone (remove formatação)
     */
    private static function normalize_phone($phone) {
        if (empty($phone)) return '';
        // Remove tudo que não é número
        $phone = preg_replace('/[^0-9]/', '', $phone);
        // Se começar com 55 (Brasil) e tiver mais de 11 dígitos, remove
        if (strlen($phone) > 11 && substr($phone, 0, 2) === '55') {
            $phone = substr($phone, 2);
        }
        // Retorna apenas se tiver pelo menos 8 dígitos
        return strlen($phone) >= 8 ? $phone : '';
    }

    /**
     * Normaliza nome para comparação
     */
    private static function normalize_name($name) {
        if (empty($name)) return '';
        // Remove acentos
        $name = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        // Converte para minúsculas
        $name = strtolower($name);
        // Remove caracteres especiais
        $name = preg_replace('/[^a-z0-9\s]/', '', $name);
        // Remove espaços extras
        $name = preg_replace('/\s+/', ' ', trim($name));
        return $name;
    }

    /**
     * Carrega emails existentes para verificação rápida
     */
    private static function load_existing_emails() {
        global $wpdb;
        $table = CRM_Dev_Database::get_tables()['contacts'];

        $results = $wpdb->get_results(
            "SELECT id, LOWER(email) as email FROM {$table} WHERE email IS NOT NULL AND email != ''",
            ARRAY_A
        );

        $index = array();
        foreach ($results as $row) {
            $index[$row['email']] = $row['id'];
        }
        return $index;
    }

    /**
     * Carrega telefones existentes para verificação rápida
     */
    private static function load_existing_phones() {
        global $wpdb;
        $table = CRM_Dev_Database::get_tables()['contacts'];

        $results = $wpdb->get_results(
            "SELECT id, telefone FROM {$table} WHERE telefone IS NOT NULL AND telefone != ''",
            ARRAY_A
        );

        $index = array();
        foreach ($results as $row) {
            $normalized = self::normalize_phone($row['telefone']);
            if ($normalized) {
                $index[$normalized] = $row['id'];
            }
        }
        return $index;
    }

    /**
     * Carrega WhatsApps existentes para verificação rápida
     */
    private static function load_existing_whatsapps() {
        global $wpdb;
        $table = CRM_Dev_Database::get_tables()['contacts'];

        $results = $wpdb->get_results(
            "SELECT id, whatsapp FROM {$table} WHERE whatsapp IS NOT NULL AND whatsapp != ''",
            ARRAY_A
        );

        $index = array();
        foreach ($results as $row) {
            $normalized = self::normalize_phone($row['whatsapp']);
            if ($normalized) {
                $index[$normalized] = $row['id'];
            }
        }
        return $index;
    }

    /**
     * Carrega combinações nome+estado existentes para verificação rápida
     */
    private static function load_existing_names_states() {
        global $wpdb;
        $table = CRM_Dev_Database::get_tables()['contacts'];

        $results = $wpdb->get_results(
            "SELECT id, nome_completo, estado FROM {$table} WHERE nome_completo IS NOT NULL AND nome_completo != ''",
            ARRAY_A
        );

        $index = array();
        foreach ($results as $row) {
            $name_normalized = self::normalize_name($row['nome_completo']);
            $estado = strtoupper($row['estado'] ?? '');
            $key = $name_normalized . '|' . $estado;
            $index[$key] = $row['id'];
        }
        return $index;
    }

    /**
     * Auto-mapeia campos baseado no cabeçalho
     */
    public static function auto_map_fields($header) {
        $mapping = array();
        $synonyms = self::get_field_synonyms();
        $available = array_keys(self::get_available_fields());

        foreach ($header as $index => $col_name) {
            $col_name = strtolower(trim($col_name));
            $col_name = preg_replace('/[^a-z0-9_]/', '', str_replace(' ', '_', $col_name));

            // Busca direta
            if (in_array($col_name, $available)) {
                $mapping[$index] = $col_name;
                continue;
            }

            // Busca por sinônimos
            foreach ($synonyms as $field => $syns) {
                foreach ($syns as $syn) {
                    $syn = preg_replace('/[^a-z0-9_]/', '', str_replace(' ', '_', strtolower($syn)));
                    if ($col_name === $syn) {
                        $mapping[$index] = $field;
                        break 2;
                    }
                }
            }

            // Se não encontrou, deixa vazio
            if (!isset($mapping[$index])) {
                $mapping[$index] = '';
            }
        }

        return $mapping;
    }

    /**
     * Retorna histórico de importações
     */
    public static function get_import_history($limit = 20) {
        global $wpdb;
        $table = CRM_Dev_Database::get_tables()['import_logs'];

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT l.*, u.display_name as user_name
                FROM {$table} l
                LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
                ORDER BY l.created_at DESC
                LIMIT %d",
                $limit
            ),
            ARRAY_A
        );
    }

    /**
     * Handler AJAX - Importar contatos
     */
    public static function ajax_import() {
        check_ajax_referer('crm_dev_nonce', 'nonce');

        if (!CRM_Dev_Helpers::can_user('import_crm_contacts')) {
            wp_send_json_error(array('message' => 'Sem permissão'));
        }

        // Recebe dados como JSON string para evitar limite de max_input_vars
        $data_json = isset($_POST['data_json']) ? $_POST['data_json'] : '';
        $mapping_json = isset($_POST['mapping_json']) ? $_POST['mapping_json'] : '';
        $options = isset($_POST['options']) ? $_POST['options'] : array();

        // Decodifica JSON
        $data = !empty($data_json) ? json_decode(stripslashes($data_json), true) : array();
        $mapping = !empty($mapping_json) ? json_decode(stripslashes($mapping_json), true) : array();

        // Fallback para formato antigo (compatibilidade)
        if (empty($data) && isset($_POST['data'])) {
            $data = $_POST['data'];
        }
        if (empty($mapping) && isset($_POST['mapping'])) {
            $mapping = $_POST['mapping'];
        }

        if (empty($data)) {
            wp_send_json_error(array('message' => 'Dados não informados'));
        }

        // Converte mapping para array numérico
        $mapping_array = array();
        foreach ($mapping as $key => $value) {
            $mapping_array[intval($key)] = sanitize_text_field($value);
        }

        $results = self::import_data($data, $mapping_array, array(
            'update_existing' => !empty($options['update_existing']),
            'skip_duplicates' => !empty($options['skip_duplicates']),
            'dry_run' => !empty($options['dry_run']),
        ));

        if (isset($results['error'])) {
            wp_send_json_error(array('message' => $results['error']));
        }

        wp_send_json_success($results);
    }

    /**
     * Handler AJAX - Exportar contatos
     */
    public static function ajax_export() {
        check_ajax_referer('crm_dev_nonce', 'nonce');

        if (!CRM_Dev_Helpers::can_user('export_crm_contacts')) {
            wp_send_json_error(array('message' => 'Sem permissão'));
        }

        $format = isset($_POST['format']) ? sanitize_text_field($_POST['format']) : 'xlsx';
        $fields = isset($_POST['fields']) ? array_map('sanitize_text_field', $_POST['fields']) : array();
        $filters = isset($_POST['filters']) ? $_POST['filters'] : array();

        // Sanitiza os filtros
        $sanitized_filters = array();
        if (!empty($filters['estado'])) {
            $sanitized_filters['estado'] = sanitize_text_field($filters['estado']);
        }
        if (!empty($filters['regiao'])) {
            $sanitized_filters['regiao'] = sanitize_text_field($filters['regiao']);
        }
        if (!empty($filters['status'])) {
            $sanitized_filters['status'] = sanitize_text_field($filters['status']);
        }
        if (!empty($filters['genero'])) {
            $sanitized_filters['genero'] = sanitize_text_field($filters['genero']);
        }
        if (!empty($filters['raca'])) {
            $sanitized_filters['raca'] = sanitize_text_field($filters['raca']);
        }
        if (!empty($filters['engajamento'])) {
            $sanitized_filters['engajamento'] = sanitize_text_field($filters['engajamento']);
        }
        if (!empty($filters['eixo'])) {
            $sanitized_filters['eixo_tematico'] = sanitize_text_field($filters['eixo']);
        }
        if (!empty($filters['period'])) {
            $sanitized_filters['period'] = sanitize_text_field($filters['period']);
        }
        if (!empty($filters['date_from'])) {
            $sanitized_filters['date_from'] = sanitize_text_field($filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $sanitized_filters['date_to'] = sanitize_text_field($filters['date_to']);
        }

        $data = self::get_export_data($sanitized_filters, $fields);

        wp_send_json_success(array('data' => $data));
    }
}
