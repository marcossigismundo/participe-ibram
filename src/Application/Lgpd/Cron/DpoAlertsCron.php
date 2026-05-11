<?php
/**
 * Cron job diário para alertas DPO (LGPD Art. 18 + email health).
 *
 * @package Ibram\ParticipeIbram\Application\Lgpd\Cron
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Lgpd\Cron;

use DateTimeImmutable;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Core\Logger\SecureLogger;
use Ibram\ParticipeIbram\Domain\Consentimento\SolicitacaoTitularRepository;
use Throwable;

/**
 * Registra o cron `pi_dpo_alerts_check` (schedule diário).
 *
 * Executa três verificações:
 *
 * **a) Solicitações Art. 18 vencendo**
 *   - D+10 sem atendimento: dispara `pi_dpo_alerta_solicitacao(int $solicitacaoId, int $diasRestantes)`
 *   - D+15 sem atendimento (vencida): dispara `pi_dpo_solicitacao_vencida(int $solicitacaoId, int $diasAtraso)`
 *
 * **b) Email queue health**
 *   - Conta mensagens com `status='falhou'` desde o último check (option `pi_dpo_last_email_check`)
 *   - Se ≥ 10 falhas: dispara `pi_dpo_alerta_email_falhas(int $totalFalhas)`
 *
 * **c) Recursos com prazo vencendo (DPO recebe cópia agregada)**
 *   - Conta recursos pendentes com prazo ≤ 2 dias
 *   - Dispara `pi_dpo_alerta_recurso(int $recursoId, int $diasRestantes)` por recurso
 *
 * Idempotente: cada verificação é isolada em try/catch — falha em uma não
 * interrompe as demais. Todas as execuções são auditadas.
 */
final class DpoAlertsCron
{
    public const HOOK            = 'pi_dpo_alerts_check';
    public const SCHEDULE        = 'daily';
    public const EMAIL_CHECK_OPT = 'pi_dpo_last_email_check';

    /** Threshold de falhas para disparar alerta de email queue. */
    private const EMAIL_FAIL_THRESHOLD = 10;

    /** Aviso de prazo: D+10 = 5 dias antes do vencimento em 15. */
    private const AVISO_DIAS = 5;

    /** @var \wpdb */
    private $wpdb;

    private SolicitacaoTitularRepository $solicitacoes;
    private AuditLogger $audit;
    private SecureLogger $logger;
    private string $tEmailQueue;
    private string $tRecursos;

    public function __construct(
        $wpdb,
        SolicitacaoTitularRepository $solicitacoes,
        AuditLogger $audit,
        SecureLogger $logger,
        ?string $prefixOverride = null
    ) {
        $this->wpdb       = $wpdb;
        $this->solicitacoes = $solicitacoes;
        $this->audit      = $audit;
        $this->logger     = $logger;
        $prefix           = $prefixOverride
            ?? (isset($wpdb->prefix) && is_string($wpdb->prefix) ? $wpdb->prefix : 'wp_');
        $this->tEmailQueue = $prefix . 'pi_email_queue';
        $this->tRecursos   = $prefix . 'pi_recursos';
    }

    /**
     * Registra o schedule e o callback do cron. Chamar em `init`.
     */
    public function registerHooks(): void
    {
        if (!function_exists('add_action')) {
            return;
        }

        \add_action(self::HOOK, [$this, 'run']);

        if (\wp_next_scheduled(self::HOOK) === false) {
            \wp_schedule_event(time(), self::SCHEDULE, self::HOOK);
        }
    }

    /**
     * Remove o schedule. Chamar no deactivation hook do plugin.
     */
    public function unschedule(): void
    {
        if (!function_exists('wp_next_scheduled')) {
            return;
        }
        $timestamp = \wp_next_scheduled(self::HOOK);
        if ($timestamp !== false) {
            \wp_unschedule_event($timestamp, self::HOOK);
        }
    }

    /**
     * Callback do cron. Executa as três verificações de forma idempotente.
     */
    public function run(): void
    {
        $start = new DateTimeImmutable('now');

        $this->logger->info('dpo_cron.run.inicio', [
            'iniciado_em' => $start->format('Y-m-d H:i:s'),
        ]);

        $this->checkSolicitacoesArt18();
        $this->checkEmailQueueHealth();
        $this->checkRecursosPrazo();

        $this->audit->log(
            'dpo_cron',
            null,
            'executado',
            null,
            ['iniciado_em' => $start->format('Y-m-d H:i:s')]
        );

        $this->logger->info('dpo_cron.run.concluido', [
            'duracao_ms' => (int) ((microtime(true) - (float) $start->format('U.u')) * 1000),
        ]);
    }

