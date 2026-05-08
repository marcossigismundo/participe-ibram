# Participe Ibram — Architecture Spec (v1.0)

> Spec normativa do refactor do plugin `crm-developer` para a plataforma federal **Participe Ibram** do IBRAM.
> Base normativa: Portaria IBRAM nº 3230/2024 (vigente) + Despacho 98/2025-DDFEM + caderno de campos `Cadastro de Agentes para Participação Social do Ibram.docx`.
> **NÃO use a Minuta 2089/2024** — foi descartada.

## 1. Goals

- Plataforma federal para Cadastro de Agentes para Participação Social do Ibram, conforme Portaria 3230/2024.
- Workflow completo de editais e votação para o CCDEM (Despacho 98/2025).
- Conformidade LGPD rigorosa: consentimento granular, criptografia de PII sensível, direitos do titular, auditoria.
- Acessibilidade WCAG 2.1 AA + eMAG; alinhamento visual com gov.br Design System (DSGov).
- Excelência de código: arquitetura em camadas, DI, testes, sem dependências em PHP global state.

## 2. Non-goals (esta fase)

- Integração efetiva com Login gov.br OIDC (interface preparada; implementação stub).
- Migração automática de dados legados do `crm-developer` (manual sob demanda via WP-CLI).
- Multi-tenant.

## 3. Decisões fundamentais (Top-Decisions)

### TD-01 — Tipologias de agente (3, conforme caderno .docx)
Discriminator `tipo` em `wp_pi_agentes`:
- **PF** — Pessoa Física (indivíduo)
- **OR** — Organização (engloba PJ formal e Coletivos sem CNPJ — Portaria 3230 alíneas a/c)
- **SM** — Sistema de Museu / Secretaria de Cultura (Portaria 3230 alínea d)

Cada tipo tem sub-tabela com colunas próprias (PF, OR, SM tables). Permite expansão sem afetar outros.

### TD-02 — Número de registro (formato definido)
`PI-{TIPO}-{ANO}-{SEQ06}` — ex.: `PI-PF-2026-000123`, `PI-OR-2026-000045`, `PI-SM-2026-000007`.
- Sequência **por tipo+ano** (3 sequências independentes).
- Gerado **somente após deferimento** (não na submissão).
- **Imutável** após gerado.
- Implementação: lock pessimista em `wp_pi_sequencias` (tipo, ano, ultimo_numero) durante geração.

### TD-03 — Prefixo e nomenclatura
- Tabelas: `wp_pi_*` (Participe Ibram).
- Namespace PHP: `Ibram\ParticipeIbram\*` (PSR-4).
- Plugin slug: `participe-ibram` (renomeado de crm-developer).
- Text domain: `participe-ibram`.
- Não tocar em `wp_crm_dev_*` (preservado para migração opcional).

### TD-04 — Wizard multi-etapas (NOT um formulão)
Cadastro PF/OR/SM em wizard de 5–7 passos com salvamento automático de rascunho a cada passo. Reduz fricção, permite retomar depois, melhora taxa de conclusão. Cada passo é validado isoladamente.

### TD-05 — Máquina de estados do cadastro (Portaria 3230, Art. 5º + 7º + 8º)
```
rascunho ──submeter──▶ submetido ──atribuir_analista──▶ em_analise
em_analise ──deferir──▶ deferido (final, gera numero_registro, dispara comunicação)
em_analise ──indeferir──▶ indeferido_aguardando_recurso (publicado, inicia prazo de 10 dias contínuos)
indeferido_aguardando_recurso ──prazo_expira──▶ indeferido_final
indeferido_aguardando_recurso ──protocolar_recurso──▶ em_retratacao
em_retratacao ──reconsiderar──▶ deferido_em_retratacao (final)
em_retratacao ──manter──▶ em_recurso_presidencia
em_recurso_presidencia ──deferir──▶ deferido_em_recurso (final)
em_recurso_presidencia ──manter──▶ indeferido_final
```
**Eventos publicáveis (Art. 8º):** `cadastro_deferido`, `cadastro_indeferido`, `recurso_decidido`, sempre com snapshot do post no site Ibram + hash + data (trilha de evidência para o Art. 7º).

