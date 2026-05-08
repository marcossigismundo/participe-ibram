# Participe Ibram — Database Schema (v1.0)

> SQL completo do schema. Prefixo: `wp_pi_*`. Charset: `utf8mb4`. Engine: `InnoDB`. Coluna `id` sempre `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT`.
> Todos os timestamps em `DATETIME` (não TIMESTAMP, para evitar Y2038 e timezone surprises).
> Todas as tabelas têm `created_at` + `updated_at`. Soft-delete via `deleted_at` quando indicado.

## Convenções

- FK definidas conceitualmente; aplicação garante integridade (WordPress não reforça FKs em todos os ambientes).
- Índices: prefixo `idx_`, únicos `uniq_`.
- JSON em `LONGTEXT` (compatibilidade MySQL 5.6+).
- Colunas criptografadas têm sufixo `_enc`.

---

## 1. Agentes (núcleo)

### `wp_pi_agentes`
```sql
CREATE TABLE wp_pi_agentes (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tipo            ENUM('PF','OR','SM') NOT NULL,
  numero_registro VARCHAR(20) DEFAULT NULL,            -- gerado no deferimento
  status_cadastro ENUM(
    'rascunho','submetido','em_analise',
    'deferido','deferido_em_retratacao','deferido_em_recurso',
    'indeferido_aguardando_recurso','em_retratacao','em_recurso_presidencia','indeferido_final'
  ) NOT NULL DEFAULT 'rascunho',
  user_id         BIGINT UNSIGNED DEFAULT NULL,        -- WP user dono
  email_principal VARCHAR(255) NOT NULL,
  telefone        VARCHAR(30) DEFAULT NULL,
  submetido_em    DATETIME DEFAULT NULL,
  deferido_em     DATETIME DEFAULT NULL,
  publicado_em    DATETIME DEFAULT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at      DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_numero_registro (numero_registro),
  UNIQUE KEY uniq_email (email_principal),
  KEY idx_tipo_status (tipo, status_cadastro),
  KEY idx_user (user_id),
  KEY idx_submetido (submetido_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
```

### `wp_pi_agentes_pf` (sub-tabela Pessoa Física)
```sql
CREATE TABLE wp_pi_agentes_pf (
  agente_id              BIGINT UNSIGNED NOT NULL,
  nome_completo          VARCHAR(255) NOT NULL,
  nome_social            VARCHAR(255) DEFAULT NULL,
  cpf_enc                VARBINARY(255) DEFAULT NULL,    -- criptografado
  cpf_hash               CHAR(64) DEFAULT NULL,          -- HMAC para busca exata
  rg_enc                 VARBINARY(255) DEFAULT NULL,
  passaporte_enc         VARBINARY(255) DEFAULT NULL,
  nacionalidade          VARCHAR(50) DEFAULT NULL,       -- valor de pi_vocabularios
  faixa_etaria           VARCHAR(20) DEFAULT NULL,
  identidade_genero      VARCHAR(50) DEFAULT NULL,
  orientacao_sexual      VARCHAR(50) DEFAULT NULL,
  raca_cor               VARCHAR(50) DEFAULT NULL,
  pessoa_deficiencia     ENUM('sim','nao','prefiro_nao_informar') DEFAULT 'prefiro_nao_informar',
  deficiencia_descricao  TEXT DEFAULT NULL,
  recursos_acessibilidade TEXT DEFAULT NULL,
  grau_instrucao         VARCHAR(50) DEFAULT NULL,
  ocupacao               VARCHAR(50) DEFAULT NULL,
  cidade_residencia      VARCHAR(255) DEFAULT NULL,
  estado_residencia      CHAR(2) DEFAULT NULL,
  bairro_residencia      VARCHAR(255) DEFAULT NULL,
  organizacao_vinculada_id BIGINT UNSIGNED DEFAULT NULL,  -- FK para wp_pi_agentes (tipo OR)
  apresentacao_md        TEXT DEFAULT NULL,              -- "carta de apresentação e intenções"
  PRIMARY KEY (agente_id),
  UNIQUE KEY uniq_cpf_hash (cpf_hash),
  KEY idx_estado (estado_residencia),
  KEY idx_organizacao_vinculada (organizacao_vinculada_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
```

