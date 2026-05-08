<?php
/**
 * Unit tests for {@see RegistrarConsentimentoHandler}.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Application\Consentimento
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Application\Consentimento;

use DateTimeImmutable;
use DomainException;
use Ibram\ParticipeIbram\Application\Consentimento\RegistrarConsentimentoCommand;
use Ibram\ParticipeIbram\Application\Consentimento\RegistrarConsentimentoHandler;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Domain\Consentimento\Consentimento;
use Ibram\ParticipeIbram\Domain\Consentimento\ConsentimentoRepository;
use Ibram\ParticipeIbram\Domain\Consentimento\Finalidade;
use Ibram\ParticipeIbram\Domain\Consentimento\Termo;
use Ibram\ParticipeIbram\Domain\Consentimento\TermoRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Ibram\ParticipeIbram\Application\Consentimento\RegistrarConsentimentoHandler
 */
final class RegistrarConsentimentoHandlerTest extends TestCase
{
    /** @var ConsentimentoRepository&MockObject */
    private $consentimentos;

    /** @var TermoRepository&MockObject */
    private $termos;

    /** @var AuditLogger&MockObject */
    private $audit;

    private RegistrarConsentimentoHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->consentimentos = $this->createMock(ConsentimentoRepository::class);
        $this->termos         = $this->createMock(TermoRepository::class);
        $this->audit          = $this->createMock(AuditLogger::class);

        $this->handler = new RegistrarConsentimentoHandler(
            $this->consentimentos,
            $this->termos,
            $this->audit
        );
    }

    public function test_handle_throws_when_termo_not_found(): void
    {
        $this->termos->method('findById')->willReturn(null);

        $command = new RegistrarConsentimentoCommand(
            1,
            42,
            [Finalidade::IDENTIFICACAO, Finalidade::COMUNICACAO],
            [],
            null,
            null
        );

        $this->expectException(DomainException::class);
        $this->handler->handle($command);
    }

    public function test_handle_throws_when_termo_not_active(): void
    {
        $termo = Termo::fromState(
            42,
            '1.0',
            'c',
            hash('sha256', 'c'),
            new DateTimeImmutable('2099-01-01T00:00:00Z'),
            null,
            1
        );
        $this->termos->method('findById')->willReturn($termo);

        $command = new RegistrarConsentimentoCommand(
            1,
            42,
            [Finalidade::IDENTIFICACAO, Finalidade::COMUNICACAO],
            [],
            null,
            null
        );

        $this->expectException(DomainException::class);
        $this->handler->handle($command);
    }

    public function test_handle_requires_all_obligatory_finalidades(): void
    {
        $this->termos->method('findById')->willReturn($this->makeActiveTermo());

        // Falta a finalidade COMUNICACAO.
        $command = new RegistrarConsentimentoCommand(
            1,
            42,
            [Finalidade::IDENTIFICACAO],
            [Finalidade::VOTACAO],
            null,
            null
        );

        $this->expectException(DomainException::class);
        $this->handler->handle($command);
    }

    public function test_handle_persists_aceito_for_each_finalidade(): void
    {
        $this->termos->method('findById')->willReturn($this->makeActiveTermo());

        $savedConsents = [];
        $this->consentimentos
            ->expects(self::exactly(3))
            ->method('save')
            ->willReturnCallback(function (Consentimento $c) use (&$savedConsents): int {
                $savedConsents[] = $c;
                return count($savedConsents) * 100;
            });

        $command = new RegistrarConsentimentoCommand(
            5,
            42,
            [Finalidade::IDENTIFICACAO, Finalidade::COMUNICACAO, Finalidade::VOTACAO],
            [],
            null,
            null
        );

        $result = $this->handler->handle($command);

        self::assertCount(3, $savedConsents);
        foreach ($savedConsents as $c) {
            self::assertTrue($c->status()->isAceito());
            self::assertSame(5, $c->agenteId());
            self::assertSame(42, $c->termoId());
        }
        self::assertSame(100, $result[Finalidade::IDENTIFICACAO]);
        self::assertSame(200, $result[Finalidade::COMUNICACAO]);
        self::assertSame(300, $result[Finalidade::VOTACAO]);
    }

    public function test_handle_persists_negado_for_negadas(): void
    {
        $this->termos->method('findById')->willReturn($this->makeActiveTermo());

        $statuses = [];
        $this->consentimentos
            ->method('save')
            ->willReturnCallback(function (Consentimento $c) use (&$statuses): int {
                $statuses[] = [$c->finalidade()->value(), $c->status()->value()];
                return count($statuses);
            });

        $command = new RegistrarConsentimentoCommand(
            7,
            42,
            [Finalidade::IDENTIFICACAO, Finalidade::COMUNICACAO],
            [Finalidade::DADOS_SENSIVEIS_GENERO, Finalidade::MAPEAMENTO],
            null,
            null
        );

        $this->handler->handle($command);

        $byFinal = [];
        foreach ($statuses as [$fin, $st]) {
            $byFinal[$fin] = $st;
        }
        self::assertSame('aceito', $byFinal[Finalidade::IDENTIFICACAO]);
        self::assertSame('aceito', $byFinal[Finalidade::COMUNICACAO]);
        self::assertSame('negado', $byFinal[Finalidade::DADOS_SENSIVEIS_GENERO]);
        self::assertSame('negado', $byFinal[Finalidade::MAPEAMENTO]);
    }

    public function test_handle_audits_each_decision(): void
    {
        $this->termos->method('findById')->willReturn($this->makeActiveTermo());
        $this->consentimentos->method('save')->willReturn(99);

        $this->audit
            ->expects(self::exactly(3))
            ->method('log');

        $command = new RegistrarConsentimentoCommand(
            7,
            42,
            [Finalidade::IDENTIFICACAO, Finalidade::COMUNICACAO],
            [Finalidade::MAPEAMENTO],
            null,
            null
        );

        $this->handler->handle($command);
    }

    public function test_command_rejects_finalidade_in_both_lists(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RegistrarConsentimentoCommand(
            1,
            1,
            [Finalidade::VOTACAO],
            [Finalidade::VOTACAO],
            null,
            null
        );
    }

    private function makeActiveTermo(): Termo
    {
        return Termo::fromState(
            42,
            '2026.05.01',
            'conteudo',
            hash('sha256', 'conteudo'),
            new DateTimeImmutable('-1 day'),
            null,
            1
        );
    }
}