### TD-06 — Editais & Votação (Despacho 98/2025)
Entidades em camadas:
- **edital** (titulo, descricao_md, abertura, encerramento_inscricoes, abertura_votacao, encerramento_votacao, status)
- **edital_categoria** (edital_id, nome, num_vagas, tipos_agente_elegivel JSON, criterios_md)
- **inscricao** (edital_id, categoria_id, agente_id, portfolio_md, status: rascunho|inscrito|habilitado|inabilitado|recurso|final_habilitado|final_inabilitado)
- **inscricao_documento** (inscricao_id, tipo, documento_id) — anexos exigidos
- **recurso_inabilitacao** (inscricao_id, fundamentacao, decisao, decidido_em)
- **votacao** (edital_id, abertura, encerramento, status, modo: por_categoria|geral)
- **voto** (votacao_id, categoria_id, eleitor_hash, candidato_inscricao_id, votado_em) — UNIQUE(votacao_id, categoria_id, eleitor_hash)
- **resultado** (votacao_id, categoria_id, candidato_inscricao_id, votos, eleito BOOL)

**Voto auditável:** `eleitor_hash = HMAC-SHA256(secret_servidor, agente_id || votacao_id)`. Garante (a) unicidade, (b) auditabilidade (mesma fórmula reconstrói), (c) anonimato na contagem (não revela identidade pelo hash).

### TD-07 — Vocabulários controlados (tabela única)
`wp_pi_vocabularios` (id, tipo, valor, rotulo, ordem, ativo, metadata JSON).
Tipos a popular: `tipos_coletivo`, `abrangencias`, `nacionalidades`, `faixas_etarias`, `identidades_genero`, `orientacoes_sexuais`, `racas_cor`, `povos_comunidades_tradicionais` (Decreto 8.750/2016), `graus_instrucao`, `ocupacoes`, `areas_tematicas`, `instancias_participacao`.
**Lista de áreas temáticas e instâncias:** ver `VOCABULARIES.md`.

### TD-08 — LGPD (ver `LGPD.md` para detalhe)
- Consentimento granular por **finalidade** (não um único checkbox).
- Termos versionados; cada consentimento referencia versão aceita.
- Criptografia de CPF, RG, Passaporte em repouso via libsodium (`sodium_crypto_secretbox`).
- Pseudonimização em logs externos (substituir IDs por hashes).
- Endpoints públicos para os 6 direitos do titular (acesso, retificação, exclusão, portabilidade, oposição, anonimização).
- Retenção configurável por categoria + cron de anonimização automática.
- Auditoria de acesso a campos sensíveis (`wp_pi_audit_log`).

### TD-09 — Documentos
- Storage privado em `wp-content/uploads/participe-ibram-private/` protegido por `.htaccess` + `web.config` (deny all).
- Acesso via `admin-ajax.php?action=pi_download_document` com autenticação + verificação de permissão.
- Hash SHA-256 + nome original + MIME real (não confiar em extensão) + tamanho.
- Validação por tipo de documento: tabela `tipos_documento` (mime_permitidos, tamanho_max_kb, requerido_para_tipo_agente).

### TD-10 — UI/UX
- Base visual: gov.br Design System (DSGov tokens, components).
- Wizard com progress bar acessível.
- **Modais explicativos** em cada seção (ícone "?" → modal com explicação contextual).
- Salvamento automático de rascunho (debounce 2s).
- Validação inline (CPF dígito verificador, CNPJ algoritmo, email).
- Mobile-first responsive.
- Estados visuais claros (loading, error, success).

### TD-11 — Acessibilidade
WCAG 2.1 AA + eMAG. Concretamente:
- Labels semânticas em todos campos.
- `aria-describedby` para mensagens de ajuda/erro.
- Skip links no header.
- Foco visível 3:1 contraste.
- Navegação 100% por teclado.
- Sem dependência exclusiva de cor.
- Anúncios de live region para mudanças de estado.
- Teste com leitor de tela (NVDA/VoiceOver) antes de release.

### TD-12 — Autenticação
- WordPress nativo agora; capabilities granulares por tipo de operação.
- **Interface preparada para Login gov.br OIDC** (`AuthProviderInterface` com `WordPressAuth` e `GovBrAuth` stub).
- Capabilities específicas (ver `SCHEMA.md` seção Roles).

