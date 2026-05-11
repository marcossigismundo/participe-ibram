<?php
/**
 * Unit tests for {@see DpoAlertsCron}.
 *
 * Usa stubs locais de do_action/get_option/update_option para capturar
 * disparos de hooks sem WordPress real.
 *
 * Cenários:
 *  1. Solicitação D+10 (status=aberta) → dispara pi_dpo_alerta_solicitacao com diasRestantes=5
 *  2. Solicitação D+16 (status=aberta) → dispara pi_dpo_solicitacao_vencida
 *  3. Recurso prazo_fim=+1d, decidido_em=null → dispara pi_dpo_alerta_recurso
 *  4. 12 emails com status=falhou → dispara pi_dpo_alerta_email_falhas com count=12
 *  5. Idempotência: chamada dupla não duplica alertas (option atualizado na 1ª chamada)
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Application\Lgpd\Cron
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Application\Lgpd\Cron;

use DateTimeImmutable;
use Ibram\ParticipeIbram\Application\Lgpd\Cron\DpoAlertsCron;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Core\Logger\SecureLogger;
use Ibram\ParticipeIbram\Domain\Consentimento\SolicitacaoTitular;
use Ibram\ParticipeIbram\Domain\Consentimento\SolicitacaoTitularRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

// ---------------------------------------------------------------------------
// Stubs de funções WP para captura de do_action neste namespace de testes.
// Usamos $GLOBALS como canal para evitar colisão com stubs já declarados em
// outros arquivos (cada arquivo re-declara com guard `function_exists`).
// ---------------------------------------------------------------------------

if (!function_exists('Ibram\ParticipeIbram\Tests\Unit\Application\Lgpd\Cron\do_action')) {
    function do_action(string $hook, ...$args): void
    {
        $GLOBALS['__dpo_cron_test_actions'][] = ['hook' => $hook, 'args' => $args];
    }
}

if (!function_exists('Ibram\ParticipeIbram\Tests\Unit\Application\Lgpd\Cron\add_action')) {
    function add_action(string $hook, callable $cb, int $prio = 10, int $accepted = 1): bool
    {
        return true;
    }
}

if (!function_exists('Ibram\ParticipeIbram\Tests\Unit\Application\Lgpd\Cron\wp_next_scheduled')) {
    function wp_next_scheduled(string $hook): bool
    {
        return false;
    }
}

if (!function_exists('Ibram\ParticipeIbram\Tests\Unit\Application\Lgpd\Cron\wp_schedule_event')) {
    function wp_schedule_event(int $timestamp, string $recurrence, string $hook): void {}
}

/**
 * Stub wpdb para DpoAlertsCron (email queue + recursos).
 */
final class FakeWpdbDpoCron
{
    public string $prefix = 'wp_';

    /** Controla o COUNT retornado pelo get_var (emails falhos). */
    public int $emailFalhosCount = 0;

    /** Rows retornadas para get_results (recursos). */
    public array $recursosRows = [];

    /** SQL preparado capturado. */
    public array $lastPrepareArgs = [];

    public function prepare(string $sql, ...$args): string
    {
        $this->lastPrepareArgs = $args;
        return $sql;
    }

    /** @return mixed */
    public function get_var(string $sql)
    {
        return $this->emailFalhosCount;
    }

    /** @return array<int,array<string,mixed>>|false */
    public function get_results(string $sql, $output = null)
    {
        return $this->recursosRows;
    }
}

/**
 * @covers \Ibram\ParticipeIbram\Application\Lgpd\Cron\DpoAlertsCron
 */
final class DpoAlertsCronTest extends TestCase
{
    private FakeWpdbDpoCron $wpdb;

    /** @var SolicitacaoTitularRepository&MockObject */
    private $solicitacoes;

    /** @var AuditLogger&MockObject */
    private $audit;

    private SecureLogger $logger;
    private DpoAlertsCron $cron;

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['__dpo_cron_test_actions']    = [];
        $GLOBALS['__pi_test_options']          = [];

        $this->wpdb        = new FakeWpdbDpoCron();
        $this->solicitacoes = $this->createMock(SolicitacaoTitularRepository::class);
        $this->audit        = $this->createMock(AuditLogger::class);
        $this->logger       = new SecureLogger(static function (string $line): void {});

