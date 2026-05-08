# R2 — LGPD aplicada ao Participe Ibram

Documento técnico-jurídico para o refactor do plugin que opera a coleta de dados pessoais e sensíveis (raça/cor, gênero, orientação sexual, deficiência, povos e comunidades tradicionais) na plataforma federal **Participe Ibram** (IBRAM).

> Base normativa: Lei nº 13.709/2018 (LGPD); **Resolução CD/ANPD nº 15/2024 (incidentes)**; **Lei nº 14.553/2023 (dados étnico-raciais)**; Decreto nº 8.750/2016 (Conselho Nacional dos Povos e Comunidades Tradicionais).

## 1. Resumo executivo

- **Base legal primária:** execução de políticas públicas (Art. 7º, III + Art. 11, II, "b"), combinada com cumprimento de obrigação legal (Art. 7º, II + Art. 11, II, "a") quando previsto (Lei 14.553/2023 para dados étnico-raciais).
- **Consentimento NÃO é a base preferida do poder público** para a finalidade-fim. Reservar para finalidades acessórias (newsletter, pesquisas científicas adicionais, cookies de analytics).
- **Transparência forçada (Art. 23):** publicar no site institucional — base legal, finalidade, procedimentos e práticas — para cada operação.
- **Segurança técnica obrigatória:** criptografia em repouso (libsodium/secretbox) para dados sensíveis; chave em env vars (KMS preferível); pseudonimização para colunas pesquisáveis (HMAC-BLAKE2b com chave separada); logs de acesso; prazos de retenção definidos.
- **Encarregado (DPO) obrigatório** (Art. 41), com contato público.
- **Janela de notificação de incidente: 3 dias úteis** à ANPD e aos titulares (Resolução CD/ANPD 15/2024 — não mais "2 dias").
- **DPIA/RIPD obrigatório** (Art. 38). Revisão mínima anual.

## 2. Bases legais aplicáveis

### 2.1 Mapeamento dado × base legal

| Categoria de dado | Exemplo | Art. 7º | Art. 11 | Observações |
|---|---|---|---|---|
| Cadastrais (nome, CPF, e-mail, endereço) | Inscrição de agente | III (políticas públicas) | n/a | Sem consentimento. Documentar em RIPD. |
| Raça/cor | Campo demográfico | II (obrigação legal) | II, "a" | Lei 14.553/2023. Sem consentimento. |
| Gênero / orientação sexual | Campo demográfico | III | II, "b" | Permitir "prefiro não informar". |
| Deficiência | Acessibilidade | III | II, "b" | Idem. |
| PCT (Decreto 8.750/2016) | Quilombola, indígena, ribeirinho | III | II, "b" | Risco alto de discriminação. |
| Newsletter institucional | Opt-in | I (consentimento) | n/a | Granular, revogável. |
| Cookies não-essenciais | GA, etc. | IX (legítimo interesse) ou I | n/a | Banner de cookies. |

### 2.2 Combinação de bases legais

1. **Uma finalidade = uma base legal por operação.** Não "empilhar".
2. Mapear cada operação ao seu fundamento no RIPD.
3. Registrar em DB qual finalidade cada consentimento cobre.
4. Quando a base é "políticas públicas": publicar no site (Art. 23, §1º).

### 2.3 Quando exigir consentimento adicional para sensíveis

Mesmo sob política pública, exija consentimento específico/destacado quando:
- Finalidade for **secundária** ao serviço público principal;
- Houver **transferência internacional**;
- Houver **compartilhamento com entes não previstos** na publicação Art. 23;
- Titular for **criança/adolescente** (Art. 14).

## 3. Pattern de consentimento granular

### 3.1 Mock-up textual da UI

