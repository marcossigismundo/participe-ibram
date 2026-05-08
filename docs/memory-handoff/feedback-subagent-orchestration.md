---
name: Organize execution via subagents
description: Para tarefas grandes neste projeto, o usuário prefere que o trabalho seja organizado em ondas de subagentes para preservar contexto da janela principal
type: feedback
originSessionId: b77ae3f1-1e39-4bac-bc54-4cbd385aac4b
---
Em refatorações de grande escala (ex.: refactor IBRAM), o usuário pediu explicitamente: "se organize em agentes que possam executar sem gastar os tokens de uma vez".

**Why:** preserva orçamento de tokens da conversa principal e permite paralelização em domínios independentes (UX, LGPD, segurança, banco de dados, frontend). O agente principal coordena; subagentes executam.

**How to apply:**
1. Antes de iniciar trabalho extenso, criar specs/anchor docs em local conhecido (ex.: `C:\Users\marcos.sigismundo\.claude\projects\c--xampp82-htdocs-wordpress-wp-content-plugins-crm-developer\refactor-spec\`).
2. Lançar subagentes de pesquisa em **background** quando possível, para não bloquear conversa.
3. Implementação: organizar em **ondas** (waves), cada onda = 1-3 agentes em paralelo, com escopo bem definido por arquivo/módulo.
4. Cada subagente deve ter prompt auto-contido com paths absolutos, decisões já tomadas, e contrato de saída claro.
5. Após cada onda, o agente principal sintetiza, atualiza specs e dispara próxima onda.
