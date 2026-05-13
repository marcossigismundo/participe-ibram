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
            case 'reativar':
                $this->actionReativar();
                break;
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
    // Card 0: Re-executar activator (BUG FIX 2026-05-11)
    // -----------------------------------------------------------------------

    /**
     * Re-roda o Activator inteiro (roles + diretório privado + migrations +
     * crons). Útil quando a primeira ativação falhou silenciosamente.
     */
    private function actionReativar(): void
    {
        $activator = 'Ibram\\ParticipeIbram\\Bootstrap\\Activator';
        if (!class_exists($activator)) {
            $this->setFlash('error', __('Classe Activator não encontrada.', 'participe-ibram'));
            return;
        }

        try {
            $activator::activate();

            $lastError = get_option('pi_activation_last_error', '');
            if ($lastError !== '') {
                $this->setFlash(
                    'error',
                    sprintf(
                        /* translators: %s = mensagem técnica do erro */
                        __('Activator rodou mas com erro: %s', 'participe-ibram'),
                        $lastError
                    )
                );
                return;
            }

            $applied = get_option('pi_activation_last_applied', []);
            $count   = is_array($applied) ? count($applied) : 0;
            $this->setFlash(
                'success',
                sprintf(
                    /* translators: %d = número de migrations aplicadas */
                    _n(
                        'Activator executado com sucesso. %d migration aplicada.',
                        'Activator executado com sucesso. %d migrations aplicadas.',
                        $count,
                        'participe-ibram'
                    ),
                    $count
                )
            );
        } catch (\Throwable $e) {
            $this->setFlash(
                'error',
                sprintf(
                    /* translators: %s = mensagem técnica do erro */
                    __('Falha ao executar Activator: %s', 'participe-ibram'),
                    $e->getMessage()
                )
            );
        }
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
    // Card 3: Popular dados (W13 refactor)
    // -----------------------------------------------------------------------

    /**
     * Ponto de entrada do seed. Orquestra sub-métodos e exibe contagem final.
     *
     * Idempotência: cada sub-método apaga registros identificados pela coluna
     * `email_principal LIKE '%@seed.ibram.test'` (agentes) ou
     * `titulo LIKE '%[SEED]%'` (editais) antes de recriar.
     */
    private function actionPopularDados(): void
    {
        if (!$this->verifyTestUsers()) {
            return;
        }

        $userIds = $this->collectTestUserIds();
        $stats   = [];
        $warnings = [];

        try {
            $seedData              = $this->seedAgentes($userIds, $warnings);
            $stats['agentes']      = $seedData['count'];
        } catch (\Throwable $e) {
            $warnings[] = 'seedAgentes: ' . $e->getMessage();
            $seedData = ['count' => 0, 'ids' => [], 'analista_id' => 0, 'extra_ids' => []];
            $stats['agentes'] = 0;
        }

        try {
            $editalData           = $this->seedEditais($userIds, $warnings);
            $stats['editais']     = $editalData['count'];
        } catch (\Throwable $e) {
            $warnings[] = 'seedEditais: ' . $e->getMessage();
            $editalData = ['count' => 0, 'edital_a_id' => 0, 'edital_b_id' => 0, 'edital_enc_id' => 0,
                           'cat_a' => [], 'cat_b' => []];
            $stats['editais'] = 0;
        }

        try {
            $inscData                = $this->seedInscricoes($seedData, $editalData, $warnings);
            $stats['inscricoes']     = $inscData['count'];
        } catch (\Throwable $e) {
            $warnings[] = 'seedInscricoes: ' . $e->getMessage();
            $inscData = ['count' => 0, 'insc_b' => [], 'insc_enc' => [], 'insc_a_inab_id' => 0];
            $stats['inscricoes'] = 0;
        }

        try {
            $stats['votos']          = $this->seedVotos($inscData, $editalData, $warnings);
        } catch (\Throwable $e) {
            $warnings[] = 'seedVotos: ' . $e->getMessage();
            $stats['votos'] = 0;
        }

        try {
            $stats['recursos']       = $this->seedRecursos($seedData, $inscData, $warnings);
        } catch (\Throwable $e) {
            $warnings[] = 'seedRecursos: ' . $e->getMessage();
            $stats['recursos'] = 0;
        }

        try {
            $stats['email_queue']    = $this->seedEmailQueue($seedData, $warnings);
        } catch (\Throwable $e) {
            $warnings[] = 'seedEmailQueue: ' . $e->getMessage();
            $stats['email_queue'] = 0;
        }

        try {
            $stats['lgpd']           = $this->seedLgpdSolicitacoes($seedData, $warnings);
        } catch (\Throwable $e) {
            $warnings[] = 'seedLgpdSolicitacoes: ' . $e->getMessage();
            $stats['lgpd'] = 0;
        }

        try {
            $stats['audit_log']      = $this->seedAuditLog($userIds, $seedData, $warnings);
        } catch (\Throwable $e) {
            $warnings[] = 'seedAuditLog: ' . $e->getMessage();
            $stats['audit_log'] = 0;
        }

        $this->audit('setup_teste', null, 'popular_dados_teste', null, $stats);

        $msg = sprintf(
            /* translators: múltiplos contadores */
            __('Dados de teste populados: %d agentes, %d editais, %d inscrições, %d votos, %d recursos, %d e-mails, %d solicitações LGPD, %d eventos de auditoria.', 'participe-ibram'),
            $stats['agentes'],
            $stats['editais'],
            $stats['inscricoes'],
            $stats['votos'],
            $stats['recursos'],
            $stats['email_queue'],
            $stats['lgpd'],
            $stats['audit_log']
        );

        if (!empty($warnings)) {
            $msg .= ' ' . __('Avisos:', 'participe-ibram') . ' ' . implode(' | ', $warnings);
            $this->setFlash('warning', $msg);
        } else {
            $this->setFlash('success', $msg);
        }
    }

    /**
     * Verifica que os usuários de teste existem; caso contrário flash+return false.
     */
    private function verifyTestUsers(): bool
    {
        $required = ['teste_agente_pf', 'teste_agente_or', 'teste_agente_sm', 'teste_analista', 'teste_presidencia'];
        $missing  = [];
        foreach ($required as $login) {
            if (!username_exists($login)) {
                $missing[] = $login;
            }
        }
        if (!empty($missing)) {
            $this->setFlash(
                'error',
                sprintf(
                    __('Crie os usuários de teste primeiro (Card 2). Faltando: %s', 'participe-ibram'),
                    implode(', ', $missing)
                )
            );
            $this->redirect();
            return false;
        }
        return true;
    }

    /**
     * Coleta os IDs dos usuários de teste em array associativo.
     *
     * @return array<string,int>
     */
    private function collectTestUserIds(): array
    {
        $logins = [
            'teste_admin', 'teste_analista', 'teste_presidencia',
            'teste_gestor_edital', 'teste_apuracao', 'teste_dpo',
            'teste_agente_pf', 'teste_agente_or', 'teste_agente_sm',
        ];
        $ids = [];
        foreach ($logins as $login) {
            $ids[$login] = (int) username_exists($login);
        }
        return $ids;
    }

    // -----------------------------------------------------------------------
    // seed: Agentes
    // -----------------------------------------------------------------------

    /**
     * Cria 8 agentes distribuídos entre PF/OR/SM com perfis realistas.
     * Idempotente: apaga agentes com email `@seed.ibram.test` antes de criar.
     *
     * @param array<string,int>   $userIds
     * @param string[]            $warnings
     * @return array{count:int, ids:array<string,int>, analista_id:int, extra_ids:array<string,int>}
     */
    private function seedAgentes(array $userIds, array &$warnings): array
    {
        global $wpdb;
        $prefix    = $wpdb->prefix;
        $now       = current_time('mysql');
        $nowTs     = current_time('timestamp');
        $analistaId = $userIds['teste_analista'] ?: get_current_user_id();
        $count     = 0;
        $ids       = [];

        // ─── Apaga seeds anteriores (identificados por domínio de email) ──
        $oldIds = $wpdb->get_col(
            "SELECT id FROM `{$prefix}pi_agentes` WHERE email_principal LIKE '%@seed.ibram.test'"
        );
        if (!empty($oldIds)) {
            $placeholders = implode(',', array_fill(0, count($oldIds), '%d'));
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->query($wpdb->prepare(
                "DELETE FROM `{$prefix}pi_agentes` WHERE id IN ({$placeholders})",
                $oldIds
            ));
        }

        // ─── Definição dos 8 agentes seed ─────────────────────────────────
        $agents = [
            // PF deferidos (3)
            [
                'tipo' => 'PF', 'status' => 'deferido',
                'numero_registro' => 'PI-PF-2026-000001',
                'email'  => 'pf1@seed.ibram.test',
                'user_id' => $userIds['teste_agente_pf'],
                'submetido_em' => gmdate('Y-m-d H:i:s', $nowTs - 30 * DAY_IN_SECONDS),
                'deferido_em'  => gmdate('Y-m-d H:i:s', $nowTs - 20 * DAY_IN_SECONDS),
                'pf' => ['nome_completo' => 'Ana Beatriz Silva', 'nome_social' => null,
                         'estado_residencia' => 'DF', 'cidade_residencia' => 'Brasília',
                         'raca_cor' => 'parda', 'faixa_etaria' => '30_39',
                         'identidade_genero' => 'mulher_cis', 'grau_instrucao' => 'superior_completo',
                         'pessoa_deficiencia' => 'nao'],
            ],
            [
                'tipo' => 'PF', 'status' => 'deferido',
                'numero_registro' => 'PI-PF-2026-000002',
                'email'  => 'pf2@seed.ibram.test',
                'user_id' => null,
                'submetido_em' => gmdate('Y-m-d H:i:s', $nowTs - 25 * DAY_IN_SECONDS),
                'deferido_em'  => gmdate('Y-m-d H:i:s', $nowTs - 18 * DAY_IN_SECONDS),
                'pf' => ['nome_completo' => 'Carlos Eduardo Fonseca', 'nome_social' => null,
                         'estado_residencia' => 'SP', 'cidade_residencia' => 'São Paulo',
                         'raca_cor' => 'branca', 'faixa_etaria' => '40_49',
                         'identidade_genero' => 'homem_cis', 'grau_instrucao' => 'pos_graduacao',
                         'pessoa_deficiencia' => 'nao'],
            ],
            [
                'tipo' => 'PF', 'status' => 'deferido',
                'numero_registro' => 'PI-PF-2026-000003',
                'email'  => 'pf3@seed.ibram.test',
                'user_id' => null,
                'submetido_em' => gmdate('Y-m-d H:i:s', $nowTs - 22 * DAY_IN_SECONDS),
                'deferido_em'  => gmdate('Y-m-d H:i:s', $nowTs - 15 * DAY_IN_SECONDS),
                'pf' => ['nome_completo' => 'Maria das Graças Oliveira', 'nome_social' => 'Graça Oliveira',
                         'estado_residencia' => 'BA', 'cidade_residencia' => 'Salvador',
                         'raca_cor' => 'preta', 'faixa_etaria' => '50_59',
                         'identidade_genero' => 'mulher_cis', 'grau_instrucao' => 'medio_completo',
                         'pessoa_deficiencia' => 'nao'],
            ],
            // PF em_analise (1)
            [
                'tipo' => 'PF', 'status' => 'em_analise',
                'numero_registro' => null,
                'email'  => 'pf4@seed.ibram.test',
                'user_id' => null,
                'submetido_em' => gmdate('Y-m-d H:i:s', $nowTs - 5 * DAY_IN_SECONDS),
                'deferido_em'  => null,
                'pf' => ['nome_completo' => 'João Pedro Alves', 'nome_social' => null,
                         'estado_residencia' => 'MG', 'cidade_residencia' => 'Belo Horizonte',
                         'raca_cor' => 'parda', 'faixa_etaria' => '25_29',
                         'identidade_genero' => 'homem_cis', 'grau_instrucao' => 'superior_completo',
                         'pessoa_deficiencia' => 'nao'],
            ],
            // PF indeferido com recurso (1)
            [
                'tipo' => 'PF', 'status' => 'indeferido_aguardando_recurso',
                'numero_registro' => null,
                'email'  => 'pf5@seed.ibram.test',
                'user_id' => null,
                'submetido_em' => gmdate('Y-m-d H:i:s', $nowTs - 15 * DAY_IN_SECONDS),
                'deferido_em'  => null,
                'pf' => ['nome_completo' => 'Fernanda Ribeiro Costa', 'nome_social' => null,
                         'estado_residencia' => 'RJ', 'cidade_residencia' => 'Rio de Janeiro',
                         'raca_cor' => 'branca', 'faixa_etaria' => '35_39',
                         'identidade_genero' => 'mulher_cis', 'grau_instrucao' => 'pos_graduacao',
                         'pessoa_deficiencia' => 'nao'],
            ],
            // OR deferidos (2)
            [
                'tipo' => 'OR', 'status' => 'deferido',
                'numero_registro' => 'PI-OR-2026-000001',
                'email'  => 'or1@seed.ibram.test',
                'user_id' => $userIds['teste_agente_or'],
                'submetido_em' => gmdate('Y-m-d H:i:s', $nowTs - 28 * DAY_IN_SECONDS),
                'deferido_em'  => gmdate('Y-m-d H:i:s', $nowTs - 19 * DAY_IN_SECONDS),
                'or' => ['nome_organizacao' => 'Museu Comunitário do Paranoá',
                         'tem_cnpj' => 'sim', 'tipo_coletivo' => 'museu_comunitario',
                         'abrangencia' => 'municipal', 'estado_sede' => 'DF', 'cidade_sede' => 'Brasília'],
            ],
            [
                'tipo' => 'OR', 'status' => 'deferido',
                'numero_registro' => 'PI-OR-2026-000002',
                'email'  => 'or2@seed.ibram.test',
                'user_id' => null,
                'submetido_em' => gmdate('Y-m-d H:i:s', $nowTs - 24 * DAY_IN_SECONDS),
                'deferido_em'  => gmdate('Y-m-d H:i:s', $nowTs - 16 * DAY_IN_SECONDS),
                'or' => ['nome_organizacao' => 'Rede Memórias do Sertão',
                         'tem_cnpj' => 'nao', 'tipo_coletivo' => 'rede',
                         'abrangencia' => 'regional', 'estado_sede' => 'CE', 'cidade_sede' => 'Fortaleza'],
            ],
            // SM deferido (1)
            [
                'tipo' => 'SM', 'status' => 'deferido',
                'numero_registro' => 'PI-SM-2026-000001',
                'email'  => 'sm1@seed.ibram.test',
                'user_id' => $userIds['teste_agente_sm'],
                'submetido_em' => gmdate('Y-m-d H:i:s', $nowTs - 26 * DAY_IN_SECONDS),
                'deferido_em'  => gmdate('Y-m-d H:i:s', $nowTs - 17 * DAY_IN_SECONDS),
                'sm' => ['nome_orgao' => 'Secretaria Municipal de Cultura de Recife',
                         'esfera' => 'municipal', 'tipo_orgao' => 'secretaria_cultura',
                         'uf' => 'PE', 'municipio' => 'Recife',
                         'representante_legal_nome' => 'Drª. Patrícia Mendes Nunes',
                         'representante_legal_cargo' => 'Secretária Municipal'],
            ],
        ];

        foreach ($agents as $idx => $def) {
            $row = [
                'tipo'            => $def['tipo'],
                'status_cadastro' => $def['status'],
                'numero_registro' => $def['numero_registro'],
                'email_principal' => $def['email'],
                'submetido_em'    => $def['submetido_em'],
                'deferido_em'     => $def['deferido_em'],
                'created_at'      => $now,
                'updated_at'      => $now,
            ];
            if (!empty($def['user_id'])) {
                $row['user_id'] = $def['user_id'];
            }

            $fmts = ['%s', '%s', isset($def['numero_registro']) ? '%s' : null, '%s', '%s', '%s', '%s', '%s'];
            $fmts = array_values(array_filter(['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']));
            if (!empty($def['user_id'])) {
                $fmts[] = '%d';
            }

            $wpdb->insert("{$prefix}pi_agentes", $row);
            if ($wpdb->last_error) {
                $warnings[] = "agente {$def['email']}: {$wpdb->last_error}";
                continue;
            }
            $agenteId = (int) $wpdb->insert_id;
            $ids[$def['email']] = $agenteId;
            $count++;

            // Sub-tabela por tipo.
            if ($def['tipo'] === 'PF' && isset($def['pf'])) {
                $pf = $def['pf'];
                $wpdb->insert("{$prefix}pi_agentes_pf", [
                    'agente_id'          => $agenteId,
                    'nome_completo'      => $pf['nome_completo'],
                    'nome_social'        => $pf['nome_social'],
                    'cpf_enc'            => base64_encode('000.000.000-00_dummy'),
                    'cpf_hash'           => hash('sha256', 'cpf_seed_' . $idx),
                    'estado_residencia'  => $pf['estado_residencia'],
                    'cidade_residencia'  => $pf['cidade_residencia'],
                    'raca_cor'           => $pf['raca_cor'],
                    'faixa_etaria'       => $pf['faixa_etaria'],
                    'identidade_genero'  => $pf['identidade_genero'],
                    'grau_instrucao'     => $pf['grau_instrucao'],
                    'pessoa_deficiencia' => $pf['pessoa_deficiencia'],
                    'apresentacao_md'    => 'Perfil de teste gerado pelo seed W13.',
                ], ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']);
            } elseif ($def['tipo'] === 'OR' && isset($def['or'])) {
                $or = $def['or'];
                $wpdb->insert("{$prefix}pi_agentes_or", [
                    'agente_id'       => $agenteId,
                    'nome_organizacao' => $or['nome_organizacao'],
                    'tem_cnpj'        => $or['tem_cnpj'],
                    'cnpj_enc'        => $or['tem_cnpj'] === 'sim' ? base64_encode('00.000.000/0001-00_dummy') : null,
                    'cnpj_hash'       => $or['tem_cnpj'] === 'sim' ? hash('sha256', 'cnpj_seed_' . $idx) : null,
                    'tipo_coletivo'   => $or['tipo_coletivo'],
                    'abrangencia'     => $or['abrangencia'],
                    'estado_sede'     => $or['estado_sede'],
                    'cidade_sede'     => $or['cidade_sede'],
                    'apresentacao_md' => 'Organização de teste gerada pelo seed W13.',
                ], ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']);

                // 2 representantes por OR deferido.
                if ($def['status'] === 'deferido') {
                    for ($r = 1; $r <= 2; $r++) {
                        $wpdb->insert("{$prefix}pi_agente_representantes", [
                            'agente_id'  => $agenteId,
                            'nome'       => "Representante {$r} de {$or['nome_organizacao']}",
                            'cpf_enc'    => base64_encode('000.000.000-0' . $r . '_dummy'),
                            'cpf_hash'   => hash('sha256', "rep_seed_{$idx}_{$r}"),
                            'email'      => "rep{$r}_{$idx}@seed.ibram.test",
                            'papel'      => $r === 1 ? 'titular' : 'suplente',
                            'principal'  => $r === 1 ? 1 : 0,
                            'ordem'      => $r,
                            'created_at' => $now,
                        ], ['%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s']);
                    }
                }
            } elseif ($def['tipo'] === 'SM' && isset($def['sm'])) {
                $sm = $def['sm'];
                $wpdb->insert("{$prefix}pi_agentes_sm", [
                    'agente_id'                  => $agenteId,
                    'nome_orgao'                 => $sm['nome_orgao'],
                    'esfera'                     => $sm['esfera'],
                    'tipo_orgao'                 => $sm['tipo_orgao'],
                    'uf'                         => $sm['uf'],
                    'municipio'                  => $sm['municipio'],
                    'representante_legal_nome'   => $sm['representante_legal_nome'],
                    'representante_legal_cargo'  => $sm['representante_legal_cargo'],
                    'representante_cpf_enc'      => base64_encode('000.000.000-00_dummy_sm'),
                    'representante_cpf_hash'     => hash('sha256', 'sm_rep_seed_' . $idx),
                ], ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']);

                // 1 representante no pi_agente_representantes para SM.
                $wpdb->insert("{$prefix}pi_agente_representantes", [
                    'agente_id'  => $agenteId,
                    'nome'       => $sm['representante_legal_nome'],
                    'cpf_enc'    => base64_encode('000.000.000-00_dummy_sm_rep'),
                    'cpf_hash'   => hash('sha256', 'sm_main_rep_' . $idx),
                    'email'      => "sm_rep{$idx}@seed.ibram.test",
                    'papel'      => 'representante_legal',
                    'principal'  => 1,
                    'ordem'      => 1,
                    'created_at' => $now,
                ], ['%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s']);
            }

            // status_historico: trilha de transições para agentes deferidos/indeferidos.
            if (in_array($def['status'], ['deferido', 'indeferido_aguardando_recurso', 'em_analise'], true)) {
                $trail = [
                    ['rascunho', 'submetido', $nowTs - 30 * DAY_IN_SECONDS],
                    ['submetido', 'em_analise', $nowTs - 20 * DAY_IN_SECONDS],
                ];
                if ($def['status'] === 'deferido') {
                    $trail[] = ['em_analise', 'deferido', $nowTs - 15 * DAY_IN_SECONDS];
                } elseif ($def['status'] === 'indeferido_aguardando_recurso') {
                    $trail[] = ['em_analise', 'indeferido_aguardando_recurso', $nowTs - 10 * DAY_IN_SECONDS];
                }
                foreach ($trail as [$ant, $novo, $ts]) {
                    $wpdb->insert("{$prefix}pi_status_historico", [
                        'agente_id'      => $agenteId,
                        'status_anterior' => $ant,
                        'status_novo'    => $novo,
                        'ator_id'        => $analistaId,
                        'observacao'     => 'Transição de teste (seed W13).',
                        'ocorrido_em'    => gmdate('Y-m-d H:i:s', $ts),
                    ], ['%d', '%s', '%s', '%d', '%s', '%s']);
                }
            }

            // pi_analises para agentes deferidos/indeferidos.
            if (in_array($def['status'], ['deferido', 'indeferido_aguardando_recurso'], true)) {
                $decisao = $def['status'] === 'deferido' ? 'deferimento' : 'indeferimento';
                $wpdb->insert("{$prefix}pi_analises", [
                    'agente_id'       => $agenteId,
                    'analista_id'     => $analistaId,
                    'decisao'         => $decisao,
                    'parecer_md'      => "Parecer de {$decisao} — análise de teste gerada pelo seed W13.",
                    'fundamentacao_md' => 'Documentação verificada conforme edital e Portaria 3230/2024.',
                    'decidido_em'     => $def['deferido_em'] ?? gmdate('Y-m-d H:i:s', $nowTs - 10 * DAY_IN_SECONDS),
                ], ['%d', '%d', '%s', '%s', '%s', '%s']);
            }

            // pi_consentimentos: 10 finalidades para deferidos.
            if ($def['status'] === 'deferido') {
                $finalidades = [
                    'identificacao', 'comunicacao', 'mapeamento', 'reconhecimento_pct',
                    'votacao', 'candidatura', 'dados_sensiveis_genero',
                    'dados_sensiveis_orientacao', 'dados_sensiveis_saude', 'dados_sensiveis_raca',
                ];
                foreach ($finalidades as $finalidade) {
                    $wpdb->insert("{$prefix}pi_consentimentos", [
                        'agente_id'     => $agenteId,
                        'termo_id'      => 1,
                        'finalidade'    => $finalidade,
                        'status'        => 'aceito',
                        'ip_hash'       => hash('sha256', 'seed_ip_' . $agenteId . $finalidade),
                        'registrado_em' => $def['submetido_em'],
                    ], ['%d', '%d', '%s', '%s', '%s', '%s']);
                }
            }

            // pi_documentos: 2 por agente deferido.
            if ($def['status'] === 'deferido') {
                $docTipos = $def['tipo'] === 'PF' ? [1, 7] : ($def['tipo'] === 'OR' ? [4, 5] : [9, 10]);
                foreach ($docTipos as $tipoDocId) {
                    $wpdb->insert("{$prefix}pi_documentos", [
                        'agente_id'        => $agenteId,
                        'tipo_documento_id' => $tipoDocId,
                        'arquivo_path'     => "participe-ibram-private/seed/agente_{$agenteId}_doc_{$tipoDocId}.pdf",
                        'nome_original'    => "documento_tipo_{$tipoDocId}_seed.pdf",
                        'mime_real'        => 'application/pdf',
                        'tamanho_bytes'    => 102400,
                        'hash_sha256'      => hash('sha256', "seed_doc_{$agenteId}_{$tipoDocId}"),
                        'uploaded_by'      => $userIds['teste_analista'] ?: get_current_user_id(),
                        'uploaded_at'      => $now,
                        'validado'         => 1,
                        'validado_em'      => $now,
                        'validado_por'     => $userIds['teste_analista'] ?: get_current_user_id(),
                    ], ['%d', '%d', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%d', '%s', '%d']);
                }
            }
        }

        return [
            'count'      => $count,
            'ids'        => $ids,
            'analista_id' => $analistaId,
            'extra_ids'  => $userIds,
        ];
    }

    // -----------------------------------------------------------------------
    // seed: Editais
    // -----------------------------------------------------------------------

    /**
     * Cria 2 editais ativos (PUBLICADO + VOTACAO_ABERTA) e 1 encerrado.
     * Idempotente: apaga editais com título contendo '[SEED]'.
     *
     * @param array<string,int> $userIds
     * @param string[]          $warnings
     * @return array{count:int, edital_a_id:int, edital_b_id:int, edital_enc_id:int, cat_a:int[], cat_b:int[]}
     */
    private function seedEditais(array $userIds, array &$warnings): array
    {
        global $wpdb;
        $prefix   = $wpdb->prefix;
        $now      = current_time('mysql');
        $nowTs    = current_time('timestamp');
        $gestorId = $userIds['teste_gestor_edital'] ?: get_current_user_id();
        $count    = 0;

        // Apaga editais seed anteriores.
        $wpdb->query("DELETE FROM `{$prefix}pi_editais` WHERE titulo LIKE '%[SEED]%'");

        // ─── Edital A — inscricoes_abertas ───────────────────────────────
        $wpdb->insert("{$prefix}pi_editais", [
            'titulo'                     => 'CCDEM 2026 — Chamada Pública [SEED]',
            'descricao_md'               => 'Edital de teste para o CCDEM 2026. Gerado pelo seed W13.',
            'status'                     => 'inscricoes_abertas',
            'abertura'                   => gmdate('Y-m-d H:i:s', $nowTs - 7 * DAY_IN_SECONDS),
            'encerramento_inscricoes'    => gmdate('Y-m-d H:i:s', $nowTs + 7 * DAY_IN_SECONDS),
            'publicacao_habilitacao'     => gmdate('Y-m-d H:i:s', $nowTs + 14 * DAY_IN_SECONDS),
            'prazo_recurso_inabilitacao' => gmdate('Y-m-d H:i:s', $nowTs + 17 * DAY_IN_SECONDS),
            'abertura_votacao'           => gmdate('Y-m-d H:i:s', $nowTs + 20 * DAY_IN_SECONDS),
            'encerramento_votacao'       => gmdate('Y-m-d H:i:s', $nowTs + 25 * DAY_IN_SECONDS),
            'publicacao_resultado'       => gmdate('Y-m-d H:i:s', $nowTs + 28 * DAY_IN_SECONDS),
            'criado_por'                 => $gestorId,
            'created_at'                 => $now,
            'updated_at'                 => $now,
        ]);
        $editalAId = (int) $wpdb->insert_id;
        if ($editalAId > 0) {
            $count++;
        } else {
            $warnings[] = 'Edital A nao criado: ' . $wpdb->last_error;
        }

        // Categorias Edital A (4).
        $catA = [];
        if ($editalAId > 0) {
            $wpdb->query("DELETE FROM `{$prefix}pi_edital_categorias` WHERE edital_id = {$editalAId}");
            foreach ([
                ['Sociedade Civil — PF', 'PF', 3, 1],
                ['Organizações Museológicas', 'OR', 3, 1],
                ['Sistemas e Secretarias Municipais', 'SM', 2, 1],
                ['Representação Regional — Norte/Nordeste', 'PF,OR', 2, 0],
            ] as [$nome, $tipos, $vagas, $suplentes]) {
                $wpdb->insert("{$prefix}pi_edital_categorias", [
                    'edital_id'             => $editalAId,
                    'nome'                  => $nome,
                    'tipos_agente_elegivel' => $tipos,
                    'num_vagas'             => $vagas,
                    'num_suplentes'         => $suplentes,
                    'ordem'                 => count($catA) + 1,
                ], ['%d', '%s', '%s', '%d', '%d', '%d']);
                $catA[] = (int) $wpdb->insert_id;
            }
        }

        // ─── Edital B — votacao_aberta ────────────────────────────────────
        $wpdb->insert("{$prefix}pi_editais", [
            'titulo'                     => 'CCDEM 2025 — Renovação de Mandato [SEED]',
            'descricao_md'               => 'Edital de renovação de mandato em fase de votação. Seed W13.',
            'status'                     => 'votacao_aberta',
            'abertura'                   => gmdate('Y-m-d H:i:s', $nowTs - 40 * DAY_IN_SECONDS),
            'encerramento_inscricoes'    => gmdate('Y-m-d H:i:s', $nowTs - 20 * DAY_IN_SECONDS),
            'publicacao_habilitacao'     => gmdate('Y-m-d H:i:s', $nowTs - 10 * DAY_IN_SECONDS),
            'prazo_recurso_inabilitacao' => gmdate('Y-m-d H:i:s', $nowTs - 7 * DAY_IN_SECONDS),
            'abertura_votacao'           => gmdate('Y-m-d H:i:s', $nowTs - 3 * DAY_IN_SECONDS),
            'encerramento_votacao'       => gmdate('Y-m-d H:i:s', $nowTs + 4 * DAY_IN_SECONDS),
            'publicacao_resultado'       => gmdate('Y-m-d H:i:s', $nowTs + 7 * DAY_IN_SECONDS),
            'criado_por'                 => $gestorId,
            'created_at'                 => $now,
            'updated_at'                 => $now,
        ]);
        $editalBId = (int) $wpdb->insert_id;
        if ($editalBId > 0) {
            $count++;
        } else {
            $warnings[] = 'Edital B nao criado: ' . $wpdb->last_error;
        }

        // Categorias Edital B (2).
        $catB = [];
        if ($editalBId > 0) {
            $wpdb->query("DELETE FROM `{$prefix}pi_edital_categorias` WHERE edital_id = {$editalBId}");
            foreach ([
                ['Sociedade Civil', 'PF,OR', 4, 2],
                ['Poder Público', 'SM', 2, 1],
            ] as [$nome, $tipos, $vagas, $suplentes]) {
                $wpdb->insert("{$prefix}pi_edital_categorias", [
                    'edital_id'             => $editalBId,
                    'nome'                  => $nome,
                    'tipos_agente_elegivel' => $tipos,
                    'num_vagas'             => $vagas,
                    'num_suplentes'         => $suplentes,
                    'ordem'                 => count($catB) + 1,
                ], ['%d', '%s', '%s', '%d', '%d', '%d']);
                $catB[] = (int) $wpdb->insert_id;
            }
        }

        // ─── Edital C — encerrado (para resultados apurados) ─────────────
        $wpdb->insert("{$prefix}pi_editais", [
            'titulo'                     => 'CCDEM 2024 — Encerrado [SEED]',
            'descricao_md'               => 'Edital encerrado com resultado apurado. Seed W13.',
            'status'                     => 'encerrado',
            'abertura'                   => gmdate('Y-m-d H:i:s', $nowTs - 120 * DAY_IN_SECONDS),
            'encerramento_inscricoes'    => gmdate('Y-m-d H:i:s', $nowTs - 90 * DAY_IN_SECONDS),
            'publicacao_habilitacao'     => gmdate('Y-m-d H:i:s', $nowTs - 80 * DAY_IN_SECONDS),
            'prazo_recurso_inabilitacao' => gmdate('Y-m-d H:i:s', $nowTs - 75 * DAY_IN_SECONDS),
            'abertura_votacao'           => gmdate('Y-m-d H:i:s', $nowTs - 70 * DAY_IN_SECONDS),
            'encerramento_votacao'       => gmdate('Y-m-d H:i:s', $nowTs - 60 * DAY_IN_SECONDS),
            'publicacao_resultado'       => gmdate('Y-m-d H:i:s', $nowTs - 55 * DAY_IN_SECONDS),
            'criado_por'                 => $gestorId,
            'created_at'                 => $now,
            'updated_at'                 => $now,
        ]);
        $editalEncId = (int) $wpdb->insert_id;
        if ($editalEncId > 0) {
            $count++;
        }

        return [
            'count'        => $count,
            'edital_a_id'  => $editalAId,
            'edital_b_id'  => $editalBId,
            'edital_enc_id' => $editalEncId,
            'cat_a'        => $catA,
            'cat_b'        => $catB,
        ];
    }

    // -----------------------------------------------------------------------
    // seed: Inscrições
    // -----------------------------------------------------------------------

    /**
     * Cria inscrições realistas nos Editais A, B e Encerrado.
     * Edital A: 5 inscrições (3 habilitadas, 1 inabilitada+recurso, 1 inscrita).
     * Edital B: 10 inscrições final_habilitadas (base para votação).
     * Edital Enc: 10 inscrições final_habilitadas (base para resultado apurado).
     *
     * @param array{count:int, ids:array<string,int>, analista_id:int, extra_ids:array<string,int>} $seedData
     * @param array{count:int, edital_a_id:int, edital_b_id:int, edital_enc_id:int, cat_a:int[], cat_b:int[]} $editalData
     * @param string[] $warnings
     * @return array{count:int, insc_b:int[], insc_enc:int[], insc_a_inab_id:int}
     */
    private function seedInscricoes(array $seedData, array $editalData, array &$warnings): array
    {
        global $wpdb;
        $prefix  = $wpdb->prefix;
        $now     = current_time('mysql');
        $nowTs   = current_time('timestamp');
        $agenteIds = array_values($seedData['ids']);
        $count   = 0;
        $inscB   = [];
        $inscEnc = [];
        $inscAInabId = 0;

        $editalAId  = $editalData['edital_a_id'];
        $editalBId  = $editalData['edital_b_id'];
        $editalEncId = $editalData['edital_enc_id'];
        $catA = $editalData['cat_a'];
        $catB = $editalData['cat_b'];

        // Garante pelo menos uma categoria em cada edital para inserir.
        $catAFirst = !empty($catA) ? $catA[0] : 0;
        $catBFirst = !empty($catB) ? $catB[0] : 0;
        $catBSecond = isset($catB[1]) ? $catB[1] : $catBFirst;

        // ─── Edital A — 5 inscrições ──────────────────────────────────────
        if ($editalAId > 0 && $catAFirst > 0 && !empty($agenteIds)) {
            $wpdb->query("DELETE FROM `{$prefix}pi_inscricoes` WHERE edital_id = {$editalAId}");

            $incsA = [
                ['status' => 'habilitado',  'habilitado_em' => gmdate('Y-m-d H:i:s', $nowTs - 3 * DAY_IN_SECONDS)],
                ['status' => 'habilitado',  'habilitado_em' => gmdate('Y-m-d H:i:s', $nowTs - 3 * DAY_IN_SECONDS)],
                ['status' => 'habilitado',  'habilitado_em' => gmdate('Y-m-d H:i:s', $nowTs - 3 * DAY_IN_SECONDS)],
                ['status' => 'inabilitado', 'inabilitado_em' => gmdate('Y-m-d H:i:s', $nowTs - 3 * DAY_IN_SECONDS),
                 'motivo_inabilitacao_md' => 'Documentação incompleta — falta carta de apresentação.'],
                ['status' => 'inscrito', 'inscrito_em' => gmdate('Y-m-d H:i:s', $nowTs - 1 * DAY_IN_SECONDS)],
            ];

            foreach ($incsA as $i => $inc) {
                $agId = $agenteIds[$i % count($agenteIds)];
                $catId = isset($catA[$i % count($catA)]) ? $catA[$i % count($catA)] : $catAFirst;
                $row = [
                    'edital_id'   => $editalAId,
                    'categoria_id' => $catId,
                    'agente_id'   => $agId,
                    'status'      => $inc['status'],
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ];
                if (isset($inc['inscrito_em']))  {
                    $row['inscrito_em'] = $inc['inscrito_em'];
                }
                if (isset($inc['habilitado_em'])) {
                    $row['inscrito_em']  = $inc['habilitado_em'];
                    $row['habilitado_em'] = $inc['habilitado_em'];
                }
                if (isset($inc['inabilitado_em'])) {
                    $row['inscrito_em']   = $inc['inabilitado_em'];
                    $row['inabilitado_em'] = $inc['inabilitado_em'];
                    $row['motivo_inabilitacao_md'] = $inc['motivo_inabilitacao_md'];
                }
                $wpdb->insert("{$prefix}pi_inscricoes", $row);
                if ($wpdb->last_error) {
                    $warnings[] = "inscricao edital_a [{$i}]: " . $wpdb->last_error;
                    continue;
                }
                $inscId = (int) $wpdb->insert_id;
                $count++;

                // Recurso de inabilitação para a inscrição inabilitada.
                if ($inc['status'] === 'inabilitado') {
                    $inscAInabId = $inscId;
                    $wpdb->insert("{$prefix}pi_recursos_inabilitacao", [
                        'inscricao_id'    => $inscId,
                        'fundamentacao_md' => 'Recurso contra inabilitação — carta enviada anteriormente e não computada no sistema.',
                        'protocolado_em'  => gmdate('Y-m-d H:i:s', $nowTs - 1 * DAY_IN_SECONDS),
                    ], ['%d', '%s', '%s']);
                }
            }
        }

        // Pool de agentes seed (array_values garante índices 0..n sem gaps).
        $deferidos = array_values(array_filter($agenteIds));

        // ─── Edital B — 10 inscrições final_habilitadas ───────────────────
        if ($editalBId > 0 && $catBFirst > 0 && !empty($deferidos)) {
            $wpdb->query("DELETE FROM `{$prefix}pi_inscricoes` WHERE edital_id = {$editalBId}");

            // Dedup: unique key e (edital_id, categoria_id, agente_id).
            // Com poucos agentes deferidos, round-robin causaria duplicatas no
            // i-esimo loop. Pulamos tuplas ja vistas em vez de quebrar a query.
            $seen = [];
            for ($i = 0; $i < 10; $i++) {
                $agId  = $deferidos[$i % count($deferidos)];
                $catId = $i < 7 ? $catBFirst : $catBSecond;
                $key = $editalBId . '-' . $catId . '-' . $agId;
                if (isset($seen[$key])) {
                    continue; // ja inserimos esta combinacao
                }
                $seen[$key] = true;
                $wpdb->insert("{$prefix}pi_inscricoes", [
                    'edital_id'    => $editalBId,
                    'categoria_id' => $catId,
                    'agente_id'    => $agId,
                    'status'       => 'final_habilitado',
                    'inscrito_em'  => gmdate('Y-m-d H:i:s', $nowTs - 25 * DAY_IN_SECONDS),
                    'habilitado_em' => gmdate('Y-m-d H:i:s', $nowTs - 12 * DAY_IN_SECONDS),
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ]);
                if ($wpdb->last_error) {
                    $warnings[] = "inscricao edital_b [{$i}]: " . $wpdb->last_error;
                    continue;
                }
                $inscB[] = (int) $wpdb->insert_id;
                $count++;
            }
        }

        // ─── Edital Encerrado — 10 inscrições final_habilitadas ──────────
        if ($editalEncId > 0 && !empty($agenteIds)) {
            $catEnc = 0;
            // Cria uma categoria para o edital encerrado.
            $wpdb->query("DELETE FROM `{$prefix}pi_edital_categorias` WHERE edital_id = {$editalEncId}");
            $wpdb->insert("{$prefix}pi_edital_categorias", [
                'edital_id'             => $editalEncId,
                'nome'                  => 'Geral',
                'tipos_agente_elegivel' => 'PF,OR,SM',
                'num_vagas'             => 5,
                'num_suplentes'         => 2,
                'ordem'                 => 1,
            ], ['%d', '%s', '%s', '%d', '%d', '%d']);
            $catEnc = (int) $wpdb->insert_id;

            $wpdb->query("DELETE FROM `{$prefix}pi_inscricoes` WHERE edital_id = {$editalEncId}");
            $defsEnc = !empty($deferidos) ? $deferidos : $agenteIds;
            // Dedup por (edital, cat, agente).
            $seenEnc = [];
            for ($i = 0; $i < 10; $i++) {
                $agId = $defsEnc[$i % max(1, count($defsEnc))];
                $key = $editalEncId . '-' . $catEnc . '-' . $agId;
                if (isset($seenEnc[$key])) {
                    continue;
                }
                $seenEnc[$key] = true;
                $wpdb->insert("{$prefix}pi_inscricoes", [
                    'edital_id'    => $editalEncId,
                    'categoria_id' => $catEnc,
                    'agente_id'    => $agId,
                    'status'       => 'final_habilitado',
                    'inscrito_em'  => gmdate('Y-m-d H:i:s', $nowTs - 115 * DAY_IN_SECONDS),
                    'habilitado_em' => gmdate('Y-m-d H:i:s', $nowTs - 85 * DAY_IN_SECONDS),
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ]);
                if ($wpdb->last_error) {
                    $warnings[] = "inscricao edital_enc [{$i}]: " . $wpdb->last_error;
                    continue;
                }
                $inscEnc[] = (int) $wpdb->insert_id;
                $count++;
            }
        }

        return [
            'count'         => $count,
            'insc_b'        => $inscB,
            'insc_enc'      => $inscEnc,
            'insc_a_inab_id' => $inscAInabId,
        ];
    }

    // -----------------------------------------------------------------------
    // seed: Votos
    // -----------------------------------------------------------------------

    /**
     * Cria votações e votos para o Edital B (aberta, 30 votos seed) e
     * Edital Encerrado (apurada, 10 votos + resultados).
     *
     * @param array{count:int, insc_b:int[], insc_enc:int[], insc_a_inab_id:int} $inscData
     * @param array{count:int, edital_a_id:int, edital_b_id:int, edital_enc_id:int, cat_a:int[], cat_b:int[]} $editalData
     * @param string[] $warnings
     * @return int  total de votos inseridos
     */
    private function seedVotos(array $inscData, array $editalData, array &$warnings): int
    {
        global $wpdb;
        $prefix  = $wpdb->prefix;
        $now     = current_time('mysql');
        $nowTs   = current_time('timestamp');
        $total   = 0;

        $editalBId  = $editalData['edital_b_id'];
        $editalEncId = $editalData['edital_enc_id'];
        $catBFirst  = !empty($editalData['cat_b']) ? $editalData['cat_b'][0] : 0;
        $catBSecond = isset($editalData['cat_b'][1]) ? $editalData['cat_b'][1] : $catBFirst;
        $inscB   = $inscData['insc_b'];
        $inscEnc = $inscData['insc_enc'];

        // ─── Votação B — aberta ───────────────────────────────────────────
        if ($editalBId > 0 && !empty($inscB)) {
            $wpdb->query("DELETE FROM `{$prefix}pi_votacoes` WHERE edital_id = {$editalBId}");
            $wpdb->insert("{$prefix}pi_votacoes", [
                'edital_id'   => $editalBId,
                'abertura'    => gmdate('Y-m-d H:i:s', $nowTs - 3 * DAY_IN_SECONDS),
                'encerramento' => gmdate('Y-m-d H:i:s', $nowTs + 4 * DAY_IN_SECONDS),
                'status'      => 'aberta',
                'modo'        => 'por_categoria',
            ], ['%d', '%s', '%s', '%s', '%s']);
            $votacaoBId = (int) $wpdb->insert_id;

            if ($votacaoBId > 0 && $catBFirst > 0) {
                $wpdb->query("DELETE FROM `{$prefix}pi_votos` WHERE votacao_id = {$votacaoBId}");
                for ($v = 0; $v < 30; $v++) {
                    $candInscId = $inscB[$v % count($inscB)];
                    $catId      = $v < 20 ? $catBFirst : $catBSecond;
                    $eleitorHash = substr(
                        base64_encode(hash_hmac('sha256', "eleitor_{$v}_{$votacaoBId}", 'TEST_SEED', true)),
                        0, 64
                    );
                    $wpdb->insert("{$prefix}pi_votos", [
                        'votacao_id'             => $votacaoBId,
                        'categoria_id'           => $catId,
                        'eleitor_hash'           => $eleitorHash,
                        'candidato_inscricao_id' => $candInscId,
                        'votado_em'              => gmdate('Y-m-d H:i:s', $nowTs - rand(0, 3 * DAY_IN_SECONDS)),
                        'ip_hash'                => hash('sha256', "seed_ip_{$v}"),
                    ], ['%d', '%d', '%s', '%d', '%s', '%s']);
                    if (!$wpdb->last_error) {
                        $total++;
                    }
                }
            }
        }

        // ─── Votação Encerrada — apurada ──────────────────────────────────
        if ($editalEncId > 0 && !empty($inscEnc)) {
            $wpdb->query("DELETE FROM `{$prefix}pi_votacoes` WHERE edital_id = {$editalEncId}");
            $wpdb->insert("{$prefix}pi_votacoes", [
                'edital_id'    => $editalEncId,
                'abertura'     => gmdate('Y-m-d H:i:s', $nowTs - 70 * DAY_IN_SECONDS),
                'encerramento' => gmdate('Y-m-d H:i:s', $nowTs - 60 * DAY_IN_SECONDS),
                'status'       => 'apurada',
                'modo'         => 'por_categoria',
                'apurado_em'   => gmdate('Y-m-d H:i:s', $nowTs - 59 * DAY_IN_SECONDS),
            ], ['%d', '%s', '%s', '%s', '%s', '%s']);
            $votacaoEncId = (int) $wpdb->insert_id;

            // Busca a categoria do edital encerrado.
            $catEncId = (int) $wpdb->get_var(
                "SELECT id FROM `{$prefix}pi_edital_categorias` WHERE edital_id = {$editalEncId} LIMIT 1"
            );

            if ($votacaoEncId > 0 && $catEncId > 0) {
                $wpdb->query("DELETE FROM `{$prefix}pi_votos` WHERE votacao_id = {$votacaoEncId}");
                // Votos desiguais para criar ranking realista.
                $voteCounts = [5, 4, 3, 3, 2, 2, 2, 1, 1, 1];
                foreach ($inscEnc as $k => $inscId) {
                    $nv = $voteCounts[$k] ?? 1;
                    for ($v = 0; $v < $nv; $v++) {
                        $eleitorHash = substr(
                            base64_encode(hash_hmac('sha256', "enc_eleitor_{$k}_{$v}_{$votacaoEncId}", 'TEST_SEED', true)),
                            0, 64
                        );
                        $wpdb->insert("{$prefix}pi_votos", [
                            'votacao_id'             => $votacaoEncId,
                            'categoria_id'           => $catEncId,
                            'eleitor_hash'           => $eleitorHash,
                            'candidato_inscricao_id' => $inscId,
                            'votado_em'              => gmdate('Y-m-d H:i:s', $nowTs - 65 * DAY_IN_SECONDS),
                            'ip_hash'                => hash('sha256', "seed_enc_ip_{$k}_{$v}"),
                        ], ['%d', '%d', '%s', '%d', '%s', '%s']);
                        if (!$wpdb->last_error) {
                            $total++;
                        }
                    }
                }

                // pi_resultados apurados para a votação encerrada.
                $wpdb->query("DELETE FROM `{$prefix}pi_resultados` WHERE votacao_id = {$votacaoEncId}");
                arsort($voteCounts);
                $pos = 1;
                $numVagas = 5;
                $numSuplentes = 2;
                foreach ($inscEnc as $k => $inscId) {
                    $wpdb->insert("{$prefix}pi_resultados", [
                        'votacao_id'             => $votacaoEncId,
                        'categoria_id'           => $catEncId,
                        'candidato_inscricao_id' => $inscId,
                        'total_votos'            => $voteCounts[$k] ?? 1,
                        'posicao'                => $pos,
                        'eleito'                 => $pos <= $numVagas ? 1 : 0,
                        'suplente'               => ($pos > $numVagas && $pos <= $numVagas + $numSuplentes) ? 1 : 0,
                        'apurado_em'             => gmdate('Y-m-d H:i:s', $nowTs - 59 * DAY_IN_SECONDS),
                    ], ['%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s']);
                    $pos++;
                }
            }
        }

        return $total;
    }

    // -----------------------------------------------------------------------
    // seed: Recursos
    // -----------------------------------------------------------------------

    /**
     * Cria 3 recursos: 1 retratação aberto (PF indeferido), 1 presidência decidido
     * (provido), 1 inabilitação aberto.
     *
     * @param array{count:int, ids:array<string,int>, analista_id:int, extra_ids:array<string,int>} $seedData
     * @param array{count:int, insc_b:int[], insc_enc:int[], insc_a_inab_id:int} $inscData
     * @param string[] $warnings
     * @return int
     */
    private function seedRecursos(array $seedData, array $inscData, array &$warnings): int
    {
        global $wpdb;
        $prefix      = $wpdb->prefix;
        $now         = current_time('mysql');
        $nowTs       = current_time('timestamp');
        $analistaId  = $seedData['analista_id'];
        $presidId    = $seedData['extra_ids']['teste_presidencia'] ?: get_current_user_id();
        $count       = 0;

        // Busca a análise de indeferimento (pf5 = email pf5@seed.ibram.test).
        $analiseIndRef = (int) $wpdb->get_var(
            "SELECT a.id FROM `{$prefix}pi_analises` a
             INNER JOIN `{$prefix}pi_agentes` ag ON ag.id = a.agente_id
             WHERE ag.email_principal = 'pf5@seed.ibram.test'
             LIMIT 1"
        );

        // Busca qualquer análise de deferimento para recurso presidência.
        $analiseDef = (int) $wpdb->get_var(
            "SELECT id FROM `{$prefix}pi_analises` WHERE decisao = 'deferimento' LIMIT 1"
        );

        // ─── Recurso 1: retratação aberta (PF indeferido) ─────────────────
        if ($analiseIndRef > 0) {
            $pfIndId = (int) $wpdb->get_var(
                "SELECT agente_id FROM `{$prefix}pi_analises` WHERE id = {$analiseIndRef}"
            );
            $wpdb->insert("{$prefix}pi_recursos", [
                'analise_id'      => $analiseIndRef,
                'fase'            => 'retratacao',
                'recorrente_id'   => $pfIndId ?: $analistaId,
                'fundamentacao_md' => 'Recurso de retratação: documentação foi enviada por e-mail conforme protocolo e não foi considerada na análise.',
                'protocolado_em'  => gmdate('Y-m-d H:i:s', $nowTs - 8 * DAY_IN_SECONDS),
                'prazo_inicio'    => gmdate('Y-m-d H:i:s', $nowTs - 10 * DAY_IN_SECONDS),
                'prazo_fim'       => gmdate('Y-m-d H:i:s', $nowTs + 5 * DAY_IN_SECONDS),
            ], ['%d', '%s', '%d', '%s', '%s', '%s', '%s']);
            if (!$wpdb->last_error) {
                $count++;
            } else {
                $warnings[] = 'recurso retratacao: ' . $wpdb->last_error;
            }
        }

        // ─── Recurso 2: presidência decidido (provido) ────────────────────
        if ($analiseDef > 0) {
            $agDefId = (int) $wpdb->get_var(
                "SELECT agente_id FROM `{$prefix}pi_analises` WHERE id = {$analiseDef}"
            );
            $wpdb->insert("{$prefix}pi_recursos", [
                'analise_id'      => $analiseDef,
                'fase'            => 'presidencia',
                'recorrente_id'   => $agDefId ?: $analistaId,
                'fundamentacao_md' => 'Recurso à presidência contra deferimento com ressalva: mandato anterior não considerado na pontuação de experiência.',
                'protocolado_em'  => gmdate('Y-m-d H:i:s', $nowTs - 25 * DAY_IN_SECONDS),
                'prazo_inicio'    => gmdate('Y-m-d H:i:s', $nowTs - 28 * DAY_IN_SECONDS),
                'prazo_fim'       => gmdate('Y-m-d H:i:s', $nowTs - 20 * DAY_IN_SECONDS),
                'decisao'         => 'deferir',
                'decisor_id'      => $presidId,
                'decisao_md'      => 'Recurso provido. Mandato anterior comprovado via ofício de 2022.',
                'decidido_em'     => gmdate('Y-m-d H:i:s', $nowTs - 22 * DAY_IN_SECONDS),
            ], ['%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s']);
            if (!$wpdb->last_error) {
                $count++;
            } else {
                $warnings[] = 'recurso presidencia: ' . $wpdb->last_error;
            }
        }

        return $count;
    }

    // -----------------------------------------------------------------------
    // seed: Email Queue
    // -----------------------------------------------------------------------

    /**
     * Cria 10 registros na fila de e-mail (5 pendentes, 5 enviados).
     *
     * @param array{count:int, ids:array<string,int>, analista_id:int, extra_ids:array<string,int>} $seedData
     * @param string[] $warnings
     * @return int
     */
    private function seedEmailQueue(array $seedData, array &$warnings): int
    {
        global $wpdb;
        $prefix  = $wpdb->prefix;
        $now     = current_time('mysql');
        $nowTs   = current_time('timestamp');
        $count   = 0;
        $agenteIds = array_values($seedData['ids']);

        // Apaga seeds anteriores por domínio.
        $wpdb->query("DELETE FROM `{$prefix}pi_email_queue` WHERE destinatario LIKE '%@seed.ibram.test'");

        $emailDefs = [
            // Pendentes (5).
            ['status' => 'pendente', 'evento' => 'agente_deferido',
             'assunto' => '[Participe Ibram] Cadastro deferido — PI-PF-2026-000001'],
            ['status' => 'pendente', 'evento' => 'agente_em_analise',
             'assunto' => '[Participe Ibram] Cadastro em análise'],
            ['status' => 'pendente', 'evento' => 'inscricao_habilitada',
             'assunto' => '[Participe Ibram] Inscrição habilitada — CCDEM 2026'],
            ['status' => 'pendente', 'evento' => 'votacao_aberta',
             'assunto' => '[Participe Ibram] Votação aberta — CCDEM 2025'],
            ['status' => 'pendente', 'evento' => 'recurso_protocolado',
             'assunto' => '[Participe Ibram] Recurso de retratação protocolado'],
            // Enviados (5).
            ['status' => 'enviado', 'evento' => 'agente_deferido',
             'assunto' => '[Participe Ibram] Cadastro deferido — PI-PF-2026-000002',
             'enviado_em' => gmdate('Y-m-d H:i:s', $nowTs - 18 * DAY_IN_SECONDS)],
            ['status' => 'enviado', 'evento' => 'agente_deferido',
             'assunto' => '[Participe Ibram] Cadastro deferido — PI-OR-2026-000001',
             'enviado_em' => gmdate('Y-m-d H:i:s', $nowTs - 16 * DAY_IN_SECONDS)],
            ['status' => 'enviado', 'evento' => 'edital_publicado',
             'assunto' => '[Participe Ibram] Edital CCDEM 2026 publicado',
             'enviado_em' => gmdate('Y-m-d H:i:s', $nowTs - 7 * DAY_IN_SECONDS)],
            ['status' => 'enviado', 'evento' => 'inscricao_inabilitada',
             'assunto' => '[Participe Ibram] Inscrição inabilitada — prazo recurso: 3 dias',
             'enviado_em' => gmdate('Y-m-d H:i:s', $nowTs - 2 * DAY_IN_SECONDS)],
            ['status' => 'enviado', 'evento' => 'lgpd_solicitacao_atendida',
             'assunto' => '[Participe Ibram] Solicitação LGPD atendida',
             'enviado_em' => gmdate('Y-m-d H:i:s', $nowTs - 5 * DAY_IN_SECONDS)],
        ];

        foreach ($emailDefs as $i => $def) {
            $agId = !empty($agenteIds) ? $agenteIds[$i % count($agenteIds)] : null;
            $row  = [
                'evento'        => $def['evento'],
                'agente_id'     => $agId,
                'destinatario'  => "notif{$i}@seed.ibram.test",
                'assunto'       => $def['assunto'],
                'corpo_html'    => "<p>E-mail de teste (seed W13) — evento: {$def['evento']}.</p>",
                'tentativas'    => $def['status'] === 'enviado' ? 1 : 0,
                'status'        => $def['status'],
                'agendado_para' => $def['status'] === 'enviado'
                    ? $def['enviado_em']
                    : gmdate('Y-m-d H:i:s', $nowTs + rand(0, 3600)),
                'enviado_em'    => $def['enviado_em'] ?? null,
                'created_at'    => $now,
            ];
            $fmts = ['%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s'];
            $wpdb->insert("{$prefix}pi_email_queue", $row, $fmts);
            if (!$wpdb->last_error) {
                $count++;
            } else {
                $warnings[] = "email_queue [{$i}]: " . $wpdb->last_error;
            }
        }

        return $count;
    }

    // -----------------------------------------------------------------------
    // seed: Solicitações LGPD (pi_solicitacoes_titular)
    // -----------------------------------------------------------------------

    /**
     * Cria 3 solicitações LGPD: 1 aberta, 1 atendida, 1 negada.
     *
     * @param array{count:int, ids:array<string,int>, analista_id:int, extra_ids:array<string,int>} $seedData
     * @param string[] $warnings
     * @return int
     */
    private function seedLgpdSolicitacoes(array $seedData, array &$warnings): int
    {
        global $wpdb;
        $prefix   = $wpdb->prefix;
        $now      = current_time('mysql');
        $nowTs    = current_time('timestamp');
        $dpoId    = $seedData['extra_ids']['teste_dpo'] ?: get_current_user_id();
        $count    = 0;
        $agenteIds = array_values($seedData['ids']);

        // pi_solicitacoes_titular (não pi_lgpd_solicitacoes).
        $table = "{$prefix}pi_solicitacoes_titular";
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            $warnings[] = 'pi_solicitacoes_titular nao encontrada — skip LGPD';
            return 0;
        }

        // Apaga seeds anteriores pelo agente_id dos agentes seed.
        if (!empty($agenteIds)) {
            $placeholders = implode(',', array_fill(0, count($agenteIds), '%d'));
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->query($wpdb->prepare(
                "DELETE FROM `{$table}` WHERE agente_id IN ({$placeholders})",
                $agenteIds
            ));
        }

        $agId0 = !empty($agenteIds) ? $agenteIds[0] : 0;
        $agId1 = isset($agenteIds[1]) ? $agenteIds[1] : $agId0;
        $agId2 = isset($agenteIds[2]) ? $agenteIds[2] : $agId0;

        $solicitacoes = [
            [
                'agente_id'    => $agId0,
                'tipo'         => 'acesso',
                'detalhes_md'  => 'Solicito acesso a todos os meus dados pessoais armazenados na plataforma.',
                'status'       => 'aberta',
                'protocolada_em' => $now,
            ],
            [
                'agente_id'    => $agId1,
                'tipo'         => 'retificacao',
                'detalhes_md'  => 'Solicito correção do nome social cadastrado incorretamente.',
                'status'       => 'atendida',
                'resposta_md'  => 'Nome social retificado conforme solicitação. Alteração registrada.',
                'protocolada_em' => gmdate('Y-m-d H:i:s', $nowTs - 10 * DAY_IN_SECONDS),
                'atendida_em'  => gmdate('Y-m-d H:i:s', $nowTs - 5 * DAY_IN_SECONDS),
                'atendida_por' => $dpoId,
            ],
            [
                'agente_id'    => $agId2,
                'tipo'         => 'anonimizacao',
                'detalhes_md'  => 'Solicito anonimização dos meus dados no histórico de votações.',
                'status'       => 'recusada',
                'resposta_md'  => 'Solicitação negada: dados de votação são necessários para cumprimento de obrigação legal (Portaria 3230/2024, Art. 18).',
                'protocolada_em' => gmdate('Y-m-d H:i:s', $nowTs - 20 * DAY_IN_SECONDS),
                'atendida_em'  => gmdate('Y-m-d H:i:s', $nowTs - 15 * DAY_IN_SECONDS),
                'atendida_por' => $dpoId,
            ],
        ];

        foreach ($solicitacoes as $sol) {
            if (empty($sol['agente_id'])) {
                continue;
            }
            $wpdb->insert($table, $sol);
            if (!$wpdb->last_error) {
                $count++;
            } else {
                $warnings[] = 'lgpd_sol: ' . $wpdb->last_error;
            }
        }

        return $count;
    }

    // -----------------------------------------------------------------------
    // seed: Audit Log
    // -----------------------------------------------------------------------

    /**
     * Cria 20+ eventos de auditoria realistas com atores variados e timestamps
     * distribuídos nos últimos 7 dias.
     *
     * @param array<string,int> $userIds
     * @param array{count:int, ids:array<string,int>, analista_id:int, extra_ids:array<string,int>} $seedData
     * @param string[] $warnings
     * @return int
     */
    private function seedAuditLog(array $userIds, array $seedData, array &$warnings): int
    {
        global $wpdb;
        $prefix  = $wpdb->prefix;
        $nowTs   = current_time('timestamp');
        $count   = 0;
        $agenteIds = array_values($seedData['ids']);

        $actors = array_filter([
            $userIds['teste_admin']        ?: 0,
            $userIds['teste_analista']     ?: 0,
            $userIds['teste_presidencia']  ?: 0,
            $userIds['teste_gestor_edital'] ?: 0,
            $userIds['teste_dpo']          ?: 0,
        ]);
        if (empty($actors)) {
            $actors = [get_current_user_id()];
        }

        $events = [
            ['agente',        $agenteIds[0] ?? 0, 'cadastro_submetido'],
            ['agente',        $agenteIds[1] ?? 0, 'cadastro_submetido'],
            ['agente',        $agenteIds[0] ?? 0, 'cadastro_em_analise'],
            ['agente',        $agenteIds[0] ?? 0, 'cadastro_deferido'],
            ['agente',        $agenteIds[1] ?? 0, 'cadastro_deferido'],
            ['agente',        $agenteIds[4] ?? 0, 'cadastro_indeferido'],
            ['agente',        $agenteIds[4] ?? 0, 'recurso_retratacao_protocolado'],
            ['edital',        0,                   'edital_publicado'],
            ['edital',        0,                   'inscricoes_abertas'],
            ['inscricao',     0,                   'inscricao_submetida'],
            ['inscricao',     0,                   'inscricao_habilitada'],
            ['inscricao',     0,                   'inscricao_inabilitada'],
            ['inscricao',     0,                   'recurso_inabilitacao_protocolado'],
            ['votacao',       0,                   'votacao_iniciada'],
            ['votacao',       0,                   'voto_registrado'],
            ['votacao',       0,                   'voto_registrado'],
            ['votacao',       0,                   'voto_registrado'],
            ['lgpd',          0,                   'solicitacao_criada'],
            ['lgpd',          0,                   'solicitacao_atendida'],
            ['lgpd',          0,                   'solicitacao_negada'],
            ['setup_teste',   0,                   'seed_w13_executado'],
        ];

        foreach ($events as $i => [$entidade, $entidadeId, $acao]) {
            $atorId = $actors[$i % count($actors)];
            $ts     = gmdate('Y-m-d H:i:s', $nowTs - rand(0, 7 * DAY_IN_SECONDS));

            $wpdb->insert("{$prefix}pi_audit_log", [
                'entidade'    => $entidade,
                'entidade_id' => $entidadeId > 0 ? $entidadeId : null,
                'acao'        => $acao,
                'ator_id'     => $atorId > 0 ? $atorId : null,
                'dados_depois' => wp_json_encode(['seed' => 'W13', 'seq' => $i + 1]),
                'ocorrido_em' => $ts,
            ], ['%s', '%d', '%s', '%d', '%s', '%s']);

            if (!$wpdb->last_error) {
                $count++;
            } else {
                $warnings[] = "audit_log [{$i}]: " . $wpdb->last_error;
            }
        }

        return $count;
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
