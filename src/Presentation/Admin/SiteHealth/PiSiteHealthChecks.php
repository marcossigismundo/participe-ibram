<?php
/**
 * Integração com WordPress Site Health — 12 checks do Participe Ibram.
 *
 * SEGURANÇA: nenhuma mensagem expõe caminhos absolutos do servidor,
 * valores de secrets, IDs internos ou conteúdo de variáveis de ambiente.
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\SiteHealth
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\SiteHealth;

use Ibram\ParticipeIbram\Application\Lgpd\Configuracao\DpoConfig;
use Ibram\ParticipeIbram\Core\Encryption\KeyManager;
use wpdb;

/**
 * Registra 12 testes de integridade no WordPress Site Health.
 *
 * Instanciar e chamar `register()` (ou deixar o hook `init` cuidar disso).
 */
final class PiSiteHealthChecks
{
    private KeyManager $keyManager;
    private wpdb $db;

    public function __construct(KeyManager $keyManager, ?wpdb $db = null)
    {
        $this->keyManager = $keyManager;

        if ($db !== null) {
            $this->db = $db;
        } elseif (isset($GLOBALS['wpdb']) && $GLOBALS['wpdb'] instanceof wpdb) {
            $this->db = $GLOBALS['wpdb'];
        }
    }

    /**
     * Registra o filtro que injeta os testes no Site Health.
     *
     * Deve ser chamado no hook `init`.
     */
    public function register(): void
    {
        add_filter('site_status_tests', [$this, 'addTests']);
    }