```
+----------------------------------------------------------------------+
| TERMO DE PRIVACIDADE - PARTICIPE IBRAM                v3.2  06/05/26 |
|                                                                       |
| O IBRAM coleta os dados abaixo para execucao de politica publica     |
| museologica federal (Lei 11.906/2009; Decreto 8.124/2013), com base  |
| no Art. 7o, III e Art. 11, II, "b" da LGPD.                          |
|                                                                       |
| [x] Acessei a Politica de Privacidade (versao 3.2)        [link]     |
|     -> SHA-256 da politica armazenado no aceite                      |
|                                                                       |
| --- Tratamentos baseados em politica publica (nao opcionais) ------- |
|  - Dados cadastrais (CPF, nome, e-mail) - identificacao              |
|    e comunicacao institucional. Base: Art. 7o, III LGPD.             |
|  - Dados etnico-raciais (raca/cor) - exigido por Lei 14.553/2023.    |
|  - Genero/orientacao/deficiencia/PCT - indicadores de equidade.      |
|    Permite "prefiro nao informar".                                   |
|                                                                       |
| --- Tratamentos opcionais (consentimento granular) ----------------- |
| [ ] Receber boletim informativo do IBRAM por e-mail                  |
|     Base: Art. 7o, I LGPD. Revogavel a qualquer tempo em <link>.     |
| [ ] Receber convites para pesquisas cientificas IBRAM/parceiros      |
|     Base: Art. 7o, I LGPD. Revogavel.                                |
| [ ] Compartilhar meus dados pseudonimizados com SBM para estatistica |
|     Base: Art. 7o, I + Art. 11 LGPD.                                 |
| [ ] Aceitar cookies de analytics (nao essenciais)                    |
|                                                                       |
| Acesse, corrija ou elimine seus dados, ou revogue consentimentos     |
| em: https://participe.museus.gov.br/meus-dados                       |
|                                                                       |
| Encarregado (DPO): encarregado@museus.gov.br                         |
| [ Confirmar e prosseguir ]                                           |
+----------------------------------------------------------------------+
```

Princípios: caixas separadas por finalidade; pré-marcação proibida; linguagem clara; versão visível; revogação no mesmo nível de fricção do aceite (Art. 8º, §5º).

### 3.2 Estrutura de dados (versionamento + registro)

```sql
-- Versoes do termo
CREATE TABLE wp_pi_privacy_policy (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    version      VARCHAR(16) NOT NULL UNIQUE,
    published_at DATETIME NOT NULL,
    effective_at DATETIME NOT NULL,
    body_html    MEDIUMTEXT NOT NULL,
    body_sha256  CHAR(64) NOT NULL,
    pdf_url      VARCHAR(512) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Catalogo de finalidades
CREATE TABLE wp_pi_consent_purpose (
    code            VARCHAR(64) PRIMARY KEY,
    label           VARCHAR(255) NOT NULL,
    legal_basis     ENUM('consent','public_policy','legal_obligation','legitimate_interest',
                         'contract','vital_interest','research','rights_exercise',
                         'health_protection','credit_protection') NOT NULL,
    legal_reference VARCHAR(512) NULL,
    sensitive       TINYINT(1) NOT NULL DEFAULT 0,
    required        TINYINT(1) NOT NULL DEFAULT 0,
    active          TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Append-only (prova juridica)
CREATE TABLE wp_pi_consent_log (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NOT NULL,
    purpose_code    VARCHAR(64) NOT NULL,
    policy_version  VARCHAR(16) NOT NULL,
    action          ENUM('granted','revoked') NOT NULL,
    occurred_at     DATETIME(3) NOT NULL,
    source          VARCHAR(64) NOT NULL,
    ip_hash         CHAR(64) NULL,
    user_agent_hash CHAR(64) NULL,
    proof_payload   JSON NULL,
    INDEX idx_user_purpose (user_id, purpose_code, occurred_at),
    FOREIGN KEY (purpose_code) REFERENCES wp_pi_consent_purpose(code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- View "estado atual"
CREATE OR REPLACE VIEW v_pi_consent_current AS
SELECT user_id, purpose_code, action, occurred_at, policy_version
FROM wp_pi_consent_log cl1
WHERE occurred_at = (
    SELECT MAX(cl2.occurred_at) FROM wp_pi_consent_log cl2
    WHERE cl2.user_id = cl1.user_id AND cl2.purpose_code = cl1.purpose_code
);
```

Append-only porque o controlador precisa **provar** quando, para qual versão e em qual escopo o consentimento foi dado/revogado.

### 3.3 Revogação (Art. 8º, §5º)

- Endpoint dedicado e ostensivo (`/meus-dados/consentimentos`).
- 1 clique = 1 revogação. Sem dark pattern.
- Insert imediato em `consent_log(action='revoked')`.
- Para imediatamente o tratamento baseado naquele consentimento.
- Notificar terceiros que receberam os dados (Art. 18, §6º).

## 4. Criptografia em PHP com libsodium

### 4.1 Decisões de design

