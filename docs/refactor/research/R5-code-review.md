# R5 — Code Review do plugin `crm-developer`

Revisão exaustiva do plugin atual (~17.265 LOC, 22 arquivos) no commit `7b14f09`. Foco federal/governamental.

## 1. Resumo executivo — Top 10 issues mais graves

| # | Sev | Categoria | Issue | Local |
|---|-----|-----------|-------|-------|
| 1 | CRÍTICO | SQLi defense-in-depth | `$base_query` "preparado" é concatenado em ~30 SELECTs com `'{$regiao}'`, `{$h}`, `{$month_start}` | `includes/class-dashboard.php:478-690` |
| 2 | CRÍTICO | SQLi/column injection | `"{$interesse_field} = 'sim'"` com valor de `sanitize_key($filters['interesse'])` (permite qualquer column existente) | `includes/class-email.php:763` |
| 3 | CRÍTICO | XSS stored | `json_encode($data)` direto em `<script>` sem flags `JSON_HEX_TAG/AMP/APOS/QUOT` no dashboard público | `public/views/dashboard.php:91,110,129` |
| 4 | CRÍTICO | XSS reflected | `<?php echo $percent; ?>%` sem escape | `public/views/dashboard.php:49` |
| 5 | CRÍTICO | LGPD/info disclosure | `error_log('CRM Dev Insert Data: ' . print_r($sanitized, true))` despeja PII completo (nome, email, telefone, raça, deficiência) | `includes/class-contacts.php:271-272` |
| 6 | CRÍTICO | Auth bypass | `can_user($cap)` retorna `true` para qualquer usuário com `manage_options`, derrotando o modelo de capabilities granular | `includes/class-helpers.php:349-351` |
| 7 | CRÍTICO | DoS | Export aceita `per_page=999999`, carrega base inteira em memória; import faz `set_time_limit(600)` + `ini_set('memory_limit', '512M')` | `includes/class-import-export.php:86,148,300` |
| 8 | ALTO | Authz missing em views | `contact-view.php`, `contact-form.php`, `reports.php` não chamam `current_user_can` — dependem só do menu pai | `admin/views/*` |
| 9 | ALTO | Anti-spam | Endpoint `wp_ajax_nopriv_crm_dev_public_register` sem rate limit, captcha, honeypot ou bloqueio | `public/class-public.php:48` |
| 10 | ALTO | XSS impressão | `html += '<tr><td>${c.nome_completo}...'` concatena dados sem escape no relatório de impressão | `admin/views/reports.php:1340-1350` |

## 2. Bugs