### TD-13 — Comunicação automática
Cada transição relevante dispara evento → fila assíncrona de e-mail (`wp_pi_email_queue`).
Eventos: `cadastro_submetido`, `cadastro_deferido`, `cadastro_indeferido`, `recurso_decidido`, `edital_aberto`, `inscricao_recebida`, `habilitacao_publicada`, `recurso_inabilitacao_decidido`, `votacao_aberta`, `votacao_encerrada`, `resultado_publicado`.
Despacho 98/2025 item 7 exige comunicação a **todos os cadastrados** em alguns eventos (ex.: edital aberto, resultado).

### TD-14 — Auditoria
`wp_pi_audit_log` append-only (sem UPDATE/DELETE pela aplicação). Registra:
- Ações administrativas (deferir, indeferir, decidir recurso, abrir edital).
- Acesso a dados sensíveis (visualizar CPF, RG, passaporte).
- Decisões automáticas do sistema.
Campos: `entidade`, `entidade_id`, `acao`, `ator_id`, `dados_antes` (JSON), `dados_depois` (JSON), `ip`, `user_agent`, `timestamp`.

### TD-15 — Migração
- Schema novo separado (`wp_pi_*`).
- Tabelas legadas (`wp_crm_dev_*`) preservadas; não tocar.
- Comando WP-CLI opcional `wp pi migrate-legacy` para importar contatos como agentes PF (modo manual, requer revisão).

### TD-16 — Internacionalização
Português brasileiro (pt_BR) como idioma primário. Estrutura preparada para inglês e espanhol futuro.
Text domain único: `participe-ibram`.

### TD-17 — Performance
- Lazy loading de scripts admin (apenas em páginas do plugin).
- Cache de vocabulários em `wp_options` com versionamento (busted ao editar).
- Paginação obrigatória em todas listagens (default 25, max 100).
- Índices em todas as colunas usadas em WHERE/JOIN/ORDER (ver `SCHEMA.md`).

### TD-18 — Segurança
- Nonces em todos os endpoints AJAX/REST.
- Capability checks em **toda** ação que muda estado.
- Sanitização na entrada + escape na saída (esc_html, esc_attr, esc_url, wp_kses).
- Prepared statements obrigatórios em SQL custom (`$wpdb->prepare`).
- CSRF tokens em formulários longos.
- Rate limiting em endpoints públicos (cadastro, login).
- HSTS + CSP no nível do tema/plugin.
- Logs de tentativas de acesso negadas.

## 4. Estrutura de arquivos