    /**
     * Adiciona os 12 checks ao array de testes do Site Health.
     *
     * @param array<string,array<string,mixed>> $tests
     * @return array<string,array<string,mixed>>
     */
    public function addTests(array $tests): array
    {
        // Direct tests (executados imediatamente)
        $tests['direct']['pi_check_php_version'] = [
            'label' => __('PHP 7.4 ou superior', 'participe-ibram'),
            'test'  => [$this, 'checkPhpVersion'],
        ];

        $tests['direct']['pi_check_sodium'] = [
            'label' => __('Extensão Sodium (libsodium)', 'participe-ibram'),
            'test'  => [$this, 'checkSodium'],
        ];

        $tests['direct']['pi_check_encryption_keys'] = [
            'label' => __('Chaves de criptografia configuradas', 'participe-ibram'),
            'test'  => [$this, 'checkEncryptionKeys'],
        ];

        $tests['direct']['pi_check_voting_secret'] = [
            'label' => __('Segredo de votação (PI_VOTING_SECRET)', 'participe-ibram'),
            'test'  => [$this, 'checkVotingSecret'],
        ];

        $tests['direct']['pi_check_unsubscribe_secret'] = [
            'label' => __('Segredo de descadastramento distinto (PI_UNSUBSCRIBE_SECRET)', 'participe-ibram'),
            'test'  => [$this, 'checkUnsubscribeSecret'],
        ];

        $tests['direct']['pi_check_dpo_email'] = [
            'label' => __('E-mail do DPO configurado', 'participe-ibram'),
            'test'  => [$this, 'checkDpoEmail'],
        ];

        $tests['direct']['pi_check_private_storage'] = [
            'label' => __('Diretório de armazenamento privado protegido', 'participe-ibram'),
            'test'  => [$this, 'checkPrivateStorage'],
        ];

        $tests['direct']['pi_check_cron_pi_email_queue_tick'] = [
            'label' => __('Cron: fila de e-mails agendada', 'participe-ibram'),
            'test'  => [$this, 'checkCronEmailQueueTick'],
        ];

        $tests['direct']['pi_check_cron_pi_dpo_alerts_check'] = [
            'label' => __('Cron: alertas do DPO agendados', 'participe-ibram'),
            'test'  => [$this, 'checkCronDpoAlertsCheck'],
        ];

        // Async tests (executados via AJAX em segundo plano)
        $tests['async']['pi_check_email_queue_health'] = [
            'label'             => __('Saúde da fila de e-mails', 'participe-ibram'),
            'test'              => 'pi_check_email_queue_health',
            'has_rest'          => false,
            'async_direct_test' => [$this, 'checkEmailQueueHealth'],
        ];

        $tests['async']['pi_check_audit_log_size'] = [
            'label'             => __('Tamanho do audit log', 'participe-ibram'),
            'test'              => 'pi_check_audit_log_size',
            'has_rest'          => false,
            'async_direct_test' => [$this, 'checkAuditLogSize'],
        ];

        $tests['async']['pi_check_migrations'] = [
            'label'             => __('Migrations do banco de dados sincronizadas', 'participe-ibram'),
            'test'              => 'pi_check_migrations',
            'has_rest'          => false,
            'async_direct_test' => [$this, 'checkMigrations'],
        ];

        return $tests;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 1. PHP Version
    // ─────────────────────────────────────────────────────────────────────────

    /** @return array<string,mixed> */
    public function checkPhpVersion(): array
    {
        $ajudaUrl = admin_url('admin.php?page=participe-ibram_ajuda');

        if (version_compare(PHP_VERSION, '7.4', '>=')) {
            return [
                'label'       => __('PHP 7.4 ou superior está instalado', 'participe-ibram'),
                'status'      => 'good',
                'badge'       => ['label' => __('Desempenho', 'participe-ibram'), 'color' => 'green'],
                'description' => '<p>' . esc_html__('A versão instalada do PHP é compatível com o Participe Ibram.', 'participe-ibram') . '</p>',
                'actions'     => '',
                'test'        => 'pi_check_php_version',
            ];
        }

        return [
            'label'       => __('PHP abaixo da versão mínima exigida', 'participe-ibram'),
            'status'      => 'critical',
            'badge'       => ['label' => __('Segurança', 'participe-ibram'), 'color' => 'red'],
            'description' => '<p>' . esc_html__(
                'O Participe Ibram requer PHP 7.4 ou superior para garantir o uso de criptografia segura (libsodium nativa) e tipagem forte. '
                . 'A versão atual não é suportada e pode expor dados sensíveis. '
                . 'Solicite ao administrador do servidor a atualização do PHP.',
                'participe-ibram'
            ) . '</p>',
            'actions'     => '<a href="' . esc_url($ajudaUrl) . '">' . esc_html__('Consulte a documentação de requisitos', 'participe-ibram') . '</a>',
            'test'        => 'pi_check_php_version',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. Sodium
    // ─────────────────────────────────────────────────────────────────────────

    /** @return array<string,mixed> */
    public function checkSodium(): array
    {
        $ajudaUrl = admin_url('admin.php?page=participe-ibram_ajuda');

        if (function_exists('sodium_crypto_secretbox')) {
            return [
                'label'       => __('Extensão Sodium disponível', 'participe-ibram'),
                'status'      => 'good',
                'badge'       => ['label' => __('Segurança', 'participe-ibram'), 'color' => 'green'],
                'description' => '<p>' . esc_html__('A extensão libsodium está ativa e pode ser usada para criptografia de dados pessoais.', 'participe-ibram') . '</p>',
                'actions'     => '',
                'test'        => 'pi_check_sodium',
            ];
        }

        return [
            'label'       => __('Extensão Sodium ausente', 'participe-ibram'),
            'status'      => 'critical',
            'badge'       => ['label' => __('Segurança', 'participe-ibram'), 'color' => 'red'],
            'description' => '<p>' . esc_html__(
                'A extensão libsodium (sodium_crypto_secretbox) é obrigatória para criptografar campos sensíveis de dados pessoais conforme a LGPD. '
                . 'Sem ela, o plugin não consegue proteger CPFs, endereços e outros dados dos agentes cadastrados. '
                . 'Ative a extensão php-sodium no servidor e reinicie o PHP-FPM ou Apache.',
                'participe-ibram'
            ) . '</p>',
            'actions'     => '<a href="' . esc_url($ajudaUrl) . '">' . esc_html__('Ver requisitos do servidor', 'participe-ibram') . '</a>',
            'test'        => 'pi_check_sodium',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. Encryption keys
    // ─────────────────────────────────────────────────────────────────────────

    /** @return array<string,mixed> */
    public function checkEncryptionKeys(): array
    {
        $ajudaUrl = admin_url('admin.php?page=participe-ibram_ajuda');
        $problems = $this->keyManager->verifyKeysConfigured();

        if (empty($problems)) {
            return [
                'label'       => __('Chaves de criptografia configuradas corretamente', 'participe-ibram'),
                'status'      => 'good',
                'badge'       => ['label' => __('Segurança', 'participe-ibram'), 'color' => 'green'],
                'description' => '<p>' . esc_html__('Todas as constantes de criptografia estão definidas e com o comprimento correto.', 'participe-ibram') . '</p>',
                'actions'     => '',
                'test'        => 'pi_check_encryption_keys',
            ];
        }

        // Extrair apenas os NOMES das constantes mencionadas — nunca os valores.
        $constantNames = [];
        foreach ($problems as $problem) {
            if (preg_match_all('/\b(PI_[A-Z0-9_]+)\b/', $problem, $m)) {
                $constantNames = array_merge($constantNames, $m[1]);
            }
        }
        $constantNames = array_unique($constantNames);
        $missingList   = implode(', ', array_map('esc_html', $constantNames));

        $description = '<p>' . esc_html__(
            'Uma ou mais constantes de criptografia necessárias não estão configuradas ou possuem comprimento inválido em wp-config.php. '
            . 'Isso impede a proteção de dados pessoais sensíveis dos agentes culturais. '
            . 'Adicione as constantes indicadas ao wp-config.php seguindo as instruções da documentação.',
            'participe-ibram'
        ) . '</p>';

        if ($missingList !== '') {
            $description .= '<p><strong>' . esc_html__('Constantes com problema:', 'participe-ibram') . '</strong> '
                . '<code>' . $missingList . '</code></p>';
        }

        return [
            'label'       => __('Chaves de criptografia incompletas ou inválidas', 'participe-ibram'),
            'status'      => 'recommended',
            'badge'       => ['label' => __('Segurança', 'participe-ibram'), 'color' => 'orange'],
            'description' => $description,
            'actions'     => '<a href="' . esc_url($ajudaUrl) . '">' . esc_html__('Ver instruções de configuração (wp-config)', 'participe-ibram') . '</a>',
            'test'        => 'pi_check_encryption_keys',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 4. Voting secret
    // ─────────────────────────────────────────────────────────────────────────

    /** @return array<string,mixed> */
    public function checkVotingSecret(): array
    {
        $ajudaUrl = admin_url('admin.php?page=participe-ibram_ajuda');
        $ok       = false;

        if (defined('PI_VOTING_SECRET')) {
            $decoded = base64_decode((string) PI_VOTING_SECRET, true);
            $ok      = ($decoded !== false && strlen($decoded) >= 32);
        }

        if ($ok) {
            return [
                'label'       => __('Segredo de votação configurado', 'participe-ibram'),
                'status'      => 'good',
                'badge'       => ['label' => __('Segurança', 'participe-ibram'), 'color' => 'green'],
                'description' => '<p>' . esc_html__('A constante PI_VOTING_SECRET está definida e possui comprimento adequado.', 'participe-ibram') . '</p>',
                'actions'     => '',
                'test'        => 'pi_check_voting_secret',
            ];
        }

        return [
            'label'       => __('Segredo de votação ausente ou insuficiente', 'participe-ibram'),
            'status'      => 'recommended',
            'badge'       => ['label' => __('Segurança', 'participe-ibram'), 'color' => 'orange'],
            'description' => '<p>' . esc_html__(
                'A constante PI_VOTING_SECRET não está definida ou o valor decodificado em base64 é menor que 32 bytes. '
                . 'Esse segredo é usado para assinar hashes de votação e garantir a integridade da apuração. '
                . 'Gere um valor seguro com php -r "echo base64_encode(random_bytes(32));" e adicione ao wp-config.php.',
                'participe-ibram'
            ) . '</p>',
            'actions'     => '<a href="' . esc_url($ajudaUrl) . '">' . esc_html__('Configurar PI_VOTING_SECRET', 'participe-ibram') . '</a>',
            'test'        => 'pi_check_voting_secret',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 5. Unsubscribe secret distinct from HMAC key
    // ─────────────────────────────────────────────────────────────────────────

    /** @return array<string,mixed> */
    public function checkUnsubscribeSecret(): array
    {
        $ajudaUrl = admin_url('admin.php?page=participe-ibram_ajuda');

        if (!defined('PI_UNSUBSCRIBE_SECRET')) {
            return [
                'label'       => __('PI_UNSUBSCRIBE_SECRET não definida', 'participe-ibram'),
                'status'      => 'recommended',
                'badge'       => ['label' => __('Segurança', 'participe-ibram'), 'color' => 'orange'],
                'description' => '<p>' . esc_html__(
                    'A constante PI_UNSUBSCRIBE_SECRET está ausente no wp-config.php. '
                    . 'Ela é usada para assinar links de descadastramento de e-mails transacionais, evitando que terceiros cancelem inscrições indevidamente. '
                    . 'Adicione-a com um valor independente de PI_HMAC_KEY.',
                    'participe-ibram'
                ) . '</p>',
                'actions'     => '<a href="' . esc_url($ajudaUrl) . '">' . esc_html__('Configurar PI_UNSUBSCRIBE_SECRET', 'participe-ibram') . '</a>',
                'test'        => 'pi_check_unsubscribe_secret',
            ];
        }

        // Comparar hashes para não expor valores
        $isDistinct = true;
        if (defined('PI_HMAC_KEY')) {
            $hashUnsub  = hash('sha256', (string) PI_UNSUBSCRIBE_SECRET);
            $hashHmac   = hash('sha256', (string) PI_HMAC_KEY);
            $isDistinct = !hash_equals($hashUnsub, $hashHmac);
        }

        if ($isDistinct) {
            return [
                'label'       => __('PI_UNSUBSCRIBE_SECRET distinta e configurada', 'participe-ibram'),
                'status'      => 'good',
                'badge'       => ['label' => __('Segurança', 'participe-ibram'), 'color' => 'green'],
                'description' => '<p>' . esc_html__('A constante PI_UNSUBSCRIBE_SECRET está definida e é diferente de PI_HMAC_KEY.', 'participe-ibram') . '</p>',
                'actions'     => '',
                'test'        => 'pi_check_unsubscribe_secret',
            ];
        }

        return [
            'label'       => __('PI_UNSUBSCRIBE_SECRET igual a PI_HMAC_KEY', 'participe-ibram'),
            'status'      => 'recommended',
            'badge'       => ['label' => __('Segurança', 'participe-ibram'), 'color' => 'orange'],
            'description' => '<p>' . esc_html__(
                'As constantes PI_UNSUBSCRIBE_SECRET e PI_HMAC_KEY possuem o mesmo valor. '
                . 'Reutilizar segredos em contextos diferentes reduz a segurança: se um segredo for comprometido, o outro também estará. '
                . 'Gere um valor independente para PI_UNSUBSCRIBE_SECRET.',
                'participe-ibram'
            ) . '</p>',
            'actions'     => '<a href="' . esc_url($ajudaUrl) . '">' . esc_html__('Ver boas práticas de segredo', 'participe-ibram') . '</a>',
            'test'        => 'pi_check_unsubscribe_secret',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 6. DPO email
    // ─────────────────────────────────────────────────────────────────────────

    /** @return array<string,mixed> */
    public function checkDpoEmail(): array
    {
        $ajudaUrl = admin_url('admin.php?page=participe-ibram_ajuda');
        $email    = DpoConfig::getEmail();

        if ($email !== null) {
            return [
                'label'       => __('E-mail do DPO configurado', 'participe-ibram'),
                'status'      => 'good',
                'badge'       => ['label' => __('LGPD', 'participe-ibram'), 'color' => 'blue'],
                'description' => '<p>' . esc_html__('O Encarregado de Proteção de Dados (DPO) possui e-mail válido registrado.', 'participe-ibram') . '</p>',
                'actions'     => '',
                'test'        => 'pi_check_dpo_email',
            ];
        }

        return [
            'label'       => __('E-mail do DPO não configurado', 'participe-ibram'),
            'status'      => 'recommended',
            'badge'       => ['label' => __('LGPD', 'participe-ibram'), 'color' => 'orange'],
            'description' => '<p>' . esc_html__(
                'O e-mail do Encarregado de Proteção de Dados (DPO) não está configurado ou é inválido. '
                . 'A LGPD (Art. 41) exige a indicação do encarregado e disponibilização de seus dados de contato. '
                . 'Configure o e-mail do DPO nas configurações LGPD do plugin.',
                'participe-ibram'
            ) . '</p>',
            'actions'     => '<a href="' . esc_url(admin_url('admin.php?page=participe-ibram_lgpd')) . '">'
                . esc_html__('Configurar DPO', 'participe-ibram') . '</a>',
            'test'        => 'pi_check_dpo_email',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 7. Email queue health (async)
    // ─────────────────────────────────────────────────────────────────────────

    /** @return array<string,mixed> */
    public function checkEmailQueueHealth(): array
    {
        $ajudaUrl = admin_url('admin.php?page=participe-ibram_ajuda');
        $count    = 0;

        if (isset($this->db)) {
            $table = $this->db->prefix . 'pi_email_queue';
            $sql   = $this->db->prepare(
                "SELECT COUNT(*) FROM `{$table}` WHERE status = %s AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
                'falhou'
            );
            /** @var string|null $result */
            $result = $this->db->get_var($sql);
            $count  = (int) ($result ?? 0);
        }

        if ($count === 0) {
            return [
                'label'       => __('Fila de e-mails sem falhas recentes', 'participe-ibram'),
                'status'      => 'good',
                'badge'       => ['label' => __('E-mail', 'participe-ibram'), 'color' => 'green'],
                'description' => '<p>' . esc_html__('Nenhum e-mail falhou nos últimos 7 dias.', 'participe-ibram') . '</p>',
                'actions'     => '',
                'test'        => 'pi_check_email_queue_health',
            ];
        }

        if ($count > 500) {
            return [
                'label'       => sprintf(
                    /* translators: %d: número de e-mails com falha */
                    _n(
                        '%d e-mail falhou nos últimos 7 dias (crítico)',
                        '%d e-mails falharam nos últimos 7 dias (crítico)',
                        $count,
                        'participe-ibram'
                    ),
                    $count
                ),
                'status'      => 'critical',
                'badge'       => ['label' => __('E-mail', 'participe-ibram'), 'color' => 'red'],
                'description' => '<p>' . esc_html__(
                    'Mais de 500 e-mails falharam nos últimos 7 dias, indicando uma falha sistêmica no provedor de envio. '
                    . 'Notificações de deferimento, indeferimento e solicitações LGPD podem não estar chegando aos destinatários. '
                    . 'Verifique as configurações SMTP e os logs do servidor de e-mail.',
                    'participe-ibram'
                ) . '</p>',
                'actions'     => '<a href="' . esc_url($ajudaUrl) . '">' . esc_html__('Ver diagnóstico de e-mail', 'participe-ibram') . '</a>',
                'test'        => 'pi_check_email_queue_health',
            ];
        }

        return [
            'label'       => sprintf(
                /* translators: %d: número de e-mails com falha */
                _n(
                    '%d e-mail falhou nos últimos 7 dias',
                    '%d e-mails falharam nos últimos 7 dias',
                    $count,
                    'participe-ibram'
                ),
                $count
            ),
            'status'      => 'recommended',
            'badge'       => ['label' => __('E-mail', 'participe-ibram'), 'color' => 'orange'],
            'description' => '<p>' . esc_html__(
                'Alguns e-mails não puderam ser entregues nos últimos 7 dias. '
                . 'Isso pode indicar problemas de configuração SMTP ou bloqueio por SPF/DKIM. '
                . 'Revise os logs da fila e as configurações do provedor de e-mail.',
                'participe-ibram'
            ) . '</p>',
            'actions'     => '<a href="' . esc_url($ajudaUrl) . '">' . esc_html__('Ver diagnóstico de e-mail', 'participe-ibram') . '</a>',
            'test'        => 'pi_check_email_queue_health',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 8. Audit log size (async)
    // ─────────────────────────────────────────────────────────────────────────

    /** @return array<string,mixed> */
    public function checkAuditLogSize(): array
    {
        $ajudaUrl  = admin_url('admin.php?page=participe-ibram_ajuda');
        $sizeBytes = 0;

        if (isset($this->db)) {
            $table = $this->db->prefix . 'pi_audit_log';
            $row   = $this->db->get_row(
                $this->db->prepare("SHOW TABLE STATUS LIKE %s", $table)
            );
            if ($row !== null) {
                $sizeBytes = (int) ($row->Data_length ?? 0) + (int) ($row->Index_length ?? 0);
            }
        }

        $sizeGb = $sizeBytes / (1024 ** 3);

        if ($sizeGb < 1.0) {
            return [
                'label'       => __('Audit log com tamanho adequado', 'participe-ibram'),
                'status'      => 'good',
                'badge'       => ['label' => __('Desempenho', 'participe-ibram'), 'color' => 'green'],
                'description' => '<p>' . esc_html__('O tamanho do audit log está dentro do limite recomendado.', 'participe-ibram') . '</p>',
                'actions'     => '',
                'test'        => 'pi_check_audit_log_size',
            ];
        }

        if ($sizeGb >= 5.0) {
            return [
                'label'       => __('Audit log muito grande — ação necessária', 'participe-ibram'),
                'status'      => 'critical',
                'badge'       => ['label' => __('Desempenho', 'participe-ibram'), 'color' => 'red'],
                'description' => '<p>' . esc_html__(
                    'O audit log ultrapassou 5 GB e pode estar causando lentidão nas consultas administrativas. '
                    . 'Registros de auditoria muito antigos podem ser arquivados externamente (nunca deletados) para preservar a trilha de conformidade. '
                    . 'Consulte a equipe técnica para planejar uma estratégia de retenção conforme a política de privacidade do Ibram.',
                    'participe-ibram'
                ) . '</p>',
                'actions'     => '<a href="' . esc_url($ajudaUrl) . '">' . esc_html__('Ver política de retenção de audit log', 'participe-ibram') . '</a>',
                'test'        => 'pi_check_audit_log_size',
            ];
        }

        return [
            'label'       => __('Audit log próximo do limite recomendado', 'participe-ibram'),
            'status'      => 'recommended',
            'badge'       => ['label' => __('Desempenho', 'participe-ibram'), 'color' => 'orange'],
            'description' => '<p>' . esc_html__(
                'O audit log ultrapassou 1 GB. Embora ainda funcional, tabelas muito grandes podem degradar o desempenho de consultas de auditoria. '
                . 'Avalie o arquivamento de registros mais antigos que o período de retenção definido na política de privacidade. '
                . 'Nunca exclua registros de auditoria sem aprovação formal do DPO.',
                'participe-ibram'
            ) . '</p>',
            'actions'     => '<a href="' . esc_url($ajudaUrl) . '">' . esc_html__('Ver política de retenção de audit log', 'participe-ibram') . '</a>',
            'test'        => 'pi_check_audit_log_size',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 9. Migrations (async)
    // ─────────────────────────────────────────────────────────────────────────

    /** @return array<string,mixed> */
    public function checkMigrations(): array
    {
        $ajudaUrl = admin_url('admin.php?page=participe-ibram_ajuda');

        // Contar arquivos de migration no filesystem (sem expor caminho absoluto)
        $migrationsDir  = plugin_dir_path(dirname(__DIR__, 3)) . 'migrations';
        $filesystemCount = 0;

        if (is_dir($migrationsDir)) {
            $files           = glob($migrationsDir . '/V*.sql') ?: [];
            $filesystemCount = count($files);
        }

        // Contar migrations registradas no banco
        $dbCount = 0;
        if (isset($this->db)) {
            $table   = $this->db->prefix . 'pi_migrations';
            $result  = $this->db->get_var("SELECT COUNT(*) FROM `{$table}`");
            $dbCount = (int) ($result ?? 0);
        }

        if ($filesystemCount === $dbCount) {
            return [
                'label'       => __('Migrations do banco de dados sincronizadas', 'participe-ibram'),
                'status'      => 'good',
                'badge'       => ['label' => __('Banco de dados', 'participe-ibram'), 'color' => 'green'],
                'description' => '<p>' . esc_html__('O número de arquivos de migration coincide com o registrado no banco de dados.', 'participe-ibram') . '</p>',
                'actions'     => '',
                'test'        => 'pi_check_migrations',
            ];
        }

        $diff = abs($filesystemCount - $dbCount);

        return [
            'label'       => sprintf(
                /* translators: %d: número de migrations divergentes */
                _n(
                    'Migrations divergentes: %d migration pendente ou não registrada',
                    'Migrations divergentes: %d migrations pendentes ou não registradas',
                    $diff,
                    'participe-ibram'
                ),
                $diff
            ),
            'status'      => 'critical',
            'badge'       => ['label' => __('Banco de dados', 'participe-ibram'), 'color' => 'red'],
            'description' => '<p>' . esc_html__(
                'O número de arquivos de migration SQL não coincide com o registrado na tabela de controle do banco de dados. '
                . 'Isso pode indicar que uma atualização do plugin não foi concluída corretamente ou que migrations foram aplicadas manualmente. '
                . 'Execute o processo de atualização do plugin ou revise manualmente a tabela de controle de migrations.',
                'participe-ibram'
            ) . '</p>',
            'actions'     => '<a href="' . esc_url($ajudaUrl) . '">' . esc_html__('Ver guia de atualização do plugin', 'participe-ibram') . '</a>',
            'test'        => 'pi_check_migrations',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 10. Private storage
    // ─────────────────────────────────────────────────────────────────────────

    /** @return array<string,mixed> */
    public function checkPrivateStorage(): array
    {
        $ajudaUrl   = admin_url('admin.php?page=participe-ibram_ajuda');
        $uploadDir  = wp_upload_dir();
        $privateDir = trailingslashit($uploadDir['basedir']) . 'participe-ibram-private';
        $htaccess   = $privateDir . '/.htaccess';

        $dirExists     = is_dir($privateDir);
        $htaccessOk    = false;

        if ($dirExists && is_readable($htaccess)) {
            $contents   = (string) file_get_contents($htaccess);
            $htaccessOk = (
                strpos($contents, 'Deny from all') !== false
                || strpos($contents, 'Require all denied') !== false
            );
        }

        if ($dirExists && $htaccessOk) {
            return [
                'label'       => __('Diretório privado protegido corretamente', 'participe-ibram'),
                'status'      => 'good',
                'badge'       => ['label' => __('Segurança', 'participe-ibram'), 'color' => 'green'],
                'description' => '<p>' . esc_html__(
                    'O diretório wp-content/uploads/participe-ibram-private/ existe e possui arquivo .htaccess bloqueando acesso direto.',
                    'participe-ibram'
                ) . '</p>',
                'actions'     => '',
                'test'        => 'pi_check_private_storage',
            ];
        }

        if (!$dirExists) {
            $description = '<p>' . esc_html__(
                'O diretório wp-content/uploads/participe-ibram-private/ não existe. '
                . 'Documentos sensíveis dos agentes culturais (comprovantes, declarações LGPD) precisam ser armazenados fora do acesso público. '
                . 'Ative o plugin novamente ou crie o diretório manualmente e adicione um arquivo .htaccess com "Deny from all".',
                'participe-ibram'
            ) . '</p>';
        } else {
            $description = '<p>' . esc_html__(
                'O diretório wp-content/uploads/participe-ibram-private/ existe, mas o arquivo .htaccess está ausente ou não bloqueia o acesso. '
                . 'Sem essa proteção, documentos sensíveis dos agentes culturais podem ser acessados diretamente pela internet. '
                . 'Adicione um arquivo .htaccess com o conteúdo "Deny from all" (Apache) ou "Require all denied" (Apache 2.4+).',
                'participe-ibram'
            ) . '</p>';
        }

        return [
            'label'       => __('Diretório de armazenamento privado sem proteção', 'participe-ibram'),
            'status'      => 'critical',
            'badge'       => ['label' => __('Segurança', 'participe-ibram'), 'color' => 'red'],
            'description' => $description,
            'actions'     => '<a href="' . esc_url($ajudaUrl) . '">' . esc_html__('Ver configuração de armazenamento seguro', 'participe-ibram') . '</a>',
            'test'        => 'pi_check_private_storage',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 11. Cron: pi_email_queue_tick
    // ─────────────────────────────────────────────────────────────────────────

    /** @return array<string,mixed> */
    public function checkCronEmailQueueTick(): array
    {
        $ajudaUrl  = admin_url('admin.php?page=participe-ibram_ajuda');
        $scheduled = wp_next_scheduled('pi_email_queue_tick');

        if ($scheduled !== false) {
            return [
                'label'       => __('Cron de fila de e-mails agendado', 'participe-ibram'),
                'status'      => 'good',
                'badge'       => ['label' => __('E-mail', 'participe-ibram'), 'color' => 'green'],
                'description' => '<p>' . esc_html__('O evento cron pi_email_queue_tick está agendado e processará a fila de e-mails automaticamente.', 'participe-ibram') . '</p>',
                'actions'     => '',
                'test'        => 'pi_check_cron_pi_email_queue_tick',
            ];
        }

        return [
            'label'       => __('Cron de fila de e-mails não agendado', 'participe-ibram'),
            'status'      => 'recommended',
            'badge'       => ['label' => __('E-mail', 'participe-ibram'), 'color' => 'orange'],
            'description' => '<p>' . esc_html__(
                'O evento cron pi_email_queue_tick não está agendado no WP-Cron. '
                . 'Sem ele, e-mails de notificação (deferimento, LGPD, solicitações de titulares) não serão enviados automaticamente. '
                . 'Desative e reative o plugin para recriar os eventos de cron, ou configure um cron real no servidor.',
                'participe-ibram'
            ) . '</p>',
            'actions'     => '<a href="' . esc_url($ajudaUrl) . '">' . esc_html__('Ver configuração de cron', 'participe-ibram') . '</a>',
            'test'        => 'pi_check_cron_pi_email_queue_tick',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 12. Cron: pi_dpo_alerts_check
    // ─────────────────────────────────────────────────────────────────────────

    /** @return array<string,mixed> */
    public function checkCronDpoAlertsCheck(): array
    {
        $ajudaUrl  = admin_url('admin.php?page=participe-ibram_ajuda');
        $scheduled = wp_next_scheduled('pi_dpo_alerts_check');

        if ($scheduled !== false) {
            return [
                'label'       => __('Cron de alertas do DPO agendado', 'participe-ibram'),
                'status'      => 'good',
                'badge'       => ['label' => __('LGPD', 'participe-ibram'), 'color' => 'green'],
                'description' => '<p>' . esc_html__('O evento cron pi_dpo_alerts_check está agendado e monitorará prazos de solicitações de titulares.', 'participe-ibram') . '</p>',
                'actions'     => '',
                'test'        => 'pi_check_cron_pi_dpo_alerts_check',
            ];
        }

        return [
            'label'       => __('Cron de alertas do DPO não agendado', 'participe-ibram'),
            'status'      => 'recommended',
            'badge'       => ['label' => __('LGPD', 'participe-ibram'), 'color' => 'orange'],
            'description' => '<p>' . esc_html__(
                'O evento cron pi_dpo_alerts_check não está agendado no WP-Cron. '
                . 'Sem ele, o DPO não receberá alertas automáticos sobre prazos vencendo de solicitações de titulares de dados (Art. 18 LGPD). '
                . 'Desative e reative o plugin para recriar os eventos de cron.',
                'participe-ibram'
            ) . '</p>',
            'actions'     => '<a href="' . esc_url($ajudaUrl) . '">' . esc_html__('Ver configuração de cron', 'participe-ibram') . '</a>',
            'test'        => 'pi_check_cron_pi_dpo_alerts_check',
        ];
    }
}