| Aspecto | Escolha | Justificativa |
|---|---|---|
| Cifra simétrica | `sodium_crypto_secretbox` (XSalsa20+Poly1305) | AEAD nativa em PHP ≥7.2 |
| Chave | 32 bytes (`SODIUM_CRYPTO_SECRETBOX_KEYBYTES`) | Fixo da primitiva |
| Nonce | 24 bytes via `random_bytes` | 24 bytes torna seguro o sorteio aleatório |
| Storage | `nonce || ciphertext` em base64, prefixado com versão da chave | Self-contained + rotação |
| Hash de busca | `sodium_crypto_generichash` (BLAKE2b) com chave separada | Lookup exato sem decifrar |
| Rotação | Versionamento `v1:`, `v2:` + decrypt-and-rewrite agendado | Invalida chave antiga sem perder dados |

### 4.2 Gestão de chaves no WordPress

**Não armazenar a chave em banco.** Ordem de preferência:

1. **Variável de ambiente** lida em `wp-config.php` (Apache `SetEnv` ou `.env` fora do docroot via `vlucas/phpdotenv`).
2. **wp-config.php** com `0640`, dono diferente do usuário PHP.
3. **KMS externo** (AWS KMS, GCP KMS, Vault) — **recomendado para produção federal**.

```php
// wp-config.php  (NUNCA commitar com chave preenchida)
define( 'PI_ENC_KEY_V1', getenv( 'PI_ENC_KEY_V1' ) ?: '' );
define( 'PI_ENC_KEY_CURRENT', 'v1' );
define( 'PI_HMAC_KEY', getenv( 'PI_HMAC_KEY' ) ?: '' );
define( 'PI_IP_PEPPER', getenv( 'PI_IP_PEPPER' ) ?: '' );
// Gerar: php -r "echo base64_encode(random_bytes(32));"
```

### 4.3 Implementação completa

```php
<?php
declare(strict_types=1);

namespace Ibram\ParticipeIbram\Core\Encryption;

/**
 * Criptografia em repouso para dados pessoais sensiveis.
 * Padrao de armazenamento: "<key_version>:<base64( nonce || ciphertext )>"
 */
final class SodiumCipher {

    /** @var array<string,string> map: version => raw 32-byte key */
    private array $keys;
    private string $current_version;

    public function __construct() {
        $this->keys = [
            'v1' => base64_decode( PI_ENC_KEY_V1, true ) ?: '',
            // 'v2' => base64_decode( PI_ENC_KEY_V2, true ) ?: '',
        ];
        foreach ( $this->keys as $v => $k ) {
            if ( strlen( $k ) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES ) {
                throw new \RuntimeException( "Chave de criptografia {$v} invalida." );
            }
        }
        $this->current_version = PI_ENC_KEY_CURRENT;
        if ( ! isset( $this->keys[ $this->current_version ] ) ) {
            throw new \RuntimeException( 'PI_ENC_KEY_CURRENT aponta para versao inexistente.' );
        }
    }

    public function encrypt( string $plaintext ): string {
        $version = $this->current_version;
        $key     = $this->keys[ $version ];
        $nonce   = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
        $cipher  = sodium_crypto_secretbox( $plaintext, $nonce, $key );
        $blob    = base64_encode( $nonce . $cipher );
        sodium_memzero( $plaintext );
        return $version . ':' . $blob;
    }

    public function decrypt( string $stored ): string {
        $sep = strpos( $stored, ':' );
        if ( $sep === false ) {
            throw new \RuntimeException( 'Formato invalido (sem prefixo de versao).' );
        }
        $version = substr( $stored, 0, $sep );
        $blob    = base64_decode( substr( $stored, $sep + 1 ), true );
        if ( $blob === false ) {
            throw new \RuntimeException( 'Base64 invalido.' );
        }
        if ( ! isset( $this->keys[ $version ] ) ) {
            throw new \RuntimeException( "Chave da versao {$version} indisponivel." );
        }
        $nonce_len = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
        if ( strlen( $blob ) < $nonce_len + SODIUM_CRYPTO_SECRETBOX_MACBYTES ) {
            throw new \RuntimeException( 'Payload truncado.' );
        }
        $nonce  = substr( $blob, 0, $nonce_len );
        $cipher = substr( $blob, $nonce_len );
        $plain  = sodium_crypto_secretbox_open( $cipher, $nonce, $this->keys[ $version ] );
        if ( $plain === false ) {
            throw new \RuntimeException( 'Falha de autenticacao (MAC invalido).' );
        }
        return $plain;
    }

    /** HMAC-style deterministico para buscas exatas. NAO usa a chave de criptografia. */
    public function search_hash( string $value ): string {
        $hmac_key = base64_decode( PI_HMAC_KEY, true ) ?: '';
        if ( strlen( $hmac_key ) !== SODIUM_CRYPTO_GENERICHASH_KEYBYTES ) {
            throw new \RuntimeException( 'PI_HMAC_KEY invalida.' );
        }
        $normalized = mb_strtolower( trim( $value ), 'UTF-8' );
        return sodium_bin2hex(
            sodium_crypto_generichash( $normalized, $hmac_key, 32 )
        );
    }

    /** Rotacao: rewrite para a versao corrente. */
    public function rewrap( string $stored ): string {
        return $this->encrypt( $this->decrypt( $stored ) );
    }
}
```

