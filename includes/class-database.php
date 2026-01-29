<?php
/**
 * Classe de gerenciamento do banco de dados
 *
 * @package CRM_Developer
 */

if (!defined('ABSPATH')) {
    exit;
}

class CRM_Dev_Database {

    /**
     * Prefixo das tabelas
     */
    public static function get_prefix() {
        global $wpdb;
        return $wpdb->prefix . 'crm_dev_';
    }

    /**
     * Nomes das tabelas
     */
    public static function get_tables() {
        $prefix = self::get_prefix();
        return array(
            'contacts' => $prefix . 'contacts',
            'interactions' => $prefix . 'interactions',
            'tags' => $prefix . 'tags',
            'contact_tags' => $prefix . 'contact_tags',
            'import_logs' => $prefix . 'import_logs',
            'consent_logs' => $prefix . 'consent_logs',
        );
    }

    /**
     * Cria todas as tabelas
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $tables = self::get_tables();

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Tabela de contatos
        $sql_contacts = "CREATE TABLE {$tables['contacts']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,

            -- Dados Pessoais
            foto_id bigint(20) UNSIGNED DEFAULT NULL,
            nome_completo varchar(255) NOT NULL,
            nome_social varchar(255) DEFAULT NULL,
            data_nascimento date DEFAULT NULL,
            genero varchar(50) DEFAULT NULL,
            raca_etnia varchar(50) DEFAULT NULL,
            pessoa_deficiencia enum('sim','nao') DEFAULT 'nao',
            deficiencia_descricao text DEFAULT NULL,

            -- Contato
            email varchar(255) DEFAULT NULL,
            telefone varchar(30) DEFAULT NULL,
            whatsapp varchar(30) DEFAULT NULL,

            -- Localização
            municipio varchar(255) DEFAULT NULL,
            estado varchar(2) DEFAULT NULL,
            regiao varchar(50) DEFAULT NULL,

            -- Participação em Conferência
            etapa_participacao text DEFAULT NULL,
            tipo_participacao text DEFAULT NULL,
            categoria_representacao text DEFAULT NULL,

            -- Eixo Temático
            eixo_tematico text DEFAULT NULL,

            -- Perfil Sociopolítico
            comunidade_territorio varchar(255) DEFAULT NULL,
            participa_coletivos enum('sim','nao') DEFAULT NULL,
            coletivos_descricao text DEFAULT NULL,
            tempo_atuacao_ambiental varchar(100) DEFAULT NULL,
            atua_justica_climatica enum('sim','nao') DEFAULT NULL,
            papel_lideranca enum('sim','nao') DEFAULT NULL,
            lideranca_descricao text DEFAULT NULL,

            -- Mobilização Futura
            continuar_participando enum('sim','nao','talvez') DEFAULT NULL,
            interesse_formacao enum('sim','nao') DEFAULT NULL,
            interesse_conteudo enum('sim','nao') DEFAULT NULL,
            interesse_incidencia enum('sim','nao') DEFAULT NULL,
            interesse_mobilizacao enum('sim','nao') DEFAULT NULL,
            interesse_voluntariado enum('sim','nao') DEFAULT NULL,
            interesse_foruns enum('sim','nao') DEFAULT NULL,

            -- Dados Complementares
            cargo_publico varchar(255) DEFAULT NULL,
            vinculacao_institucional varchar(255) DEFAULT NULL,
            observacoes text DEFAULT NULL,

            -- Controle
            origem varchar(100) DEFAULT 'manual',
            consentimento_lgpd enum('sim','nao') DEFAULT 'nao',
            data_consentimento datetime DEFAULT NULL,
            status enum('ativo','inativo','pendente') DEFAULT 'ativo',
            score_engajamento int(3) DEFAULT 0,
            ultima_interacao datetime DEFAULT NULL,
            criado_por bigint(20) UNSIGNED DEFAULT NULL,
            atualizado_por bigint(20) UNSIGNED DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY (id),
            KEY idx_email (email),
            KEY idx_estado (estado),
            KEY idx_status (status),
            KEY idx_score (score_engajamento),
            KEY idx_created (created_at)
        ) $charset_collate;";

        dbDelta($sql_contacts);

        // Tabela de interações
        $sql_interactions = "CREATE TABLE {$tables['interactions']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            contact_id bigint(20) UNSIGNED NOT NULL,
            tipo varchar(50) NOT NULL,
            titulo varchar(255) NOT NULL,
            descricao text DEFAULT NULL,
            resultado varchar(100) DEFAULT NULL,
            proxima_acao text DEFAULT NULL,
            data_proxima_acao date DEFAULT NULL,
            anexos text DEFAULT NULL,
            created_by bigint(20) UNSIGNED NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,

            PRIMARY KEY (id),
            KEY idx_contact (contact_id),
            KEY idx_tipo (tipo),
            KEY idx_data_proxima (data_proxima_acao),
            KEY idx_created (created_at)
        ) $charset_collate;";

        dbDelta($sql_interactions);

        // Tabela de tags
        $sql_tags = "CREATE TABLE {$tables['tags']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            nome varchar(100) NOT NULL,
            cor varchar(7) DEFAULT '#3498db',
            descricao text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,

            PRIMARY KEY (id),
            UNIQUE KEY idx_nome (nome)
        ) $charset_collate;";

        dbDelta($sql_tags);

        // Tabela de relacionamento contatos-tags
        $sql_contact_tags = "CREATE TABLE {$tables['contact_tags']} (
            contact_id bigint(20) UNSIGNED NOT NULL,
            tag_id bigint(20) UNSIGNED NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,

            PRIMARY KEY (contact_id, tag_id),
            KEY idx_tag (tag_id)
        ) $charset_collate;";

        dbDelta($sql_contact_tags);

        // Tabela de logs de importação
        $sql_import_logs = "CREATE TABLE {$tables['import_logs']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            arquivo varchar(255) NOT NULL,
            total_linhas int(11) DEFAULT 0,
            importados int(11) DEFAULT 0,
            erros int(11) DEFAULT 0,
            detalhes_erros longtext DEFAULT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,

            PRIMARY KEY (id),
            KEY idx_user (user_id),
            KEY idx_created (created_at)
        ) $charset_collate;";

        dbDelta($sql_import_logs);

        // Tabela de logs de consentimento LGPD
        $sql_consent_logs = "CREATE TABLE {$tables['consent_logs']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            contact_id bigint(20) UNSIGNED NOT NULL,
            tipo varchar(50) NOT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            dados_consentidos text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,

            PRIMARY KEY (id),
            KEY idx_contact (contact_id),
            KEY idx_created (created_at)
        ) $charset_collate;";

        dbDelta($sql_consent_logs);

        // Insere tags padrão
        self::insert_default_tags();

        // Salva versão do banco
        update_option('crm_dev_db_version', CRM_DEV_VERSION);
    }

    /**
     * Insere tags padrão
     */
    private static function insert_default_tags() {
        global $wpdb;
        $tables = self::get_tables();

        $tags = array(
            array('nome' => 'Liderança', 'cor' => '#e74c3c'),
            array('nome' => 'Alta Prioridade', 'cor' => '#e67e22'),
            array('nome' => 'Voluntário', 'cor' => '#27ae60'),
            array('nome' => 'Multiplicador', 'cor' => '#3498db'),
            array('nome' => 'Especialista', 'cor' => '#9b59b6'),
            array('nome' => 'Governo', 'cor' => '#1abc9c'),
            array('nome' => 'Sociedade Civil', 'cor' => '#f39c12'),
            array('nome' => 'Juventude', 'cor' => '#e91e63'),
        );

        foreach ($tags as $tag) {
            $wpdb->insert(
                $tables['tags'],
                $tag,
                array('%s', '%s')
            );
        }
    }

