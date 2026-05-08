# Participe Ibram — Plano de orquestração de subagentes

> Como o trabalho será dividido entre subagentes para preservar o orçamento de tokens e maximizar paralelismo.

## Princípios

1. **Cada subagente é briefado com contrato claro**: arquivos a criar, contrato de saída, decisões já tomadas (links para `ARCHITECTURE.md`/`SCHEMA.md`/`LGPD.md`).
2. **Subagentes não veem a conversa principal** — todo contexto vai no prompt.
3. **Ondas paralelas** quando o trabalho é independente; sequencial quando há dependência.
4. **Background quando possível** para não bloquear conversa.
5. **Cada onda termina com agente de revisão** que valida resultado antes da próxima.

## Onda 0 — Pesquisa (BACKGROUND, paralela)

Lança 5 agentes em paralelo, todos em background. Resultado consumido na Onda 1.

| Agente | Tópico | Output |
|---|---|---|
| R1 | gov.br Design System (DSGov): tokens, componentes, integração com WordPress, cdn vs. self-host | Lista de tokens CSS, lista de componentes prioritários, código de exemplo de wizard, links |
| R2 | LGPD para cadastros federais: consentimento granular, criptografia libsodium em PHP, anonimização, ANPD guidance | Pattern de implementação, snippets, gotchas |
| R3 | Login gov.br OIDC: bibliotecas PHP, scopes, fluxo, integração WP | Código de stub `GovBrAuth`, configuração necessária |
| R4 | eMAG / WCAG 2.1 AA para formulários complexos: checklist concreto, ARIA patterns para wizard | Checklist + patterns aplicáveis |
| R5 | Code review do plugin atual: bugs, vulnerabilidades, padrões a evitar | Lista de issues encontrados |

## Onda 1 — Core infrastructure (sequencial após Onda 0)

| Agente | Escopo | Arquivos a criar |
|---|---|---|
| I1 | Bootstrap + DI Container + Plugin entry | `participe-ibram.php`, `src/Bootstrap/Plugin.php`, `src/Bootstrap/Container.php`, `src/Bootstrap/Activator.php`, `composer.json`, `uninstall.php` |
| I2 | Schema + Migrations runner | `migrations/V001__init.sql` (todo `SCHEMA.md`), `src/Core/Database/Schema.php`, `src/Core/Database/MigrationRunner.php` |
| I3 | Encryption service (libsodium) + audit logger | `src/Core/Encryption/SodiumCipher.php`, `src/Core/Audit/AuditLogger.php` |
| I4 | Validation services (CPF, CNPJ, email) + helpers | `src/Core/Validation/*.php`, `src/Core/Helpers.php` |

Onda 1 → review agent valida que (a) plugin ativa sem erro, (b) tabelas criam, (c) chave de criptografia gera, (d) testes unitários básicos passam.

## Onda 2 — Domain models + Repositories (paralela)

| Agente | Escopo |
|---|---|
| D1 | Domain Agente (Agente, AgentePF, AgenteOR, AgenteSM, NumeroRegistro VO, StatusCadastro enum) + AgenteRepository |
| D2 | Domain Documento + DocumentoRepository + PrivateFileStorage |
| D3 | Domain Consentimento + Termo + ConsentimentoRepository |
| D4 | Domain Vocabulario + VocabularioRepository + Seeders (popula vocabulários iniciais) |
| D5 | Domain Edital + Categoria + Inscricao + repositories |
| D6 | Domain Votacao + Voto + VotacaoRepository + lógica de eleitor_hash |

Cada agente: cria classes do domínio + repositório + testes unitários básicos.

## Onda 3 — Cadastro wizard (PF/OR/SM) — frente principal (paralela)

| Agente | Escopo |
|---|---|
| W1 | Wizard frontend — JS modular ES6, salvamento automático, validação inline, navegação por teclado |
| W2 | Wizard backend — REST endpoints `/wizard/rascunho`, `/wizard/passo`, `/wizard/submeter` |
| W3 | UI/UX — SCSS DSGov-aligned, modais explicativos, responsivo |
| W4 | Use cases Cadastro — `SubmeterCadastro`, `SalvarRascunho` em Application/ |
| W5 | LGPD — UI granular de consentimento + texto inteligente do termo + endpoints LGPD |

## Onda 4 — Análise técnica + Recursos (paralela)

| Agente | Escopo |
|---|---|
| A1 | Admin UI — fila de análise, decisão (deferir/indeferir), publicação |
| A2 | Workflow estados (state machine) + transições + validações |
| A3 | Recursos — UI agente (protocolar) + admin (decidir retratação + presidência) |
| A4 | Eventos + comunicação automática — fila de e-mail + templates por evento |

## Onda 5 — Editais (paralela)