### 4.4 Padrão de uso em colunas (alinhado ao SCHEMA.md do Participe Ibram)

```sql
-- Em wp_pi_agentes_pf:
cpf_enc                VARBINARY(255) DEFAULT NULL,    -- v1:base64...
cpf_hash               CHAR(64) DEFAULT NULL,          -- HMAC para busca exata
rg_enc                 VARBINARY(255) DEFAULT NULL,
passaporte_enc         VARBINARY(255) DEFAULT NULL,

-- Em wp_pi_agentes_or:
cnpj_enc               VARBINARY(255) DEFAULT NULL,
cnpj_hash              CHAR(64) DEFAULT NULL,
```

### 4.5 Rotação de chaves

1. Gerar `PI_ENC_KEY_V2`; adicioná-la **sem** alterar `PI_ENC_KEY_CURRENT`.
2. Trocar `PI_ENC_KEY_CURRENT = 'v2'`. Novos dados gravam v2; antigos decifram v1.
3. WP-Cron percorre registros `v1:` em batches (N=200) e chama `rewrap()`.
4. Quando 100% rotacionado, manter v1 em arquivo morto offline e remover do `wp-config`.

### 4.6 Encrypt-at-rest vs hash-for-search

| | Encrypt-at-rest | Hash-for-search |
|---|---|---|
| Reversível? | Sim | Não |
| Chave | `PI_ENC_KEY_*` (secretbox) | `PI_HMAC_KEY` (BLAKE2b/HMAC) |
| Busca exata? | Não | Sim |
| Substring? | Não | Não |
| Quando usar | Todo dado sensível em repouso | Lookup exato (CPF, e-mail) |

**Nunca** reutilizar a mesma chave para encrypt e HMAC. **Sempre** chaves distintas em variáveis distintas.

## 5. Anonimização vs. pseudonimização (Art. 5º, X-XI)

### 5.1 Definições legais

- **Anonimização (Art. 5º, XI):** o dado **perde** a possibilidade de associação direta ou indireta a um indivíduo.
- **Dado anonimizado (Art. 5º, III):** **não é dado pessoal** (Art. 12) — fora do escopo da LGPD —, salvo se a anonimização puder ser revertida com esforços razoáveis.
- **Pseudonimização (Art. 13, §4º):** dado perde associação **exceto pelo uso de informação adicional mantida separadamente** em ambiente seguro. **Continua sendo dado pessoal.**

### 5.2 Quando aplicar cada uma

| Cenário | Técnica | Justificativa |
|---|---|---|
| Portal de Dados Abertos | **Anonimização** + k-anonymity (k≥5) | Sai do escopo LGPD. |
| Pesquisa científica IBRAM interna | **Pseudonimização** | Permite recontato/correção. |
| Logs de auditoria | **Pseudonimização** (hash user_id+pepper) | Reduz risco. |
| BI / dashboards | **Pseudonimização** + agregação | Equilíbrio. |
| Backups frios | Cifrados (não anonimizados) | Necessário restaurar. |

### 5.3 Pattern para extrato de BI

```sql
CREATE TABLE bi_pi_pseudo_users (
    pseudo_id     CHAR(64) PRIMARY KEY,         -- HMAC(user_id) com chave de BI
    age_bucket    ENUM('18-24','25-34','35-49','50-64','65+','na'),
    state_uf      CHAR(2),
    race_label    VARCHAR(32),
    gender_label  VARCHAR(32),
    pct_flag      TINYINT(1),
    created_month DATE
);
```

**Anonimização para portal aberto:**

