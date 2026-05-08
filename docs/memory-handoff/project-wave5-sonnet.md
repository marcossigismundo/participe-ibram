---
name: Wave 5 executed with Sonnet 4.6 — requires Wave 10 audit
description: Onda 5 (módulo de Editais) do refactor Participe Ibram foi feita com Sonnet 4.6 em vez de Opus por economia de quota; precisa auditoria extra na Onda 10
type: project
originSessionId: b77ae3f1-1e39-4bac-bc54-4cbd385aac4b
---
A Onda 5 do refactor Participe Ibram foi executada com **Sonnet 4.6** (e não Opus 4.7 como as ondas anteriores) para preservar quota do Opus para componentes mais críticos (Onda 6 votação eletrônica, Onda 10 QA final).

**Why:** Onda 5 é majoritariamente Presentation Layer seguindo padrões já estabelecidos (admin UI seguindo Wave 4, público seguindo Wave 3, REST seguindo W3-B); domínio Edital/Inscricao/Habilitação foi criado em Wave 2 com Opus. Risco principal: vazamento de PII em páginas/endpoints públicos. Quota do Opus foi limitada e o usuário pediu reservar para Onda 6 e Onda 10.

**How to apply:** Quando chegar à Onda 10 (QA final com Opus), revisar com atenção redobrada TODOS os arquivos criados na Onda 5:
- Listagens públicas de editais e inscritos: confirmar whitelist de campos (apenas numero_registro, nome_publico, categoria — sem CPF/email/dados sensíveis)
- Capability checks em toda action admin (R5 V-06)
- `wp_unslash()` antes de sanitize (R5 V-08, AP-02)
- Audit em todas as transições (`pi_edital_publicado`, `pi_inscricao_recebida`, `pi_habilitacao_decidida`, `pi_recurso_inabilitacao_decidido`)
- State machine guards passam por entidades (`Edital::publicar()` etc.) — não SQL direto
- Race conditions de inscrição (UNIQUE constraint + tratamento gracioso de erro 1062)
- Documentos de inscrição via `PrivateFileStorage` com MIME real
- Rate limiting em endpoints públicos
- WCAG 2.1 AA — wizard reusa Wave 3, modal reusa Modal.js Wave 3

Detalhes do plano de auditoria estão em `refactor-spec/AGENTS-PLAN.md` seção "ALERTA: Auditoria obrigatória da Onda 5 na Onda 10".
