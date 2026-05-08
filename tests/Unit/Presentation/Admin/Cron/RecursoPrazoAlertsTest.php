<?php
/**
 * Unit tests for {@see Ibram\ParticipeIbram\Presentation\Admin\Cron\RecursoPrazoAlerts}.
 *
 * D+2 dispara warning, D+0/D-1 dispara vencido. Recursos ja decididos sao
 * ignorados.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Presentation\Admin\Cron
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Presentation\Admin\Cron;

use DateTimeImmutable;
use Ibram\ParticipeIbram\Domain\Analise\Recurso;
use Ibram\ParticipeIbram\Domain\Analise\RecursoRepository;
use Ibram\ParticipeIbram\Presentation\Admin\Cron\RecursoPrazoAlerts;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

if (!function_exists('do_action')) {
    function do_action(string $hook, ...$args): void
    {
        $GLOBALS['__pi_test_actions'][] = ['hook' => $hook, 'args' => $args];
    }
}
if (!function_exists('apply_filters')) {
    /** @return mixed */
    function apply_filters(string $hook, $value, ...$args)
    {
        return $value;
    }
}
if (!function_exists('add_action')) {
    function add_action(string $hook, callable $callback, int $priority = 10, int $accepted = 1): bool
    {
        $GLOBALS['__pi_test_added_actions'][] = $hook;
        return true;
    }
}

/**
 * @covers \Ibram\ParticipeIbram\Presentation\Admin\Cron\RecursoPrazoAlerts
 */
final class RecursoPrazoAlertsTest extends TestCase
{
    /** @var RecursoRepository&MockObject */
    private $recursos;

    private RecursoPrazoAlerts $alerts;

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['__pi_test_actions'] = [];
        $this->recursos = $this->createMock(RecursoRepository::class);
        $this->alerts   = new RecursoPrazoAlerts($this->recursos);
    }

    public function test_d_mais_2_dispara_warning(): void
    {
        $now      = new DateTimeImmutable('2026-05-07 10:00:00');
        $recurso  = new Recurso(
            101,
            50,
            Recurso::FASE_RETRATACAO,
            7,
            'fund',
            new DateTimeImmutable('2026-05-01 10:00:00'),
            new DateTimeImmutable('2026-05-01 10:00:00'),
            new DateTimeImmutable('2026-05-09 10:00:00') // D+2 (relativo a now)
        );

        $this->recursos
            ->method('findVencendoEm')
            ->willReturnCallback(static function (int $dias) use ($recurso): array {
                return $dias >= 2 ? [$recurso] : [];
            });

        $this->alerts->runChecks($now);

        $warnings = $this->actionsByHook('pi_recurso_prazo_warning');
        $this->assertCount(1, $warnings, 'esperava 1 warning para recurso em D+2');
        $this->assertSame(101, $warnings[0]['args'][0]);
        $this->assertSame(2, $warnings[0]['args'][1]);

        $vencidos = $this->actionsByHook('pi_recurso_prazo_vencido');
        $this->assertCount(0, $vencidos, 'D+2 nao pode disparar vencido');
    }

    public function test_d_zero_dispara_vencido(): void
    {
        $now     = new DateTimeImmutable('2026-05-07 10:00:00');
        $recurso = new Recurso(
            202,
            51,
            Recurso::FASE_PRESIDENCIA,
            7,
            'f',
            new DateTimeImmutable('2026-04-25 10:00:00'),
            new DateTimeImmutable('2026-04-25 10:00:00'),
            new DateTimeImmutable('2026-05-06 10:00:00') // ja venceu (-1 dia em relacao a now)
        );

        $this->recursos
            ->method('findVencendoEm')
            ->willReturn([$recurso]);

        $this->alerts->runChecks($now);

        $vencidos = $this->actionsByHook('pi_recurso_prazo_vencido');
        $this->assertNotEmpty($vencidos);
        $this->assertSame(202, $vencidos[0]['args'][0]);
    }

    public function test_recurso_ja_decidido_e_ignorado(): void
    {
        $now     = new DateTimeImmutable('2026-05-07 10:00:00');
        $recurso = new Recurso(
            303,
            52,
            Recurso::FASE_RETRATACAO,
            7,
            'f',
            new DateTimeImmutable('2026-05-01 10:00:00'),
            new DateTimeImmutable('2026-05-01 10:00:00'),
            new DateTimeImmutable('2026-05-09 10:00:00'),
            Recurso::DECISAO_RECONSIDERAR,
            5,
            'decisao tomada anteriormente',
            new DateTimeImmutable('2026-05-06 10:00:00')
        );

        $this->recursos
            ->method('findVencendoEm')
            ->willReturn([$recurso]);

        $this->alerts->runChecks($now);

        $this->assertEmpty($this->actionsByHook('pi_recurso_prazo_warning'));
        $this->assertEmpty($this->actionsByHook('pi_recurso_prazo_vencido'));
    }

    /**
     * @return array<int,array{hook:string,args:array<int,mixed>}>
     */
    private function actionsByHook(string $hook): array
    {
        $out = [];
        foreach ((array) ($GLOBALS['__pi_test_actions'] ?? []) as $entry) {
            if (is_array($entry) && ($entry['hook'] ?? null) === $hook) {
                $out[] = $entry;
            }
        }
        return $out;
    }
}
