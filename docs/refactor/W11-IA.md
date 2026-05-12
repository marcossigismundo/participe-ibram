# W11-A — Nova Arquitetura de Informação do Admin Participe Ibram

Documento gerado pela Onda 11-A. Reorganiza os submenus admin do plugin segundo
o fluxo de trabalho do usuário (workflow + role), substituindo a ordem cronológica
de wave que vinha desde a Onda 4.

Capabilities e controllers **não foram alterados** — apenas:
- ordem global dos submenus (via parâmetro `$position` em `add_submenu_page`);
- alguns labels renomeados quando ambíguos (sem alteração de slug);
- agrupamento visual via separadores (labels HTML `── Grupo ──`) nos pontos de quebra.

---

## Grupo 1 — Visão Geral

Painel de entrada do plugin. Visível para qualquer usuário com `pi_listar_cadastros`
(papel-base de leitura). Exibe KPIs e CTAs role-aware.

| Slug | Label de menu | Capability | Controller / Render |
|------|---------------|------------|---------------------|
| `participe-ibram` (root) | Painel | `pi_listar_cadastros` | `Plugin::renderRootStub` (fallback) / `MenuRegistry::renderDashboard` (com `dashboard.php`) |

---

## Grupo 2 — Análise de cadastros

Analista e Presidência. Inclui o fluxo de análise inicial, retratação e recurso
final.

| Slug | Label de menu | Capability | Controller |
|------|---------------|------------|------------|
| `participe-ibram_cadastros` | Cadastros — Fila de Análise | `pi_listar_cadastros` | `FilaAnaliseController` |
| `participe-ibram_agentes` | Cadastros — Todos os agentes | `pi_listar_cadastros` | `TodosAgentesController` |
| `participe-ibram_recursos_retratacao` | Recursos — Retratação | `pi_analisar_cadastro` | `RecursoRetratacaoController` |
| `participe-ibram_recursos_presidencia` | Recursos — Presidência | `pi_decidir_recurso_presidencia` | `RecursoPresidenciaController` |
| `participe-ibram_recursos_prazos` | Recursos — Prazos vencendo | `pi_listar_cadastros` | `RecursoPrazosController` |

Slugs ocultos (acessados por querystring, registrados em `options.php`):
`participe-ibram_agente` (detalhes do agente).

---

## Grupo 3 — Editais & habilitações

Gestor de Edital. Fluxo de criação → publicação → habilitação de inscritos.

| Slug | Label de menu | Capability | Controller |
|------|---------------|------------|------------|
| `participe-ibram_editais` | Editais — Lista | `pi_listar_cadastros` | `EditalListController` |
| `participe-ibram_edital_novo` | Editais — Novo edital | `pi_criar_edital` | `EditalFormController::renderCreate` |
| `participe-ibram_habilitacoes` | Habilitações — Pendentes | `pi_decidir_habilitacao` | `HabilitacaoListController` |
| `participe-ibram_recursos_inabilitacao` | Habilitações — Recursos de inabilitação | `pi_decidir_habilitacao` | `RecursoInabilitacaoListController` |

Slugs ocultos: `participe-ibram_edital`, `participe-ibram_categoria` (detalhes e
categorias do edital).

---

## Grupo 4 — Votações

Apurador. Lista de votações + apuração + auditoria interna do processo de voto
(distinto do log geral de auditoria do plugin).

| Slug | Label de menu | Capability | Controller |
|------|---------------|------------|------------|
| `participe-ibram_votacoes` | Votações — Lista | `pi_apurar_votacao` | `VotacaoListController` |
| `participe-ibram_votacao_auditoria` | Votações — Auditoria | `pi_visualizar_audit_log` | `VotacaoAuditoriaController` |

Slug oculto: `participe-ibram_apurar` (detalhe / executar apuração).

---

## Grupo 5 — Conformidade & LGPD

DPO + Auditor. Logs de auditoria de eventos do plugin (R5 V-06).

| Slug | Label de menu | Capability | Controller |
|------|---------------|------------|------------|
| `participe-ibram_audit_log` | Auditoria — Log de eventos | `pi_visualizar_audit_log` | `AuditLogController` |
| `participe-ibram_audit_pii` | Auditoria — Acessos a PII | `pi_visualizar_audit_log` | `AuditLogController` |
| `participe-ibram_audit_decisoes` | Auditoria — Decisões | `pi_visualizar_audit_log` | `AuditLogController` |

Slug oculto: `participe-ibram_audit_log_detalhe` (registro individual).

**Nota:** o submenu de Configurações DPO continua registrado por
`DpoConfigController` em outro parent slug (`pi-participe-ibram`) — fora do
escopo desta Onda; será re-parented em W11-C.

---

## Grupo 6 — Ferramentas

Administrador. Operações pontuais (popular dados de teste, fila de e-mail,
glossário, ajuda).

| Slug | Label de menu | Capability | Controller |
|------|---------------|------------|------------|
| `participe-ibram_setup_teste` | Ferramentas — Setup de teste | `pi_administrador` (fallback `manage_options`) | `SetupTesteController` |
| `participe-ibram_ajuda` | Ferramentas — Ajuda | `read` | `AjudaController` |

**Nota:** a fila/admin de e-mail é registrada pelo `EmailController` como um
**top-level menu separado** (slug `pi-participe-ibram-email`). Sua re-parentagem
para dentro de "Participe Ibram → Ferramentas" também é W11-C.

---

## Implementação do ordenamento

Cada `add_submenu_page` agora recebe o argumento `$position` (10º param,
disponível desde WP 5.3). Como as registries hookam em `admin_menu` com
prioridades diferentes (10, 20, 25, 30, 99), o `position` é o único mecanismo
estável para garantir a ordem global desejada.

A faixa de posições é alocada em saltos de 10 por grupo, deixando folga para
futuros submenus dentro de cada bloco sem precisar renumerar tudo:

| Grupo | Faixa `$position` |
|-------|-------------------|
| Visão Geral (auto-criado como root) | 0 |
| Análise de cadastros | 10 – 19 |
| Editais & habilitações | 20 – 29 |
| Votações | 30 – 39 |
| Conformidade & LGPD | 40 – 49 |
| Ferramentas | 50 – 59 |

Caso o WordPress receba `position` duplicado entre registries diferentes
(possível em cenários de wave parcial), o comportamento ainda é determinístico
porque WP usa `array_replace` em strings derivadas da posição, e o conjunto
final ainda obedece a ordem dos grupos.
