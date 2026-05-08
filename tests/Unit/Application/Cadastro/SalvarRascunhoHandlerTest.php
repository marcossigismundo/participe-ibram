<?php
/**
 * Unit tests for {@see Ibram\ParticipeIbram\Application\Cadastro\SalvarRascunhoHandler}.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Application\Cadastro
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Application\Cadastro;

use DomainException;
use Ibram\ParticipeIbram\Application\Cadastro\SalvarRascunhoCommand;
use Ibram\ParticipeIbram\Application\Cadastro\SalvarRascunhoHandler;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Domain\Agente\Agente;
use Ibram\ParticipeIbram\Domain\Agente\AgentePF;
use Ibram\ParticipeIbram\Domain\Agente\AgenteRepository;
use Ibram\ParticipeIbram\Domain\Vocabulario\VocabularioRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Ibram\ParticipeIbram\Application\Cadastro\SalvarRascunhoHandler
 */
final class SalvarRascunhoHandlerTest extends TestCase
{
    /** @var AgenteRepository&MockObject */
    private $agentes;

    /** @var VocabularioRepository&MockObject */
    private $vocab;

    /** @var AuditLogger&MockObject */
    private $audit;

    private SalvarRascunhoHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agentes = $this->createMock(AgenteRepository::class);
        $this->vocab   = $this->createMock(VocabularioRepository::class);
        $this->audit   = $this->createMock(AuditLogger::class);
        $this->handler = new SalvarRascunhoHandler($this->agentes, $this->vocab, $this->audit);
    }

    public function test_cria_rascunho_novo_para_PF(): void
    {
        $this->vocab->method('validar')->willReturn(true);
        $this->agentes
            ->expects(self::once())
            ->method('save')
            ->with(
                self::isInstanceOf(Agente::class),
                self::isInstanceOf(AgentePF::class)
            )
            ->willReturn(42);

        $this->audit->expects(self::once())->method('log');

        $cmd = new SalvarRascunhoCommand(
            null,
            'PF',
            ['email_principal' => 'a@b.com', 'telefone' => '11999998888'],
            ['nome_completo' => 'Fulano', 'pessoa_deficiencia' => 'nao'],
            ['areas_tematicas' => ['memoria_arquivistica']],
            [],
            7
        );

        $id = $this->handler->handle($cmd);
        self::assertSame(42, $id);
    }

    public function test_rejeita_email_principal_vazio(): void
    {
        $cmd = new SalvarRascunhoCommand(
            null,
            'PF',
            ['email_principal' => ''],
            ['nome_completo' => 'Fulano'],
            [],
            [],
            7
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->handler->handle($cmd);
    }

    public function test_rejeita_vocabulario_invalido(): void
    {
        $this->vocab->method('validar')->willReturn(false);

        $cmd = new SalvarRascunhoCommand(
            null,
            'PF',
            ['email_principal' => 'a@b.com'],
            ['nome_completo' => 'F'],
            ['areas_tematicas' => ['inexistente']],
            [],
            7
        );

        $this->expectException(DomainException::class);
        $this->handler->handle($cmd);
    }

    public function test_rejeita_atualizacao_de_agente_inexistente(): void
    {
        $this->agentes->method('findById')->willReturn(null);

        $cmd = new SalvarRascunhoCommand(
            55,
            'PF',
            ['email_principal' => 'a@b.com'],
            ['nome_completo' => 'F'],
            [],
            [],
            7
        );

        $this->expectException(DomainException::class);
        $this->handler->handle($cmd);
    }

    public function test_command_rejeita_tipoAgente_invalido(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SalvarRascunhoCommand(null, 'XY', ['email_principal' => 'a@b.com'], [], [], [], 1);
    }
}