```sql
SELECT age_bucket, state_uf, race_label, gender_label, COUNT(*) AS n
FROM bi_pi_pseudo_users
GROUP BY age_bucket, state_uf, race_label, gender_label
HAVING n >= 5;
```

Se algum cruzamento (UF × idade × raça × gênero) der n<5, generalizar (regiões em vez de UF) ou suprimir.

## 6. Direitos do titular (Art. 18) — endpoints REST

| # | Direito | Prazo | Formato |
|---|---|---|---|
| I | Confirmação da existência de tratamento | Imediato (simplificada) ou 15 dias (completa) | Eletrônico ou impresso |
| II | Acesso aos dados | 15 dias | Idem |
| III | Correção | 15 dias | Idem |
| IV | Anonimização, bloqueio, eliminação de dados desnecessários/excessivos | 15 dias | Idem |
| V | Portabilidade | 15 dias | Estruturado (JSON/CSV) |
| VI | Eliminação de dados tratados com base em consentimento | 15 dias | Confirmação eletrônica |
| VII | Informação sobre compartilhamento | 15 dias | Lista eletrônica |
| VIII | Informação sobre não-consentimento e consequências | Imediato (na coleta) | UI |
| IX | Revogação do consentimento | Imediato | UI auto-serviço |

Adicionalmente, **Art. 20** garante revisão de decisão automatizada e explicação dos critérios.

### 6.1 Routes REST (`pi/v1`)

| Método | Rota | Direito | Resposta |
|---|---|---|---|
| GET | `/pi/v1/me/data-summary` | I | `{ has_processing, purposes }` |
| GET | `/pi/v1/me/data-export?format=json|csv` | II, V | Arquivo estruturado |
| PATCH | `/pi/v1/me/profile` | III | `{ updated, errors }` |
| POST | `/pi/v1/me/dsr-requests` | IV, VI | `{ request_id, status, deadline }` |
| GET | `/pi/v1/me/dsr-requests/{id}` | acompanhar | status + history |
| GET | `/pi/v1/me/sharing-log?since=` | VII | lista de cessões |
| GET | `/pi/v1/me/consents` | IX | consentimentos vigentes |
| POST | `/pi/v1/me/consents/{purpose_code}/revoke` | IX | confirmação imediata |
| POST | `/pi/v1/me/automated-review` | Art. 20 | abre revisão humana |

### 6.2 Payload exemplo: data-export

```json
{
  "request_id": "dsr_2026-05-06_a1b2c3",
  "generated_at": "2026-05-06T14:30:00-03:00",
  "data_subject": {
    "user_id": 12345,
    "cpf_masked": "***.***.789-**",
    "name": "Fulano de Tal",
    "email": "fulano@example.org"
  },
  "personal_data": {
    "demographic": {
      "race": "Parda",
      "gender": "Mulher cisgenero",
      "sexual_orientation": "Bissexual",
      "disability": null,
      "pct": "Quilombola",
      "self_declared": true,
      "collected_at": "2024-08-12T10:11:00-03:00",
      "legal_basis": {
        "code": "public_policy",
        "reference": "Lei 11.906/2009; LGPD Art. 11, II, b"
      }
    }
  },
  "consents": [
    { "purpose_code": "newsletter", "current_state": "granted",
      "since": "2024-08-12T10:11:00-03:00", "policy_version": "3.2" }
  ],
  "shared_with": [
    { "recipient": "Sistema Brasileiro de Museus (SBM)",
      "purpose": "Estatisticas pseudonimizadas",
      "shared_at": "2025-12-01",
      "data_categories": ["demographic_pseudonymized"] }
  ],
  "policies": {
    "retention_until": "2031-08-12",
    "controller": "Instituto Brasileiro de Museus (IBRAM)",
    "dpo_contact": "encarregado@museus.gov.br"
  }
}
```

### 6.3 Requisitos transversais

- Autenticação forte (2FA/OTP) antes de export.
- Rate-limit por user_id (1 export/24h).
- Log de auditoria de cada DSR.
- Alerta ao DPO em D+10 sem atendimento; escalação automática.

## 7. Estrutura de DPIA / RIPD

LGPD Art. 38 requer no mínimo: descrição dos tipos de dados; metodologia de coleta e segurança; análise de medidas, salvaguardas e mitigação de risco.

Para o Participe Ibram o RIPD é **indispensável** (sensíveis em larga escala, órgão público, dados de PCT, possível compartilhamento entre órgãos).

### Estrutura mínima