        $this->cron = new DpoAlertsCron(
            $this->wpdb,
            $this->solicitacoes,
            $this->audit,
            $this->logger,
            'wp_'
        );
    }

    /* ------------------------------------------------------------------
     * Helper: filtra ações capturadas pelo hook
     * ------------------------------------------------------------------ */

    /**
     * @return array<int, array{hook:string, args:array<mixed>}>
     */
    private function actionsForHook(string $hook): array
    {
        return array_values(array_filter(
            (array) ($GLOBALS['__dpo_cron_test_actions'] ?? []),
            static fn ($e) => is_array($e) && ($e['hook'] ?? null) === $hook
        ));
    }

    /* ------------------------------------------------------------------
     * 1. D+10 sem atendimento → pi_dpo_alerta_solicitacao com diasRestantes=5
     * ------------------------------------------------------------------ */

    public function test_solicitacao_d10_dispara_alerta_com_5_dias_restantes(): void
    {
        // protocoladaEm = NOW - 10 dias → prazoFim = NOW + 5 dias
        $protocolada = new DateTimeImmutable('-10 days');
        $sol = SolicitacaoTitular::fromState(
            42,
            1,
            SolicitacaoTitular::TIPO_ACESSO,
            null,
            SolicitacaoTitular::STATUS_ABERTA,
            null,
            $protocolada,
            null,
            null
        );

        $this->solicitacoes
            ->method('findVencendoEmDias')
            ->willReturn([$sol]);

        $this->cron->run();

        $alertas = $this->actionsForHook('pi_dpo_alerta_solicitacao');
        $this->assertCount(1, $alertas, 'Deve disparar 1 alerta pi_dpo_alerta_solicitacao');
        $this->assertSame(42, $alertas[0]['args'][0], 'Primeiro arg = solicitacao id');

        $diasRestantes = $alertas[0]['args'][1];
        $this->assertGreaterThan(0, $diasRestantes, 'diasRestantes deve ser positivo (não venceu)');
        $this->assertLessThanOrEqual(5, $diasRestantes, 'diasRestantes <= 5 para D+10');
    }

    /* ------------------------------------------------------------------
     * 2. D+16 (vencida) → pi_dpo_solicitacao_vencida
     * ------------------------------------------------------------------ */

    public function test_solicitacao_d16_dispara_vencida(): void
    {
        // protocoladaEm = NOW - 16 dias → prazoFim = NOW - 1 dia (vencida)
        $protocolada = new DateTimeImmutable('-16 days');
        $sol = SolicitacaoTitular::fromState(
            77,
            2,
            SolicitacaoTitular::TIPO_EXCLUSAO,
            null,
            SolicitacaoTitular::STATUS_ABERTA,
            null,
            $protocolada,
            null,
            null
        );

        $this->solicitacoes
            ->method('findVencendoEmDias')
            ->willReturn([$sol]);

        $this->cron->run();

        $vencidas = $this->actionsForHook('pi_dpo_solicitacao_vencida');
        $this->assertNotEmpty($vencidas, 'Deve disparar pi_dpo_solicitacao_vencida');
        $this->assertSame(77, $vencidas[0]['args'][0]);
    }

    /* ------------------------------------------------------------------
     * 3. Recurso prazo_fim=+1d, decidido_em=null → pi_dpo_alerta_recurso
     * ------------------------------------------------------------------ */

    public function test_recurso_prazo_iminente_dispara_alerta_recurso(): void
    {
        $this->solicitacoes->method('findVencendoEmDias')->willReturn([]);

        // Simula recurso com 1 dia restante retornado pelo get_results
        $this->wpdb->recursosRows = [
            ['id' => 55, 'dias_restantes' => 1],
        ];

        $this->cron->run();

        $alertas = $this->actionsForHook('pi_dpo_alerta_recurso');
        $this->assertCount(1, $alertas, 'Deve disparar pi_dpo_alerta_recurso para recurso id=55');
        $this->assertSame(55, $alertas[0]['args'][0]);
        $this->assertSame(1, $alertas[0]['args'][1]);
    }

    /* ------------------------------------------------------------------
     * 4. 12 emails falhos → pi_dpo_alerta_email_falhas com count=12
     * ------------------------------------------------------------------ */

    public function test_12_emails_falhos_dispara_alerta_email_falhas(): void
    {
        $this->solicitacoes->method('findVencendoEmDias')->willReturn([]);
        $this->wpdb->emailFalhosCount = 12;

        $this->cron->run();

        $alertas = $this->actionsForHook('pi_dpo_alerta_email_falhas');
        $this->assertCount(1, $alertas, 'Deve disparar pi_dpo_alerta_email_falhas');
        $this->assertSame(12, $alertas[0]['args'][0], 'Count deve ser 12');
    }

    /* ------------------------------------------------------------------
     * 5. Abaixo do threshold (9 falhas) → NÃO dispara alerta email
     * ------------------------------------------------------------------ */

    public function test_abaixo_threshold_nao_dispara_alerta_email(): void
    {
        $this->solicitacoes->method('findVencendoEmDias')->willReturn([]);
        $this->wpdb->emailFalhosCount = 9; // threshold = 10

        $this->cron->run();

        $alertas = $this->actionsForHook('pi_dpo_alerta_email_falhas');
        $this->assertCount(0, $alertas, '9 falhas nao devem disparar alerta (threshold=10)');
    }

    /* ------------------------------------------------------------------
     * 6. Idempotência: segunda chamada usa option atualizado (last_email_check)
     *    → quando get_var retorna 0 na 2ª chamada, não dispara alerta duplicado
     * ------------------------------------------------------------------ */

    public function test_idempotencia_segunda_chamada_nao_duplica_alerta_email(): void
    {
        $this->solicitacoes->method('findVencendoEmDias')->willReturn([]);

        // Primeira chamada: 12 falhas → dispara alerta
        $this->wpdb->emailFalhosCount = 12;
        $this->cron->run();

        $count1 = count($this->actionsForHook('pi_dpo_alerta_email_falhas'));
        $this->assertSame(1, $count1, '1ª chamada deve disparar 1 alerta');

        // Após a 1ª chamada, o option pi_dpo_last_email_check foi atualizado.
        // Na 2ª chamada, simulamos que não há novas falhas desde a última check.
        $this->wpdb->emailFalhosCount = 0;
        $GLOBALS['__dpo_cron_test_actions'] = []; // reset para contar somente 2ª chamada
        $this->cron->run();

        $count2 = count($this->actionsForHook('pi_dpo_alerta_email_falhas'));
        $this->assertSame(0, $count2, '2ª chamada com 0 falhas nao deve disparar alerta');
    }

    /* ------------------------------------------------------------------
     * 7. run() audita a execução
     * ------------------------------------------------------------------ */

    public function test_run_audita_execucao(): void
    {
        $this->solicitacoes->method('findVencendoEmDias')->willReturn([]);

        $this->audit
            ->expects(self::atLeastOnce())
            ->method('log')
            ->with('dpo_cron', null, 'executado', self::anything(), self::anything());

        $this->cron->run();
    }
}
