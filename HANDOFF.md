# HANDOFF — Participe Ibram refactor

> Documento de transferência de contexto. Leia este arquivo primeiro ao retomar o projeto em outro VSCode/máquina.

---

## 1. O que é este projeto

Refactor completo do plugin `crm-developer` (WordPress) para virar a plataforma federal **Participe Ibram** do **IBRAM** (Instituto Brasileiro de Museus, autarquia do Ministério da Cultura). Hospedagem prevista em `cadastro.museus.gov.br`.

**Norma vigente:** Portaria IBRAM nº 3230/2024 + Despacho 98/2025-DDFEM. Documentos-fonte em `C:\Users\marcos.sigismundo\Documents\TAINACAN\PARTICIPA IBRAM` (na máquina original; copie se mudar de máquina).

**Escopo funcional:**
- Cadastro de Agentes para Participação Social (3 tipologias: PF, OR=Organização, SM=Sistema/Secretaria)
- Análise técnica + workflow de recursos (retratação → Presidência)
- Editais (Despacho 98/2025: Divulgação → Lançamento → Manifestação → Habilitação → Recurso → Votação → Resultado)
- Votação eletrônica auditável com anti-rastreio voto↔eleitor
- Conformidade LGPD rigorosa (consentimento granular, libsodium, anonimização, Art. 18 endpoints)
- Acessibilidade WCAG 2.1 AA + eMAG, alinhamento gov.br Design System

---

## 2. Estado atual (snapshot)

| Onda | Modelo | Status | Arquivos | Notas |
|---|---|---|---|---|
| 0 — Pesquisa | Opus | ✅ | 5 relatórios | DSGov, LGPD, gov.br OIDC, eMAG, Code Review |
| 1 — Core Infrastructure | Opus 4.7 | ✅ | 50 | DI, encryption, audit, validators, schema runner |
| 2 — Domain | Opus 4.7 | ✅ | +125 | 6 bounded contexts, state machines, repos |
| 3 — Cadastro Wizard | Opus 4.7 | ✅ | +118 | Wizard PF/OR/SM, REST, SCSS DSGov, LGPD UI |
| 4 — Admin + Email | Opus 4.7 | ✅ | +80 | Fila análise, recurso, queue worker assíncrono |
| 5 — Editais | **Sonnet 4.6** | ✅ | +54 | ⚠️ **Auditar na Onda 10** (ver `docs/refactor/AGENTS-PLAN.md` §"ALERTA") |
| 6 — Votação | Opus 4.7 | ✅ | +53 | Anti-rastreio, transparência pública, apuração |
| 7 — Comunicação + Audit UI | a decidir | ⏳ pendente | — | candidato Sonnet (pattern-following) |
| 8 — Minha conta | a decidir | ⏳ pendente | — | área autenticada do agente |
| 9 — REST + LAI | a decidir | ⏳ pendente | — | endpoints públicos LAI + portabilidade LGPD |
| 10 — QA final | **Opus obrigatório** | ⏳ pendente | — | revisão crítica + auditoria reforçada Onda 5 |

**Total atual: 480 arquivos** (~70K LOC + JS + SCSS + templates + testes).

---

## 3. Repositório