| Agente | Escopo |
|---|---|
| E1 | Admin — criação/edição de edital, categorias, vagas, datas, critérios |
| E2 | Público — listagem de editais, página de detalhe, fluxo de inscrição |
| E3 | Habilitação — UI admin para avaliar inscrições; recurso de inabilitação |

## Onda 6 — Votação (sequencial)

| Agente | Escopo |
|---|---|
| V1 | Backend — abertura/encerramento de votação, registro de voto com eleitor_hash, hash pré-apuração |
| V2 | Frontend — interface de votação para agente eleitor |
| V3 | Apuração + resultado — page admin de apuração, geração de relatório, publicação |

## Onda 7 — Comunicação + auditoria (paralela)

| Agente | Escopo |
|---|---|
| C1 | Templates de e-mail HTML acessíveis para cada evento |
| C2 | Worker cron de envio + retry + log de falhas |
| C3 | Admin de auditoria — visualização do `wp_pi_audit_log` com filtros |

## Onda 8 — Área autenticada do agente (paralela)

| Agente | Escopo |
|---|---|
| M1 | Dashboard "Minha conta" — status do cadastro, número de registro, próximos passos |
| M2 | Editar dados, gerenciar consentimentos LGPD |
| M3 | Histórico de inscrições e votos do agente |

## Onda 9 — REST API + LAI (paralela)

| Agente | Escopo |
|---|---|
| AP1 | REST endpoints completos com OpenAPI doc |
| AP2 | Endpoints LAI públicos (lista de agentes deferidos, editais, resultados) JSON+CSV |
| AP3 | Endpoints LGPD do titular |

## Onda 10 — QA final (sequencial)

| Agente | Escopo |
|---|---|
| Q1 | i18n — extração e tradução de strings |
| Q2 | Auditoria de acessibilidade (WCAG 2.1 AA + eMAG) |
| Q3 | Auditoria de segurança (OWASP Top 10) |
| Q4 | Testes E2E críticos + load test em endpoints públicos |
| Q5 | Documentação final (README, manuais admin/agente, DPIA) |

## Convenção do prompt para subagentes

Todo prompt de subagente DEVE conter:

```
# Contexto
Você está implementando o plugin Participe Ibram (refactor do crm-developer) para uso federal pelo IBRAM.
Branch: refactor/participe-ibram
Working dir: c:\xampp82\htdocs\wordpress\wp-content\plugins\crm-developer

# Specs (LEIA antes de codar)
- ARCHITECTURE.md: C:\Users\marcos.sigismundo\.claude\projects\c--xampp82-htdocs-wordpress-wp-content-plugins-crm-developer\refactor-spec\ARCHITECTURE.md
- SCHEMA.md: ...\SCHEMA.md
- LGPD.md: ...\LGPD.md
- VOCABULARIES.md: ...\VOCABULARIES.md

# Sua tarefa
{escopo concreto, arquivos a criar/editar, contrato de saída}

# Não fazer
- Não invente decisões — siga as specs.
- Não toque em arquivos fora do seu escopo.
- Não escreva docs/comentários longos — siga padrão do projeto.

# Saída esperada
{descrição precisa do que entregar}
```

## Snapshot de progresso

Manter este arquivo atualizado conforme as ondas avançam. Cada onda → seção "Status" abaixo.

### Status atual
- [x] Onda 0 — Pesquisa: **CONCLUÍDA** (5 relatórios em `refactor-spec/research/`)
- [x] Onda 1 — Core: **CONCLUÍDA** (Opus 4.7 — 50 arquivos)
- [x] Onda 2 — Domain: **CONCLUÍDA** (Opus 4.7 — 175 arquivos totais)
- [x] Onda 3 — Cadastro: **CONCLUÍDA** (Opus 4.7 — 293 arquivos totais)
- [x] Onda 4 — Análise & Recurso & Email: **CONCLUÍDA** (Opus 4.7 — 373 arquivos totais)
- [x] Onda 5 — Editais: **CONCLUÍDA** (Sonnet 4.6 — 427 arquivos totais) ⚠️ REVISAR EM ONDA 10
- [ ] Onda 6 — Votação: **EM ANDAMENTO** (Opus 4.7 — criptografia + atomicidade + tie-break)
- [ ] Onda 7 — Comunicação & Auditoria
- [ ] Onda 8 — Minha conta
- [ ] Onda 9 — REST + LAI
- [ ] Onda 10 — QA (Opus obrigatório — revisão crítica de tudo + revisão extra da Onda 5)

## ⚠️ ALERTA: Auditoria obrigatória da Onda 5 na Onda 10

A Onda 5 (módulo de Editais) foi executada com **Sonnet 4.6** para preservar quota de tokens do Opus para componentes mais críticos (votação, QA final). A Onda 10 (QA final, com Opus) DEVE revisar especificamente:

