# Test Credentials — Participe Ibram

> **AMBIENTE DE TESTE** — Senhas geradas automaticamente e armazenadas em `wp_options.pi_test_credentials`.
> REMOVA antes de ir para produção.
> Este arquivo é **atualizado automaticamente** ao clicar "Criar 9 usuários de teste".

| Login | Role | Senha | URL Login |
|---|---|---|---|
| teste_admin | administrator + pi_administrador | *(gerada ao criar usuários)* | /wp-login.php |
| teste_analista | pi_analista | *(gerada ao criar usuários)* | /wp-login.php |
| teste_presidencia | pi_presidencia | *(gerada ao criar usuários)* | /wp-login.php |
| teste_gestor_edital | pi_gestor_edital | *(gerada ao criar usuários)* | /wp-login.php |
| teste_apuracao | pi_apuracao | *(gerada ao criar usuários)* | /wp-login.php |
| teste_dpo | pi_dpo | *(gerada ao criar usuários)* | /wp-login.php |
| teste_agente_pf | pi_agente + subscriber | *(gerada ao criar usuários)* | /wp-login.php |
| teste_agente_or | pi_agente + subscriber | *(gerada ao criar usuários)* | /wp-login.php |
| teste_agente_sm | pi_agente + subscriber | *(gerada ao criar usuários)* | /wp-login.php |

## Reset

Para regenerar senhas: **Setup de Teste → "Criar 9 usuários de teste"** (idempotente — recria usuários existentes com nova senha).

## Obter senhas atuais

As senhas reais ficam em `wp_options` com a chave `pi_test_credentials`.
Consulte via wp-admin → Setup de Teste → Card 2 (tabela de credenciais).

## Cleanup

**Setup de Teste → "Remover dados de teste"** → Digite `CONFIRMAR` → Confirmar.
Remove usuários, agentes, edital, inscrições, votação, audit seeds e opção `pi_test_credentials`.