- **Remote:** [github.com/marcossigismundo/crm-developer](https://github.com/marcossigismundo/crm-developer)
- **Branch ativa:** `refactor/participe-ibram` (master tem o `crm-developer` original preservado)
- **Último commit pushed:** `f5ae9c4` (Waves 1-5)
- **Último commit local:** depende — verificar com `git log` ao retomar

```bash
git clone https://github.com/marcossigismundo/crm-developer
cd crm-developer
git checkout refactor/participe-ibram
git log --oneline -5
```

---

## 4. Estrutura de pastas

```
crm-developer/
├── HANDOFF.md                          # este arquivo
├── participe-ibram.php                 # bootstrap (substitui crm-developer.php no novo plugin slug)
├── crm-developer.php                   # plugin antigo (preservado para migração futura)
├── composer.json
├── phpunit.xml.dist
├── readme.txt
├── uninstall.php
├── docs/
│   ├── refactor/                       # SPECS — leia antes de continuar
│   │   ├── ARCHITECTURE.md             # 18 decisões fundamentais (TD-01 a TD-18)
│   │   ├── SCHEMA.md                   # 26 tabelas wp_pi_*
│   │   ├── LGPD.md                     # bases legais, criptografia, Art. 18
│   │   ├── VOCABULARIES.md             # 13 vocabulários populáveis
│   │   ├── AGENTS-PLAN.md              # plano de ondas + ⚠️ auditoria Onda 5
│   │   └── research/
│   │       ├── R1-dsgov.md             # gov.br Design System @3.7.0
│   │       ├── R2-lgpd.md              # LGPD federal + libsodium pattern
│   │       ├── R3-govbr-oidc.md        # AuthProviderInterface + GovBrAuth stub
│   │       ├── R4-acessibilidade.md    # eMAG + WCAG 2.1 AA
│   │       └── R5-code-review.md       # 41 convenções obrigatórias
│   └── memory-handoff/                 # entradas de memória (re-import em nova máquina)
│       ├── MEMORY.md
│       ├── participe-ibram-project.md
│       ├── feedback-subagent-orchestration.md
│       ├── reference-ibram-sources.md
│       └── project-wave5-sonnet.md
├── migrations/
│   ├── V001__init.sql                  # 26 tabelas
│   ├── V002__seed_vocabularios.sql     # 13 vocabulários, 152+ itens
│   └── V003__seed_tipos_documento.sql
├── src/                                # código novo (PSR-4: Ibram\ParticipeIbram\*)
│   ├── Bootstrap/                      # Plugin singleton, DI Container, Activator
│   ├── Core/                           # Audit, Database, Encryption, Helpers, Logger, Network, Validation
│   ├── Domain/                         # Agente, Analise, Consentimento, Documento, Edital, Email, Vocabulario, Votacao
│   ├── Application/                    # Use cases (Commands + Handlers + Adapters + Ports)
│   ├── Infrastructure/                 # Repository (Wpdb*), Storage (PrivateFileStorage), Auth (stubs)
│   └── Presentation/                   # Admin (Controllers, ListTables, Ajax, Cron, Helpers, Support), Public, Rest, Assets
├── templates/                          # PHP templates (admin + public + emails)
├── assets/
│   ├── src/                            # SCSS source + JS modules ES6
│   └── dist/                           # CSS+JS pré-compilados (sem npm ainda — Wave 10 pode wirear esbuild/vite)
└── tests/                              # PHPUnit Unit + Integration
```

**Estrutura preservada:** `includes/`, `admin/`, `public/`, `assets/css/`, `assets/js/` (do plugin antigo) NÃO foram tocadas — ficam para migração ou cleanup na Onda 10.

---

## 5. Constantes wp-config.php obrigatórias

Adicione ao `wp-config.php` antes do `/* That's all, stop editing! */`. Cada chave gerada por `php -r "echo base64_encode(random_bytes(32));"` — **as 6 devem ser DISTINTAS entre si** (princípio de segregação por finalidade).

```php
// === Participe Ibram ========================================================

// Encryption-at-rest (libsodium secretbox) — CPF, RG, Passaporte, CNPJ
define('PI_ENC_KEY_V1', getenv('PI_ENC_KEY_V1') ?: '');
define('PI_ENC_KEY_CURRENT', 'v1');

// HMAC para busca exata (CPF/CNPJ hash) — DEVE ser distinta de PI_ENC_KEY_V*
define('PI_HMAC_KEY', getenv('PI_HMAC_KEY') ?: '');

// Pepper para HMAC de IPs em audit log (não armazena IP cru)
define('PI_IP_PEPPER', getenv('PI_IP_PEPPER') ?: '');

// Voting secret (anti-rastreio voto↔eleitor) — DEVE ser distinta das demais
define('PI_VOTING_SECRET', getenv('PI_VOTING_SECRET') ?: '');

// Unsubscribe tokens (HMAC com expiração 90d)
define('PI_UNSUBSCRIBE_SECRET', getenv('PI_UNSUBSCRIBE_SECRET') ?: '');

// gov.br OIDC (feature flag — fica desligada até credenciais homologadas)
define('PI_GOVBR_ENABLED', false);
define('PI_GOVBR_ENV', 'staging');
define('PI_GOVBR_CLIENT_ID',     getenv('PI_GOVBR_CLIENT_ID')     ?: '');
define('PI_GOVBR_CLIENT_SECRET', getenv('PI_GOVBR_CLIENT_SECRET') ?: '');
define('PI_GOVBR_REDIRECT_URI',  'https://cadastro.museus.gov.br/wp-login.php?action=govbr_callback');
define('PI_GOVBR_LOGOUT_URI',    'https://cadastro.museus.gov.br/');

// Trusted proxies (opcional — para ambientes com CDN/load balancer)
define('PI_TRUSTED_PROXIES', '10.0.0.0/8,127.0.0.1');
```

**Em produção:** carregar via env vars (Apache `SetEnv` ou systemd `Environment=`), nunca commitar valores reais.

---

## 6. Procedimento de retomada em outra máquina/VSCode

### Passo 1 — Clone + setup

```bash
git clone https://github.com/marcossigismundo/crm-developer
cd crm-developer
git checkout refactor/participe-ibram
git log --oneline -10  # ver últimos commits
```

### Passo 2 — Re-importar memória do Claude Code (se quiser que o /memory mantenha contexto entre sessões)

Em qualquer máquina, o Claude Code procura memória em:
`<HOME>/.claude/projects/<slug-do-cwd>/memory/`

O slug é o caminho do diretório com `/` substituído por `-` e `:` removido. Para este plugin:
- Linux/Mac: `~/.claude/projects/-<...>-crm-developer/memory/`
- Windows: `%USERPROFILE%\.claude\projects\c--xampp82-htdocs-wordpress-wp-content-plugins-crm-developer\memory\` (ou ajuste o slug ao caminho local da nova máquina)

Copie os arquivos de `docs/memory-handoff/` para essa pasta:

```bash
# Linux/Mac (ajuste $TARGET ao slug real da sua nova máquina)
TARGET="$HOME/.claude/projects/-...-crm-developer/memory"
mkdir -p "$TARGET"
cp docs/memory-handoff/*.md "$TARGET/"
```

```powershell
# Windows PowerShell (slug pode mudar dependendo do path do plugin)
$slug = (Get-Location).Path -replace '[:\\/]','-'
$target = "$env:USERPROFILE\.claude\projects\$slug\memory"
New-Item -ItemType Directory -Path $target -Force | Out-Null
Copy-Item docs\memory-handoff\*.md $target
```

### Passo 3 — Verificar estado

```bash
# Sanity check: número de arquivos do refactor
find src tests templates assets/src assets/dist migrations -type f \( -name "*.php" -o -name "*.js" -o -name "*.scss" -o -name "*.css" -o -name "*.sql" \) | wc -l
# Esperado: ~480 (em sync com último commit) ou mais (se houver Wave 7 commitada)

# Status git
git status
git log --oneline -5
```

### Passo 4 — wp-config.php

Verificar/criar as 6 constantes da §5. Em ambiente local de dev, gerar chaves dummy é OK; em produção, **NUNCA** commitar valores.

### Passo 5 — Composer e dependências

```bash
composer install   # instala phpunit, phpcs WPCS, phpstan
```

### Passo 6 — Continuar a partir da próxima onda pendente

Ler `docs/refactor/AGENTS-PLAN.md` para entender o plano. Próxima onda pendente: **Onda 7 (Comunicação automática + Audit UI)**.

---

## 7. ⚠️ Lembretes críticos

### 7.1 Wave 5 foi feita com Sonnet 4.6 — auditar na Onda 10

A Onda 5 (módulo de Editais — 54 arquivos) foi executada com Sonnet 4.6 para preservar quota do Opus. A Onda 10 (QA final, com Opus) **DEVE** revisar especificamente os 10 pontos listados em `docs/refactor/AGENTS-PLAN.md` seção "ALERTA: Auditoria obrigatória da Onda 5 na Onda 10":

1. Vazamento de PII em endpoints/páginas públicos (especialmente `/publico/edital/{id}/inscritos-habilitados`)
2. Whitelist defensiva — ler código de `whitelistInscrito()` e confirmar lista fechada
3. Capability checks — grep `current_user_can` em todos os arquivos novos
4. `wp_unslash()` antes de sanitize em superglobals
5. State machine guards (Edital::publicar() etc., não SQL direto)
6. Race conditions de inscrição (UNIQUE 1062 tratamento)
7. Documentos via `PrivateFileStorage` com MIME real
8. Rate limiting em endpoints públicos
9. WCAG 2.1 AA — wizard reusa Wave 3 (verificar se W5 não introduziu padrão divergente)
10. Audit em todas as transições

### 7.2 Anti-rastreio voto↔eleitor (Onda 6) — auditar manutenção

Qualquer onda subsequente que toque em endpoint de votação, audit log, export ou hook deve preservar:
- `agente_id` JAMAIS aparece em response, hook payload, audit `dados_*`, ou export
- `eleitor_hash` é HMAC com `PI_VOTING_SECRET` (segregada de outras keys)
- Logs publicados (`audit-public`) só contêm: `ocorrido_em, categoria_id, eleitor_hash, candidato_inscricao_id, ip_hash`
- Test `AntiRastreioTest.php` é gate de release

### 7.3 Princípios LGPD a preservar

- Consentimento **granular por finalidade** (10 finalidades em `Domain/Consentimento/Finalidade.php`)
- Documentos sensíveis sempre em `wp-content/uploads/participe-ibram-private/` (com `.htaccess` deny)
- CPF/CNPJ/RG/Passaporte sempre cifrados em repouso (`SodiumCipher::encrypt`)
- Logs nunca contêm PII (use `SecureLogger` ou `PiiMasker`)
- Audit log append-only (sem UPDATE/DELETE pela aplicação)

### 7.4 Convenções obrigatórias (de R5 — code review do plugin antigo)

41 convenções listadas em `docs/refactor/research/R5-code-review.md` §7. Top 10:
1. `declare(strict_types=1);` em TODO arquivo
2. `wp_unslash()` antes de qualquer sanitize em superglobais
3. `$wpdb->prepare()` em uma única chamada (proibido concatenar strings preparadas)
4. Whitelist explícita para orderby/order/coluna dinâmica
5. `current_user_can()` no topo de toda view/handler
6. Nunca `error_log` com PII — use `SecureLogger`
7. Capabilities granulares `pi_*` (sem fallback `manage_options`)
8. Rate limit em endpoints públicos
9. CSP `script-src 'self'` (sem CDN externa)
10. `wp_json_encode` com `JSON_HEX_*` flags em script context

---

## 8. Como lançar a próxima onda (Onda 7)

A Onda 7 envolve:
- Audit log admin UI (list table + filtros + export)
- Listeners adicionais para hooks de edital/votação
- Cron de alertas DPO (15 dias prazo Art. 18 LGPD)
- Notificações broadcast aos cadastrados (edital, votação, resultado)

**Recomendação:** 3 agentes em paralelo (mesmo padrão das ondas anteriores). **Modelo: Sonnet 4.6** (pattern-following, baixo risco — segue padrões de Waves 4-5; preserva Opus para Onda 8 sensível e Onda 10 obrigatória). Marcar para auditoria reforçada na Onda 10 junto com a Wave 5.

Template de prompt para subagente:

```
# Contexto
Onda 7 do refactor Participe Ibram. Você é W7-X (descrição).
Branch: refactor/participe-ibram
Plugin dir: <plugin path>

# Specs OBRIGATÓRIAS de leitura
- docs/refactor/ARCHITECTURE.md TD-13, TD-14
- docs/refactor/AGENTS-PLAN.md §"ALERTA Onda 5"
- docs/refactor/research/R5-code-review.md §7

# Wave 1-6 já criou (use, NÃO recrie)
[lista específica]

# Sua tarefa
[escopo concreto]

# Convenções OBRIGATÓRIAS
[as 10 do §7.4 acima]

# Saída esperada
Crie os arquivos. NÃO escreva relatórios .md.
Resposta ≤ 250 palavras: lista de arquivos criados, capabilities por endpoint,
hooks disparados, decisões, status.
```

---

## 9. Open questions

Itens em aberto que precisam confirmação com a CGSIM/Ibram antes de ir para produção:

1. **Lista definitiva de "áreas temáticas"** — em `docs/refactor/VOCABULARIES.md` há sugestão de 22, recomenda-se consolidar para 12-15.
2. **Lista definitiva de "instâncias de participação"** — flag `recorrente` no metadata é flexível, mas confirmar quais estão ativas.
3. **Política exata de retenção** por categoria de dado (defaults estão em `docs/refactor/LGPD.md` §10).
4. **Integração Login gov.br** — interface pronta, ativação posterior requer cadastro institucional do Ibram em acesso.gov.br (ver `docs/refactor/research/R3-govbr-oidc.md` §2).
5. **Texto definitivo do termo LGPD** — versão sugerida em `docs/refactor/LGPD.md` §4 precisa revisão jurídica do Ibram.
6. **Modelos de documento** — geração via PHPWord recomendada (ver Wave 10 para implementar `templates/documents/*`).
7. **Stack de hospedagem `.gov.br`** — confirmar se WordPress é compatível com a infra da CGSIM ou se exige stack específica (Serpro, Dataprev).

---

## 10. Histórico de decisões importantes

| Data | Decisão | Por quê |
|---|---|---|
| 2026-05-06 | 3 tipologias PF/OR/SM (não 4 como Portaria) | Caderno de campos `.docx` consolidou PJ + Coletivo em "Organização" |
| 2026-05-06 | Número de registro `PI-{TIPO}-{ANO}-{SEQ06}` | Espelho do Cadastro Nacional de Museus (CNM) |
| 2026-05-06 | `wp_pi_consent_log` append-only | Prova jurídica para ANPD (Art. 8º §5º) |
| 2026-05-06 | `aria-current="step"` (NÃO `role="tablist"`) em wizard | Fluxo linear, R4 §4.1 |
| 2026-05-06 | libsodium `secretbox` versionado (`v1:`, `v2:`) | Suporta rotação de chave sem migração destrutiva |
| 2026-05-06 | `PI_HMAC_KEY` separada de `PI_ENC_KEY_*` | Princípio de segregação por finalidade |
| 2026-05-06 | `PI_VOTING_SECRET` separada das outras | Anti-rastreio voto↔eleitor |
| 2026-05-06 | Resolução CD/ANPD nº 15/2024: 3 dias úteis para incidente | Atualiza prazo de 2 para 3 dias |
| 2026-05-07 | Onda 5 com Sonnet 4.6 | Preservar Opus para Wave 6/10; padrões já consolidados |
| 2026-05-07 | Tie-break apuração: `total DESC, inscrito_em ASC, candidato_inscricao_id ASC` | Determinístico, sem sorteio |
| 2026-05-08 | Specs movidas para `docs/refactor/` no repo | Portabilidade entre máquinas/VSCodes |

---

## 11. Contatos / canais

- DPO IBRAM: a definir (ver `docs/refactor/LGPD.md` §4 — placeholder `{nome_dpo}` / `{email_dpo}`)
- CGSIM IBRAM: integracaoid@gestao.gov.br (canal gov.br para integrações OIDC)
- ANPD: https://www.gov.br/anpd (incidentes em até 3 dias úteis)

---

> **Lembre-se:** ao retomar, confira primeiro `docs/refactor/AGENTS-PLAN.md` (status das ondas) e este HANDOFF.md (contexto). Se algum spec divergir do código, o **código vence** e o spec deve ser atualizado.