| ID | Sev | Arquivo:linha | Descrição | Correção |
|----|-----|--------------|-----------|----------|
| B-01 | CRÍTICO | `class-dashboard.php:480-690` | `$base_query` preparado é concatenado dezenas de vezes — quebra o invariant de SQL safety | Reescrever cada SELECT com seu próprio `prepare` |
| B-02 | CRÍTICO | `class-dashboard.php:609-615` | `regiao = '{$regiao}'` no loop. Hoje vem de array hardcoded; time bomb se virar dinâmico | `prepare("AND regiao = %s", $regiao)` |
| B-03 | CRÍTICO | `class-email.php:763` | `sanitize_key` permite qualquer string `[a-z0-9_-]` como nome de coluna — column injection | Whitelist explícita de colunas |
| B-04 | ALTO | `class-interactions.php:48-65` | `sanitize_sql_orderby($args['orderby'].' '.$args['order'])` com fallback inseguro | Whitelist explícita |
| B-05 | ALTO | `class-contacts.php:172` | `$wpdb->prepare($query, $values)` (array) só é oficial em WP 6.2+; o plugin declara `Requires at least: 5.8` | Spread `...$values` ou bumpar versão mínima |
| B-06 | ALTO | `class-import-export.php:617` | `iconv('UTF-8','ASCII//TRANSLIT//IGNORE',...)` falha silenciosamente em encoding misto, retornando `false` → `strtolower(false) = ''`, gera duplicatas falsas | `Normalizer::normalize` (intl) com fallback |
| B-07 | ALTO | `class-contacts.php:600` | `$existing['id'] != $id` — comparação fraca | `(int)$existing['id'] !== (int)$id` |
| B-08 | ALTO | `class-email.php:336-344,397` | `sleep($settings['batch_delay'])` dentro de hook agendado bloqueia o worker e PHP-FPM mata por timeout | Action Scheduler com offsets |
| B-09 | ALTO | `class-email.php:328` | Rate-limit não atômico: dois cron jobs simultâneos passam o check antes de incrementarem | Transient/wp_cache atômico ou `SELECT ... FOR UPDATE` |
| B-10 | ALTO | `class-database.php:233-238` | `insert_default_tags` insere 8 tags toda vez sem `INSERT IGNORE` — gera 8 erros silenciosos por reativação | `INSERT IGNORE` |
| B-11 | MÉDIO | `class-contacts.php:155` | `get_var($count_query)` sem `prepare` quando `$values` está vazio — fragilidade futura | Sempre passar por `prepare` |
| B-12 | MÉDIO | `class-interactions.php:139` | `$wpdb->insert(..., array de 10 formatos)` com `$sanitized` de 8 chaves; ordem dependente do array do PHP | Construir formato dinamicamente |
| B-13 | MÉDIO | `class-import-export.php:104-109` | `array_filter($decoded)` sem callback remove `0`/`false` — IDs WP nunca são 0, mas frágil | `array_filter($decoded, fn($v) => $v > 0)` |
| B-14 | MÉDIO | `class-helpers.php:222` | `new DateTime($birth_date)` sem try/catch — datas corrompidas no banco crasham página | Try/catch + validação |
| B-15 | MÉDIO | `class-import-export.php:300-301` | `@set_time_limit` / `@ini_set` com supressão de erro, sem fallback | Chunking em background |
| B-16 | MÉDIO | `public/class-public.php:115-116` | `REMOTE_ADDR` direto, ignora `HTTP_X_FORWARDED_FOR` atrás de proxy/CDN — IP errado nos logs LGPD | Resolução de IP real com trusted proxy list |
| B-17 | MÉDIO | `class-import-export.php:266-275` | Detecção de delimitador CSV pega só primeira linha — quebra com aspas/escape | Lib robusta (League\Csv) |
| B-18 | BAIXO | `class-helpers.php:357` | `md5(uniqid(rand(), true))` — PRNG fraco, MD5 quebrado | `random_bytes`+`bin2hex` ou `wp_generate_uuid4` |
| B-19 | BAIXO | `class-email.php:159,204` | `wp_hash($id . $email . 'unsubscribe')` — concatenação sem separador, risco teórico de colisão | `hash_hmac('sha256', json_encode([id,email]), wp_salt())` |
| B-20 | BAIXO | `class-database.php:259-294` | `maybe_upgrade` é uma checagem ad-hoc por coluna — não tem sistema de versão | Migration system versionado |

## 3. Vulnerabilidades de segurança

