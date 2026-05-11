<?php
/**
 * SetupTesteController — lógica da página de Setup de Teste (Wave 8.5).
 *
 * 4 cards:
 *  1. Pre-flight check
 *  2. Criar usuários de teste
 *  3. Seed de dados de teste
 *  4. Cleanup
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\SetupTeste
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\SetupTeste;

/**
 * Responsável por: renderizar a view, processar POSTs, fornecer dados
 * ao template via variáveis explícitas.
 *
 * Não usa DI container para manter zero-dependências extras — funciona
 * imediatamente após ativação mesmo sem migrations rodadas.
 */
final class SetupTesteController
{
    /** Nonce action para ações de criação. */
    private const NONCE_ACTION = 'pi_setup_teste_action';

    /** Option que armazena credenciais temporárias. */
    private const OPT_CREDENTIALS = 'pi_test_credentials';

    /** Prefixo para transient de flash message. */
    private const FLASH_PREFIX = 'pi_setup_flash_';

    // -----------------------------------------------------------------------
    // Render
    // -----------------------------------------------------------------------

    public function render(): void
    {
        if (!current_user_can(SetupTesteMenuRegistry::effectiveCap())) {
            wp_die(
                esc_html__('Você não tem permissão para acessar esta página.', 'participe-ibram'),
                403
            );
            return;
        }

        // Coleta dados para os cards.
        $preflight  = $this->runPreflight();
        $flash      = $this->getFlash();
        $credentials = get_option(self::OPT_CREDENTIALS, []);
        $nonce      = wp_create_nonce(self::NONCE_ACTION);

        $template = $this->templatePath('setup-teste/index.php');
        if ($template !== null) {
            include $template;
            return;
        }

        echo '<div class="wrap"><p>' .
            esc_html__('Template não encontrado.', 'participe-ibram') .
            '</p></div>';
    }

    // -----------------------------------------------------------------------
    // POST handler (chamado via admin_init)
    // -----------------------------------------------------------------------

    public function handlePost(): void
    {
        if (!isset($_POST['pi_setup_action'])) {
            return;
        }

        if (!wp_verify_nonce(
            sanitize_text_field(wp_unslash($_POST['_wpnonce'] ?? '')),
            self::NONCE_ACTION
        )) {
            $this->setFlash('error', __('Token de segurança inválido. Recarregue a página.', 'participe-ibram'));
            $this->redirect();
            return;
        }

        if (!current_user_can(SetupTesteMenuRegistry::effectiveCap())) {
            $this->setFlash('error', __('Sem permissão.', 'participe-ibram'));
            $this->redirect();
            return;
        }

        $action = sanitize_key(wp_unslash($_POST['pi_setup_action']));

        switch ($action) {
            case 'criar_usuarios':
                $this->actionCriarUsuarios();
                break;
            case 'popular_dados':
                $this->actionPopularDados();
                break;
            case 'cleanup':
                $this->actionCleanup();
                break;
            default:
                $this->setFlash('error', __('Ação desconhecida.', 'participe-ibram'));
        }

        $this->redirect();
    }

    // -----------------------------------------------------------------------
    // Card 1: Pre-flight
    // -----------------------------------------------------------------------

