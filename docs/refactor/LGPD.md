# Participe Ibram — Estratégia LGPD (v1.0)

> Como o sistema cumpre a Lei 13.709/2018 (LGPD) na prática. Esta é a referência para implementação.

## 1. Bases legais e finalidades (Art. 7º + Art. 11)

**Tratamento dado misto** — combina três bases legais:

| Categoria de dado | Base legal | Fundamento |
|---|---|---|
| Dados cadastrais (nome, contato, vínculo institucional) | **Art. 7º, III — execução de políticas públicas** previstas em lei (Decreto 8.124/2013, Portaria 3230/2024) | Cadastro federal por norma do Ibram |
| Dados sensíveis (raça, gênero, orientação sexual, deficiência, PCT) | **Art. 11, II, "a" — execução de políticas públicas** + **consentimento específico** (Art. 11, I) | Coleta opcional declarada; usuário pode "Prefiro não informar" |
| Dados para comunicação (e-mail, telefone) | **Art. 7º, IX — interesse legítimo** + **consentimento** | Pode ser revogado |
| Documentos comprobatórios (CPF, RG, CNPJ, ata, estatuto) | **Art. 7º, II — cumprimento de obrigação legal** + Art. 11, II, "a" | Exigidos pela Portaria 3230/2024, Art. 6º |

**Cada finalidade é registrada em `wp_pi_consentimentos` com base legal explícita.**

## 2. Princípios aplicados (Art. 6º)

| Princípio | Como cumprimos |
|---|---|
| **Finalidade** | Cada finalidade declarada e registrada granularmente; não há uso secundário sem nova base |
| **Adequação** | Coleta limitada ao previsto na Portaria 3230 + caderno de campos |
| **Necessidade** | Faixa etária (não data exata) por padrão; documentos só na fase em que são necessários |
| **Livre acesso** | Endpoint `/pi/api/lgpd/meus-dados` retorna ZIP com todos os dados em JSON+PDF |
| **Qualidade** | Valida CPF/CNPJ por algoritmo; agente edita seu próprio cadastro |
| **Transparência** | Aviso claro de coleta + termo versionado + log de quem acessou |
| **Segurança** | Criptografia em repouso, HTTPS obrigatório, RBAC, audit log |
| **Prevenção** | Rate limit, bloqueio de tentativas, atualização de dependências |
| **Não discriminação** | Coleta de raça/PCT em base legal de política pública afirmativa |
| **Responsabilização** | Audit log append-only, DPO designado, DPIA documentado |

## 3. Consentimento granular — UI proposta

Em vez de um único checkbox "Concordo com a privacidade", o termo é **estratificado**:

```
┌─ Termo de Privacidade — Participe Ibram (v2026.05.01) ─────────────┐
│                                                                    │
│ Para que possamos te incluir no Cadastro de Agentes para           │
│ Participação Social, precisamos do seu consentimento para tratar   │
│ os seguintes grupos de dados:                                      │
│                                                                    │
│ [✓] OBRIGATÓRIO — Identificação e cadastro                         │
│       Nome, CPF/CNPJ/Passaporte, contato, vínculo institucional    │
│       Base legal: política pública (Portaria IBRAM 3230/2024)      │
│       Sem isso não é possível registrar você como agente.          │
│                                                                    │
│ [✓] OBRIGATÓRIO — Comunicação institucional                        │
│       Receber notificações sobre seu cadastro, editais e votações  │
│       Base legal: execução do cadastro                             │
│       Você pode desativar comunicações não-essenciais depois.      │
│                                                                    │
│ [ ] OPCIONAL — Dados de gênero e orientação sexual                 │
│       Identidade de gênero, orientação sexual                      │
│       Finalidade: análise de representatividade nas instâncias     │
│       Você pode pular ou marcar "Prefiro não informar".            │
│                                                                    │
│ [ ] OPCIONAL — Dados de raça/cor                                   │
│       Autodeclaração de raça/cor (IBGE)                            │
│       Finalidade: política afirmativa de representação             │
│                                                                    │
│ [ ] OPCIONAL — Filiação a povos e comunidades tradicionais         │
│       Conforme Decreto 8.750/2016                                  │
│       Finalidade: garantir representação de PCT                    │
│                                                                    │
│ [ ] OPCIONAL — Dados de deficiência e acessibilidade               │
│       Para garantirmos acessibilidade a você nas atividades        │
│                                                                    │
│ [Ver termo completo (PDF v2026.05.01)]  [Não concordo] [Concordo]  │
└────────────────────────────────────────────────────────────────────┘
```

