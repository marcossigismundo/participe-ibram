-- =====================================================================
-- Participe Ibram - Initial schema (V001)
-- =====================================================================
-- Reference: refactor-spec/SCHEMA.md (v1.0)
-- Conventions:
--   * Prefix placeholder `{prefix}` -> replaced at runtime by $wpdb->prefix . 'pi_'
--   * Charset: utf8mb4 / Collate: utf8mb4_unicode_520_ci / Engine: InnoDB
--   * BIGINT UNSIGNED ids; DATETIME (not TIMESTAMP) for Y2038/timezone safety
--   * created_at + updated_at on entities; deleted_at where soft-delete is needed
--   * FK enforcement: WordPress shared environments are unreliable; we declare
--     CASCADE FKs only for tightly coupled sub-tables. Looser relationships are
--     enforced at the application layer (see SCHEMA.md note on FKs).
--   * SCHEMA.md retains ENUM('sim','nao') in legacy/spec'd columns; we honor
--     the spec literally. New TINYINT(1) booleans are used where SCHEMA.md
--     already prescribes them.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 0. Migration control table (must exist before recording any version)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `{prefix}migrations` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `versao`        VARCHAR(20)     NOT NULL,
  `descricao`     VARCHAR(255)    NOT NULL,
  `aplicada_em`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `hash_arquivo`  CHAR(64)        NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_versao` (`versao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- ---------------------------------------------------------------------
