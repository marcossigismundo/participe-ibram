<?php
/**
 * Unit tests for {@see Ibram\ParticipeIbram\Application\Cadastro\SubmeterCadastroHandler}.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Application\Cadastro
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Application\Cadastro;

use DateTimeImmutable;
use DomainException;
use Ibram\ParticipeIbram\Application\Cadastro\AgenteDetalhesLoader;
use Ibram\ParticipeIbram\Application\Cadastro\SubmeterCadastroCommand;
use Ibram\ParticipeIbram\Application\Cadastro\SubmeterCadastroHandler;
use Ibram\ParticipeIbram\Application\Consentimento\RegistrarConsentimentoCommand;
use Ibram\ParticipeIbram\Application\Consentimento\RegistrarConsentimentoHandler;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Core\Network\IpResolver;
use Ibram\ParticipeIbram\Domain\Agente\Agente;
use Ibram\ParticipeIbram\Domain\Agente\AgentePF;
use Ibram\ParticipeIbram\Domain\Agente\AgenteRepository;
use Ibram\ParticipeIbram\Domain\Agente\StatusCadastro;
use Ibram\ParticipeIbram\Domain\Agente\TipoAgente;
use Ibram\ParticipeIbram\Domain\Analise\StatusHistoricoRepository;
use Ibram\ParticipeIbram\Domain\Consentimento\Finalidade;
use Ibram\ParticipeIbram\Domain\Consentimento\Termo;
use Ibram\ParticipeIbram\Domain\Consentimento\TermoRepository;
use Ibram\ParticipeIbram\Domain\Documento\DocumentoRepository;
use Ibram\ParticipeIbram\Domain\Documento\TipoDocumento;
use Ibram\ParticipeIbram\Domain\Documento\TipoDocumentoRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Ibram\ParticipeIbram\Application\Cadastro\SubmeterCadastroHandler
 */
final class SubmeterCadastroHandlerTest extends TestCase
{
    /** @var AgenteRepository&MockObject */
    private $agentes;
    /** @var AgenteDetalhesLoader&MockObject */
    private $detalhesLoader;
    /** @var DocumentoRepository&MockObject */
    private $documentos;
    /** @var TipoDocumentoRepository&MockObject */
    private $tipos;
    /** @var TermoRepository&MockObject */
    private $termos;
    /** @var RegistrarConsentimentoHandler&MockObject */
    private $registrarConsent;
    /** @var StatusHistoricoRepository&MockObject */
    private $historico;
    /** @var AuditLogger&MockObject */
    private $audit;

    private IpResolver $ip;

    private SubmeterCadastroHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agentes          = $this->createMock(AgenteRepository::class);
        $this->detalhesLoader   = $this->createMock(AgenteDetalhesLoader::class);
        $this->documentos       = $this->createMock(DocumentoRepository::class);
        $this->tipos            = $this->createMock(TipoDocumentoRepository::class);
        $this->termos           = $this->createMock(TermoRepository::class);
        $this->registrarConsent = $this->createMock(RegistrarConsentimentoHandler::class);
        $this->historico        = $this->createMock(StatusHistoricoRepository::class);
        $this->audit            = $this->createMock(AuditLogger::class);
        $this->ip               = new IpResolver([], []);

        $this->handler = new SubmeterCadastroHandler(
            $this->agentes,
            $this->detalhesLoader,
            $this->documentos,
            $this->tipos,
            $this->termos,
            $this->registrarConsent,
            $this->historico,
            $this->audit,
            $this->ip
        );
    }

    public function test_rejeita_quando_agente_nao_encontrado(): void
    {
        $this->agentes->method('findById')->willReturn(null);

        $this->expectException(DomainException::class);
        $this->handler->handle($this->makeCmd());
    }

    public function test_rejeita_quando_finalidade_obrigatoria_ausente(): void
    {
        $this->agentes->method('findById')->willReturn($this->makeAgenteRascunho());
        $this->tipos->method('findObrigatoriosPara')->willReturn([]);

        $cmd = new SubmeterCadastroCommand(
            10,
            5,
            [
                'finalidades_aceitas' => [Finalidade::IDENTIFICACAO], // falta COMUNICACAO
                'finalidades_negadas' => [],
            ],
            '127.0.0.1',
            'UA'
        );

        $this->expectException(DomainException::class);
        $this->handler->handle($cmd);
    }

    public function test_rejeita_quando_documento_obrigatorio_faltando(): void
    {
        $this->agentes->method('findById')->willReturn($this->makeAgenteRascunho());

        $tipoObrigatorio = new TipoDocumento(99, 'cnpj', 'CNPJ', null, 'OR', 'application/pdf', 1024, true, 0);
        $this->tipos->method('findObrigatoriosPara')->willReturn([$tipoObrigatorio]);
        $this->documentos->method('findByAgente')->willReturn([]);

        $this->expectException(DomainException::class);
        $this->handler->handle($this->makeCmd());
    }

    public function test_fluxo_feliz_persiste_agente_e_audita(): void
    {
        $agente = $this->makeAgenteRascunho();
        $this->agentes->method('findById')->willReturn($agente);
        $this->tipos->method('findObrigatoriosPara')->willReturn([]);
        $this->termos->method('findAtivoCorrente')->willReturn($this->makeActiveTermo());

        $this->detalhesLoader->method('loadDetalhes')->willReturn(new AgentePF(1, 'X'));
        $this->detalhesLoader->method('loadRepresentantes')->willReturn([]);

        $this->registrarConsent
            ->expects(self::once())
            ->method('handle')
            ->with(self::isInstanceOf(RegistrarConsentimentoCommand::class));

        $this->agentes->expects(self::once())->method('save')->willReturn(10);
        $this->historico->expects(self::once())->method('registrar');
        $this->audit->expects(self::once())->method('log');

        $this->handler->handle($this->makeCmd());

        self::assertSame(StatusCadastro::SUBMETIDO, $agente->getStatusCadastro()->value());
    }

    private function makeCmd(): SubmeterCadastroCommand
    {
        return new SubmeterCadastroCommand(
            10,
            5,
            [
                'finalidades_aceitas' => [Finalidade::IDENTIFICACAO, Finalidade::COMUNICACAO],
                'finalidades_negadas' => [],
            ],
            '127.0.0.1',
            'UA'
        );
    }

    private function makeAgenteRascunho(): Agente
    {
        return new Agente(
            10,
            TipoAgente::pf(),
            null,
            StatusCadastro::rascunho(),
            5,
            'a@b.com',
            null,
            null,
            null,
            null,
            new DateTimeImmutable('2026-01-01T00:00:00Z'),
            new DateTimeImmutable('2026-01-01T00:00:00Z'),
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
