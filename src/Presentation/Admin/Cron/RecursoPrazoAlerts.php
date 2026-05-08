<?php
/**
 * Cron WP — alerta de prazos de Recursos vencendo / vencidos.
 *
 * Roda diariamente. Para cada recurso ainda não decidido com prazo em D+2
 * (warning) ou D+0 (vencido), dispara as actions
 * `pi_recurso_prazo_warning(int $recursoId, int $diasRestantes)` e
 * `pi_recurso_prazo_vencido(int $recursoId)`. W4-C escuta para email.
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\Cron
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\Cron;

use DateTimeImmutable;
use Ibram\ParticipeIbram\Domain\Analise\Recurso;
use Ibram\ParticipeIbram\Domain\Analise\RecursoRepository;

/**
 * Auto-agendamento idempotente:
 *  - hook `init` registra o schedule via `wp_schedule_event` se não estiver
 *    agendado (fallback caso o activation hook não tenha sido chamado).
 *  - hook `pi_recurso_prazo_check` é o evento WP-Cron que executa
 *    {@see runChecks()}.
 *
 * Janela de warning configurável via filtro `pi_recurso_warning_dias`
 * (default: 2).
 */
final class RecursoPrazoAlerts
{
    public const HOOK = 'pi_recurso_prazo_check';

    public const WARNING_DIAS_DEFAULT = 2;

    private RecursoRepository $recursos;

    public function __construct(RecursoRepository $recursos)
    {
        $this->recursos = $recursos;
    }

    /**
     * Registra hooks WP. Idempotente.
     */
    public function registerHooks(): void
    {
        if (!function_exists('add_action')) {
            return;
        }
        \add_action(self::HOOK, [$this, 'runChecks']);
        \add_action('init', [$this, 'maybeSchedule']);
    }

    /**
     * Agenda o evento se ainda não estiver agendado. Pode ser chamado tanto no
     * activation hook quanto em `init` (fallback).
     */
    public function maybeSchedule(): void
    {
        if (!function_exists('wp_next_scheduled') || !function_exists('wp_schedule_event')) {
            return;
        }
        if (\wp_next_scheduled(self::HOOK) === false) {
            \wp_schedule_event(time() + 60, 'daily', self::HOOK);
        }
    }

    /**
     * Cancela o agendamento. Use no deactivation hook.
     */
    public function unschedule(): void
    {
        if (!function_exists('wp_clear_scheduled_hook')) {
            return;
        }
        \wp_clear_scheduled_hook(self::HOOK);
    }

    /**
     * Execução do cron. Itera sobre os recursos e dispara as actions
     * apropriadas.
     */
    public function runChecks(?DateTimeImmutable $now = null): void
    {
        $now      = $now ?? new DateTimeImmutable('now');
        $warnDias = self::WARNING_DIAS_DEFAULT;
        if (function_exists('apply_filters')) {
            $filtered = \apply_filters('pi_recurso_warning_dias', $warnDias);
            if (is_int($filtered) && $filtered >= 0) {
                $warnDias = $filtered;
            }
        }

        // Busca todos os recursos com prazo dentro de D+warnDias (inclui vencidos
        // recentes em D-1 não cobertos, então fazemos uma segunda passada para
        // vencidos via janela maior).
        $candidatos = $this->recursos->findVencendoEm($warnDias);
        foreach ($candidatos as $recurso) {
            if ($recurso->isDecidido()) {
                continue;
            }
            $dias = $this->diasRestantes($recurso, $now);
            if ($dias < 0) {
                $this->fireVencido($recurso);
                continue;
            }
            if ($dias <= $warnDias) {
                $this->fireWarning($recurso, $dias);
            }
        }

        // Segunda passada — vencidos: usa findVencendoEm(0) que devolve apenas
        // os que ainda estão dentro da janela atual; varremos os com prazo
        // < now via filter de candidatos. Aqui invocamos novamente com 0.
        $hojeOuAntes = $this->recursos->findVencendoEm(0);
        foreach ($hojeOuAntes as $recurso) {
            if ($recurso->isDecidido()) {
                continue;
            }
            $dias = $this->diasRestantes($recurso, $now);
            if ($dias < 0) {
                $this->fireVencido($recurso);
            }
        }
    }

    private function fireWarning(Recurso $recurso, int $dias): void
    {
        if (!function_exists('do_action')) {
            return;
        }
        \do_action('pi_recurso_prazo_warning', (int) $recurso->id(), $dias);
    }

    private function fireVencido(Recurso $recurso): void
    {
        if (!function_exists('do_action')) {
            return;
        }
        \do_action('pi_recurso_prazo_vencido', (int) $recurso->id());
    }

    private function diasRestantes(Recurso $recurso, DateTimeImmutable $now): int
    {
        $diff = $now->diff($recurso->prazoFim());
        return (int) $diff->format('%r%a');
    }
}