| ID | CVSS | Tipo | Local | Correção |
|----|------|------|-------|----------|
| V-01 | 8.8 | Info disclosure (PII) | `class-contacts.php:271-277` `error_log` com `print_r` de todos os dados pessoais | Remover; logger estruturado com mascaramento |
| V-02 | 8.1 | Auth bypass | `class-helpers.php:349-351` `can_user` curto-circuita com `manage_options` | Remover OR; capabilities granulares |
| V-03 | 7.5 | SQLi defense-in-depth | `class-dashboard.php:478-690` concatenação de strings preparadas | Reescrever cada query |
| V-04 | 7.5 | DoS | `class-import-export.php:86,148` exports massivos sem chunking | Streaming/chunked download |
| V-05 | 7.4 | XSS stored (script) | `public/views/dashboard.php:91,110,129` `json_encode` sem flags hex | `wp_json_encode($d, JSON_HEX_TAG\|JSON_HEX_AMP\|JSON_HEX_APOS\|JSON_HEX_QUOT)` |
| V-06 | 7.0 | Authz missing | `admin/views/contact-view.php:12`, `contact-form.php`, `reports.php` sem `current_user_can` | Guard no topo |
| V-07 | 6.5 | XSS attribute | `public/views/dashboard.php:49` `style="width: <?php echo $percent; ?>%"` | `esc_attr(intval($percent))` |
| V-08 | 6.5 | wp_unslash ausente | TODO o plugin (0 ocorrências de `wp_unslash` em 14 arquivos lendo `$_POST`/`$_GET`) | `sanitize_text_field(wp_unslash($_POST['x'] ?? ''))` em todo handler |
| V-09 | 6.5 | Sem rate limit/anti-spam | `public/class-public.php:48` endpoint público sem proteção | Honeypot + transient throttle + Turnstile/hCaptcha |
| V-10 | 6.0 | Senha SMTP em claro | `class-email.php:74` `smtp_password` em wp_options sem cifragem | `sodium_crypto_secretbox` com chave em `wp-config.php` |
| V-11 | 5.0 | CSRF unsubscribe via GET | `class-email.php:901-931` GET descadastra direto, scanners de email descadastram involuntariamente | GET mostra confirmação, POST efetua |
| V-12 | 5.0 | Supply chain (CDN sem SRI) | `crm-developer.php:164,167,170` Font Awesome, Chart.js, SheetJS via CDN sem `integrity` | Bundlear local OU adicionar SRI |
| V-13 | 4.5 | XSS em impressão | `admin/views/reports.php:1340-1350` template literals com dados não escapados | `textContent` ou `escapeHtml` (já existe em admin.js) |
| V-14 | 4.0 | Token unsubscribe sem expiração | `class-email.php:914` `wp_hash` permanente | Incluir timestamp + janela de validade |
| V-15 | 4.0 | MIME não validado | `class-import-export.php:233` confia em extensão (`.csv`/`.xlsx`) sem checar magic bytes | `finfo_file` + size cap |
| V-16 | 3.5 | Info disclosure em erro | `class-contacts.php:613-617`, `public/class-public.php:104-106` retorna `$wpdb->last_error` no JSON | Wrap em `WP_DEBUG && current_user_can('manage_options')` |
| V-17 | 3.5 | Email enumeration | `public/class-public.php:80-84` "Este email já está cadastrado" | Mensagem genérica |
| V-18 | 3.0 | CSP ausente | global, sem header CSP | `script-src 'self'` em páginas do plugin |

## 4. Anti-patterns observados

| Tag | Descrição |
|-----|-----------|
| AP-01 | Singleton + métodos 100% estáticos em todas as classes (impede DI/mock/test) |
| AP-02 | `wp_unslash` ausente em **todo** o plugin (0 ocorrências) |
| AP-03 | SQL via concatenação de strings preparadas em `class-dashboard.php` |
| AP-04 | Lógica de negócio em views (queries SQL diretas em `admin/views/email.php:19-20`, `settings.php:16-22`) |
| AP-05 | `error_log` com PII sem mascaramento |
| AP-06 | Funções globais `display_field`, `display_array_field`, `get_value`, `is_checked` declaradas dentro de views — risco de redeclaração |
| AP-07 | `maybe_serialize`/`maybe_unserialize` para arrays + queries `LIKE '%valor%'` quebram com mudança PHP serialize entre versões e abrem object-injection |
| AP-08 | `SHOW TABLES LIKE` chamado em código quente (cada `save_contact`, `get_templates`) |
| AP-09 | `set_time_limit`/`ini_set` em runtime em vez de chunking via cron |
| AP-10 | Métodos gigantes: `import_data` (300 linhas), `get_filtered_report_data` (300 linhas) |
| AP-11 | Mensagens AJAX hardcoded em PT sem text domain (`'Sem permissão'`, `'ID inválido'`) |
| AP-12 | Schema mistura PT/EN (`nome_completo` + `created_at`) |
| AP-13 | CSS único de 3.550 linhas |
| AP-14 | Duplicação massiva de sanitização de filtros entre 5 handlers AJAX |
| AP-15 | Hardcoded CDN externo — não-conforme com requisitos federais |
| AP-16 | View `email.php` com 2.359 linhas misturando HTML+JS+PHP |
| AP-17 | `flush_rewrite_rules` sem CPT/rewrite — chamada inútil |
| AP-18 | Sem tipos PHP 7.4+ em assinaturas |
| AP-19 | Retorno booleano misturado com retorno de id em `save_contact` |
| AP-20 | `admin_scripts` carrega Chart.js (250KB) + SheetJS (900KB) em todas as páginas (mesmo lista) |
| AP-21 | Sem cache em queries de dashboard (~30 COUNTs por page load) |
| AP-22 | `enum('sim','nao')` em MySQL (anti-pattern) |
| AP-23 | Sem `FOREIGN KEY` entre `contacts.id` e `interactions.contact_id` (sem cascade) |
| AP-24 | Tabela `contacts` com 35+ colunas todas DEFAULT NULL (viola 3FN) |
| AP-25 | Não usa `WP_List_Table` — reinventa paginação via JS |
| AP-26 | Zero testes (sem `tests/`, `phpunit.xml`, CI) |
| AP-27 | Dois prefixos coexistindo: `crm_dev_` em código + `crm-developer` em URLs |
| AP-28 | `score_engajamento` materializado mas não recalculado em massa quando schema muda |
| AP-29 | wp_options crescendo: `crm_dev_alert_logs` mantém 100 logs serializados |