**Cada checkbox opcional é independente.** O agente pode revogar qualquer consentimento opcional depois em "Minha conta → Privacidade".

## 4. Texto sugerido do termo (versão prática e inteligente)

> **Termo de Tratamento de Dados — Participe Ibram**
> *Versão {versao} — Vigente a partir de {data}*
>
> Quem trata seus dados: **Instituto Brasileiro de Museus (Ibram)**, autarquia federal vinculada ao Ministério da Cultura, CNPJ 10.898.596/0001-93. Encarregada de Dados (DPO): {nome_dpo} — {email_dpo}.
>
> Por que coletamos seus dados: para registrar você como **Agente de Participação Social**, conforme a Portaria IBRAM nº 3230/2024 e o Decreto nº 8.124/2013. Sem esses dados, não podemos te incluir no Cadastro nem te habilitar a concorrer a vagas em conselhos e instâncias do Ibram.
>
> O que coletamos:
> 1. **Identificação** (obrigatório): nome, CPF ou CNPJ ou passaporte, e-mail, telefone, endereço.
> 2. **Documentos legais** (obrigatório): conforme o tipo de cadastro — comprovantes de inscrição (CPF/CNPJ), estatutos, atas, ofícios.
> 3. **Perfil** (opcional, granular): faixa etária, identidade de gênero, orientação sexual, raça/cor, filiação a povo/comunidade tradicional, deficiência, escolaridade, ocupação. Você escolhe o que informar — todas têm "Prefiro não informar".
> 4. **Manifestações** (obrigatório): áreas temáticas de interesse, instâncias em que pretende atuar.
>
> O que **não fazemos** com seus dados:
> - Não vendemos para terceiros.
> - Não usamos para publicidade.
> - Não compartilhamos fora do Ibram, exceto por (a) determinação judicial ou (b) Lei de Acesso à Informação — Lei 12.527/2011 (e mesmo nestes casos, apenas o estritamente necessário).
>
> Quanto tempo guardamos:
> - Cadastro ativo: enquanto você for agente.
> - Após revogação total ou exclusão da conta: **anonimização em até 30 dias**, com retenção de logs de auditoria por 5 anos (obrigação legal).
> - Documentos pessoais (CPF, RG, passaporte): guardados criptografados e descartados quando não mais necessários para o cadastro.
>
> Seus direitos (Art. 18 LGPD): você pode pedir, a qualquer momento e sem justificar, acesso completo aos seus dados, correção, exclusão, portabilidade, oposição, anonimização e revisão de decisões automatizadas. Acesse "Minha conta → Privacidade" ou escreva para {email_dpo}. Resposta em até 15 dias úteis.
>
> Como protegemos:
> - HTTPS sempre, criptografia em repouso para CPF, RG e passaporte (libsodium).
> - Acesso administrativo restrito por perfil; cada acesso a dados sensíveis é registrado.
> - Backups criptografados, retenção controlada.
> - Política interna de privacidade auditável.
>
> Mudanças neste termo: você será avisado e precisará reconfirmar. Versões anteriores ficam arquivadas e disponíveis.
>
> Reclamações: ANPD — Autoridade Nacional de Proteção de Dados — anpd.gov.br.

Texto final precisa ser revisado pelo jurídico do Ibram antes de produção.

## 5. Criptografia em repouso

**Algoritmo:** `sodium_crypto_secretbox` (XSalsa20 + Poly1305).

**Chave master:** armazenada em `wp-config.php` via constante `PI_ENCRYPTION_KEY` (base64 de 32 bytes). Geração no Activator se ausente, com instrução para mover para variável de ambiente em produção.

**Pattern de uso:**
```php
$cpf_enc = SodiumCipher::encrypt($cpf_plaintext);  // VARBINARY no banco
$cpf_hash = hash_hmac('sha256', $cpf_normalizado, PI_HMAC_KEY);  // CHAR(64) — busca exata
```

**Buscas por CPF/CNPJ:** sempre via `cpf_hash` ou `cnpj_hash` (HMAC determinístico). Para listagem de admin, descriptografa só os primeiros N registros visíveis.

**Rotação:** procedimento documentado para re-encriptar lote quando necessário (ex.: vazamento de chave).

## 6. Pseudonimização e anonimização