### `wp_pi_agentes_or` (sub-tabela Organização — PJ ou Coletivo)
```sql
CREATE TABLE wp_pi_agentes_or (
  agente_id              BIGINT UNSIGNED NOT NULL,
  nome_organizacao       VARCHAR(255) NOT NULL,
  tem_cnpj               ENUM('sim','nao') NOT NULL,
  cnpj_enc               VARBINARY(255) DEFAULT NULL,
  cnpj_hash              CHAR(64) DEFAULT NULL,
  tipo_coletivo          VARCHAR(50) DEFAULT NULL,        -- vocabulario tipos_coletivo
  abrangencia            VARCHAR(20) DEFAULT NULL,        -- nacional/regional/...
  cidade_sede            VARCHAR(255) DEFAULT NULL,
  estado_sede            CHAR(2) DEFAULT NULL,
  bairro_sede            VARCHAR(255) DEFAULT NULL,
  apresentacao_md        TEXT DEFAULT NULL,               -- até 3000 chars
  estrutura_governanca_md TEXT DEFAULT NULL,              -- até 3000 chars
  data_fundacao          DATE DEFAULT NULL,
  PRIMARY KEY (agente_id),
  UNIQUE KEY uniq_cnpj_hash (cnpj_hash),
  KEY idx_estado (estado_sede),
  KEY idx_tipo_coletivo (tipo_coletivo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
```

### `wp_pi_agentes_sm` (sub-tabela Sistema/Secretaria)
```sql
CREATE TABLE wp_pi_agentes_sm (
  agente_id              BIGINT UNSIGNED NOT NULL,
  nome_orgao             VARCHAR(255) NOT NULL,
  esfera                 ENUM('federal','estadual','distrital','municipal','regional') NOT NULL,
  tipo_orgao             ENUM('sistema_museus','secretaria_cultura','secretaria_turismo','outro') NOT NULL,
  uf                     CHAR(2) DEFAULT NULL,
  municipio              VARCHAR(255) DEFAULT NULL,
  lei_instituicao        VARCHAR(255) DEFAULT NULL,       -- referência da lei
  ano_lei                SMALLINT UNSIGNED DEFAULT NULL,
  representante_legal_nome  VARCHAR(255) NOT NULL,
  representante_legal_cargo VARCHAR(255) DEFAULT NULL,
  representante_cpf_enc  VARBINARY(255) DEFAULT NULL,
  representante_cpf_hash CHAR(64) DEFAULT NULL,
  PRIMARY KEY (agente_id),
  KEY idx_esfera_uf (esfera, uf)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
```

### `wp_pi_agente_representantes` (representantes de coletivos colegiados)
```sql
CREATE TABLE wp_pi_agente_representantes (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  agente_id     BIGINT UNSIGNED NOT NULL,                 -- FK -> wp_pi_agentes (tipo OR ou SM)
  nome          VARCHAR(255) NOT NULL,
  cpf_enc       VARBINARY(255) DEFAULT NULL,
  cpf_hash      CHAR(64) DEFAULT NULL,
  email         VARCHAR(255) DEFAULT NULL,
  telefone      VARCHAR(30) DEFAULT NULL,
  papel         VARCHAR(100) DEFAULT NULL,                -- ex.: representante legal, coordenador
  principal     TINYINT(1) NOT NULL DEFAULT 0,            -- representante de contato
  ordem         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_agente (agente_id),
  KEY idx_cpf_hash (cpf_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
```

### `wp_pi_agente_vocabularios` (multi-select: áreas temáticas, instâncias, PCT, etc.)
```sql
CREATE TABLE wp_pi_agente_vocabularios (
  agente_id     BIGINT UNSIGNED NOT NULL,
  vocabulario   VARCHAR(50) NOT NULL,                     -- ex.: 'areas_tematicas'
  valor         VARCHAR(100) NOT NULL,
  ordem         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (agente_id, vocabulario, valor),
  KEY idx_vocab_valor (vocabulario, valor)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
```

### `wp_pi_sequencias` (geração de número de registro)
```sql
CREATE TABLE wp_pi_sequencias (
  tipo            ENUM('PF','OR','SM') NOT NULL,
  ano             SMALLINT UNSIGNED NOT NULL,
  ultimo_numero   INT UNSIGNED NOT NULL DEFAULT 0,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (tipo, ano)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
```

