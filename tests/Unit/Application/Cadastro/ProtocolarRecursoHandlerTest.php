<?php
/**
 * Unit tests for {@see Ibram\ParticipeIbram\Application\Cadastro\ProtocolarRecursoHandler}.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Application\Cadastro
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Application\Cadastro;

use DateTimeImmutable;
use DomainException;
use Ibram\ParticipeIbram\Application\Cadastro\AgenteDetalhesLoader;
use Ibram\ParticipeIbram\Application\Cadastro\ProtocolarRecursoCommand;
use Ibram\ParticipeIbram\Application\Cadastro\ProtocolarRecursoHandler;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Domain\Agente\Agente;
use Ibram\ParticipeIbram\Domain\Agente\AgentePF;
use Ibram\ParticipeIbram\Domain\Agente\AgenteRepository;
use Ibram\ParticipeIbram\Domain\Agente\StatusCadastro;
use Ibram\ParticipeIbram\Domain\Agente\TipoAgente;
use Ibram\ParticipeIbram\Domain\Analise\Analise;
use Ibram\ParticipeIbram\Domain\Analise\AnaliseRepository;
use Ibram\ParticipeIbram\Domain\Analise\Recurso;
use Ibram\ParticipeIbram\Domain\Analise\RecursoRepository;
use Ibram\ParticipeIbram\Domain\Analise\StatusHistoricoRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Ibram\ParticipeIbram\Application\Cadastro\ProtocolarRecursoHandler
 */
final class ProtocolarRecursoHandlerTest extends TestCase
{
    /** @var AgenteRepository&MockObject */
    private $agentes;
    /** @var AgenteDetalhesLoader&MockObject */
    private $detalhesLoader;
    /** @var AnaliseRepository&MockObject */
    private $analises;
    /** @var RecursoRepository&MockObject */
    private $recursos;
    /** @var StatusHistoricoRepository&MockObject */
    private $historico;
    /** @var AuditLogger&MockObject */
    private $audit;

    private ProtocolarRecursoHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agentes        = $this->createMock(AgenteRepository::class);
        $this->detalhesLoader = $this->createMock(AgenteDetalhesLoader::class);
        $this->analises       = $this->createMock(AnaliseRepository::class);
        $this->recursos       = $this->createMock(RecursoRepository::class);
        $this->historico      = $this->createMock(StatusHistoricoRepository::class);
        $this->audit          = $this->createMock(AuditLogger::class);

        $this->handler = new ProtocolarRecursoHandler(
            $this->agentes,
            $this->detalhesLoader,
            $this->analises,
            $this->recursos,
            $this->historico,
            $this->audit
        );
    }

    public function test_rejeita_quando_status_nao_indeferido_aguardando(): void
    {
        $agente = $this->makeAgente(StatusCadastro::deferido(), new \Ibram\ParticipeIbram\Domain\Agente\NumeroRegistro('PI-PF-2026-000001'));
        $this->agentes->method('findById')->willReturn($agente);

        $this->expectException(DomainException::class);
        $this->handler->handle(new ProtocolarRecursoCommand(10, 7, 'fund'));
    }

    public function test_rejeita_quando_prazo_expirou(): void
    {
        $agente = $this->makeAgente(StatusCadastro::indeferidoAguardandoRecurso(), null);
        $this->agentes->method('findById')->willReturn($agente);

        // Análise indeferida há 30 dias — fora dos 10 dias do Art. 7º.
        $analise = Analise::indeferir(10, 5, 'p', 'fund', new DateTimeImmutable('-30 days'));
        $analise = $analise->withId(50);
        $this->analises->method('findByAgente')->willReturn([$analise]);

        $this->expectException(DomainException::class);
        $this->handler->handle(new ProtocolarRecursoCommand(10, 7, 'fundamento'));
    }

    public function test_protocola_dentro_do_prazo(): void
    {
        $agente = $this->makeAgente(StatusCadastro::indeferidoAguardandoRecurso(), null);
        $this->agentes->method('findById')->willReturn($agente);

        $analise = Analise::indeferir(10, 5, 'p', 'fund', new DateTimeImmutable('-2 days'));
        $analise = $analise->withId(50);
        $this->analises->method('findByAgente')->willReturn([$analise]);

        $this->recursos->method('findPorAgenteEFase')->willReturn(null);
        $this->recursos
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(static function (Recurso $r): bool {
                return $r->fase() === Recurso::FASE_RETRATACAO
                    && $r->analiseId() === 50
                    && $r->recorrenteId() === 7;
            }))
            ->willReturn(123);

        $this->detalhesLoader->method('loadDetalhes')->willReturn(new AgentePF(10, 'X'));
        $this->detalhesLoader->method('loadRepresentantes')->willReturn([]);
        $this->agentes->expects(self::once())->method('save');
        $this->historico->expects(self::once())->method('registrar');
        $this->audit->expects(self::once())->method('log');

        $id = $this->handler->handle(new ProtocolarRecursoCommand(10, 7, 'Fundamentacao do recurso.'));

        self::assertSame(123, $id);
        self::assertSame(StatusCadastro::EM_RETRATACAO, $agente->getStatusCadastro()->value());
    }

    public function test_rejeita_recurso_duplicado_em_retratacao(): void
    {
        $agente = $this->makeAgente(StatusCadastro::indeferidoAguardandoRecurso(), null);
        $this->agentes->method('findById')->willReturn($agente);

        $analise = Analise::indeferir(10, 5, 'p', 'fund', new DateTimeImmutable('-2 days'))->withId(50);
        $this->analises->method('findByAgente')->willReturn([$analise]);

        $existing = Recurso::protocolar(50, Recurso::FASE_RETRATACAO, 7, 'f', new DateTimeImmutable('-1 day'), new DateTimeImmutable('-2 days'), new DateTimeImmutable('+8 days'));
        $this->recursos->method('findPorAgenteEFase')->willReturn($existing);

        $this->expectException(DomainException::class);
        $this->handler->handle(new ProtocolarRecursoCommand(10, 7, 'F'));
    }

    private function makeAgente(StatusCadastro $status, ?\Ibram\ParticipeIbram\Domain\Agente\NumeroRegistro $numero): Agente
    {
        return new Agente(
            10,
            TipoAgente::pf(),
            $numero,
            $status,
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
