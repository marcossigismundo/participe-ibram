# Test Plan — Participe Ibram (Wave 8.5)

> Ambiente: XAMPP/Windows, PHP 7.4+, WordPress 6.2+, sem composer.

---

## Pré-requisitos

1. Plugin ativado via **wp-admin → Plugins → Participe Ibram → Ativar**
2. Seis constantes adicionadas ao `wp-config.php` (antes do comentário "stop editing"):

```php
define('PI_ENC_KEY_V1',           '<base64-32-bytes>');
define('PI_ENC_KEY_CURRENT',      '<base64-32-bytes>');
define('PI_HMAC_KEY',             '<base64-32-bytes>');
define('PI_IP_PEPPER',            '<base64-32-bytes>');
define('PI_VOTING_SECRET',        '<base64-32-bytes>');
define('PI_UNSUBSCRIBE_SECRET',   '<base64-32-bytes>');
```

> Valores dummy para dev estão disponíveis em **wp-admin → Participe Ibram → Setup de Teste → Card 1 → "Como configurar as constantes"**.

3. Verificar que todos os checks do **Card 1 (Pre-flight)** estão verdes (ou amarelos com justificativa aceitável).

---

## Setup

1. Acesse **wp-admin → Participe Ibram → Setup de Teste**
2. Confirme que o Card 1 (Pre-flight) está OK
3. Clique **"Criar 9 usuários de teste"** (Card 2) — aguarde sucesso
4. Clique **"Popular dados de teste"** (Card 3) — aguarde sucesso
5. Anote as credenciais da tabela (ou consulte `TEST-CREDENTIALS.md`)
6. Crie páginas WordPress com os shortcodes necessários:
   - `[pi_minha_conta]` — ex.: Minha Conta
   - `[pi_editais_publicos]` — ex.: Editais Abertos
   - `[pi_inscricao_edital]` — ex.: Inscrição Edital
   - `[pi_votacao]` — ex.: Votação
   - `[pi_cadastro]` — ex.: Cadastrar-se

---

## Cenários de teste

---

### 1. Agente PF (`teste_agente_pf`)

> **Objetivo:** Auto-serviço na Minha Conta; exibição de dados cifrados.

- [ ] Login em `/wp-login.php` com `teste_agente_pf`
- [ ] Acessar página com shortcode `[pi_minha_conta]`
- [ ] Verificar que o dashboard mostra status **DEFERIDO** e número **PI-PF-2026-000001**
- [ ] Aba **"Meus dados"**: clicar em "Mostrar CPF" — modal deve pedir confirmação antes de revelar
- [ ] Verificar que o CPF revelado é mascarado (ex.: `***.456.789-**`) e não armazena em JS claro
- [ ] Aba **"Privacidade"**: revogar consentimento opcional "newsletter" — mensagem de sucesso
- [ ] Aba **"Privacidade"**: solicitar exportação de dados — deve exigir senha e gerar download
- [ ] Sair e verificar no wp-admin → Audit Log que cada ação foi registrada
- [ ] **Critério:** Sem erros PHP, todos os passos completam, audit log presente

---

### 2. Agente OR (`teste_agente_or`) — Primeiro cadastro (wizard)

> **Objetivo:** Fluxo completo de submissão de cadastro de Organização.

- [ ] Login com `teste_agente_or`
- [ ] Acessar `[pi_cadastro]`
- [ ] Verificar que status inicial é **SUBMETIDO** (seed já criou)
- [ ] Simular edição de dados: atualizar nome da organização → salvar
- [ ] Verificar mensagem de confirmação de submissão
- [ ] Admin: login com `teste_admin` → Participe Ibram → Fila de Análise → localizar OR
- [ ] **Critério:** Cadastro aparece na fila, dados atualizados, audit log registrado

---

### 3. Agente SM (`teste_agente_sm`) — Rascunho

> **Objetivo:** Verificar que SM em RASCUNHO pode editar sem restrições.

- [ ] Login com `teste_agente_sm`
- [ ] Acessar `[pi_minha_conta]` — status deve ser **RASCUNHO**
- [ ] Acessar `[pi_cadastro]` — campos editáveis
- [ ] Preencher campo obrigatório faltante → salvar como rascunho
- [ ] Tentar submeter sem todos os campos — deve exibir validação
- [ ] Preencher todos os campos obrigatórios → submeter
- [ ] **Critério:** Status muda para SUBMETIDO após submissão válida

---

### 4. Analista (`teste_analista`)

> **Objetivo:** Fluxo completo da fila de análise.

- [ ] Login com `teste_analista`
- [ ] Acessar wp-admin → Participe Ibram → Fila de Análise
- [ ] Verificar que apenas cadastros atribuídos/disponíveis aparecem
- [ ] Clicar em "Assumir" em um cadastro SUBMETIDO
- [ ] Verificar que status muda para EM_ANALISE
- [ ] Abrir detalhes do agente → ver dados (com mascaramento de PII)
- [ ] Clicar "Deferir" → preencher justificativa → confirmar
- [ ] Verificar status DEFERIDO e número de registro gerado
- [ ] Repetir com outro cadastro: "Indeferir" com motivo
- [ ] **Critério:** Workflow completo sem erros, notificações disparadas (verificar fila de email)

---

### 5. Presidência (`teste_presidencia`) — Recurso de retratação

> **Objetivo:** Decisão sobre recurso negado pelo analista.

