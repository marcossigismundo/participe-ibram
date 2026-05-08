<?php
/**
 * Unit tests for {@see Ibram\ParticipeIbram\Application\Cadastro\DeferirCadastroHandler}.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Application\Cadastro
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Application\Cadastro;

use DateTimeImmutable;
use Ibram\ParticipeIbram\Application\Cadastro\AgenteDetalhesLoader;
use Ibram\ParticipeIbram\Application\Cadastro\DeferirCadastroCommand;
use Ibram\ParticipeIbram\Application\Cadastro\DeferirCadastroHandler;
use Ibram\ParticipeIbram\Application\Cadastro\NumeroRegistroAllocator;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Domain\Agente\Agente;
use Ibram\ParticipeIbram\Domain\Agente\AgentePF;
use Ibram\ParticipeIbram\Domain\Agente\AgenteRepository;
use Ibram\ParticipeIbram\Domain\Agente\StatusCadastro;
use Ibram\ParticipeIbram\Domain\Agente\TipoAgente;
use Ibram\ParticipeIbram\Domain\Analise\Analise;
use Ibram\ParticipeIbram\Domain\Analise\AnaliseRepository;
use Ibram\ParticipeIbram\Domain\Analise\StatusHistoricoRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Ibram\ParticipeIbram\Application\Cadastro\DeferirCadastroHandler
 */
final class DeferirCadastroHandlerTest extends TestCase
{
    /** @var AgenteRepository&MockObject */
    private $agentes;
    /** @var AgenteDetalhesLoader&MockObject */
    private $detalhesLoader;
    /** @var AnaliseRepository&MockObject */
    private $analises;
    /** @var StatusHistoricoRepository&MockObject */
    private $historico;
    /** @var NumeroRegistroAllocator&MockObject */
    private $sequence;
    /** @var AuditLogger&MockObject */
    private $audit;

    private DeferirCadastroHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agentes        = $this->createMock(AgenteRepository::class);
        $this->detalhesLoader = $this->createMock(AgenteDetalhesLoader::class);
        $this->analises       = $this->createMock(AnaliseRepository::class);
        $this->historico      = $this->createMock(StatusHistoricoRepository::class);
        $this->sequence       = $this->createMock(NumeroRegistroAllocator::class);
        $this->audit          = $this->createMock(AuditLogger::class);

        $this->handler = new DeferirCadastroHandler(
            $this->agentes,
            $this->detalhesLoader,
            $this->analises,
            $this->historico,
            $this->sequence,
            $this->audit
        );
    }

    public function test_defere_gera_numero_e_cria_analise(): void
    {
        $agente = $this->makeAgenteEmAnalise();

        $this->agentes->method('findById')->willReturn($agente);
        $this->sequence->expects(self::once())->method('alocar')->with('PF')->willReturn('PI-PF-2026-000001');
        $this->detalhesLoader->method('loadDetalhes')->willReturn(new AgentePF(10, 'X'));
        $this->detalhesLoader->method('loadRepresentantes')->willReturn([]);
        $this->agentes->expects(self::once())->method('save')->willReturn(10);

        $this->analises
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(static function (Analise $a): bool {
                return $a->isDeferimento() && $a->agenteId() === 10 && $a->analistaId() === 5;
            }))
            ->willReturn(77);

        $this->historico->expects(self::once())->method('registrar');
        $this->audit->expects(self::once())->method('log');

        $cmd = new DeferirCadastroCommand(10, 5, 'Parecer ok.');

        $analiseId = $this->handler->handle($cmd);

        self::assertSame(77, $analiseId);
        self::assertTrue($agente->getStatusCadastro()->isDeferido());
        self::assertNotNull($agente->getNumeroRegistro());
        self::assertSame('PI-PF-2026-000001', (string) $agente->getNumeroRegistro());
    }

    public function test_rejeita_quando_agente_nao_encontrado(): void
    {
        $this->agentes->method('findById')->willReturn(null);

        $this->expectException(\DomainException::class);
        $this->handler->handle(new DeferirCadastroCommand(10, 5, 'p'));
    }

    private function makeAgenteEmAnalise(): Agente
    {
        return new Agente(
            10,
            TipoAgente::pf(),
            null,
            StatusCadastro::emAnalise(),
            5,
            'a@b.com',
            null,
            new DateTimeImmutable('2026-01-01T00:00:00Z'),
            null,
            null,
            new DateTimeImmutable('2026-01-01T00:00:00Z'),
            new DateTimeImmutable('2026-01-01T00:00:00Z'),
            null
        );
    }
}
