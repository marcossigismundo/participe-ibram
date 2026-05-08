---
name: Participe Ibram refactor
description: Plugin crm-developer está sendo refatorado completamente para virar a plataforma federal Participe Ibram do IBRAM (Instituto Brasileiro de Museus), conforme Portaria 3230/2024
type: project
originSessionId: b77ae3f1-1e39-4bac-bc54-4cbd385aac4b
---
Contexto: o plugin `crm-developer` (em c:\xampp82\htdocs\wordpress\wp-content\plugins\crm-developer) está sendo reescrito para se tornar a plataforma federal **Participe Ibram** — sistema de Cadastro de Agentes para Participação Social do Instituto Brasileiro de Museus, hospedado em `cadastro.museus.gov.br`.

**Why:** Trata-se de sistema federal regulado pela Portaria IBRAM nº 3230/2024 (com Despacho 98/2025-DDFEM detalhando o fluxo de editais/votação para o CCDEM). Excelência é requisito explícito: dados sensíveis em escala federal, conformidade LGPD obrigatória, alinhamento com gov.br Design System e eMAG.

**How to apply:** Em qualquer trabalho neste projeto, aplicar critérios federais: Portaria 3230/2024 é a norma vigente (NÃO a minuta 2089/2024). Três tipologias de agente: PF (Pessoa Física), OR (Organização — engloba PJ e Coletivos sem CNPJ), SM (Sistema de Museu/Secretaria de Cultura). Prefixo de tabelas: `wp_pi_*`. Número de registro: `PI-{TIPO}-{ANO}-{SEQ06}`. Consentimento LGPD granular por finalidade. Documentos pessoais NUNCA em `/wp-content/uploads` público — sempre em storage privado autenticado. CPF/RG/Passaporte criptografados em repouso (libsodium). Branch de trabalho: `refactor/participe-ibram`.

**Decisões consolidadas:**
- Formato do número de registro: `PI-{TIPO}-{ANO}-{SEQ06}` (sequência por tipo+ano).
- 3 abas separadas no formulário (PF, Organização, Sistema/Secretaria) — wizard multi-etapas.
- Áreas temáticas: vocabulário sugerido pelo agente (não consolidado pelo Ibram).
- Texto LGPD inteligente e granular por finalidade.
- Lista de instâncias de participação flexível (tabela de vocabulário com flag `recorrente`).
- Modelos de documento (carta de apresentação, carta de indicação, ofício) gerados pelo sistema.

**Documentos-fonte:** C:\Users\marcos.sigismundo\Documents\TAINACAN\PARTICIPA IBRAM (4 arquivos: Minuta 2089/2024, Portaria 3230/2024, Despacho 98/2025, Cadastro de Agentes .docx).