## 5. Performance

- **P-01** `class-dashboard.php:get_filtered_report_data` faz ~50 queries por chamada — sem cache.
- **P-02** `class-import-export.php:load_existing_*` faz 4 SELECTs carregando toda a base na memória PHP (~50MB para 100k contatos).
- **P-03** Chart.js + SheetJS carregados em todas as páginas do plugin.
- **P-04** Sem `UNIQUE KEY` em `contacts.email` — permite duplicatas em race condition.
- **P-05** Loop de 12 queries (uma por mês) em `monthly_registrations` — deveria ser 1 query com `GROUP BY YEAR, MONTH`.
- **P-06** Loop de 24 queries em `by_hour` — `GROUP BY HOUR`.
- **P-07** Loop de 5 regiões × 3 scores = 15 queries — uma só com `CASE WHEN`.
- **P-08** `send_mass_email` insere 1 INSERT por contato (10k INSERTs para 10k destinatários) — usar batch.

## 6. LGPD / Privacidade

- **L-01 CRÍTICO** `class-contacts.php:271` `error_log` com PII completo.
- **L-02 ALTO** `class-alerts.php:131` logs de alerta com payload PII em `wp_options` (visível em qualquer dump SQL ou plugin de listagem).
- **L-03 ALTO** Templates de alerta enviam todos os dados do contato por email para múltiplos destinatários, sem cifragem.
- **L-04 MÉDIO** `class-public.php:115-116` coleta IP+UA sem mencionar isso explicitamente no consentimento.
- **L-05 MÉDIO** `consent_logs` sem TTL (viola minimização da LGPD).
- **L-06 MÉDIO** Mensagem "Email já cadastrado" expõe presença de pessoa na base (enumeration).
- **L-07 ALTO** Sem rotina de retenção/anonimização — dados ficam para sempre.
- **L-08 ALTO** Sem export de dados do titular (Art. 18 — portabilidade).
- **L-09 ALTO** Sem fluxo de exclusão automática a pedido do titular.

## 7. Lições para o refactor — Convenções obrigatórias

### Segurança
1. `wp_unslash()` antes de qualquer sanitização em superglobais (helper `req_post('campo', 'sanitize_text_field')`).
2. `$wpdb->prepare()` em uma única chamada — proibido concatenar strings preparadas.
3. Whitelist explícita para nome de coluna/orderby/order — `sanitize_sql_orderby` não basta.
4. Toda view/handler deve declarar `current_user_can()` no topo. Linter custom para detectar.
5. Nunca `error_log` com PII — logger dedicado com mascaramento (`j***@gmail.com`).
6. Capabilities granulares sem fallback para `manage_options`. Roles `pibram_viewer/editor/manager/admin`.
7. Rate limit obrigatório em endpoint público (transient + IP, default 5 req/min).
8. Honeypot + Turnstile/hCaptcha em form público.
9. CSP `script-src 'self'` nas páginas do plugin. Bundlear assets locais (sem CDN).
10. SRI obrigatório se algum CDN for inevitável.
11. Cifrar segredos em wp_options (sodium_crypto_secretbox), chave em `wp-config.php`.
12. Token de unsubscribe com expiração + POST de confirmação.
13. `wp_json_encode` com `JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT` em script context.

### LGPD
14. Base legal documentada para cada dado coletado.
15. Endpoint nativo de exportação e exclusão de dados do titular.
16. TTL configurável para logs de consentimento.
17. Mascarar emails em logs e alertas; não enviar PII por email para múltiplos destinatários.
18. Mensagens genéricas para evitar enumeration ("Se este email estiver cadastrado…").