```
1. IDENTIFICACAO
   1.1 Controlador (IBRAM - CNPJ, endereco)
   1.2 Operadores (Serpro, fornecedores PaaS)
   1.3 Encarregado (nome, contato publico)
   1.4 Versao, data, assinaturas

2. DESCRICAO DO TRATAMENTO
   2.1 Finalidade(s)
   2.2 Necessidade e proporcionalidade
   2.3 Base legal por finalidade (Art. 7o / Art. 11)
   2.4 Categorias de titulares
   2.5 Categorias de dados (com flag sensivel)
   2.6 Volume estimado
   2.7 Fluxo de dados (diagrama)
   2.8 Compartilhamento e transferencias internacionais

3. CICLO DE VIDA
   3.1 Coleta / 3.2 Armazenamento / 3.3 Acesso (MFA)
   3.4 Retencao e eliminacao / 3.5 Backup e DR

4. PRINCIPIOS LGPD (Art. 6o) - checklist

5. DIREITOS DOS TITULARES - como cada um e atendido tecnicamente

6. ANALISE DE RISCO
   6.1 Ameacas / 6.2 Probabilidade x impacto / 6.3 Risco residual

7. MEDIDAS
   7.1 Tecnicas / 7.2 Organizacionais / 7.3 Resposta a incidentes

8. CONSULTA AOS TITULARES (quando aplicavel)

9. APROVACOES E REVISAO ANUAL
```

Modelo oficial: **Guia/Modelo de RIPD do PPSI** (`gov.br/governodigital/.../guia_template_ripd.docx`).

## 8. Procedimento de resposta a incidentes (Art. 48 + Resolução CD/ANPD 15/2024)

### 8.1 Critério para acionamento (cumulativo)

1. Incidente confirmado pelo controlador.
2. Envolve dados pessoais sob LGPD.
3. Pode causar **risco ou dano relevante** aos titulares.

Mesmo se não comunicado externamente, **registrar internamente** e manter por **5 anos**.

### 8.2 Janela legal

- **3 dias úteis** para notificar ANPD + titulares.
- **20 dias úteis** para complementação posterior.

### 8.3 Conteúdo mínimo

1. Natureza e categoria de dados afetados.
2. Medidas técnicas e de segurança aplicadas.
3. Riscos e impactos potenciais.
4. Motivos de eventual atraso.
5. Medidas adotadas/a adotar para mitigar.
6. Data em que o incidente foi descoberto.
7. Contato do Encarregado.

### 8.4 Runbook

```
T+0     DETECCAO
        SOC ou denuncia ao DPO. Abrir ticket categoria LGPD.
        Notificar Encarregado + CISO em ate 1h.
T+1h    CONTENCAO
        Isolar sistemas. Rotacionar credenciais. Snapshot forense.
T+1d    ANALISE DE IMPACTO
        Quantos titulares? Que categorias? Sensiveis? Criancas? PCT?
T+3d    NOTIFICACAO (se aplicavel)
        ANPD: formulario CIS no portal. Titulares: e-mail + aviso no portal.
T+20d   COMPLEMENTACAO
        Causa raiz, novos dados, plano de acao.
T+30d   POST-MORTEM
        Revisao do RIPD afetado. Atualizacao de controles.
5 anos  RETENCAO DO REGISTRO INTERNO
```

## 9. Encarregado (DPO) — atribuições e ferramentas

### 9.1 Atribuições (Art. 41, §2º)

1. Aceitar reclamações e comunicações dos titulares.
2. Receber comunicações da ANPD.
3. Orientar funcionários e contratados.
4. Demais atribuições determinadas pelo controlador.
5. Identidade e contato **publicados** no site institucional.

### 9.2 Ferramentas no plugin

| Ferramenta | Função | Implementação |
|---|---|---|
| Painel DPO | Visão consolidada DSR/incidentes/RIPD | Admin page + cap `pi_manage_dpo` |
| Fila de DSRs | Triagem dos pedidos do Art. 18 | List table com filtros |
| Editor de RIPD | Manter RIPDs por finalidade | CPT + JSON Schema |
| Registro de incidentes | Append-only, retenção 5 anos | Tabela própria; bloquear UPDATE/DELETE |
| Catálogo de finalidades | CRUD de `wp_pi_consent_purpose` | Admin UI |
| Página Art. 23 | Auto-gerada do catálogo | Front-end |
| Logs de acesso PII | Quem decifrou o quê | Hook em `SodiumCipher::decrypt` |
| Exportador de auditoria | CSV/JSON para a ANPD | Admin export |

