<?php
/**
 * Cron: encerra automaticamente votações cujo `encerramento` já passou.
 *
 * Crítico (TD-06):
 *  - Ao expirar a janela, a urna PRECISA ser encerrada — caso contrário, votos
 *    poderiam ser registrados depois do horário (a verificação `dentroDaJanela`
 *    no handler do voto já protege, mas o agregado deve refletir o estado real).
 *  - Encerrar gera o `hash_pre_apuracao` IMUTÁVEL — esse hash é a evidência de
 *    que o conjunto de votos contado no fim do dia é o mesmo conjunto que
 *    estava na urna no momento do encerramento.
 *  - Idempotente: se outro processo já encerrou, captura
 *    {@see IllegalStateTransition} e segue.
 *
 * @package Ibram\ParticipeIbram\Application\Votacao\Cron
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Votacao\Cron;

use DateTimeImmutable;
use DateTimeZone;
use Ibram\ParticipeIbram\Application\Votacao\EncerrarVotacaoCommand;
use Ibram\ParticipeIbram\Application\Votacao\EncerrarVotacaoHandler;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Domain\Votacao\IllegalStateTransition;
use Throwable;

/**
 * Roda a cada 10 minutos. Para cada votação aberta com `encerramento <= now`:
 *  1. Invoca {@see EncerrarVotacaoHandler} (calcula hash, transita estado).
 *  2. Audita o encerramento automático.
 *  3. Em falha (transição ilegal — geralmente race com encerramento manual),
 *     loga e segue. Não bloqueia o lote.
 */
final class AutoEncerramentoVotacao
{
    public const HOOK            = 'pi_votacao_auto_encerrar';
    public const SCHEDULE        = 'pi_dezminutos';
    public const SCHEDULE_LABEL  = 'A cada 10 minutos (Participe Ibram)';

    /** @var \wpdb */
    private $wpdb;

    private string $tableName;

    private EncerrarVotacaoHandler $encerrarHandler;

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
        EncerrarVotacaoHandler $encerrarHandler,
        AuditLogger $audit,
        ?string $tableName = null,
        ?callable $clock = null
    ) {
        $this->wpdb            = $wpdb;
        $this->encerrarHandler = $encerrarHandler;
        $this->audit           = $audit;
        $prefix                = isset($wpdb->prefix) && is_string($wpdb->prefix) ? $wpdb->prefix : 'wp_';
        $this->tableName       = $tableName ?? ($prefix . 'pi_votacoes');
        $this->clock           = $clock ?? static fn (): DateTimeImmutable
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
     * Adiciona o intervalo `pi_dezminutos` para WP-Cron.
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
             WHERE status = %s AND encerramento <= %s
             ORDER BY id ASC
             LIMIT 50",
            'aberta',
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
                $this->encerrarHandler->handle(new EncerrarVotacaoCommand($id, null));

                $this->audit->log(
                    'votacao',
                    $id,
                    'auto_encerrada',
                    null,
                    [
                        'votacao_id'  => $id,
                        'motivo'      => 'cron_janela_expirada',
                        'executado_em' => $nowMysql,
                    ],
                    null
                );
            } catch (IllegalStateTransition $e) {
                // Race com encerramento manual — loga e continua.
                $this->audit->log(
                    'votacao',
                    $id,
                    'auto_encerrada_skip',
                    null,
                    [
                        'votacao_id' => $id,
                        'motivo'     => 'transicao_invalida',
                        'detalhe'    => 'ja_encerrada_ou_estado_terminal',
                    ],
                    null
                );
                continue;
            } catch (Throwable $e) {
                // Erro inesperado — loga e segue (não bloqueia o lote).
                $msg = $e->getMessage();
                if (function_exists('error_log')) {
                    \error_log('[participe-ibram][cron] auto_encerrar votacao=' . $id . ': ' . $msg);
                }
                continue;
            }
        }
    }
}