**Pseudonimização** (reversível, dia a dia):
- Logs externos referenciam `agente_id` e nunca PII.
- IPs em audit log são HMAC-hashados.

**Anonimização** (irreversível, por direito do titular ou retenção):
- Substitui campos PII por `[ANON]` ou null.
- Mantém estatística não identificável (ex.: faixa etária, estado).
- Ações: 
  - `nome_completo` → `[ANON-{id_short}]`
  - `cpf_enc`, `rg_enc`, `passaporte_enc` → NULL
  - `email_principal` → `anon-{id}@participe-ibram.local`
  - `telefone` → NULL
  - Documentos → arquivos físicos deletados, registro mantém apenas tipo + hash + tamanho
  - Audit log preservado (obrigação legal)

## 7. Endpoints LGPD (REST API)

Base: `/wp-json/pi/v1/`

| Endpoint | Verbo | Auth | Função |
|---|---|---|---|
| `/lgpd/meus-dados` | GET | agente | Retorna ZIP com JSON + PDF de todos os dados do agente logado |
| `/lgpd/solicitacoes` | GET, POST | agente | Lista e cria solicitações Art. 18 |
| `/lgpd/solicitacoes/{id}` | GET | agente / dpo | Detalha solicitação |
| `/lgpd/consentimentos` | GET, PATCH | agente | Lista consentimentos atuais; revoga opcionais |
| `/lgpd/anonimizar` | POST | agente | Solicita anonimização do próprio cadastro (com confirmação por e-mail) |
| `/lgpd/termos/{versao}` | GET | público | Texto + hash do termo em qualquer versão |

Para o **DPO**:
- Página admin "LGPD" com fila de solicitações e estatísticas.
- Atendimento em até 15 dias úteis (Art. 19 LGPD); contagem visível.

## 8. Cron jobs LGPD

| Job | Frequência | Ação |
|---|---|---|
| `pi_lgpd_anonimizar_pendentes` | Diário | Anonimiza agentes com solicitação aprovada há >1 dia |
| `pi_lgpd_aplicar_retencao` | Diário | Anonimiza agentes inativos há >X anos (configurável) |
| `pi_lgpd_arquivar_audit_log` | Mensal | Move audit log >5 anos para arquivo cold |
| `pi_lgpd_alerta_dpo` | Diário | E-mail ao DPO com solicitações próximas do prazo |

## 9. DPIA (Relatório de Impacto à Proteção de Dados Pessoais)

**Necessário** porque o sistema:
- Trata dados sensíveis em larga escala (Art. 38 LGPD).
- É de pessoa jurídica de direito público.

**Elementos a documentar** (entregue como `DPIA.md` separado ao Ibram, não no plugin):
1. Mapeamento de fluxos.
2. Necessidade e proporcionalidade.
3. Riscos identificados (vazamento, acesso indevido, perda).
4. Medidas mitigadoras (criptografia, RBAC, audit log, retenção).
5. Plano de resposta a incidentes.
6. Avaliação residual.

## 10. Plano de resposta a incidentes

**Detecção:**
- Audit log com alertas para padrões suspeitos (10+ falhas de login, acesso a 100+ CPFs em 1 min).
- Monitoramento de integridade de arquivos críticos.

**Resposta:**
1. Conter (revogar tokens, bloquear contas).
2. Investigar (audit log + logs de servidor).
3. Notificar ANPD em até **3 dias úteis** se confirmado risco (Resolução CD/ANPD nº 15/2024 — atualiza prazo de 2 dias do briefing inicial).
4. Notificar titulares afetados no mesmo prazo.
5. Complementar em até 20 dias úteis (causa raiz + plano de ação).
6. Pós-mortem em 30 dias; revisão do RIPD afetado.
7. Reter registro interno do incidente por **5 anos**.

## 11. Checklist de implementação por componente

- [ ] Schema com `_enc` colunas e `_hash` colunas.
- [ ] `SodiumCipher` service com encrypt/decrypt + rotação.
- [ ] `ConsentimentoService` com versão de termo.
- [ ] UI granular de consentimento.
- [ ] Endpoints REST LGPD.
- [ ] Cron de anonimização e retenção.
- [ ] Audit log em todo acesso a sensível.
- [ ] Página admin DPO.
- [ ] Documento DPIA externo.
- [ ] Termo legal aprovado pelo jurídico.
- [ ] Treinamento da equipe Ibram (sessão pelo DPO).