    /**
     * Executa todas as verificações de pré-voo.
     *
     * @return array<string, array{label:string, status:string, detail:string}>
     */
    public function runPreflight(): array
    {
        global $wpdb;
        $checks = [];

        // 1. PHP version.
        $phpOk = version_compare(PHP_VERSION, '7.4', '>=');
        $checks['php_version'] = [
            'label'  => sprintf(__('PHP ≥ 7.4 (encontrado: %s)', 'participe-ibram'), PHP_VERSION),
            'status' => $phpOk ? 'ok' : 'error',
            'detail' => $phpOk ? '' : __('Atualize o PHP para 7.4 ou superior.', 'participe-ibram'),
        ];

        // 2. Sodium.
        $sodiumOk = function_exists('sodium_crypto_secretbox');
        $checks['sodium'] = [
            'label'  => __('Extensão sodium disponível', 'participe-ibram'),
            'status' => $sodiumOk ? 'ok' : 'error',
            'detail' => $sodiumOk ? '' : __('Habilite ext-sodium no php.ini do XAMPP.', 'participe-ibram'),
        ];

        // 3. Constantes wp-config.
        $requiredConsts = [
            'PI_ENC_KEY_V1',
            'PI_ENC_KEY_CURRENT',
            'PI_HMAC_KEY',
            'PI_IP_PEPPER',
            'PI_VOTING_SECRET',
            'PI_UNSUBSCRIBE_SECRET',
        ];
        $missingConsts = [];
        foreach ($requiredConsts as $const) {
            if (!defined($const)) {
                $missingConsts[] = $const;
            }
        }
        $checks['constants'] = [
            'label'  => __('Constantes wp-config (6 chaves)', 'participe-ibram'),
            'status' => empty($missingConsts) ? 'ok' : 'warning',
            'detail' => empty($missingConsts)
                ? ''
                : sprintf(
                    __('Faltando: %s. Veja o modal "Como configurar" abaixo.', 'participe-ibram'),
                    implode(', ', $missingConsts)
                ),
            'missing_consts' => $missingConsts,
        ];

        // 4. Tabelas wp_pi_*.
        $tableCount = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME LIKE '{$wpdb->prefix}pi_%'"
        );
        $checks['tables'] = [
            'label'  => sprintf(__('Tabelas wp_pi_* (esperado: 26, encontrado: %d)', 'participe-ibram'), $tableCount),
            'status' => $tableCount >= 26 ? 'ok' : ($tableCount > 0 ? 'warning' : 'error'),
            'detail' => $tableCount >= 26
                ? ''
                : __('Execute a ativação do plugin novamente (Desativar → Ativar no wp-admin → Plugins).', 'participe-ibram'),
        ];

        // 5. Diretório privado + .htaccess.
        $privateDir = WP_CONTENT_DIR . '/uploads/participe-ibram-private';
        $htaccess   = $privateDir . '/.htaccess';
        $dirOk      = is_dir($privateDir);
        $htOk       = file_exists($htaccess);
        $checks['private_dir'] = [
            'label'  => __('Diretório uploads/participe-ibram-private/ + .htaccess', 'participe-ibram'),
            'status' => ($dirOk && $htOk) ? 'ok' : ($dirOk ? 'warning' : 'error'),
            'detail' => (!$dirOk)
                ? __('Diretório não encontrado. Ative o plugin.', 'participe-ibram')
                : (!$htOk ? __('.htaccess deny ausente. Segurança comprometida!', 'participe-ibram') : ''),
        ];

        // 6. Autoload.
        $autoloadOk = class_exists('Ibram\\ParticipeIbram\\Bootstrap\\Plugin');
        $checks['autoload'] = [
            'label'  => __('Autoload PSR-4 funcional (classe Plugin encontrada)', 'participe-ibram'),
            'status' => $autoloadOk ? 'ok' : 'error',
            'detail' => $autoloadOk ? '' : __('O autoloader PSR-4 não está funcionando.', 'participe-ibram'),
        ];

        // 7. Cron pi_email_queue_tick.
        $cronEmail = wp_next_scheduled('pi_email_queue_tick');
        $checks['cron_email'] = [
            'label'  => __('Cron pi_email_queue_tick agendado', 'participe-ibram'),
            'status' => $cronEmail !== false ? 'ok' : 'warning',
            'detail' => $cronEmail !== false
                ? sprintf(__('Próxima execução: %s', 'participe-ibram'), wp_date('d/m/Y H:i:s', $cronEmail))
                : __('Não agendado. Ative o plugin ou acesse qualquer página do front-end.', 'participe-ibram'),
        ];

        // 8. Cron pi_dpo_alerts_check.
        $cronDpo = wp_next_scheduled('pi_dpo_alerts_check');
        $checks['cron_dpo'] = [
            'label'  => __('Cron pi_dpo_alerts_check agendado', 'participe-ibram'),
            'status' => $cronDpo !== false ? 'ok' : 'warning',
            'detail' => $cronDpo !== false
                ? sprintf(__('Próxima execução: %s', 'participe-ibram'), wp_date('d/m/Y H:i:s', $cronDpo))
                : __('Não agendado. Ative o plugin ou acesse qualquer página do front-end.', 'participe-ibram'),
        ];