- [ ] Pré-requisito: ter um cadastro indeferido com recurso submetido pelo agente
- [ ] Login com `teste_presidencia`
- [ ] Acessar wp-admin → Participe Ibram → Recursos → Presidência
- [ ] Localizar recurso com status AGUARDANDO_PRESIDENCIA
- [ ] Abrir detalhes — ver histórico do processo
- [ ] Decidir: "Negar retratação" com justificativa
- [ ] Verificar status do cadastro permanece INDEFERIDO
- [ ] Tentar acessar página de Fila de Análise — deve ser negado (403)
- [ ] **Critério:** Ação restrita à role; audit log registra decisão

---

### 6. Gestor de Edital (`teste_gestor_edital`)

> **Objetivo:** Criar e publicar edital.

- [ ] Login com `teste_gestor_edital`
- [ ] Acessar wp-admin → Participe Ibram → Editais
- [ ] Verificar que o "Edital de Teste — CCDEM 2026" está listado
- [ ] Criar novo edital: título "Edital Smoke Test", preencher todos os campos obrigatórios
- [ ] Salvar como RASCUNHO → verificar que aparece na lista
- [ ] Editar → mudar status para PUBLICADO → salvar
- [ ] Adicionar categoria: "Categoria Teste", tipo PF, 2 vagas + 1 suplente
- [ ] Acessar `[pi_editais_publicos]` no front — verificar que edital publicado aparece
- [ ] **Critério:** Edital publicado visível no front; categorias salvas corretamente

---

### 7. Apurador (`teste_apuracao`) — Apuração de votação

> **Objetivo:** Encerrar votação, verificar hash de integridade, publicar resultado.

- [ ] Pré-requisito: votação AGENDADA criada pelo seed
- [ ] Login com `teste_apuracao`
- [ ] Acessar wp-admin → Participe Ibram → Votações
- [ ] Localizar votação do "Edital de Teste — CCDEM 2026"
- [ ] Clicar "Encerrar votação" → confirmar
- [ ] Verificar que hash de integridade é exibido e pode ser copiado
- [ ] Tela de Apuração: verificar contagem de votos por categoria
- [ ] Clicar "Publicar resultado" → confirmar
- [ ] Verificar que resultado aparece no `[pi_editais_publicos]`
- [ ] Acessar wp-admin → Votações → Auditoria → verificar log imutável
- [ ] **Critério:** Hash consistente; resultado publicado; audit log intacto

---

### 8. DPO (`teste_dpo`) — Solicitações Art. 18 LGPD

> **Objetivo:** Atender solicitação de acesso a dados do agente PF.

- [ ] Login com `teste_dpo`
- [ ] Acessar wp-admin → Participe Ibram → LGPD/DPO (ou menu correspondente)
- [ ] Verificar que a solicitação de `teste_agente_pf` (tipo: acesso, status: aberta) aparece
- [ ] Abrir solicitação → ver detalhes do pedido
- [ ] Clicar "Atender" → preencher resposta → confirmar
- [ ] Verificar que status muda para ATENDIDA
- [ ] Verificar que notificação foi enviada ao agente (fila de email)
- [ ] Tentar acessar Fila de Análise — deve ser negado
- [ ] **Critério:** Fluxo Art. 18 completo; notificação disparada; acesso restrito à role DPO

---

### 9. Eleitor — Votação com countdown

> **Objetivo:** Votação com timer de 3 segundos e emissão de recibo.

- [ ] Pré-requisito: votação em status ABERTA com período ativo
  *(ajuste as datas no seed se necessário via SQL direto: `UPDATE wp_pi_votacoes SET status='ABERTA' WHERE edital_id=<id>`)*
- [ ] Login com `teste_agente_pf` (eleitor habilitado)
- [ ] Acessar página com `[pi_votacao]`
- [ ] Verificar que o edital/candidatos aparecem
- [ ] Selecionar candidato → clicar "Votar"
- [ ] Verificar que countdown de 3 segundos aparece antes de confirmar
- [ ] Confirmar voto → recibo exibido com hash único
- [ ] Copiar/imprimir recibo
- [ ] Tentar votar novamente — deve bloquear (voto já computado)
- [ ] **Critério:** Countdown funcional; recibo emitido; duplo voto bloqueado

---

## Critérios de sucesso (gates de release)

- [ ] Pre-flight: 0 checks vermelhos, todos os crons agendados
- [ ] 26 tabelas `wp_pi_*` criadas
- [ ] Todas as 6 constantes wp-config definidas
- [ ] 9 usuários de teste criados com roles corretas
- [ ] Cada cenário (1-9) executado sem PHP fatal errors ou warnings críticos
- [ ] Audit log registra: acesso a dado sensível, decisão de análise, ação DPO, apuração
- [ ] PII (CPF, CNPJ) nunca aparece em texto claro no HTML ou log
- [ ] Nonces validados em todas as ações POST (CSRF)
- [ ] Cleanup remove 100% dos dados de teste (verificar via SQL: `SELECT * FROM wp_pi_agentes WHERE pi_test_seed='1'`)

---

## Como reportar bugs

```
**Ambiente:** XAMPP Windows / PHP X.Y.Z / WP X.Y.Z
**Cenário:** [número e nome do cenário]
**Passo:** [passo exato onde falhou]
**Resultado esperado:** [o que deveria acontecer]
**Resultado obtido:** [o que aconteceu]
**Erro PHP (se houver):** [cole o erro do debug.log]
**Screenshot:** [anexe se possível]
```

---

## Cleanup pós-teste

1. **wp-admin → Participe Ibram → Setup de Teste → Card 4 → "Remover dados de teste"**
2. Digite `CONFIRMAR` no modal
3. Verifique via wp-admin → Usuários que os 9 usuários de teste foram removidos
4. Remova as constantes de dev do `wp-config.php` antes de ir para produção