---

## 2. Documentos

### `wp_pi_tipos_documento`
```sql
CREATE TABLE wp_pi_tipos_documento (
  id                       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo                   VARCHAR(50) NOT NULL,           -- ex.: 'cnpj','estatuto','ata_posse',...
  nome                     VARCHAR(255) NOT NULL,
  descricao                TEXT DEFAULT NULL,
  obrigatorio_para         VARCHAR(50) DEFAULT NULL,       -- ex.: 'OR' ou 'PF,OR' (CSV)
  mime_permitidos          VARCHAR(255) NOT NULL DEFAULT 'application/pdf,image/jpeg,image/png',
  tamanho_max_kb           INT UNSIGNED NOT NULL DEFAULT 10240,
  ativo                    TINYINT(1) NOT NULL DEFAULT 1,
  ordem                    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
```

### `wp_pi_documentos`
```sql
CREATE TABLE wp_pi_documentos (
  id                       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  agente_id                BIGINT UNSIGNED DEFAULT NULL,
  inscricao_id             BIGINT UNSIGNED DEFAULT NULL,
  tipo_documento_id        BIGINT UNSIGNED NOT NULL,
  arquivo_path             VARCHAR(500) NOT NULL,           -- caminho relativo em uploads privados
  nome_original            VARCHAR(255) NOT NULL,
  mime_real                VARCHAR(100) NOT NULL,
  tamanho_bytes            BIGINT UNSIGNED NOT NULL,
  hash_sha256              CHAR(64) NOT NULL,
  uploaded_by              BIGINT UNSIGNED NOT NULL,
  uploaded_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  validado                 TINYINT(1) NOT NULL DEFAULT 0,
  validado_em              DATETIME DEFAULT NULL,
  validado_por             BIGINT UNSIGNED DEFAULT NULL,
  observacoes_validacao    TEXT DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_agente (agente_id),
  KEY idx_inscricao (inscricao_id),
  KEY idx_hash (hash_sha256),
  KEY idx_tipo (tipo_documento_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
```

---

## 3. Análises e Recursos

### `wp_pi_analises`
```sql
CREATE TABLE wp_pi_analises (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  agente_id         BIGINT UNSIGNED NOT NULL,
  analista_id       BIGINT UNSIGNED NOT NULL,
  decisao           ENUM('deferimento','indeferimento') NOT NULL,
  parecer_md        TEXT NOT NULL,
  fundamentacao_md  TEXT DEFAULT NULL,
  decidido_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  publicado_em      DATETIME DEFAULT NULL,
  url_publicacao    VARCHAR(500) DEFAULT NULL,
  hash_publicacao   CHAR(64) DEFAULT NULL,                 -- evidência do snapshot publicado
  PRIMARY KEY (id),
  KEY idx_agente (agente_id),
  KEY idx_decidido (decidido_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
```

### `wp_pi_recursos`
```sql
CREATE TABLE wp_pi_recursos (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  analise_id          BIGINT UNSIGNED NOT NULL,
  fase                ENUM('retratacao','presidencia') NOT NULL,
  recorrente_id       BIGINT UNSIGNED NOT NULL,            -- WP user
  fundamentacao_md    TEXT NOT NULL,
  protocolado_em      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  prazo_inicio        DATETIME NOT NULL,                   -- 10 dias contados de prazo_inicio
  prazo_fim           DATETIME NOT NULL,
  decisao             ENUM('reconsiderar','manter','deferir','indeferir') DEFAULT NULL,
  decisor_id          BIGINT UNSIGNED DEFAULT NULL,
  decisao_md          TEXT DEFAULT NULL,
  decidido_em         DATETIME DEFAULT NULL,
  publicado_em        DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_analise (analise_id),
  KEY idx_prazo_fim (prazo_fim)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
```

