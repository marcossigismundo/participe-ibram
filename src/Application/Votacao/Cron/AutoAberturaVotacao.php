<?php
/**
 * Cron: abre automaticamente votações cujo `abertura` já chegou.
 *
 * Mirror exato de {@see AutoEncerramentoVotacao}, com as seguintes diferenças:
 *  - HOOK     = `pi_votacao_auto_abrir`
 *  - SQL      : `WHERE status = 'agendada' AND abertura <= NOW()`
 *  - Handler  : {@see AbrirVotacaoHandler}
 *  - Auditoria: `votacao_auto_abertura`
 *
 * @package Ibram\ParticipeIbram\Application\Votacao\Cron
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Votacao\Cron;

use DateTimeImmutable;
use DateTimeZone;
use Ibram\ParticipeIbram\Application\Votacao\AbrirVotacaoCommand;
use Ibram\ParticipeIbram\Application\Votacao\AbrirVotacaoHandler;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Domain\Votacao\IllegalStateTransition;
use Throwable;

/**
 * Roda a cada 10 minutos. Para cada votação agendada com `abertura <= now`:
 *  1. Invoca {@see AbrirVotacaoHandler} (transita agendada → aberta).
 *  2. Audita a abertura automática.
 *  3. Em falha (transição ilegal — race com abertura manual), loga e segue.
 */
final class AutoAberturaVotacao
{
    public const HOOK            = 'pi_votacao_auto_abrir';
    public const SCHEDULE        = 'pi_dezminutos';
    public const SCHEDULE_LABEL  = 'A cada 10 minutos (Participe Ibram)';

    /** @var \wpdb */
    private $wpdb;

    private string $tableName;

    private AbrirVotacaoHandler $abrirHandler;

    private AuditLogger $audit;

    /**
     * @var callable():DateTimeImmutable
     */
    private $clock;

    /**
     * @param \wpdb $wpdb
     */
    public function __construct(
        $wpdb,
        AbrirVotacaoHandler $abrirHandler,
        AuditLogger $audit,
        ?string $tableName = null,
        ?callable $clock = null
    ) {
        $this->wpdb         = $wpdb;
        $this->abrirHandler = $abrirHandler;
        $this->audit        = $audit;
        $prefix             = isset($wpdb->prefix) && is_string($wpdb->prefix) ? $wpdb->prefix : 'wp_';
        $this->tableName    = $tableName ?? ($prefix . 'pi_votacoes');
        $this->clock        = $clock ?? static fn (): DateTimeImmutable
            => new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    /**
     * Registra hooks WP. Idempotente.
     */
    public function registerHooks(): void
    {
        if (!function_exists('add_action')) {
            return;
        }
        \add_action(self::HOOK, [$this, 'run']);
        \add_action('init', [$this, 'maybeSchedule']);
        \add_filter('cron_schedules', [$this, 'registerScheduleInterval']);
    }

    /**
     * Adiciona o intervalo `pi_dezminutos` para WP-Cron (se ainda não existir).
     *
     * @param array<string,array<string,mixed>> $schedules
     *
     * @return array<string,array<string,mixed>>
     */
    public function registerScheduleInterval($schedules): array
    {
        if (!is_array($schedules)) {
            $schedules = [];
        }
        if (!isset($schedules[self::SCHEDULE])) {
            $schedules[self::SCHEDULE] = [
                'interval' => 600,
                'display'  => self::SCHEDULE_LABEL,
            ];
        }

        return $schedules;
    }

    /**
     * Agenda o evento se ainda não estiver agendado.
     */
    public function maybeSchedule(): void
    {
        if (!function_exists('wp_next_scheduled') || !function_exists('wp_schedule_event')) {
            return;
        }
        if (\wp_next_scheduled(self::HOOK) === false) {
            \wp_schedule_event(time() + 60, self::SCHEDULE, self::HOOK);
        }
    }

    /**
     * Cancela o agendamento. Use em deactivation hook.
     */
    public function unschedule(): void
    {
        if (!function_exists('wp_clear_scheduled_hook')) {
            return;
        }
        \wp_clear_scheduled_hook(self::HOOK);
    }

    /**
     * Executa o lote.
     */
    public function run(): void
    {
        $now      = ($this->clock)();
        $nowMysql = $now->format('Y-m-d H:i:s');

        $sql = $this->wpdb->prepare(
            "SELECT id FROM {$this->tableName}
             WHERE status = %s AND abertura <= %s
             ORDER BY id ASC
             LIMIT 50",
            'agendada',
            $nowMysql
        );

        $rows = $this->wpdb->get_col($sql);
        if (!is_array($rows) || count($rows) === 0) {
            return;
        }

        foreach ($rows as $rawId) {
            $id = (int) $rawId;
            if ($id <= 0) {
                continue;
            }

            try {
                $this->abrirHandler->handle(new AbrirVotacaoCommand($id, null));

                $this->audit->log(
                    'votacao',
                    $id,
                    'votacao_auto_abertura',
                    null,
                    [
                        'votacao_id'   => $id,
                        'motivo'       => 'cron_janela_iniciada',
                        'executado_em' => $nowMysql,
                    ],
                    null
                );
            } catch (IllegalStateTransition $e) {
                // Race com abertura manual — loga e continua.
                $this->audit->log(
                    'votacao',
                    $id,
                    'votacao_auto_abertura_skip',
                    null,
                    [
                        'votacao_id' => $id,
                        'motivo'     => 'transicao_invalida',
                        'detalhe'    => 'ja_aberta_ou_estado_terminal',
                    ],
                    null
                );
                continue;
            } catch (Throwable $e) {
                // Erro inesperado — loga e segue (não bloqueia o lote).
                $msg = $e->getMessage();
                if (function_exists('error_log')) {
                    \error_log('[participe-ibram][cron] auto_abrir votacao=' . $id . ': ' . $msg);
                }
                continue;
            }
        }
    }
}