### Arquitetura
19. PHP 8.1+ mínimo, strict types em todos os arquivos.
20. Camadas Repository/Service/Controller/View. SQL proibido em view.
21. DI container leve (PHP-League). Abolir static singletons.
22. Tabelas relacionais: separar `contacts` (pessoais), `participations` (1-N), `interests` (N-N), `consents` (1-N com timestamp + base legal).
23. Migration system versionado. Não checar coluna por coluna.
24. Foreign keys com `ON DELETE CASCADE` (InnoDB).
25. Imports via Action Scheduler com chunking — nunca `set_time_limit`.
26. `WP_List_Table` para listagens admin.
27. Object Cache + invalidação por hooks em queries de dashboard.
28. Batch INSERT (`INSERT IGNORE` ou `ON DUPLICATE KEY UPDATE`).
29. i18n em 100% das strings (incluindo mensagens AJAX e `wp_die`).
30. Schema 100% inglês.
31. Sem funções globais — métodos estáticos de helper class ou namespace.
32. Limite de 500 linhas por arquivo. Quebrar `email.php` (2.359) e `reports.php` (1.382).
33. Testes obrigatórios (PHPUnit + integration).
34. CI verde antes de merge: PHPCS WPCS, PHPStan level 6+, Psalm.
35. Asset pipeline (esbuild/vite) com hash + carregamento condicional por seção.
36. Action Scheduler para fila de email.
37. Prefixo único `pibram_`. Composer PSR-4.
38. Constantes em arquivo dedicado.
39. Hooks centralizados em bootstrap, jamais espalhados em classes.
40. Docblocks `@param/@return/@throws` em métodos públicos.
41. Score de engajamento como view materializada ou cron de recálculo, não em coluna estagnada.

## 8. Apêndice — Estatísticas

### Tamanho

- **Total LOC:** ~17.265
- **PHP:** ~12.671 LOC em 22 arquivos
- **JS:** 166 LOC (admin.js 138 + public.js 28)
- **CSS:** 4.063 LOC (admin.css 3.550 + public.css 513)

### Top 5 arquivos por tamanho

| Arquivo | LOC |
|---------|-----|
| `admin/views/email.php` | 2.359 |
| `admin/views/reports.php` | 1.382 |
| `includes/class-email.php` | 931 |
| `includes/class-import-export.php` | 871 |
| `admin/views/contact-view.php` | 743 |

### Estrutura

- **Classes:** 11 (todas com métodos 100% estáticos — anti-pattern)
- **Tabelas custom:** 11 (`contacts`, `interactions`, `tags`, `contact_tags`, `import_logs`, `consent_logs`, `email_templates`, `email_campaigns`, `email_queue`, `email_logs`, `email_unsubscribes`)
- **Ações AJAX:** 27 (1 público `nopriv`)
- **Shortcodes:** 2 (`crm_cadastro`, `crm_dashboard_publico`)
- **Capabilities:** 6 — anuladas pelo bypass `manage_options` em `can_user`
- **wp_options:** ≥10

### Métricas críticas

| Métrica | Valor | Nota |
|---------|-------|------|
| Uso de `wp_unslash` | **0** | CRÍTICO — 14 arquivos com `$_POST` ignoram |
| Uso de `$wpdb->prepare` | 29 | OK em volume, mas ~20 SELECTs ainda concatenam |
| `check_ajax_referer` / `wp_verify_nonce` | 28 | Bom — todo handler AJAX tem nonce |
| `current_user_can`/`can_user` | 28 | Volume bom, implementação ruim (`can_user` faz bypass) |
| `error_log` com PII | 3 (1 com `print_r` completo) | CRÍTICO |
| Funções globais em views | 4 | Anti-pattern |
| Cobertura de testes | 0% | — |

### CDNs externos sem SRI

- `cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/`
- `cdn.jsdelivr.net/npm/chart.js@4.4.0/`
- `cdn.sheetjs.com/xlsx-0.20.0/`

### Risco geral

**ALTO** — 7 vulnerabilidades CRÍTICAS, 4 ALTAS, 7 MÉDIAS/BAIXAS. **Não está pronto para produção federal/governamental**. Issues de LGPD (PII em log, sem retenção, sem export/exclusão de titular), capability bypass, SQLi defense-in-depth, XSS no frontend público e DoS via export bloqueiam aprovação. **Recomendação: rewrite do zero** com as 41 convenções acima, reaproveitando apenas o schema conceitual e a UX dos formulários. Migrar dados em lote único após validação.