### `wp_pi_status_historico` (auditoria de transições de estado)
```sql
CREATE TABLE wp_pi_status_historico (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  agente_id       BIGINT UNSIGNED NOT NULL,
  status_anterior VARCHAR(50) DEFAULT NULL,
  status_novo     VARCHAR(50) NOT NULL,
  ator_id         BIGINT UNSIGNED DEFAULT NULL,
  observacao      TEXT DEFAULT NULL,
  ocorrido_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_agente (agente_id),
  KEY idx_ocorrido (ocorrido_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
```

---

## 4. Editais e Inscrições

### `wp_pi_editais`
```sql
CREATE TABLE wp_pi_editais (
  id                          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  titulo                      VARCHAR(255) NOT NULL,
  descricao_md                LONGTEXT DEFAULT NULL,
  status                      ENUM('rascunho','publicado','inscricoes_abertas','em_habilitacao','em_recurso','votacao_aberta','votacao_encerrada','encerrado') NOT NULL DEFAULT 'rascunho',
  abertura                    DATETIME DEFAULT NULL,
  encerramento_inscricoes     DATETIME DEFAULT NULL,
  publicacao_habilitacao      DATETIME DEFAULT NULL,
  prazo_recurso_inabilitacao  DATETIME DEFAULT NULL,
  abertura_votacao            DATETIME DEFAULT NULL,
  encerramento_votacao        DATETIME DEFAULT NULL,
  publicacao_resultado        DATETIME DEFAULT NULL,
  criado_por                  BIGINT UNSIGNED NOT NULL,
  created_at                  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at                  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_status (status),
  KEY idx_abertura (abertura)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
```

### `wp_pi_edital_categorias`
```sql
CREATE TABLE wp_pi_edital_categorias (
  id                       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  edital_id                BIGINT UNSIGNED NOT NULL,
  nome                     VARCHAR(255) NOT NULL,
  descricao_md             TEXT DEFAULT NULL,
  num_vagas                SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  num_suplentes            SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  tipos_agente_elegivel    VARCHAR(20) NOT NULL DEFAULT 'PF,OR,SM',  -- CSV
  criterios_md             TEXT DEFAULT NULL,
  documentos_exigidos      LONGTEXT DEFAULT NULL,                    -- JSON: [tipo_documento_codigo,...]
  ordem                    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_edital (edital_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
```

### `wp_pi_inscricoes`
```sql
CREATE TABLE wp_pi_inscricoes (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  edital_id         BIGINT UNSIGNED NOT NULL,
  categoria_id      BIGINT UNSIGNED NOT NULL,
  agente_id         BIGINT UNSIGNED NOT NULL,
  portfolio_md      LONGTEXT DEFAULT NULL,
  status            ENUM('rascunho','inscrito','em_habilitacao','habilitado','inabilitado','em_recurso','final_habilitado','final_inabilitado') NOT NULL DEFAULT 'rascunho',
  inscrito_em       DATETIME DEFAULT NULL,
  habilitado_em     DATETIME DEFAULT NULL,
  inabilitado_em    DATETIME DEFAULT NULL,
  motivo_inabilitacao_md  TEXT DEFAULT NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_edital_categoria_agente (edital_id, categoria_id, agente_id),
  KEY idx_edital_status (edital_id, status),
  KEY idx_agente (agente_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
```

### `wp_pi_recursos_inabilitacao`
```sql
CREATE TABLE wp_pi_recursos_inabilitacao (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  inscricao_id      BIGINT UNSIGNED NOT NULL,
  fundamentacao_md  TEXT NOT NULL,
  protocolado_em    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  decisao           ENUM('deferir','manter') DEFAULT NULL,
  decisor_id        BIGINT UNSIGNED DEFAULT NULL,
  decisao_md        TEXT DEFAULT NULL,
  decidido_em       DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_inscricao (inscricao_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
```

---

## 5. Votação

### `wp_pi_votacoes`
```sql
CREATE TABLE wp_pi_votacoes (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  edital_id         BIGINT UNSIGNED NOT NULL,
  abertura          DATETIME NOT NULL,
  encerramento      DATETIME NOT NULL,
  status            ENUM('agendada','aberta','encerrada','apurada','cancelada') NOT NULL DEFAULT 'agendada',
  modo              ENUM('por_categoria','geral') NOT NULL DEFAULT 'por_categoria',
  hash_pre_apuracao CHAR(64) DEFAULT NULL,                  -- hash do conjunto de votos antes de tabular
  apurado_em        DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_edital (edital_id),
  KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
```