### 9.3 Capability dedicada

```php
add_role( 'pi_dpo', 'Encarregado IBRAM', [
    'read' => true,
    'pi_manage_dpo'      => true,
    'pi_view_dsr'        => true,
    'pi_resolve_dsr'     => true,
    'pi_view_incident'   => true,
    'pi_create_incident' => true,
    'pi_view_ripd'       => true,
    'pi_edit_ripd'       => true,
    // SEM pi_view_pii_decrypted: DPO nao decifra PII alheia por padrao.
] );
```

## 10. Retenção e eliminação (Art. 15-16)

### 10.1 Critérios para fim do tratamento (Art. 15)

- Finalidade atingida ou dados deixaram de ser necessários.
- Fim do período de tratamento.
- Comunicação do titular (revogação — Art. 8º, §5º).
- Determinação da ANPD por violação.

### 10.2 Conservação após fim (Art. 16)

1. Cumprimento de obrigação legal/regulatória (Decreto 20.910/1932 — 5 anos para a Fazenda Pública).
2. Estudo por órgão de pesquisa (com anonimização sempre que possível).
3. Transferência a terceiro respeitando os requisitos legais.
4. Uso exclusivo do controlador, vedado acesso por terceiros, sob anonimização.

### 10.3 Política proposta

| Categoria | Prazo ativo | Após | Fundamento |
|---|---|---|---|
| Cadastro ativo | Enquanto ativo + 5 anos pós-inatividade | Anonimização ou eliminação | Art. 16, IV |
| Logs de auditoria | 5 anos | Eliminação | Res. ANPD 15/2024 + boas práticas |
| Consent log | Indefinido (prova) | Mantém referência | Accountability |
| Dados étnico-raciais | Vinculado ao cadastro | Idem | Lei 14.553/2023 |
| Dados de menores | Cadastro + restrição | Eliminação prioritária na maioridade | Art. 14 |
| Backups | Máx. 12 meses (BCP) | Rotação automática | Necessidade |

## 11. Boas práticas internacionais

### 11.1 GDPR Art. 32

LGPD Art. 46 espelha em parte. Adotar:
- Pseudonimização e criptografia.
- Capacidade de assegurar confidencialidade, integridade, disponibilidade, resiliência.
- Capacidade de restaurar disponibilidade após incidente (RTO/RPO).
- Teste, avaliação e validação regulares.

### 11.2 NIST SP 800-53 (Rev. 5)

| Família | Controles | Aplicação |
|---|---|---|
| AC | AC-2, AC-3, AC-6 | Capabilities WP, segregação |
| AU | AU-2, AU-3, AU-12 | Log PII, log DSR, retenção 5 anos |
| IA | IA-2 (MFA), IA-5 | MFA para roles privilegiadas; gov.br |
| SC | SC-8, SC-13, SC-28 | TLS 1.3; libsodium em repouso |
| SI | SI-4, SI-7 | Monitor de anomalias; assinatura de releases |
| IR | IR-4, IR-6, IR-8 | Runbook §8; integração CTIR Gov |

## 12. Checklist de conformidade (gate de release)

### Bases legais e transparência
- [ ] Cada finalidade em `wp_pi_consent_purpose` com base legal explícita.
- [ ] Página pública Art. 23 gerada automaticamente do catálogo.
- [ ] Política versionada com SHA-256 e data; diff visível.
- [ ] Aceite registrado com `policy_version` e `proof_payload`.

### Consentimento
- [ ] Checkboxes granulares por finalidade — sem agrupamento.
- [ ] Sem pré-marcação.
- [ ] Fluxo específico para crianças/adolescentes (Art. 14).
- [ ] Revogação 1-clique funcional.
- [ ] Notificação a operadores quando revogação afeta compartilhamento.

### Direitos do titular
- [ ] 9 endpoints REST do §6.1 implementados e documentados.
- [ ] Resposta em ≤15 dias monitorada (alerta D+10).
- [ ] Export estruturado e portável (JSON/CSV).
- [ ] Auditoria 2FA antes de export.
- [ ] Rate-limit aplicado.