-- 1. Agentes (core)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `{prefix}agentes` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tipo`            ENUM('PF','OR','SM') NOT NULL,
  `numero_registro` VARCHAR(20) DEFAULT NULL,
  `status_cadastro` ENUM(
    'rascunho','submetido','em_analise',
    'deferido','deferido_em_retratacao','deferido_em_recurso',
    'indeferido_aguardando_recurso','em_retratacao','em_recurso_presidencia','indeferido_final'
  ) NOT NULL DEFAULT 'rascunho',
  `user_id`         BIGINT UNSIGNED DEFAULT NULL,
  `email_principal` VARCHAR(255) NOT NULL,
  `telefone`        VARCHAR(30) DEFAULT NULL,
  `submetido_em`    DATETIME DEFAULT NULL,
  `deferido_em`     DATETIME DEFAULT NULL,
  `publicado_em`    DATETIME DEFAULT NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`      DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_numero_registro` (`numero_registro`),
  UNIQUE KEY `uniq_email` (`email_principal`),
  KEY `idx_tipo_status` (`tipo`, `status_cadastro`),
  KEY `idx_user` (`user_id`),
  KEY `idx_submetido` (`submetido_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE IF NOT EXISTS `{prefix}agentes_pf` (
  `agente_id`              BIGINT UNSIGNED NOT NULL,
  `nome_completo`          VARCHAR(255) NOT NULL,
  `nome_social`            VARCHAR(255) DEFAULT NULL,
  `cpf_enc`                VARBINARY(255) DEFAULT NULL,
  `cpf_hash`               CHAR(64) DEFAULT NULL,
  `rg_enc`                 VARBINARY(255) DEFAULT NULL,
  `passaporte_enc`         VARBINARY(255) DEFAULT NULL,
  `nacionalidade`          VARCHAR(50) DEFAULT NULL,
  `faixa_etaria`           VARCHAR(20) DEFAULT NULL,
  `identidade_genero`      VARCHAR(50) DEFAULT NULL,
  `orientacao_sexual`      VARCHAR(50) DEFAULT NULL,
  `raca_cor`               VARCHAR(50) DEFAULT NULL,
  `pessoa_deficiencia`     ENUM('sim','nao','prefiro_nao_informar') DEFAULT 'prefiro_nao_informar',
  `deficiencia_descricao`  TEXT DEFAULT NULL,
  `recursos_acessibilidade` TEXT DEFAULT NULL,
  `grau_instrucao`         VARCHAR(50) DEFAULT NULL,
  `ocupacao`               VARCHAR(50) DEFAULT NULL,
  `cidade_residencia`      VARCHAR(255) DEFAULT NULL,
  `estado_residencia`      CHAR(2) DEFAULT NULL,
  `bairro_residencia`      VARCHAR(255) DEFAULT NULL,
  `organizacao_vinculada_id` BIGINT UNSIGNED DEFAULT NULL,
  `apresentacao_md`        TEXT DEFAULT NULL,
  PRIMARY KEY (`agente_id`),
  UNIQUE KEY `uniq_cpf_hash` (`cpf_hash`),
  KEY `idx_estado` (`estado_residencia`),
  KEY `idx_organizacao_vinculada` (`organizacao_vinculada_id`),
  CONSTRAINT `fk_agentes_pf_agente`
    FOREIGN KEY (`agente_id`) REFERENCES `{prefix}agentes` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE IF NOT EXISTS `{prefix}agentes_or` (
  `agente_id`              BIGINT UNSIGNED NOT NULL,
  `nome_organizacao`       VARCHAR(255) NOT NULL,
  `tem_cnpj`               ENUM('sim','nao') NOT NULL,
  `cnpj_enc`               VARBINARY(255) DEFAULT NULL,
  `cnpj_hash`              CHAR(64) DEFAULT NULL,
  `tipo_coletivo`          VARCHAR(50) DEFAULT NULL,
  `abrangencia`            VARCHAR(20) DEFAULT NULL,
  `cidade_sede`            VARCHAR(255) DEFAULT NULL,
  `estado_sede`            CHAR(2) DEFAULT NULL,
  `bairro_sede`            VARCHAR(255) DEFAULT NULL,
  `apresentacao_md`        TEXT DEFAULT NULL,
  `estrutura_governanca_md` TEXT DEFAULT NULL,
  `data_fundacao`          DATE DEFAULT NULL,
  PRIMARY KEY (`agente_id`),
  UNIQUE KEY `uniq_cnpj_hash` (`cnpj_hash`),
  KEY `idx_estado` (`estado_sede`),
  KEY `idx_tipo_coletivo` (`tipo_coletivo`),
  CONSTRAINT `fk_agentes_or_agente`
    FOREIGN KEY (`agente_id`) REFERENCES `{prefix}agentes` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE IF NOT EXISTS `{prefix}agentes_sm` (
  `agente_id`              BIGINT UNSIGNED NOT NULL,
  `nome_orgao`             VARCHAR(255) NOT NULL,
  `esfera`                 ENUM('federal','estadual','distrital','municipal','regional') NOT NULL,
  `tipo_orgao`             ENUM('sistema_museus','secretaria_cultura','secretaria_turismo','outro') NOT NULL,
  `uf`                     CHAR(2) DEFAULT NULL,
  `municipio`              VARCHAR(255) DEFAULT NULL,
  `lei_instituicao`        VARCHAR(255) DEFAULT NULL,
  `ano_lei`                SMALLINT UNSIGNED DEFAULT NULL,
  `representante_legal_nome`  VARCHAR(255) NOT NULL,
  `representante_legal_cargo` VARCHAR(255) DEFAULT NULL,
  `representante_cpf_enc`  VARBINARY(255) DEFAULT NULL,
  `representante_cpf_hash` CHAR(64) DEFAULT NULL,
  PRIMARY KEY (`agente_id`),
  KEY `idx_esfera_uf` (`esfera`, `uf`),
  CONSTRAINT `fk_agentes_sm_agente`
    FOREIGN KEY (`agente_id`) REFERENCES `{prefix}agentes` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE IF NOT EXISTS `{prefix}agente_representantes` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `agente_id`     BIGINT UNSIGNED NOT NULL,
  `nome`          VARCHAR(255) NOT NULL,
  `cpf_enc`       VARBINARY(255) DEFAULT NULL,
  `cpf_hash`      CHAR(64) DEFAULT NULL,
  `email`         VARCHAR(255) DEFAULT NULL,
  `telefone`      VARCHAR(30) DEFAULT NULL,
  `papel`         VARCHAR(100) DEFAULT NULL,
  `principal`     TINYINT(1) NOT NULL DEFAULT 0,
  `ordem`         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_agente` (`agente_id`),
  KEY `idx_cpf_hash` (`cpf_hash`),
  CONSTRAINT `fk_agente_representantes_agente`
    FOREIGN KEY (`agente_id`) REFERENCES `{prefix}agentes` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE IF NOT EXISTS `{prefix}agente_vocabularios` (
  `agente_id`     BIGINT UNSIGNED NOT NULL,
  `vocabulario`   VARCHAR(50) NOT NULL,
  `valor`         VARCHAR(100) NOT NULL,
  `ordem`         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`agente_id`, `vocabulario`, `valor`),
  KEY `idx_vocab_valor` (`vocabulario`, `valor`),
  CONSTRAINT `fk_agente_vocabularios_agente`
    FOREIGN KEY (`agente_id`) REFERENCES `{prefix}agentes` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE IF NOT EXISTS `{prefix}sequencias` (
  `tipo`            ENUM('PF','OR','SM') NOT NULL,
  `ano`             SMALLINT UNSIGNED NOT NULL,
  `ultimo_numero`   INT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`tipo`, `ano`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- ---------------------------------------------------------------------
-- 2. Documentos
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `{prefix}tipos_documento` (
  `id`                       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `codigo`                   VARCHAR(50) NOT NULL,
  `nome`                     VARCHAR(255) NOT NULL,
  `descricao`                TEXT DEFAULT NULL,
  `obrigatorio_para`         VARCHAR(50) DEFAULT NULL,
  `mime_permitidos`          VARCHAR(255) NOT NULL DEFAULT 'application/pdf,image/jpeg,image/png',
  `tamanho_max_kb`           INT UNSIGNED NOT NULL DEFAULT 10240,
  `ativo`                    TINYINT(1) NOT NULL DEFAULT 1,
  `ordem`                    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE IF NOT EXISTS `{prefix}documentos` (
  `id`                       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `agente_id`                BIGINT UNSIGNED DEFAULT NULL,
  `inscricao_id`             BIGINT UNSIGNED DEFAULT NULL,
  `tipo_documento_id`        BIGINT UNSIGNED NOT NULL,
  `arquivo_path`             VARCHAR(500) NOT NULL,
  `nome_original`            VARCHAR(255) NOT NULL,
  `mime_real`                VARCHAR(100) NOT NULL,
  `tamanho_bytes`            BIGINT UNSIGNED NOT NULL,
  `hash_sha256`              CHAR(64) NOT NULL,
  `uploaded_by`              BIGINT UNSIGNED NOT NULL,
  `uploaded_at`              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `validado`                 TINYINT(1) NOT NULL DEFAULT 0,
  `validado_em`              DATETIME DEFAULT NULL,
  `validado_por`             BIGINT UNSIGNED DEFAULT NULL,
  `observacoes_validacao`    TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_agente` (`agente_id`),
  KEY `idx_inscricao` (`inscricao_id`),
  KEY `idx_hash` (`hash_sha256`),
  KEY `idx_tipo` (`tipo_documento_id`)
  -- FK documentos.tipo_documento_id -> tipos_documento.id (no cascade) TODO
  -- Application layer enforces; avoiding FK keeps dbDelta-style rollouts safe.
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- ---------------------------------------------------------------------
-- 3. Analises e Recursos
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `{prefix}analises` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `agente_id`         BIGINT UNSIGNED NOT NULL,
  `analista_id`       BIGINT UNSIGNED NOT NULL,
  `decisao`           ENUM('deferimento','indeferimento') NOT NULL,
  `parecer_md`        TEXT NOT NULL,
  `fundamentacao_md`  TEXT DEFAULT NULL,
  `decidido_em`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `publicado_em`      DATETIME DEFAULT NULL,
  `url_publicacao`    VARCHAR(500) DEFAULT NULL,
  `hash_publicacao`   CHAR(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_agente` (`agente_id`),
  KEY `idx_decidido` (`decidido_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE IF NOT EXISTS `{prefix}recursos` (
  `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `analise_id`          BIGINT UNSIGNED NOT NULL,
  `fase`                ENUM('retratacao','presidencia') NOT NULL,
  `recorrente_id`       BIGINT UNSIGNED NOT NULL,
  `fundamentacao_md`    TEXT NOT NULL,
  `protocolado_em`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `prazo_inicio`        DATETIME NOT NULL,
  `prazo_fim`           DATETIME NOT NULL,
  `decisao`             ENUM('reconsiderar','manter','deferir','indeferir') DEFAULT NULL,
  `decisor_id`          BIGINT UNSIGNED DEFAULT NULL,
  `decisao_md`          TEXT DEFAULT NULL,
  `decidido_em`         DATETIME DEFAULT NULL,
  `publicado_em`        DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_analise` (`analise_id`),
  KEY `idx_prazo_fim` (`prazo_fim`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE IF NOT EXISTS `{prefix}status_historico` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `agente_id`       BIGINT UNSIGNED NOT NULL,
  `status_anterior` VARCHAR(50) DEFAULT NULL,
  `status_novo`     VARCHAR(50) NOT NULL,
  `ator_id`         BIGINT UNSIGNED DEFAULT NULL,
  `observacao`      TEXT DEFAULT NULL,
  `ocorrido_em`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_agente` (`agente_id`),
  KEY `idx_ocorrido` (`ocorrido_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- ---------------------------------------------------------------------
-- 4. Editais e Inscricoes
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `{prefix}editais` (
  `id`                          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `titulo`                      VARCHAR(255) NOT NULL,
  `descricao_md`                LONGTEXT DEFAULT NULL,
  `status`                      ENUM('rascunho','publicado','inscricoes_abertas','em_habilitacao','em_recurso','votacao_aberta','votacao_encerrada','encerrado') NOT NULL DEFAULT 'rascunho',
  `abertura`                    DATETIME DEFAULT NULL,
  `encerramento_inscricoes`     DATETIME DEFAULT NULL,
  `publicacao_habilitacao`      DATETIME DEFAULT NULL,
  `prazo_recurso_inabilitacao`  DATETIME DEFAULT NULL,
  `abertura_votacao`            DATETIME DEFAULT NULL,
  `encerramento_votacao`        DATETIME DEFAULT NULL,
  `publicacao_resultado`        DATETIME DEFAULT NULL,
  `criado_por`                  BIGINT UNSIGNED NOT NULL,
  `created_at`                  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`                  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_abertura` (`abertura`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE IF NOT EXISTS `{prefix}edital_categorias` (
  `id`                       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `edital_id`                BIGINT UNSIGNED NOT NULL,
  `nome`                     VARCHAR(255) NOT NULL,
  `descricao_md`             TEXT DEFAULT NULL,
  `num_vagas`                SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `num_suplentes`            SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `tipos_agente_elegivel`    VARCHAR(20) NOT NULL DEFAULT 'PF,OR,SM',
  `criterios_md`             TEXT DEFAULT NULL,
  `documentos_exigidos`      LONGTEXT DEFAULT NULL,
  `ordem`                    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_edital` (`edital_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE IF NOT EXISTS `{prefix}inscricoes` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `edital_id`         BIGINT UNSIGNED NOT NULL,
  `categoria_id`      BIGINT UNSIGNED NOT NULL,
  `agente_id`         BIGINT UNSIGNED NOT NULL,
  `portfolio_md`      LONGTEXT DEFAULT NULL,
  `status`            ENUM('rascunho','inscrito','em_habilitacao','habilitado','inabilitado','em_recurso','final_habilitado','final_inabilitado') NOT NULL DEFAULT 'rascunho',
  `inscrito_em`       DATETIME DEFAULT NULL,
  `habilitado_em`     DATETIME DEFAULT NULL,
  `inabilitado_em`    DATETIME DEFAULT NULL,
  `motivo_inabilitacao_md`  TEXT DEFAULT NULL,
  `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_edital_categoria_agente` (`edital_id`, `categoria_id`, `agente_id`),
  KEY `idx_edital_status` (`edital_id`, `status`),
  KEY `idx_agente` (`agente_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE IF NOT EXISTS `{prefix}recursos_inabilitacao` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `inscricao_id`      BIGINT UNSIGNED NOT NULL,
  `fundamentacao_md`  TEXT NOT NULL,
  `protocolado_em`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `decisao`           ENUM('deferir','manter') DEFAULT NULL,
  `decisor_id`        BIGINT UNSIGNED DEFAULT NULL,
  `decisao_md`        TEXT DEFAULT NULL,
  `decidido_em`       DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_inscricao` (`inscricao_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- ---------------------------------------------------------------------
-- 5. Votacao
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `{prefix}votacoes` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `edital_id`         BIGINT UNSIGNED NOT NULL,
  `abertura`          DATETIME NOT NULL,
  `encerramento`      DATETIME NOT NULL,
  `status`            ENUM('agendada','aberta','encerrada','apurada','cancelada') NOT NULL DEFAULT 'agendada',
  `modo`              ENUM('por_categoria','geral') NOT NULL DEFAULT 'por_categoria',
  `hash_pre_apuracao` CHAR(64) DEFAULT NULL,
  `apurado_em`        DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_edital` (`edital_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE IF NOT EXISTS `{prefix}votos` (
  `id`                       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `votacao_id`               BIGINT UNSIGNED NOT NULL,
  `categoria_id`             BIGINT UNSIGNED NOT NULL,
  `eleitor_hash`             CHAR(64) NOT NULL,
  `candidato_inscricao_id`   BIGINT UNSIGNED NOT NULL,
  `votado_em`                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_hash`                  CHAR(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_eleitor_categoria` (`votacao_id`, `categoria_id`, `eleitor_hash`),
  KEY `idx_candidato` (`candidato_inscricao_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE IF NOT EXISTS `{prefix}resultados` (
  `id`                       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `votacao_id`               BIGINT UNSIGNED NOT NULL,
  `categoria_id`             BIGINT UNSIGNED NOT NULL,
  `candidato_inscricao_id`   BIGINT UNSIGNED NOT NULL,
  `total_votos`              INT UNSIGNED NOT NULL DEFAULT 0,
  `posicao`                  SMALLINT UNSIGNED NOT NULL,
  `eleito`                   TINYINT(1) NOT NULL DEFAULT 0,
  `suplente`                 TINYINT(1) NOT NULL DEFAULT 0,
  `apurado_em`               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_votacao_categoria_candidato` (`votacao_id`, `categoria_id`, `candidato_inscricao_id`),
  KEY `idx_eleito` (`eleito`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- ---------------------------------------------------------------------
-- 6. LGPD
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `{prefix}termos` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `versao`        VARCHAR(20) NOT NULL,
  `conteudo_md`   LONGTEXT NOT NULL,
  `hash_conteudo` CHAR(64) NOT NULL,
  `ativo_em`      DATETIME NOT NULL,
  `inativo_em`    DATETIME DEFAULT NULL,
  `publicado_por` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_versao` (`versao`),
  KEY `idx_ativo` (`ativo_em`, `inativo_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE IF NOT EXISTS `{prefix}consentimentos` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `agente_id`     BIGINT UNSIGNED NOT NULL,
  `termo_id`      BIGINT UNSIGNED NOT NULL,
  `finalidade`    ENUM(
    'identificacao','comunicacao','mapeamento','reconhecimento_pct',
    'votacao','candidatura','dados_sensiveis_genero','dados_sensiveis_orientacao',
    'dados_sensiveis_saude','dados_sensiveis_raca'
  ) NOT NULL,
  `status`        ENUM('aceito','negado','revogado') NOT NULL,
  `ip_hash`       CHAR(64) DEFAULT NULL,
  `user_agent`    TEXT DEFAULT NULL,
  `registrado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `revogado_em`   DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_agente_finalidade` (`agente_id`, `finalidade`),
  KEY `idx_termo` (`termo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE IF NOT EXISTS `{prefix}solicitacoes_titular` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `agente_id`       BIGINT UNSIGNED NOT NULL,
  `tipo`            ENUM('acesso','retificacao','exclusao','portabilidade','oposicao','anonimizacao','revisao_decisao_automatizada') NOT NULL,
  `detalhes_md`     TEXT DEFAULT NULL,
  `status`          ENUM('aberta','em_atendimento','atendida','recusada') NOT NULL DEFAULT 'aberta',
  `resposta_md`     TEXT DEFAULT NULL,
  `protocolada_em`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atendida_em`     DATETIME DEFAULT NULL,
  `atendida_por`    BIGINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_agente` (`agente_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- ---------------------------------------------------------------------
-- 7. Vocabularios, Comunicacao, Auditoria
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `{prefix}vocabularios` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tipo`          VARCHAR(50) NOT NULL,
  `valor`         VARCHAR(100) NOT NULL,
  `rotulo`        VARCHAR(255) NOT NULL,
  `descricao`     TEXT DEFAULT NULL,
  `ordem`         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `ativo`         TINYINT(1) NOT NULL DEFAULT 1,
  `metadata`      LONGTEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_tipo_valor` (`tipo`, `valor`),
  KEY `idx_tipo_ativo` (`tipo`, `ativo`, `ordem`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE IF NOT EXISTS `{prefix}email_queue` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `evento`        VARCHAR(50) NOT NULL,
  `agente_id`     BIGINT UNSIGNED DEFAULT NULL,
  `destinatario`  VARCHAR(255) NOT NULL,
  `assunto`       VARCHAR(255) NOT NULL,
  `corpo_html`    LONGTEXT NOT NULL,
  `payload_json`  LONGTEXT DEFAULT NULL,
  `tentativas`    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `status`        ENUM('pendente','enviando','enviado','falhou') NOT NULL DEFAULT 'pendente',
  `ultimo_erro`   TEXT DEFAULT NULL,
  `agendado_para` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `enviado_em`    DATETIME DEFAULT NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status_agendado` (`status`, `agendado_para`),
  KEY `idx_agente` (`agente_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE IF NOT EXISTS `{prefix}audit_log` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `entidade`      VARCHAR(50) NOT NULL,
  `entidade_id`   BIGINT UNSIGNED DEFAULT NULL,
  `acao`          VARCHAR(50) NOT NULL,
  `ator_id`       BIGINT UNSIGNED DEFAULT NULL,
  `dados_antes`   LONGTEXT DEFAULT NULL,
  `dados_depois`  LONGTEXT DEFAULT NULL,
  `ip_hash`       CHAR(64) DEFAULT NULL,
  `user_agent`    TEXT DEFAULT NULL,
  `ocorrido_em`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_entidade` (`entidade`, `entidade_id`),
  KEY `idx_ator` (`ator_id`),
  KEY `idx_ocorrido` (`ocorrido_em`),
  KEY `idx_acao` (`acao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