    /**
     * Remove todas as tabelas (usar com cuidado!)
     */
    public static function drop_tables() {
        global $wpdb;
        $tables = self::get_tables();

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$table}");
        }

        delete_option('crm_dev_db_version');
    }

    /**
     * Verifica e adiciona colunas faltantes (migrações)
     */
    public static function maybe_upgrade() {
        global $wpdb;
        $tables = self::get_tables();

        // Verifica se coluna foto_id existe
        $column_exists = $wpdb->get_results(
            $wpdb->prepare(
                "SHOW COLUMNS FROM {$tables['contacts']} LIKE %s",
                'foto_id'
            )
        );

        if (empty($column_exists)) {
            $wpdb->query(
                "ALTER TABLE {$tables['contacts']}
                ADD COLUMN foto_id bigint(20) UNSIGNED DEFAULT NULL
                AFTER id"
            );
        }

        // Verifica se coluna anexos existe na tabela de interações
        $anexos_exists = $wpdb->get_results(
            $wpdb->prepare(
                "SHOW COLUMNS FROM {$tables['interactions']} LIKE %s",
                'anexos'
            )
        );

        if (empty($anexos_exists)) {
            $wpdb->query(
                "ALTER TABLE {$tables['interactions']}
                ADD COLUMN anexos text DEFAULT NULL
                AFTER data_proxima_acao"
            );
        }
    }
}