    /* =====================================================================
     * Verificação a) Solicitações Art. 18
     * ===================================================================== */

    private function checkSolicitacoesArt18(): void
    {
        try {
            // Busca solicitações vencendo em AVISO_DIAS (D+10, ou seja 5 dias antes do vencimento D+15)
            $vencendo = $this->solicitacoes->findVencendoEmDias(self::AVISO_DIAS);
            $now      = new DateTimeImmutable('now');

            foreach ($vencendo as $sol) {
                try {
                    $protocolada = $sol->protocoladaEm();
                    $prazoFim    = $protocolada->modify('+' . \Ibram\ParticipeIbram\Domain\Consentimento\SolicitacaoTitular::PRAZO_DIAS . ' days');
                    $diffSecs    = $prazoFim->getTimestamp() - $now->getTimestamp();
                    $diasRestantes = (int) ceil($diffSecs / 86400);

                    if ($diasRestantes <= 0) {
                        // Vencida
                        $diasAtraso = abs($diasRestantes);
                        if (function_exists('do_action')) {
                            \do_action('pi_dpo_solicitacao_vencida', $sol->id(), $diasAtraso);
                        }
                    } else {
                        // Vencendo em breve
                        if (function_exists('do_action')) {
                            \do_action('pi_dpo_alerta_solicitacao', $sol->id(), $diasRestantes);
                        }
                    }
                } catch (Throwable $e) {
                    $this->logger->warning('dpo_cron.solicitacao.erro_individual', [
                        'solicitacao_id' => $sol->id(),
                        'erro'           => $e->getMessage(),
                    ]);
                }
            }
        } catch (Throwable $e) {
            $this->logger->error('dpo_cron.solicitacoes.erro', ['erro' => $e->getMessage()]);
        }
    }

    /* =====================================================================
     * Verificação b) Email queue health
     * ===================================================================== */

    private function checkEmailQueueHealth(): void
    {
        try {
            $lastCheck = (string) \get_option(self::EMAIL_CHECK_OPT, '1970-01-01 00:00:00');
            $now       = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');

            $sql = $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->tEmailQueue}
                 WHERE status = 'falhou'
                   AND updated_at >= %s",
                $lastCheck
            );
            $total = (int) $this->wpdb->get_var($sql);

            \update_option(self::EMAIL_CHECK_OPT, $now, false);

            $this->logger->info('dpo_cron.email_health', [
                'falhas_desde_ultimo_check' => $total,
                'ultimo_check'             => $lastCheck,
            ]);

            if ($total >= self::EMAIL_FAIL_THRESHOLD && function_exists('do_action')) {
                \do_action('pi_dpo_alerta_email_falhas', $total);
            }
        } catch (Throwable $e) {
            $this->logger->error('dpo_cron.email_health.erro', ['erro' => $e->getMessage()]);
        }
    }

    /* =====================================================================
     * Verificação c) Recursos com prazo vencendo
     * ===================================================================== */

    private function checkRecursosPrazo(): void
    {
        try {
            $sql = $this->wpdb->prepare(
                "SELECT id,
                        DATEDIFF(prazo_fim, NOW()) AS dias_restantes
                 FROM {$this->tRecursos}
                 WHERE decidido_em IS NULL
                   AND prazo_fim IS NOT NULL
                   AND DATEDIFF(prazo_fim, NOW()) <= %d
                   AND DATEDIFF(prazo_fim, NOW()) >= 0",
                2
            );
            $rows = $this->wpdb->get_results($sql, ARRAY_A);
            if (!is_array($rows)) {
                return;
            }
            foreach ($rows as $row) {
                try {
                    $recursoId     = (int) $row['id'];
                    $diasRestantes = (int) $row['dias_restantes'];
                    if (function_exists('do_action')) {
                        \do_action('pi_dpo_alerta_recurso', $recursoId, $diasRestantes);
                    }
                } catch (Throwable $e) {
                    $this->logger->warning('dpo_cron.recurso.erro_individual', [
                        'recurso_id' => $row['id'] ?? null,
                        'erro'       => $e->getMessage(),
                    ]);
                }
            }
        } catch (Throwable $e) {
            $this->logger->error('dpo_cron.recursos.erro', ['erro' => $e->getMessage()]);
        }
    }
}