### `wp_pi_votos`
```sql
CREATE TABLE wp_pi_votos (
  id                       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  votacao_id               BIGINT UNSIGNED NOT NULL,
  categoria_id             BIGINT UNSIGNED NOT NULL,
  eleitor_hash             CHAR(64) NOT NULL,               -- HMAC(secret, agente_id||votacao_id)
  candidato_inscricao_id   BIGINT UNSIGNED NOT NULL,
  votado_em                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ip_hash                  CHAR(64) DEFAULT NULL,           -- HMAC do IP, não armazenar IP cru
  PRIMARY KEY (id),
  UNIQUE KEY uniq_eleitor_categoria (votacao_id, categoria_id, eleitor_hash),
  KEY idx_candidato (candidato_inscricao_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
```

### `wp_pi_resultados`
```sql
CREATE TABLE wp_pi_resultados (
  id                       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  votacao_id               BIGINT UNSIGNED NOT NULL,
  categoria_id             BIGINT UNSIGNED NOT NULL,
  candidato_inscricao_id   BIGINT UNSIGNED NOT NULL,
  total_votos              INT UNSIGNED NOT NULL DEFAULT 0,
  posicao                  SMALLINT UNSIGNED NOT NULL,      -- 1=eleito top, 2=eleito #2 ou suplente, ...
  eleito                   TINYINT(1) NOT NULL DEFAULT 0,
  suplente                 TINYINT(1) NOT NULL DEFAULT 0,
  apurado_em               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_votacao_categoria_candidato (votacao_id, categoria_id, candidato_inscricao_id),
  KEY idx_eleito (eleito)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
```

---

## 6. LGPD

### `wp_pi_termos`
```sql
CREATE TABLE wp_pi_termos (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  versao        VARCHAR(20) NOT NULL,                       -- ex.: '2026.05.01'
  conteudo_md   LONGTEXT NOT NULL,
  hash_conteudo CHAR(64) NOT NULL,                          -- SHA-256 do conteúdo, prova de versão
  ativo_em      DATETIME NOT NULL,
  inativo_em    DATETIME DEFAULT NULL,
  publicado_por BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_versao (versao),
  KEY idx_ativo (ativo_em, inativo_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
```

### `wp_pi_consentimentos`
```sql
CREATE TABLE wp_pi_consentimentos (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  agente_id     BIGINT UNSIGNED NOT NULL,
  termo_id      BIGINT UNSIGNED NOT NULL,
  finalidade    ENUM(
    'identificacao','comunicacao','mapeamento','reconhecimento_pct',
    'votacao','candidatura','dados_sensiveis_genero','dados_sensiveis_orientacao',
    'dados_sensiveis_saude','dados_sensiveis_raca'
  ) NOT NULL,
  status        ENUM('aceito','negado','revogado') NOT NULL,
  ip_hash       CHAR(64) DEFAULT NULL,
  user_agent    TEXT DEFAULT NULL,
  registrado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revogado_em   DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_agente_finalidade (agente_id, finalidade),
  KEY idx_termo (termo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
```

### `wp_pi_solicitacoes_titular` (direitos LGPD art. 18)
```sql
CREATE TABLE wp_pi_solicitacoes_titular (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  agente_id       BIGINT UNSIGNED NOT NULL,
  tipo            ENUM('acesso','retificacao','exclusao','portabilidade','oposicao','anonimizacao','revisao_decisao_automatizada') NOT NULL,
  detalhes_md     TEXT DEFAULT NULL,
  status          ENUM('aberta','em_atendimento','atendida','recusada') NOT NULL DEFAULT 'aberta',
  resposta_md     TEXT DEFAULT NULL,
  protocolada_em  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atendida_em     DATETIME DEFAULT NULL,
  atendida_por    BIGINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_agente (agente_id),
  KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
```

---

## 7. Vocabulários, Comunicação, Auditoria