### Pontos críticos a auditar na Onda 5
1. **Vazamento de PII em endpoints/páginas públicos** — listagem de inscritos habilitados, detalhes de edital, página de votação. Verificar que apenas `numero_registro`, `nome_publico`, `categoria` são expostos. **Nunca** CPF, email pessoal, telefone, raça, orientação, deficiência.
2. **Whitelist defensiva** — todo endpoint público deve seguir o padrão de `W3-B PublicEndpoints::agentes-deferidos`: filtra cada item para chaves fixas mesmo que o provider devolva PII por engano.
3. **Capability checks completos** — TODA action admin deve ter `current_user_can()` no topo. R5 V-06.
4. **`wp_unslash()` antes de sanitize** em superglobais. R5 V-08, AP-02.
5. **Audit logging em transições de estado** — `do_action('pi_edital_publicado')`, `pi_inscricao_recebida`, `pi_habilitacao_decidida`, `pi_recurso_inabilitacao_decidido`. Verificar com `AuditLogger::log` em cada handler.
6. **State machine guards** — toda transição passa por `Edital::publicar()`, `Inscricao::habilitar()` etc. — não atualiza status diretamente via SQL.
7. **Race conditions em inscrição** — UNIQUE(edital_id, categoria_id, agente_id) deve ser respeitado; tratar erro 1062 graciosamente.
8. **Documentos de inscrição** — armazenamento privado (mesmo padrão `PrivateFileStorage`), MIME real validado, hash SHA-256.
9. **Rate limiting** — endpoints de inscrição pública devem ter rate limit (RateLimiter::keyForUser).
10. **Acessibilidade WCAG 2.1 AA** — wizard de inscrição reusa `Wizard.js` Wave 3 (deveria estar OK), modal de confirmação reusa `Modal.js`. Auditar.

### Estratégia de auditoria
- Subagente Opus dedicado lê TODOS os arquivos criados pela Onda 5 e produz relatório de gaps similar ao R5 (code review)
- Testes de regressão automatizados validam ausência de PII em respostas públicas
- Revisão manual da query SQL de listagens públicas (verificar SELECT explícito de campos)

## Índice de pesquisa (Onda 0)

Todos os relatórios DEVEM ser consultados pelos agentes de implementação cujo escopo se sobrepõe:

| ID | Arquivo | Conteúdo crítico | Agentes Wave que precisam ler |
|---|---|---|---|
| R1 | `research/R1-dsgov.md` | gov.br DS v3.7.0 self-host, tokens, wizard com `aria-current="step"`, modal pattern, plano de integração WP, `.participe-ibram-scope` | W1, W3, W4, W5, W6, W8 (todo frontend) |
| R2 | `research/R2-lgpd.md` | Bases legais, libsodium versionado, HMAC separado, consentimento granular, endpoints REST do Art. 18, Resolução 15/2024 (3 dias úteis), Lei 14.553/2023 | I3, D3, W5, AP3 |
| R3 | `research/R3-govbr-oidc.md` | `AuthProviderInterface`, `WordPressAuth`, `GovBrAuth` stub, fluxo PKCE S256, mapeamento claims | I1 (registry no bootstrap), próxima onda de Auth |
| R4 | `research/R4-acessibilidade.md` | Wizard `aria-current="step"` (não `tablist`), modal com `inert`, validação `aria-invalid`+`role="alert"`, file upload acessível, ASES Web ≥95% | W1, W3 |
| R5 | `research/R5-code-review.md` | 41 convenções obrigatórias, 7 vulnerabilidades críticas a evitar, 18 vulnerabilidades menores, lições por categoria | TODOS os agentes implementadores |

## Convenções obrigatórias herdadas de R5 (resumo executivo)

Aplicar em todo código novo:

1. PHP 7.4+ com `declare(strict_types=1);` — bumpamos para 8.1+ se possível.
2. `wp_unslash()` antes de qualquer sanitização em superglobais.
3. `$wpdb->prepare()` em **uma única chamada** — proibido concatenar strings preparadas.
4. Whitelist explícita para nome de coluna/orderby/order.
5. `current_user_can()` no topo de toda view/handler — sem fallback para `manage_options`.
6. Nunca `error_log` com PII — logger dedicado com mascaramento.
7. Capabilities granulares (`pi_*`).
8. Rate limit obrigatório em endpoint público (transient + IP).
9. Honeypot + Turnstile/hCaptcha em form público.
10. CSP `script-src 'self'` nas páginas do plugin. Bundlear assets locais.
11. `wp_json_encode` com `JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT` em script context.
12. Camadas Repository/Service/Controller/View. SQL proibido em view.
13. DI container leve. Abolir static singletons.
14. Foreign keys com `ON DELETE CASCADE` (InnoDB).
15. `WP_List_Table` para listagens admin.
16. i18n em 100% das strings.
17. Schema 100% inglês na infraestrutura, pt_BR no domínio.
18. Limite de 500 linhas por arquivo.
19. PHPUnit + integration tests obrigatórios.
20. PHPCS WPCS, PHPStan level 6+ verde antes de merge.