```
participe-ibram/
├── participe-ibram.php          # Plugin bootstrap (renomeado de crm-developer.php)
├── composer.json                # PSR-4, paragonie/random_compat, sodium
├── uninstall.php                # Limpeza opcional (NÃO apaga dados por padrão)
├── readme.txt                   # WP plugin metadata
├── languages/                   # .po/.mo
├── migrations/                  # SQL files versionados (V001__init.sql, V002__...)
├── src/
│   ├── Bootstrap/
│   │   ├── Plugin.php           # Singleton, init hooks
│   │   ├── Container.php        # DI container (simples)
│   │   └── Activator.php        # register_activation_hook handler
│   ├── Core/
│   │   ├── Database/
│   │   │   ├── Schema.php
│   │   │   ├── Migration.php
│   │   │   └── QueryBuilder.php
│   │   ├── Encryption/
│   │   │   └── SodiumCipher.php
│   │   ├── Audit/
│   │   │   └── AuditLogger.php
│   │   ├── Mail/
│   │   │   ├── Mailer.php
│   │   │   └── Queue.php
│   │   ├── Storage/
│   │   │   └── PrivateFileStorage.php
│   │   └── Validation/
│   │       ├── CpfValidator.php
│   │       ├── CnpjValidator.php
│   │       └── EmailValidator.php
│   ├── Domain/
│   │   ├── Agente/
│   │   │   ├── Agente.php
│   │   │   ├── AgentePF.php
│   │   │   ├── AgenteOR.php
│   │   │   ├── AgenteSM.php
│   │   │   ├── StatusCadastro.php  # enum
│   │   │   ├── NumeroRegistro.php  # value object
│   │   │   └── AgenteRepository.php
│   │   ├── Edital/
│   │   │   ├── Edital.php
│   │   │   ├── Categoria.php
│   │   │   ├── Inscricao.php
│   │   │   └── EditalRepository.php
│   │   ├── Votacao/
│   │   │   ├── Votacao.php
│   │   │   ├── Voto.php
│   │   │   └── VotacaoRepository.php
│   │   ├── Vocabulario/
│   │   ├── Documento/
│   │   ├── Consentimento/
│   │   └── Auditoria/
│   ├── Application/
│   │   ├── Cadastro/
│   │   │   ├── SubmeterCadastro.php   # use cases
│   │   │   ├── SalvarRascunho.php
│   │   │   ├── DeferirCadastro.php
│   │   │   ├── IndeferirCadastro.php
│   │   │   └── ProtocolarRecurso.php
│   │   ├── Edital/
│   │   ├── Votacao/
│   │   └── Lgpd/
│   │       ├── ExportarDadosTitular.php
│   │       └── AnonimizarTitular.php
│   ├── Infrastructure/
│   │   ├── Wpdb/                # Implementações concretas
│   │   ├── Auth/
│   │   │   ├── AuthProviderInterface.php
│   │   │   ├── WordPressAuth.php
│   │   │   └── GovBrAuth.php    # stub OIDC
│   │   ├── Email/
│   │   └── Cron/
│   └── Presentation/
│       ├── Admin/
│       │   ├── Pages/
│       │   │   ├── DashboardPage.php
│       │   │   ├── AgentesPage.php
│       │   │   ├── AnalisesPage.php
│       │   │   ├── EditaisPage.php
│       │   │   ├── VocabulariosPage.php
│       │   │   └── ConfiguracoesPage.php
│       │   ├── Controllers/
│       │   └── Views/
│       ├── Public/
│       │   ├── Wizard/
│       │   │   ├── WizardController.php
│       │   │   ├── steps/
│       │   │   └── views/
│       │   ├── MinhaConta/
│       │   ├── Editais/
│       │   └── Votacao/
│       └── Rest/
│           ├── AgenteEndpoints.php
│           ├── EditalEndpoints.php
│           ├── LgpdEndpoints.php
│           └── LaiEndpoints.php   # Lei de Acesso à Informação (público)
├── assets/
│   ├── src/
│   │   ├── scss/
│   │   │   ├── tokens.scss     # DSGov tokens
│   │   │   └── components/
│   │   ├── js/
│   │   │   ├── wizard/
│   │   │   ├── admin/
│   │   │   └── lib/
│   │   └── images/
│   └── dist/                   # build output
├── templates/                  # PHP templates (separados do código)
│   ├── public/
│   ├── admin/
│   ├── emails/
│   └── documents/              # cartas/ofícios geráveis
└── tests/
    ├── Unit/
    └── Integration/
```

## 5. Convenções de código

- PHP 7.4 mínimo (compatível com Hostinger/cPanel comum em órgãos federais).
- PSR-12 code style.
- Strict types em todos arquivos novos: `declare(strict_types=1);`.
- Classes finais por padrão; abertas só quando há motivo.
- Sem variáveis globais. Tudo via DI Container.
- Nomes em pt_BR no domínio (ex.: `Agente`, `Cadastro`), inglês na infraestrutura técnica (ex.: `Repository`, `Controller`).
- Toda função com docblock + tipos de retorno + tipos de parâmetros.
- Sem `error_log` com PII em produção (corrigir bug atual em `class-contacts.php:271`).

## 6. Plano de migração do plugin atual

1. Branch dedicada: `refactor/participe-ibram` (já criada).
2. Manter `crm-developer.php` no master até refactor estar maduro.
3. No branch novo: renomear pasta para `participe-ibram/`, novo bootstrap.
4. Tabelas antigas preservadas; novas em paralelo.
5. Lançamento: rename do plugin slug + WP-CLI migra dados se solicitado.

## 7. Ordem de implementação (waves)

Ver `AGENTS-PLAN.md` para a orquestração das ondas de subagentes.

## 8. Open questions / TBD

Nenhuma bloqueante para start. Itens a confirmar com CGSIM ao longo do desenvolvimento:
- Lista definitiva de "instâncias de participação" (a v1 segue lista do .docx + sugestões em `VOCABULARIES.md`).
- Lista definitiva de "áreas temáticas" (idem).
- Política exata de retenção de dados por categoria (configuráveis).
- Integração com Login gov.br (interface pronta, ativação posterior).