### `wp_pi_vocabularios`
```sql
CREATE TABLE wp_pi_vocabularios (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tipo          VARCHAR(50) NOT NULL,                         -- ex.: 'tipos_coletivo'
  valor         VARCHAR(100) NOT NULL,                        -- key
  rotulo        VARCHAR(255) NOT NULL,                        -- label visível
  descricao     TEXT DEFAULT NULL,                            -- texto para tooltip/modal
  ordem         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  ativo         TINYINT(1) NOT NULL DEFAULT 1,
  metadata      LONGTEXT DEFAULT NULL,                        -- JSON livre
  PRIMARY KEY (id),
  UNIQUE KEY uniq_tipo_valor (tipo, valor),
  KEY idx_tipo_ativo (tipo, ativo, ordem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
```

### `wp_pi_email_queue`
```sql
CREATE TABLE wp_pi_email_queue (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  evento        VARCHAR(50) NOT NULL,                         -- ex.: 'cadastro_deferido'
  agente_id     BIGINT UNSIGNED DEFAULT NULL,
  destinatario  VARCHAR(255) NOT NULL,
  assunto       VARCHAR(255) NOT NULL,
  corpo_html    LONGTEXT NOT NULL,
  payload_json  LONGTEXT DEFAULT NULL,
  tentativas    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  status        ENUM('pendente','enviando','enviado','falhou') NOT NULL DEFAULT 'pendente',
  ultimo_erro   TEXT DEFAULT NULL,
  agendado_para DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  enviado_em    DATETIME DEFAULT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_status_agendado (status, agendado_para),
  KEY idx_agente (agente_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
```

### `wp_pi_audit_log` (append-only)
```sql
CREATE TABLE wp_pi_audit_log (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  entidade        VARCHAR(50) NOT NULL,                        -- ex.: 'agente','edital','voto'
  entidade_id    BIGINT UNSIGNED DEFAULT NULL,
  acao            VARCHAR(50) NOT NULL,                        -- ex.: 'criar','atualizar','deferir','visualizar_cpf'
  ator_id         BIGINT UNSIGNED DEFAULT NULL,
  dados_antes     LONGTEXT DEFAULT NULL,                       -- JSON
  dados_depois    LONGTEXT DEFAULT NULL,                       -- JSON
  ip_hash         CHAR(64) DEFAULT NULL,
  user_agent      TEXT DEFAULT NULL,
  ocorrido_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_entidade (entidade, entidade_id),
  KEY idx_ator (ator_id),
  KEY idx_ocorrido (ocorrido_em),
  KEY idx_acao (acao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
```

---

## 8. Roles e Capabilities

Adicionar ao WordPress (no Activator):

| Role | Capabilities |
|---|---|
| `pi_administrador` | tudo |
| `pi_analista` | `pi_listar_cadastros`, `pi_analisar_cadastro`, `pi_deferir`, `pi_indeferir`, `pi_visualizar_documentos`, `pi_visualizar_dados_sensiveis` |
| `pi_presidencia` | tudo de analista + `pi_decidir_recurso_presidencia` |
| `pi_gestor_edital` | `pi_criar_edital`, `pi_editar_edital`, `pi_publicar_edital`, `pi_decidir_habilitacao` |
| `pi_apuracao` | `pi_apurar_votacao`, `pi_publicar_resultado` |
| `pi_dpo` | `pi_atender_solicitacao_titular`, `pi_visualizar_audit_log`, `pi_anonimizar_titular` |
| `pi_agente` | `pi_editar_proprio_cadastro`, `pi_inscrever_em_edital`, `pi_votar`, `pi_solicitar_direitos_titular` |

## 9. Índices adicionais e performance

- Quando usuário busca agente por CPF, usar `cpf_hash` (HMAC) e nunca o cipher.
- Listagens com `ORDER BY created_at DESC` já têm índice.
- Dashboards agregam por `tipo`, `status_cadastro`, `estado` — todos cobertos.
- Audit log é alvo de retenção: rotacionar para arquivo após 5 anos via cron + Cold Storage.

## 10. Migrações

Cada migração em `migrations/V{NNN}__{descricao}.sql`. Estado em `wp_pi_migrations`:
```sql
CREATE TABLE wp_pi_migrations (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  versao        VARCHAR(20) NOT NULL,
  descricao     VARCHAR(255) NOT NULL,
  aplicada_em   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  hash_arquivo  CHAR(64) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_versao (versao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
```

V001 = todas as tabelas acima.
