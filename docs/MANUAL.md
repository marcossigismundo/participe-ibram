# Manual do Participe Ibram

**Plataforma federal de Cadastro Nacional de Agentes Culturais (CNAC)** do
Instituto Brasileiro de Museus (IBRAM / Ministério da Cultura), em
conformidade com a Portaria IBRAM nº 3.230/2024, o Despacho nº 98/2025-DDFEM
e a Lei Geral de Proteção de Dados (Lei 13.709/2018).

> Este manual cobre instalação, operação por perfil, fluxos de trabalho,
> conformidade LGPD, troubleshooting e suporte. Documento vivo — para a
> última versão consulte
> [github.com/marcossigismundo/participe-ibram](https://github.com/marcossigismundo/participe-ibram).

---

## Sumário

1. [Visão geral](#1-visão-geral)
2. [Base normativa](#2-base-normativa)
3. [Instalação e configuração](#3-instalação-e-configuração)
4. [Perfis de usuário](#4-perfis-de-usuário)
5. [Fluxos de trabalho](#5-fluxos-de-trabalho)
6. [LGPD e direitos do titular](#6-lgpd-e-direitos-do-titular)
7. [Operação técnica](#7-operação-técnica)
8. [REST API e shortcodes](#8-rest-api-e-shortcodes)
9. [Troubleshooting](#9-troubleshooting)
10. [FAQ](#10-faq)
11. [Glossário](#11-glossário)
12. [Suporte](#12-suporte)

---

## 1. Visão geral

O **Participe Ibram** é um plugin WordPress que implementa o back-office
administrativo do CNAC — o cadastro federal de agentes culturais do IBRAM.
Suporta três tipos de agente:

| Tipo | Quem é | Identificador | Documentos |
|------|--------|---------------|------------|
| **PF** | Pessoa Física | CPF (criptografado) | RG ou passaporte |
| **OR** | Organização (com ou sem CNPJ) | CNPJ ou auto-declaração | Estatuto, ata, comprovante |
| **SM** | Sistema Municipal de Cultura | Lei municipal | Lei + decreto de nomeação |

### O que o plugin faz

- **Cadastro completo** dos três tipos com criptografia em repouso (libsodium
  XSalsa20+Poly1305) e dados étnico-raciais conforme Lei 14.553/2023.
- **Análise técnica** com fila por analista, deferimento/indeferimento
  motivado e geração automática do **Número de Registro** no formato
  `PI-{TIPO}-{ANO}-{SEQ06}` (ex: `PI-PF-2026-000123`).
- **Recursos administrativos** em três fases (retratação → presidência →
  arquivamento), com prazos auditáveis.
- **Editais culturais** com inscrições, habilitações, recursos de
  inabilitação e categorias.
- **Votações secretas** com anti-rastreio voto↔eleitor via HMAC
  (BLAKE2b com chave separada `PI_VOTING_SECRET`).
- **Apuração** com critério de desempate determinístico
  (`total DESC, inscrito_em ASC, candidato_inscricao_id ASC`) e geração de
  relatório oficial em ZIP (CSV + PDF + ata).
- **Auditoria** integral: 100% das ações sensíveis registradas em
  `pi_audit_log` com hash de IP (não a IP bruta) e mascaramento de PII.
- **Conformidade LGPD completa**: consentimento granular por 10
  finalidades, direitos Art. 18 (acesso, retificação, anonimização,
  portabilidade), notificação de incidente em 3 dias úteis
  (Resolução CD/ANPD 15/2024).
- **LAI**: endpoints públicos sem PII para transparência ativa
  (dados-abertos.json, dados-abertos.csv, resultados de votação).
- **gov.br Design System (DSGov 3.7.0)** scoped a `.participe-ibram-scope`,
  WCAG 2.1 AA, eMAG.

### Quem usa

Sete perfis cobrem o ciclo completo. Veja
[Seção 4](#4-perfis-de-usuário) para detalhes.

---

## 2. Base normativa

| Norma | Conteúdo | Onde se aplica |
|-------|----------|----------------|
| **Portaria IBRAM nº 3.230/2024** | Regulamenta o CNAC | Cadastro, análise, recursos |
| **Despacho nº 98/2025-DDFEM** | Operacionaliza editais e processo CCDEM | Editais, habilitação, votação |
| **Lei 13.709/2018 (LGPD)** | Tratamento de dados pessoais | Consentimento, Art. 18, criptografia |
| **Lei 14.553/2023** | Coleta obrigatória de dados étnico-raciais | Cadastro PF |
| **Resolução CD/ANPD 15/2024** | Notificação de incidente em 3 dias úteis | DPO, auditoria |
| **Lei 12.527/2011 (LAI)** | Acesso à informação pública | Endpoints `/lai/*` sem PII |
| **eMAG 3.1 + WCAG 2.1 AA** | Acessibilidade governamental | Templates, sidebar, formulários |
| **gov.br Design System (DSGov 3.7.0)** | Identidade visual federal | Todos os componentes admin |

---

## 3. Instalação e configuração

### 3.1 Requisitos

| Componente | Mínimo | Recomendado |
|------------|--------|-------------|
| PHP | 7.4 | 8.2 |
| WordPress | 6.2 | 6.5+ |
| MySQL/MariaDB | 5.7 / 10.3 | 8.0 / 10.6 |
| Extensão `sodium` (libsodium) | obrigatória | — |
| Memória PHP | 128 MB | 256 MB |
| `wp-cron` ativo | sim | sim |
| HTTPS | recomendado | obrigatório em produção |

### 3.2 Constantes obrigatórias em `wp-config.php`

Cole **antes** da linha `/* That's all, stop editing! */`:

```php
// ---- Participe Ibram (libsodium + HMAC + voto + unsubscribe) ----------------
define('PI_ENC_KEY_V1',         '<base64 de 32 bytes>');
define('PI_ENC_KEY_CURRENT',    'v1');
define('PI_HMAC_KEY',           '<base64 de 32 bytes, DIFERENTE de PI_ENC_KEY_V1>');
define('PI_IP_PEPPER',          '<base64 de 32 bytes, distinto>');
define('PI_VOTING_SECRET',      '<base64 de 32 bytes, distinto>');
define('PI_UNSUBSCRIBE_SECRET', '<base64 de 32 bytes, distinto>');
```

> **CRÍTICO**: cada constante DEVE ter um valor independente. Reutilizar a
> mesma chave em dois papéis quebra a separação criptográfica exigida pela
> LGPD R2 §4.6 e a separação voto↔eleitor.

### 3.3 Gerando as chaves

Em um terminal com PHP CLI:

```bash
for k in V1 HMAC_KEY IP_PEPPER VOTING_SECRET UNSUBSCRIBE_SECRET; do
  echo -n "PI_ENC_KEY_$k = "
  php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'
done
```

### 3.4 Pre-flight check

Se você esquecer alguma constante, o plugin **não dá fatal** — em vez
disso aparece um aviso vermelho no wp-admin listando o que falta e o
snippet pronto para colar. O admin continua funcional para correção.

### 3.5 Ativação

1. Copie a pasta do plugin para `wp-content/plugins/participe-ibram/`.
2. Em **Plugins → Instalados**, ative "Participe Ibram".
3. A ativação executa automaticamente:
   - Criação das 26 tabelas `wp_pi_*`
   - Diretório privado de uploads em
     `wp-content/uploads/participe-ibram-private/` com `.htaccess`,
     `web.config` e `index.php` de silêncio
   - Agendamento de 4 cron jobs (queue de e-mail, expiração de prazos,
     limpeza de tokens, processamento de solicitações LGPD)
   - Criação dos 7 papéis customizados (`pi_administrador`, `pi_analista`,
     `pi_presidencia`, `pi_gestor_edital`, `pi_apurador`, `pi_dpo`,
     `pi_agente`) com 30+ capabilities granulares

### 3.6 Setup de Teste (opcional, ambiente de dev)

Para popular o ambiente com dados sintéticos:

1. Acesse **Participe Ibram → Ferramentas → Setup de Teste**.
2. Clique **"Criar 9 usuários de teste"** — gera um usuário para cada
   perfil + 3 agentes (PF, OR, SM) com senhas aleatórias mostradas na
   página.
3. Opcional: **"Popular edital + votação demo"** para gerar um edital
   publicado, 15 inscrições, 1 votação em andamento, 50 votos
   determinísticos.
4. **Remoção segura**: o botão "Remover dados de teste" exige digitar
   `CONFIRMAR` antes de apagar usuários, agentes, opção
   `pi_test_credentials` e tabelas relacionadas.

> Em produção, este menu pode ser ocultado removendo o `class_exists()`
> em `Plugin::wireAdminMenus()`.

---

## 4. Perfis de usuário

A navegação principal está na **sidebar custom** dentro do plugin (à
esquerda em todas as páginas). Cada item só aparece se o usuário tiver a
capability correspondente.

### 4.1 Agente (PF / OR / SM)

- **Onde atua**: front-end via shortcodes `[pi_cadastro tipo="PF|OR|SM"]`
  e `[pi_minha_conta]`.
- **O que faz**:
  - Preenche o wizard de cadastro (4-5 passos por tipo) com autosave a
    cada 30s
  - Anexa documentos (PDF / JPG / PNG, máx. 10 MB por arquivo)
  - Aceita consentimento granular por finalidade (10 finalidades LGPD)
  - Submete o cadastro → entra na fila de análise
  - Acompanha pendências em **Minha Conta**
  - Após deferimento: vê o número de registro, baixa o comprovante, e
    pode se inscrever em editais elegíveis
  - Exerce direitos LGPD: acessar dados, solicitar retificação,
    anonimização (pós-vínculo terminado), portabilidade

### 4.2 Analista (`pi_analista`)

- **Onde atua**: sidebar → Análise de cadastros.
- **O que faz**:
  - Vê **Fila de Análise** com cadastros submetidos
  - Assume análise → status muda para `em_analise` e fica atribuído a ele
  - Defere (gera número de registro automaticamente) **ou** indefere
    com motivo escrito
  - Se indefere: pode posteriormente retratar a decisão (recurso de
    retratação) dentro do prazo legal
  - Vê **Todos os agentes** com filtros por status, tipo, UF, data
- **Não pode**: alterar dados de PII do agente (read-only); decidir
  recurso de presidência.

### 4.3 Presidência (`pi_presidencia`)

- **Onde atua**: sidebar → Análise → Recursos de Presidência.
- **O que faz**:
  - Recebe recursos rejeitados pelo analista (segunda instância)
  - Decide em definitivo: defere (reverte indeferimento) ou nega o
    recurso (status final do cadastro)
  - Tem visão da timeline completa do processo

### 4.4 Gestor de Edital (`pi_gestor_edital`)

- **Onde atua**: sidebar → Editais & habilitações.
- **O que faz**:
  - Cria editais (rascunho → publicado → encerrado)
  - Define categorias (cargo/objeto de eleição) com vagas
  - Acompanha inscrições recebidas
  - **Habilitação**: aceita/inabilita inscrições por critérios formais
  - Decide **recursos de inabilitação** protocolados por candidatos
  - Acompanha datas-chave (inscrições, habilitação, votação, posse)

### 4.5 Apurador (`pi_apurador`)

- **Onde atua**: sidebar → Votações.
- **O que faz**:
  - Vê **Votações** abertas e encerradas
  - Roda apuração de uma votação encerrada (clica "Apurar" → handler
    calcula resultados com critério de desempate determinístico)
  - Gera **relatório oficial em ZIP** (CSV + ata em PDF + JSON-LD
    schema.org) salvo em diretório privado
  - **Auditoria de votação**: vê totalizações por categoria,
    hash-trail de hash de eleitores (sem expor identidades)

### 4.6 DPO / Encarregado (`pi_dpo`)

- **Onde atua**: sidebar → Conformidade & LGPD.
- **O que faz**:
  - **Log de eventos**: vê todas as ações registradas (filtro por usuário,
    tipo, data)
  - **Acessos a PII**: relatório de quem leu dados sensíveis (Art. 37
    LGPD — accountability)
  - **Decisões**: trilha de decisões administrativas com motivação
  - **Configuração DPO**: nome, e-mail e telefone públicos do encarregado
    (Art. 41 LGPD)
  - Recebe e responde **solicitações de titulares** (Art. 18) com SLA
    de 15 dias úteis
  - Em incidente: dispara o fluxo de notificação à ANPD em 3 dias úteis
    (Resolução 15/2024)

### 4.7 Administrador (`administrator` + `pi_administrador`)

- **Onde atua**: tudo (todas as capabilities).
- **O que faz**:
  - Configura SMTP, templates de e-mail, taxonomias de vocabulário
  - Acessa Setup de Teste em ambientes não-produtivos
  - Vê Site Health (verificações específicas do plugin)
  - Gerencia papéis e capabilities via WP

---

## 5. Fluxos de trabalho

### 5.1 Cadastro de agente (PF, OR ou SM)

```
[Agente] ─► Preenche wizard ─► Autosave a cada 30s
                ▼
        Anexa documentos ─► Validação MIME + tamanho
                ▼
        Aceita consentimento ─► Log append-only em pi_consentimentos
                ▼
        Submete ─► Status: submetido
                ▼
[Analista] ──► Assume análise ──► Status: em_analise
                ▼
        ┌──────────┴───────────┐
        ▼                      ▼
    Defere                Indefere
        ▼                      ▼
   Gera nº de registro    Notifica agente
   `PI-PF-2026-000123`    via e-mail
        ▼                      ▼
   Status: deferido       Status: indeferido
                               ▼
                          [Agente] protocola
                          recurso de retratação
                               ▼
                          [Analista] retrata?
                          ┌──────┴──────┐
                          ▼             ▼
                       Reverte      Mantém indef.
                          ▼             ▼
                     Defere        [Presidência] decide
                                        ▼
                                  ┌─────┴─────┐
                                  ▼           ▼
                               Defere       Nega
                               final        recurso
                                            (status final)
```

**Estados do cadastro** (10): `rascunho` → `submetido` → `em_analise` →
`deferido` | `indeferido` → `recurso_retratacao` → `recurso_presidencia` →
`deferido_apos_recurso` | `indeferido_final` | `arquivado`.

### 5.2 Edital cultural

1. **Gestor** cria edital em rascunho com objeto, datas-chave, anexos.
2. Define **categorias** (vagas por cargo). Ex: "Conselho Consultivo MIS
   2026", 3 vagas.
3. **Publica** → abre inscrições.
4. **Agentes deferidos** se inscrevem em categorias elegíveis (matriz de
   elegibilidade por tipo + UF + área).
5. Período de inscrição encerra → **gestor habilita** ou inabilita cada
   inscrição.
6. Inabilitados podem protocolar **recurso de inabilitação** dentro do
   prazo. Gestor decide.
7. Após encerramento das habilitações: **votação aberta**.
8. Eleitores votam (anti-rastreio via HMAC + `PI_VOTING_SECRET`).
9. Votação fecha → **apurador** roda apuração → resultado oficial.
10. **Posse**: comprovante público gerado.

### 5.3 Votação secreta — anti-rastreio

A separação voto↔eleitor é garantida por:

- A tabela `pi_votos` guarda apenas `votacao_id`, `categoria_id`,
  `candidato_inscricao_id`, `eleitor_hash`, `votado_em`.
- `eleitor_hash = HMAC-BLAKE2b(PI_VOTING_SECRET, agente_id||votacao_id)`.
- Quem tem acesso ao banco mas **não** tem `PI_VOTING_SECRET` não
  consegue reverter o hash para descobrir quem votou em quem.
- O segredo está em `wp-config.php` (fora do banco de dados).
- Auditoria pode verificar duplicidade (`eleitor_hash` unique por
  votação) sem revelar identidade.

### 5.4 Apuração e desempate

Critério determinístico aplicado por categoria:

```sql
ORDER BY total_votos DESC,
         inscrito_em ASC,
         candidato_inscricao_id ASC
```

Quem tiver mais votos vence. Empate → quem se inscreveu primeiro. Empate
persistente → menor ID de inscrição (ordem cronológica fina).

Resultado: array `[eleitos, suplentes]` por categoria. Suplentes
ordenados pela mesma regra.

### 5.5 Solicitação LGPD do titular

```
[Titular logado] ─► Painel "Minha Conta" ─► Aba "Meus dados (LGPD)"
                        ▼
        Escolhe direito: acesso | retificação | anonimização | portabilidade
                        ▼
        Reauth com senha (proteção contra session hijack)
                        ▼
        Rate limit 1/dia/user (Art. 18 § 1º — intervalos razoáveis)
                        ▼
[Sistema] ─► Protocola solicitacao_titular ─► Notifica DPO por e-mail
                        ▼
[DPO] ─► Responde no painel admin ─► Sistema executa ação se atendido
                        ▼
        ┌────────────────┴────────────────┐
        ▼                                 ▼
    Atendida                          Negada com motivo
        ▼                                 ▼
    Notifica titular                  Notifica titular
    (anonimização: tokens             (com instrução de
     gerados se aplicável)             recurso à ANPD)
```

SLA: **15 dias úteis** (Art. 18 § 1º). Solicitações vencidas geram alerta
no painel do DPO.

---

## 6. LGPD e direitos do titular

### 6.1 Bases legais (Art. 7º)

A plataforma trata dados pessoais com base nas seguintes hipóteses:

| Base | Quando se aplica |
|------|------------------|
| **Consentimento** (Art. 7º I) | Comunicações de marketing institucional, pesquisa |
| **Cumprimento de obrigação legal** (Art. 7º II) | Cadastro CNAC, comprovação documental |
| **Execução de política pública** (Art. 7º III) | Análise, deferimento, número de registro |
| **Exercício regular de direitos em processo** (Art. 7º VI) | Recursos administrativos |

### 6.2 Direitos do titular (Art. 18)

| Direito | Endpoint REST | Capability requerida |
|---------|---------------|----------------------|
| Confirmação e acesso | `GET /pi/v1/me/dados` | logado |
| Retificação | `PATCH /pi/v1/me/dados` | logado + reauth |
| Anonimização (Art. 18 IV) | `POST /pi/v1/me/anonimizacao` | logado + reauth + condições |
| Portabilidade (Art. 18 V) | `POST /pi/v1/me/portabilidade` | logado + reauth + rate 1/dia |
| Histórico de solicitações | `GET /pi/v1/me/portabilidade/historico` | logado |
| Revogação de consentimento | `POST /pi/v1/me/consentimento` | logado |

### 6.3 Criptografia em repouso

| Dado | Coluna | Algoritmo |
|------|--------|-----------|
| CPF | `cpf_enc` (libsodium) + `cpf_hash` (HMAC para busca) | XSalsa20+Poly1305 / BLAKE2b |
| CNPJ | `cnpj_enc` + `cnpj_hash` | idem |
| RG | `rg_enc` | XSalsa20+Poly1305 |
| Passaporte | `passaporte_enc` | XSalsa20+Poly1305 |
| Endereços completos | `endereco_completo_enc` | XSalsa20+Poly1305 |
| Telefone | `telefone_enc` | XSalsa20+Poly1305 |
| IP (audit log) | `ip_hash` (HMAC com `PI_IP_PEPPER`) | BLAKE2b — IP bruto nunca é persistido |

### 6.4 Rotação de chave

O esquema permite múltiplas versões de chave:

```php
define('PI_ENC_KEY_V1', '<chave-antiga>');   // dados antigos
define('PI_ENC_KEY_V2', '<chave-nova>');     // dados novos
define('PI_ENC_KEY_CURRENT', 'v2');           // versão para NOVAS escritas
```

Cada valor cifrado leva um prefixo `v1:` ou `v2:` que indica qual chave
usar para decifrar. Rotação não exige re-encrypt em massa — dados antigos
seguem legíveis enquanto a chave anterior estiver definida.

---

## 7. Operação técnica

### 7.1 Tabelas (26)

Prefixadas com `wp_pi_`:

- `pi_agentes`, `pi_agentes_pf`, `pi_agentes_or`, `pi_agentes_sm`,
  `pi_representantes`
- `pi_documentos`, `pi_tipos_documento`
- `pi_consentimentos`, `pi_consentimento_log`
- `pi_analises`, `pi_status_historico`, `pi_recursos`
- `pi_editais`, `pi_categorias`, `pi_inscricoes`,
  `pi_recursos_inabilitacao`
- `pi_votacoes`, `pi_votos`, `pi_resultados`
- `pi_email_queue`, `pi_email_logs`, `pi_email_templates`,
  `pi_email_unsubscribe_tokens`
- `pi_audit_log`
- `pi_solicitacoes_titular`
- `pi_vocabularios`, `pi_termos`

Para o schema completo, consulte
[docs/refactor/SCHEMA.md](refactor/SCHEMA.md).

### 7.2 Cron jobs

| Hook | Periodicidade | O que faz |
|------|---------------|-----------|
| `pi_email_queue_tick` | 5 min | Processa fila de e-mail (até 50 por execução) |
| `pi_prazos_expiracao` | 10 min | Marca recursos com prazo vencido |
| `pi_lgpd_anonimizacao_token` | diário | Limpa tokens de anonimização expirados |
| `pi_lai_dados_abertos_cache` | diário | Regenera cache de dados abertos LAI |

Verifique em **Ferramentas → Saúde do Site** que estão agendados. Em
ambientes Windows/XAMPP onde `wp-cron` é instável, considere disparar
via tarefa agendada externa.

### 7.3 Diretório privado

```
wp-content/uploads/participe-ibram-private/
├── .htaccess          # Deny all (Apache)
├── web.config         # Deny all (IIS)
├── index.php          # Silence
├── documentos/        # Anexos de cadastros
├── apuracao/          # Relatórios oficiais
└── portabilidade/     # Exports temporários (TTL 24h)
```

Acesso aos arquivos é mediado por endpoints REST com autenticação e
capability check — nunca por URL direta.

### 7.4 Migrations

Em `migrations/` há scripts SQL versionados executados pelo
`MigrationRunner` na ativação. Para re-executar manualmente em caso de
falha: **Setup de Teste → "Re-executar Activator"**.

---

## 8. REST API e shortcodes

### 8.1 Namespace REST

Todos os endpoints estão em `/wp-json/pi/v1/`.

### 8.2 Endpoints públicos (LAI — sem PII)

| Endpoint | Retorna |
|----------|---------|
| `GET /pi/v1/lai/cadastros` | Quantitativos agregados por tipo/UF/raça (sem identificadores) |
| `GET /pi/v1/lai/editais` | Lista de editais publicados |
| `GET /pi/v1/lai/edital/{id}/categorias` | Categorias e vagas |
| `GET /pi/v1/lai/votacoes/resultados` | Resultados oficiais por votação |
| `GET /pi/v1/lai/normativos` | Lista de normativos vigentes |
| `GET /pi/v1/lai/contatos` | DPO + ouvidoria |
| `GET /pi/v1/lai/dados-abertos.json` | Dump de dados abertos em JSON |
| `GET /pi/v1/lai/dados-abertos.csv` | Idem em CSV |

### 8.3 Endpoints autenticados (Minha Conta)

Requerem usuário logado + capability. Veja Seção [6.2](#62-direitos-do-titular-art-18).

### 8.4 Shortcodes

| Shortcode | Onde usar | O que faz |
|-----------|-----------|-----------|
| `[pi_cadastro tipo="PF"]` | Página pública | Wizard de cadastro PF |
| `[pi_cadastro tipo="OR"]` | Idem | Wizard OR |
| `[pi_cadastro tipo="SM"]` | Idem | Wizard SM |
| `[pi_minha_conta]` | Página autenticada | Painel do agente |
| `[pi_votacao id="123"]` | Página autenticada | Cabine de votação |
| `[pi_lgpd_meus_dados]` | Página autenticada | Painel LGPD do titular |
| `[pi_dashboard_publico]` | Página pública | KPIs públicos |

---

## 9. Troubleshooting

### 9.1 Plugin não inicializa (admin_notices vermelho)

**Causa**: faltam constantes em `wp-config.php`.

**Solução**: o próprio aviso lista o que falta e o snippet pronto. Cole
em `wp-config.php` antes de `/* That's all, stop editing! */`.

### 9.2 Menu lateral do Participe Ibram não aparece

**Causa 1**: usuário sem capability `pi_listar_cadastros`.

**Solução**: atribua um dos papéis customizados (`pi_administrador`,
`pi_analista`, etc.) ao usuário.

**Causa 2**: erro fatal no boot. Verifique `wp-content/debug.log`.

### 9.3 Erro `Unknown column 'pf.nome'` em listagens

**Causa**: este bug foi corrigido no commit `9738865`. Se ainda
aparecer, atualize o plugin.

### 9.4 "404 ao clicar em E-mail" no menu

**Causa**: cache do navegador apontando para slug obsoleto.

**Solução**: Ctrl+F5 para limpar cache CSS/HTML.

### 9.5 Migração de tabelas falhou (0/26 tabelas criadas)

**Causa**: erro silencioso no `Activator` (legado já corrigido).

**Solução**:
1. Acesse **Setup de Teste**
2. Clique **"Re-executar Activator"**
3. Verifique a `pi_activation_last_error` em `wp_options` para detalhes

### 9.6 E-mail não está sendo enviado

**Verificações**:
1. **SMTP configurado**? Vá em **Ferramentas → E-mail → Configuração SMTP**
2. **Cron rodando**? Veja **Saúde do Site** — deve listar
   `pi_email_queue_tick` como ativo
3. **Fila com pendentes**? **Ferramentas → E-mail → Fila pendente**
4. **Logs de falha**? **Ferramentas → E-mail → Logs**

Em XAMPP/local, configure um SMTP de teste como Mailtrap, Mailpit ou
similar.

### 9.7 Voto não é registrado / "voto já registrado"

**Causa**: `eleitor_hash` único por votação. Mesmo agente não vota duas
vezes na mesma votação.

**Verificação**: peça ao agente que confirme — checagem é determinística.
Se houver dúvida, **apurador** pode auditar o `eleitor_hash` específico
sem revelar identidade.

### 9.8 Solicitação LGPD travada

**Causa**: o DPO precisa responder manualmente. Status fica
`em_atendimento` até a decisão.

**Solução**: DPO acessa **Conformidade & LGPD → Solicitações** e
responde.

### 9.9 Setup de Teste falha ao criar usuários

**Causa**: erro de WP em ambientes com plugins de cache. Limpe object
cache.

**Verificação**: `wp_options` chave `pi_test_credentials` deve ter as
senhas após sucesso.

### 9.10 Sidebar custom não aparece

**Causa**: o controller da página não usa `PageLayout::open()`. Algumas
telas legadas (rotas raras) ainda podem usar markup antigo.

**Solução**: aguarde Wave 13 ou reporte a tela específica.

---

## 10. FAQ

### 10.1 Posso usar este plugin em outros órgãos públicos além do IBRAM?

Sim, o código é GPL-2.0-or-later. Mas a IA do menu, vocabulários e
mensagens são específicos do IBRAM. Para customizar, edite
`SidebarNavigation.php`, `MenuRegistry.php`, e o text-domain
`participe-ibram`.

### 10.2 É seguro publicar este plugin em repositório público?

Sim, **desde que** as constantes `PI_*` em `wp-config.php` **nunca**
sejam commitadas. O arquivo `wp-config.php` deve estar no `.gitignore`
do site WordPress (não do plugin). As chaves dev em qualquer
documentação são apenas exemplo e DEVEM ser regeradas em produção.

### 10.3 Como rotacionar uma chave de criptografia em produção?

1. Defina `PI_ENC_KEY_V2` em `wp-config.php` (sem remover V1).
2. Mude `PI_ENC_KEY_CURRENT` para `'v2'`.
3. Novas escritas usam V2; leituras de dados V1 ainda funcionam.
4. Opcionalmente, rode um script de re-encrypt em background quando
   conveniente.
5. Após confirmar que tudo está em V2, remova `PI_ENC_KEY_V1` do
   wp-config.

### 10.4 Como o plugin se comporta com `wp_cache_*`?

`AccessTracker`, `RateLimiter` e `SolicitacaoTitularRepository` usam
`wp_cache_get/set` quando disponível para reduzir round-trips ao banco.
Cache busts automáticos em escritas.

### 10.5 Quanto tempo de retenção de dados?

| Tipo de dado | Retenção |
|--------------|----------|
| Cadastros ativos | Indeterminada (enquanto vigente) |
| Cadastros anonimizados (Art. 18 IV) | Permanente em forma anonimizada (estatística) |
| Audit log | 3 anos (Resolução TCU + LGPD Art. 16) |
| Tokens de portabilidade | 24 horas |
| Tokens de unsubscribe | 30 dias |
| Logs de e-mail | 90 dias |

### 10.6 Posso integrar com gov.br OIDC?

A pesquisa R3 (em `docs/refactor/research/R3-govbr-oidc.md`) documenta a
integração. A implementação não está incluída na versão atual — é
prevista para uma onda futura.

### 10.7 Onde estão os testes?

Em `tests/` (PHPUnit). Para rodar:

```bash
composer install
./vendor/bin/phpunit
```

Em ambientes sem composer (XAMPP local), use o **Setup de Teste** no
admin para validação manual.

---

## 11. Glossário

| Termo | Definição |
|-------|-----------|
| **Agente cultural** | Pessoa física, organização ou sistema municipal que se cadastra no CNAC |
| **Análise** | Etapa de verificação documental e técnica do cadastro pelo IBRAM |
| **ANPD** | Autoridade Nacional de Proteção de Dados |
| **Cadastro deferido** | Cadastro aprovado, com número de registro emitido |
| **Cadastro indeferido** | Cadastro rejeitado por motivo documentado |
| **CCDEM** | Cadastro Central de Distinções para Editais de Museus |
| **CNAC** | Cadastro Nacional de Agentes Culturais do IBRAM |
| **Despacho 98/2025-DDFEM** | Norma que regulamenta editais culturais do IBRAM |
| **DPO** | Encarregado pelo tratamento de dados pessoais (LGPD Art. 41) |
| **DSGov** | gov.br Design System — identidade visual federal brasileira |
| **eMAG** | Modelo de Acessibilidade em Governo Eletrônico |
| **Habilitação** | Aceitação formal de uma inscrição em edital |
| **Inscrição** | Candidatura de um agente deferido a uma categoria de edital |
| **LAI** | Lei de Acesso à Informação (Lei 12.527/2011) |
| **LGPD** | Lei Geral de Proteção de Dados (Lei 13.709/2018) |
| **Número de registro** | Identificador único do cadastro: `PI-{TIPO}-{ANO}-{SEQ06}` |
| **OR** | Organização (pessoa jurídica com ou sem CNPJ) |
| **PF** | Pessoa Física |
| **Portabilidade** | Direito de receber os dados em formato estruturado (Art. 18 V) |
| **Portaria 3230/2024** | Norma que disciplina o CNAC |
| **Recurso administrativo** | Pedido de revisão de decisão (retratação ou presidência) |
| **Retratação** | Recurso de primeira instância (mesmo analista) |
| **Reauth** | Re-autenticação com senha antes de ação sensível |
| **SM** | Sistema Municipal de Cultura |
| **Suplente** | Candidato eleito como reserva |
| **WCAG 2.1 AA** | Web Content Accessibility Guidelines, nível AA |

---

## 12. Suporte

### Issues no GitHub

- Repositório oficial:
  [github.com/marcossigismundo/participe-ibram](https://github.com/marcossigismundo/participe-ibram)
- Para reportar bugs, segurança, ou solicitações de feature, abra uma
  issue com label apropriada.

### Contato institucional (IBRAM)

- Encarregado pelo tratamento de dados (DPO):
  configurável em **Conformidade & LGPD → Configuração DPO**.
- Ouvidoria IBRAM: <https://www.gov.br/museus/pt-br/canais_atendimento>

### Reportar incidente de segurança

Se você detectar uma vulnerabilidade que afete dados de titulares:

1. **NÃO** abra issue pública.
2. Envie e-mail ao DPO (configurado no plugin) com detalhes técnicos e
   evidência de impacto.
3. A equipe tem **3 dias úteis** para notificar a ANPD se confirmado
   incidente (Resolução CD/ANPD 15/2024).

---

## Apêndices

### A. Estados do cadastro (10)

```
rascunho ──► submetido ──► em_analise ──► deferido ──► (final)
                              │
                              ├──► indeferido ──► recurso_retratacao
                              │                       │
                              │                       ├──► deferido_apos_retratacao ──► (final)
                              │                       │
                              │                       └──► recurso_presidencia
                              │                               │
                              │                               ├──► deferido_apos_recurso ──► (final)
                              │                               │
                              │                               └──► indeferido_final ──► (final)
                              │
                              └──► arquivado (admin)
```

### B. Estados do edital (8)

`rascunho → publicado → inscricoes_abertas → inscricoes_encerradas →
habilitacao_aberta → habilitacao_encerrada → votacao_aberta → encerrado`

### C. Estados da inscrição (8)

`rascunho → submetida → habilitada → inabilitada → recurso → final_habilitada
→ final_inabilitada → desistencia`

### D. Capabilities (resumo)

| Capability | Quem usa |
|------------|----------|
| `pi_administrador` | Admin geral |
| `pi_listar_cadastros` | Todos os perfis admin (visualização) |
| `pi_analisar_cadastro` | Analista |
| `pi_decidir_recurso_presidencia` | Presidência |
| `pi_criar_edital`, `pi_publicar_edital` | Gestor de edital |
| `pi_decidir_habilitacao` | Gestor de edital |
| `pi_apurar_votacao` | Apurador |
| `pi_visualizar_audit_log` | DPO, auditor |
| `pi_administrar_email` | Admin (SMTP, templates) |
| `pi_administrar_dpo` | Admin (configurar DPO) |
| `pi_agente` | Agente front-end (mínimo) |

Lista completa em `src/Core/Capabilities/CapabilityMap.php`.

---

**Última atualização**: 12 de maio de 2026
**Versão do plugin**: 0.1.0
**Autoria**: refatoração colaborativa (humanos + Claude Opus/Sonnet)
**Licença**: GPL-2.0-or-later