        // 9. Migrations aplicadas vs arquivos.
        $migTable = $wpdb->prefix . 'pi_migrations';
        $appliedCount = 0;
        if ($wpdb->get_var("SHOW TABLES LIKE '{$migTable}'") === $migTable) {
            $appliedCount = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$migTable}`");
        }
        $migDir   = defined('PI_PLUGIN_DIR') ? rtrim(PI_PLUGIN_DIR, '/\\') . '/migrations' : '';
        $fileCount = 0;
        if ($migDir && is_dir($migDir)) {
            $fileCount = count(glob($migDir . '/V*.sql') ?: []);
        }
        $migOk = ($appliedCount > 0 && $appliedCount >= $fileCount);
        $checks['migrations'] = [
            'label'  => sprintf(
                __('Migrations: %d aplicadas / %d arquivos', 'participe-ibram'),
                $appliedCount,
                $fileCount
            ),
            'status' => $migOk ? 'ok' : ($appliedCount > 0 ? 'warning' : 'error'),
            'detail' => $migOk ? '' : __('Reative o plugin para rodar as migrations.', 'participe-ibram'),
        ];

        return $checks;
    }

    // -----------------------------------------------------------------------
    // Card 2: Criar usuários
    // -----------------------------------------------------------------------

    private function actionCriarUsuarios(): void
    {
        $testUsers = self::testUsersDefinition();
        $credentials = [];
        $created = 0;
        $updated = 0;
        $errors  = [];

        foreach ($testUsers as $def) {
            $login    = $def['login'];
            $role     = $def['role'];
            $extraRole = $def['extra_role'] ?? null;

            $password = wp_generate_password(16, true, true);
            $email    = $login . '@teste.participe-ibram.local';

            $existingId = username_exists($login);

            if ($existingId) {
                // Atualiza.
                $result = wp_update_user([
                    'ID'         => $existingId,
                    'user_pass'  => $password,
                    'user_email' => $email,
                ]);
                if (is_wp_error($result)) {
                    $errors[] = sprintf(__('Erro ao atualizar %s: %s', 'participe-ibram'), $login, $result->get_error_message());
                    continue;
                }
                $userId = $existingId;
                $updated++;
            } else {
                // Cria.
                $result = wp_insert_user([
                    'user_login' => $login,
                    'user_pass'  => $password,
                    'user_email' => $email,
                    'role'       => $role,
                    'display_name' => $def['display_name'] ?? $login,
                ]);
                if (is_wp_error($result)) {
                    $errors[] = sprintf(__('Erro ao criar %s: %s', 'participe-ibram'), $login, $result->get_error_message());
                    continue;
                }
                $userId = (int) $result;
                $created++;
            }

            // Ajusta roles.
            $user = new \WP_User($userId);
            $user->set_role($role);
            if ($extraRole) {
                $user->add_role($extraRole);
            }

            // Meta de teste.
            update_user_meta($userId, 'pi_test_user', '1');

            // Audita (sem PII).
            $this->audit('setup_teste', $userId, 'criar_usuario_teste', null, [
                'login' => $login,
                'role'  => $role,
            ]);

            $credentials[$login] = [
                'password' => $password,
                'role'     => $role . ($extraRole ? ' + ' . $extraRole : ''),
                'user_id'  => $userId,
            ];
        }

        // Grava credenciais em option (sem criptografia — ambiente de teste).
        update_option(self::OPT_CREDENTIALS, $credentials, false);

        // Atualiza TEST-CREDENTIALS.md dinamicamente.
        $this->updateCredentialsMd($credentials);

        if (!empty($errors)) {
            $this->setFlash('error', implode('<br>', $errors));
            return;
        }

        $this->setFlash(
            'success',
            sprintf(
                __('Usuários de teste: %d criados, %d atualizados. Credenciais salvas.', 'participe-ibram'),
                $created,
                $updated
            )
        );
    }

    /**
     * Definição dos 9 usuários de teste.
     *
     * @return array<int, array{login:string, role:string, display_name:string, extra_role?:string}>
     */
    private static function testUsersDefinition(): array
    {
        return [
            ['login' => 'teste_admin',          'role' => 'administrator',  'display_name' => 'Teste Admin',            'extra_role' => 'pi_administrador'],
            ['login' => 'teste_analista',        'role' => 'pi_analista',    'display_name' => 'Teste Analista'],
            ['login' => 'teste_presidencia',     'role' => 'pi_presidencia', 'display_name' => 'Teste Presidência'],
            ['login' => 'teste_gestor_edital',   'role' => 'pi_gestor_edital', 'display_name' => 'Teste Gestor Edital'],
            ['login' => 'teste_apuracao',        'role' => 'pi_apuracao',    'display_name' => 'Teste Apuração'],
            ['login' => 'teste_dpo',             'role' => 'pi_dpo',         'display_name' => 'Teste DPO'],
            ['login' => 'teste_agente_pf',       'role' => 'pi_agente',      'display_name' => 'Agente Teste PF',        'extra_role' => 'subscriber'],
            ['login' => 'teste_agente_or',       'role' => 'pi_agente',      'display_name' => 'Agente Teste OR',        'extra_role' => 'subscriber'],
            ['login' => 'teste_agente_sm',       'role' => 'pi_agente',      'display_name' => 'Agente Teste SM',        'extra_role' => 'subscriber'],
        ];
    }

    // -----------------------------------------------------------------------
    // Card 3: Popular dados
    // -----------------------------------------------------------------------

    private function actionPopularDados(): void
    {
        global $wpdb;

        $errors = [];
        $now    = current_time('mysql');
        $nowTs  = current_time('timestamp');

        // IDs dos usuários de teste.
        $uidPf = (int) username_exists('teste_agente_pf');
        $uidOr = (int) username_exists('teste_agente_or');
        $uidSm = (int) username_exists('teste_agente_sm');

        if (!$uidPf || !$uidOr || !$uidSm) {
            $this->setFlash('error', __('Crie os usuários de teste primeiro (Card 2).', 'participe-ibram'));
            $this->redirect();
            return;
        }

        $emailPf = get_userdata($uidPf)->user_email ?? 'teste_agente_pf@teste.participe-ibram.local';
        $emailOr = get_userdata($uidOr)->user_email ?? 'teste_agente_or@teste.participe-ibram.local';
        $emailSm = get_userdata($uidSm)->user_email ?? 'teste_agente_sm@teste.participe-ibram.local';

        $prefix = $wpdb->prefix;

        // ── Agentes ───────────────────────────────────────────────────────
        // PF — DEFERIDO
        $wpdb->delete("{$prefix}pi_agentes", ['user_id' => $uidPf]);
        $wpdb->insert("{$prefix}pi_agentes", [
            'user_id'           => $uidPf,
            'tipo_agente'       => 'PF',
            'status'            => 'DEFERIDO',
            'numero_registro'   => 'PI-PF-2026-000001',
            'nome_responsavel'  => 'Agente Teste PF',
            'email_principal'   => $emailPf,
            'cpf_enc'           => base64_encode('12345678901_dummy_enc'),
            'pi_test_seed'      => '1',
            'criado_em'         => $now,
            'atualizado_em'     => $now,
        ], ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']);
        $agentePfId = (int) $wpdb->insert_id;

        // OR — SUBMETIDO
        $wpdb->delete("{$prefix}pi_agentes", ['user_id' => $uidOr]);
        $wpdb->insert("{$prefix}pi_agentes", [
            'user_id'           => $uidOr,
            'tipo_agente'       => 'OR',
            'status'            => 'SUBMETIDO',
            'numero_registro'   => null,
            'nome_responsavel'  => 'Organização Teste',
            'email_principal'   => $emailOr,
            'cnpj_enc'          => base64_encode('00000000000191_dummy_enc'),
            'pi_test_seed'      => '1',
            'criado_em'         => $now,
            'atualizado_em'     => $now,
        ], ['%d', '%s', '%s', null, '%s', '%s', '%s', '%s', '%s', '%s']);
        $agenteOrId = (int) $wpdb->insert_id;

        // SM — RASCUNHO
        $wpdb->delete("{$prefix}pi_agentes", ['user_id' => $uidSm]);
        $wpdb->insert("{$prefix}pi_agentes", [
            'user_id'           => $uidSm,
            'tipo_agente'       => 'SM',
            'status'            => 'RASCUNHO',
            'numero_registro'   => null,
            'nome_responsavel'  => 'Sistema Teste',
            'email_principal'   => $emailSm,
            'esfera'            => 'federal',
            'pi_test_seed'      => '1',
            'criado_em'         => $now,
            'atualizado_em'     => $now,
        ], ['%d', '%s', '%s', null, '%s', '%s', '%s', '%s', '%s', '%s']);
        $agenteSmId = (int) $wpdb->insert_id;

        // ── Edital ─────────────────────────────────────────────────────────
        $wpdb->delete("{$prefix}pi_editais", ['titulo' => 'Edital de Teste — CCDEM 2026']);
        $wpdb->insert("{$prefix}pi_editais", [
            'titulo'                     => 'Edital de Teste — CCDEM 2026',
            'status'                     => 'PUBLICADO',
            'abertura_inscricoes'        => gmdate('Y-m-d H:i:s', $nowTs - 7 * DAY_IN_SECONDS),
            'encerramento_inscricoes'    => gmdate('Y-m-d H:i:s', $nowTs + 7 * DAY_IN_SECONDS),
            'publicacao_habilitacao'     => gmdate('Y-m-d H:i:s', $nowTs + 10 * DAY_IN_SECONDS),
            'prazo_recurso_inabilitacao' => gmdate('Y-m-d H:i:s', $nowTs + 13 * DAY_IN_SECONDS),
            'abertura_votacao'           => gmdate('Y-m-d H:i:s', $nowTs + 15 * DAY_IN_SECONDS),
            'encerramento_votacao'       => gmdate('Y-m-d H:i:s', $nowTs + 20 * DAY_IN_SECONDS),
            'publicacao_resultado'       => gmdate('Y-m-d H:i:s', $nowTs + 22 * DAY_IN_SECONDS),
            'pi_test_seed'               => '1',
            'criado_em'                  => $now,
            'atualizado_em'              => $now,
        ]);
        $editalId = (int) $wpdb->insert_id;

        // ── Categorias do edital ───────────────────────────────────────────
        if ($editalId > 0) {
            $wpdb->delete("{$prefix}pi_edital_categorias", ['edital_id' => $editalId]);

            $wpdb->insert("{$prefix}pi_edital_categorias", [
                'edital_id'       => $editalId,
                'nome'            => 'Sociedade Civil — PF',
                'tipos_aceitos'   => 'PF',
                'vagas_titulares' => 3,
                'vagas_suplentes' => 1,
                'criado_em'       => $now,
            ], ['%d', '%s', '%s', '%d', '%d', '%s']);
            $catPfId = (int) $wpdb->insert_id;

            $wpdb->insert("{$prefix}pi_edital_categorias", [
                'edital_id'       => $editalId,
                'nome'            => 'Organizações Museológicas',
                'tipos_aceitos'   => 'OR',
                'vagas_titulares' => 3,
                'vagas_suplentes' => 1,
                'criado_em'       => $now,
            ], ['%d', '%s', '%s', '%d', '%d', '%s']);
        }

        // ── Inscrição ─────────────────────────────────────────────────────
        if ($editalId > 0 && $agentePfId > 0) {
            $wpdb->delete("{$prefix}pi_inscricoes", [
                'edital_id' => $editalId,
                'agente_id' => $agentePfId,
            ]);
            $wpdb->insert("{$prefix}pi_inscricoes", [
                'edital_id'    => $editalId,
                'agente_id'    => $agentePfId,
                'status'       => 'HABILITADO',
                'pi_test_seed' => '1',
                'criado_em'    => $now,
                'atualizado_em' => $now,
            ], ['%d', '%d', '%s', '%s', '%s', '%s']);
        }

        // ── Votação ───────────────────────────────────────────────────────
        if ($editalId > 0) {
            $wpdb->delete("{$prefix}pi_votacoes", ['edital_id' => $editalId]);
            $wpdb->insert("{$prefix}pi_votacoes", [
                'edital_id'    => $editalId,
                'status'       => 'AGENDADA',
                'pi_test_seed' => '1',
                'criado_em'    => $now,
                'atualizado_em' => $now,
            ], ['%d', '%s', '%s', '%s', '%s']);
        }

        // ── Audit log seeds ────────────────────────────────────────────────
        $auditTable = $wpdb->prefix . 'pi_audit_log';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$auditTable}'") === $auditTable) {
            for ($i = 1; $i <= 3; $i++) {
                $wpdb->insert($auditTable, [
                    'entidade'    => 'setup_teste',
                    'entidade_id' => $i,
                    'acao'        => 'seed_audit_' . $i,
                    'ator_id'     => get_current_user_id(),
                    'dados_antes' => null,
                    'dados_depois' => wp_json_encode(['seed' => true, 'seq' => $i]),
                    'ocorrido_em' => $now,
                ], ['%s', '%d', '%s', '%d', null, '%s', '%s']);
            }
        }

        // ── Solicitação Art. 18 (DPO) ────────────────────────────────────
        $lgpdTable = $wpdb->prefix . 'pi_lgpd_solicitacoes';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$lgpdTable}'") === $lgpdTable) {
            $wpdb->delete($lgpdTable, ['solicitante_id' => $uidPf, 'pi_test_seed' => '1']);
            $wpdb->insert($lgpdTable, [
                'solicitante_id' => $uidPf,
                'tipo'           => 'acesso',
                'status'         => 'aberta',
                'descricao'      => 'Solicitação de acesso — dado de teste.',
                'pi_test_seed'   => '1',
                'criado_em'      => $now,
                'atualizado_em'  => $now,
            ], ['%d', '%s', '%s', '%s', '%s', '%s', '%s']);
        }

        $this->audit('setup_teste', null, 'popular_dados_teste', null, [
            'edital_id'   => $editalId,
            'agente_pf_id' => $agentePfId,
        ]);

        if ($wpdb->last_error) {
            $this->setFlash('warning', sprintf(
                __('Dados inseridos com possíveis avisos. Último erro DB: %s', 'participe-ibram'),
                esc_html($wpdb->last_error)
            ));
            return;
        }

        $this->setFlash('success', __('Dados de teste populados com sucesso!', 'participe-ibram'));
    }

    // -----------------------------------------------------------------------
    // Card 4: Cleanup
    // -----------------------------------------------------------------------

    private function actionCleanup(): void
    {
        global $wpdb;
        $prefix = $wpdb->prefix;

        // Confirmar via campo POST dedicado (modal confirmation).
        $confirm = sanitize_text_field(wp_unslash($_POST['pi_confirm_cleanup'] ?? ''));
        if ($confirm !== 'CONFIRMAR') {
            $this->setFlash('error', __('Confirmação incorreta. Digite CONFIRMAR para prosseguir.', 'participe-ibram'));
            $this->redirect();
            return;
        }

        $this->audit('setup_teste', null, 'cleanup_dados_teste', null, ['operador' => get_current_user_id()]);

        // Remove dados seed.
        $tables = [
            'pi_lgpd_solicitacoes', 'pi_inscricoes', 'pi_votacoes',
            'pi_edital_categorias', 'pi_editais', 'pi_agentes',
        ];
        foreach ($tables as $t) {
            $wpdb->delete("{$prefix}{$t}", ['pi_test_seed' => '1'], ['%s']);
        }

        // Remove editais pelo título caso coluna pi_test_seed não exista.
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM `{$prefix}pi_editais` WHERE titulo = %s",
                'Edital de Teste — CCDEM 2026'
            )
        );

        // Remove usuários de teste.
        $testLogins = array_column(self::testUsersDefinition(), 'login');
        foreach ($testLogins as $login) {
            $uid = (int) username_exists($login);
            if ($uid > 0) {
                require_once ABSPATH . 'wp-admin/includes/user.php';
                wp_delete_user($uid);
            }
        }

        // Reset option credenciais.
        delete_option(self::OPT_CREDENTIALS);

        $this->setFlash('success', __('Todos os dados e usuários de teste foram removidos.', 'participe-ibram'));
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Grava um evento no audit log sem dependência do container.
     *
     * @param array<string,mixed>|null $before
     * @param array<string,mixed>|null $after
     */
    private function audit(
        string $entity,
        ?int $entityId,
        string $action,
        ?array $before,
        ?array $after
    ): void {
        global $wpdb;

        $table = $wpdb->prefix . 'pi_audit_log';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return;
        }

        $wpdb->insert($table, [
            'entidade'    => substr($entity, 0, 50),
            'entidade_id' => $entityId,
            'acao'        => substr($action, 0, 50),
            'ator_id'     => get_current_user_id() ?: null,
            'dados_antes' => $before !== null ? wp_json_encode($before) : null,
            'dados_depois' => $after !== null ? wp_json_encode($after) : null,
            'ocorrido_em' => current_time('mysql'),
        ], ['%s', '%d', '%s', '%d', '%s', '%s', '%s']);
    }

    /**
     * Armazena flash message em transient (5 min).
     */
    private function setFlash(string $type, string $message): void
    {
        $userId = get_current_user_id();
        set_transient(self::FLASH_PREFIX . $userId, ['type' => $type, 'message' => $message], 300);
    }

    /**
     * Lê e remove o flash transient.
     *
     * @return array{type:string, message:string}|null
     */
    public function getFlash(): ?array
    {
        $userId = get_current_user_id();
        $key    = self::FLASH_PREFIX . $userId;
        $flash  = get_transient($key);
        if (is_array($flash)) {
            delete_transient($key);
            return $flash;
        }
        return null;
    }

    /**
     * Redireciona de volta para a página após POST.
     */
    private function redirect(): void
    {
        $url = add_query_arg(
            ['page' => SetupTesteMenuRegistry::SLUG],
            admin_url('admin.php')
        );
        wp_safe_redirect($url);
        exit;
    }

    private function templatePath(string $relative): ?string
    {
        $base = defined('PI_PLUGIN_DIR') ? (string) PI_PLUGIN_DIR : dirname(__DIR__, 4);
        $candidate = rtrim($base, '/\\') . '/templates/admin/' . ltrim($relative, '/');
        return file_exists($candidate) ? $candidate : null;
    }

    /**
     * Atualiza TEST-CREDENTIALS.md com as credenciais geradas.
     *
     * @param array<string, array{password:string, role:string, user_id:int}> $credentials
     */
    private function updateCredentialsMd(array $credentials): void
    {
        $path = defined('PI_PLUGIN_DIR')
            ? rtrim(PI_PLUGIN_DIR, '/\\') . '/TEST-CREDENTIALS.md'
            : '';

        if (!$path) {
            return;
        }

        $loginUrl = wp_login_url();
        $rows     = '';
        foreach ($credentials as $login => $data) {
            $rows .= sprintf(
                "| %s | %s | %s | %s |\n",
                esc_html($login),
                esc_html($data['role']),
                esc_html($data['password']),
                esc_url($loginUrl)
            );
        }

        $content = sprintf(
            "# Test Credentials — Participe Ibram\n\n" .
            "> **AMBIENTE DE TESTE** — Senhas geradas automaticamente e armazenadas em `wp_options.pi_test_credentials`.\n" .
            "> REMOVA antes de ir para produção. Gerado em: %s\n\n" .
            "| Login | Role | Senha | URL Login |\n" .
            "|---|---|---|---|\n%s\n" .
            "## Reset\n\n" .
            "Para regenerar senhas: **Setup de Teste → \"Criar 9 usuários de teste\"** (idempotente).\n\n" .
            "## Cleanup\n\n" .
            "**Setup de Teste → \"Remover dados de teste\"** (limpa tudo).\n",
            gmdate('d/m/Y H:i:s') . ' UTC',
            $rows
        );

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        file_put_contents($path, $content);
    }
}