### Segurança
- [ ] `PI_ENC_KEY_*` e `PI_HMAC_KEY` em env vars, não em código nem em DB.
- [ ] TLS 1.3 obrigatório (HSTS).
- [ ] Todos campos sensíveis (Art. 5º, II) cifrados em repouso.
- [ ] CPF e e-mail com `search_hash` separado (chaves distintas).
- [ ] Logs de acesso a dados decifrados.
- [ ] MFA para `pi_dpo`, `administrator` e roles com `pi_view_pii_decrypted`.
- [ ] Rotação de chave testada em staging.
- [ ] Sodium ≥ 1.0.18; CI valida `function_exists('sodium_crypto_secretbox')`.
- [ ] Backup criptografado com chave segregada.

### Pseudonimização / Anonimização
- [ ] Extratos para BI passam por pseudonimização determinística com chave de BI.
- [ ] Datasets publicados respeitam k-anonymity ≥5.
- [ ] Análise documentada de risco de reidentificação para datasets abertos.

### RIPD (Art. 38)
- [ ] RIPD elaborado e assinado pelo Encarregado e autoridade.
- [ ] Revisão a cada 12 meses ou em mudança material.
- [ ] Risco residual classificado e aceito formalmente.

### Incidentes (Art. 48 + Res. 15/2024)
- [ ] Runbook publicado e treinado.
- [ ] Canal ao DPO ≤1h.
- [ ] Registro append-only com retenção 5 anos.
- [ ] Templates de notificação à ANPD e titular pré-aprovados pelo jurídico.

### Encarregado
- [ ] DPO nomeado em ato formal do IBRAM.
- [ ] Identidade e contato publicados no site institucional.
- [ ] Painel DPO no plugin com permissões corretas.

### Retenção
- [ ] Política de retenção documentada por categoria.
- [ ] Job de eliminação/anonimização agendado (WP-Cron + monitor).
- [ ] Registro probatório da eliminação.

### Operadores e contratos
- [ ] Cláusula LGPD em contrato com cada operador.
- [ ] Lista de operadores publicada.
- [ ] Avaliação de risco do operador ≥1x/ano.

## 13. URLs e referências consultadas

### Legislação (fontes oficiais)
- Lei nº 13.709/2018 (LGPD): https://www.planalto.gov.br/ccivil_03/_ato2015-2018/2018/lei/l13709.htm
- Lei nº 14.553/2023 (dados étnico-raciais): https://www.planalto.gov.br/ccivil_03/_ato2023-2026/2023/lei/L14553.htm
- Decreto nº 8.750/2016 (CNPCT): https://www.planalto.gov.br/ccivil_03/_ato2015-2018/2016/decreto/d8750.htm

### ANPD (gov.br/anpd)
- Direitos dos titulares
- Comunicação de incidente (CIS)
- Resolução CD/ANPD nº 15/2024 (incidentes)
- Guia da atuação do Encarregado
- Guia Tratamento pelo Poder Público
- Guia Segurança da Informação
- Guia Cookies
- Enunciado dados de crianças

### Modelo RIPD (PPSI gov.br)
- https://www.gov.br/governodigital/pt-br/privacidade-e-seguranca/ppsi/guia_template_ripd.docx

### Documentação técnica
- PHP Sodium: https://www.php.net/manual/en/function.sodium-crypto-secretbox.php
- Paragonie Basic: https://paragonie.com/book/pecl-libsodium/read/04-secretkey-crypto.md
- libsodium-php: https://github.com/jedisct1/libsodium-php

### Frameworks internacionais
- GDPR Art. 32: https://gdpr-info.eu/art-32-gdpr/
- NIST SP 800-53 Rev. 5: https://csrc.nist.gov/publications/detail/sp/800-53/rev-5/final
- ISO/IEC 27701:2019

---

## Notas de atualização vs. especificações iniciais

1. **Prazo de notificação de incidente é 3 dias úteis** (Resolução CD/ANPD 15/2024) — não 2 dias como o briefing original sugeria. ARQUITETURA atualizada deve refletir isto.
2. **Lei 14.553/2023** torna obrigatória a coleta de dados étnico-raciais em registros administrativos públicos — fortalece "obrigação legal" como base para raça/cor.
3. **Encrypt key versionada `vN:`** suporta rotação sem migração destrutiva.
4. **Hash-de-busca usa chave separada** (`PI_HMAC_KEY` distinta de `PI_ENC_KEY_*`) — não compartilhar chaves entre encrypt e HMAC.
5. **`wp_pi_consent_log` append-only** — sem updates, garantia de prova jurídica.
6. **DPO não recebe `pi_view_pii_decrypted` por padrão** (princípio da minimização).
