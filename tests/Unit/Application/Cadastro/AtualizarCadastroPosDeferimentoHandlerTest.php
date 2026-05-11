<?php
/**
 * Testes unitários: AtualizarCadastroPosDeferimentoHandler.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Application\Cadastro
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Application\Cadastro;

use DateTimeImmutable;
use DomainException;
use Ibram\ParticipeIbram\Application\Cadastro\AgenteDetalhesLoader;
use Ibram\ParticipeIbram\Application\Cadastro\AtualizarCadastroPosDeferimentoCommand;
use Ibram\ParticipeIbram\Application\Cadastro\AtualizarCadastroPosDeferimentoHandler;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Domain\Agente\Agente;
use Ibram\ParticipeIbram\Domain\Agente\AgenteOR;
use Ibram\ParticipeIbram\Domain\Agente\AgentePF;
use Ibram\ParticipeIbram\Domain\Agente\AgenteRepository;
use Ibram\ParticipeIbram\Domain\Agente\AgenteSM;
use Ibram\ParticipeIbram\Domain\Agente\NumeroRegistro;
use Ibram\ParticipeIbram\Domain\Agente\StatusCadastro;
use Ibram\ParticipeIbram\Domain\Agente\TipoAgente;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Ibram\ParticipeIbram\Application\Cadastro\AtualizarCadastroPosDeferimentoHandler
 * @covers \Ibram\ParticipeIbram\Application\Cadastro\AtualizarCadastroPosDeferimentoCommand
 */
final class AtualizarCadastroPosDeferimentoHandlerTest extends TestCase
{
    /** @var array<int,array<string,mixed>> */
    private array $auditEvents = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->auditEvents = [];
    }

    /**
     * @return array<int,array{0:string}> Cada caso: status que deve bloquear.
     */
    public function statusBloqueadosProvider(): array
    {
        return [
            [StatusCadastro::SUBMETIDO],
            [StatusCadastro::EM_ANALISE],
            [StatusCadastro::INDEFERIDO_AGUARDANDO_RECURSO],
            [StatusCadastro::EM_RETRATACAO],
            [StatusCadastro::EM_RECURSO_PRESIDENCIA],
            [StatusCadastro::INDEFERIDO_FINAL],
            [StatusCadastro::RASCUNHO], // tambem bloqueado (fluxo errado).
        ];
    }

    /**
     * @dataProvider statusBloqueadosProvider
     */
    public function testStatusBloqueadosLancamDomainException(string $statusValor): void
    {
        $agente = $this->makeAgenteDeferido(7, StatusCadastro::fromString($statusValor));
        $repo = $this->makeRepo($agente, $this->makePF());
        $handler = new AtualizarCadastroPosDeferimentoHandler($repo, $repo, $this->makeAuditSpy());

        $cmd = new AtualizarCadastroPosDeferimentoCommand(42, 7, ['email_principal' => 'novo@x.org']);
        $this->expectException(DomainException::class);
        $handler->handle($cmd);
    }

    public function testDeferidoPermiteEditarEmailETelefone(): void
    {
        $agente = $this->makeAgenteDeferido(7, StatusCadastro::deferido(), 'antigo@x.org', '+551199990000');
        $repo = $this->makeRepo($agente, $this->makePF());
        $handler = new AtualizarCadastroPosDeferimentoHandler($repo, $repo, $this->makeAuditSpy());

        $cmd = new AtualizarCadastroPosDeferimentoCommand(42, 7, [
            'email_principal' => 'novo@x.org',
            'telefone'        => '+551188887777',
        ]);
        $result = $handler->handle($cmd);

        $this->assertContains('email_principal', $result['campos_alterados']);
        $this->assertContains('telefone', $result['campos_alterados']);

        $this->assertCount(1, $this->auditEvents);
        $event = $this->auditEvents[0];
        $this->assertSame('minha_conta_atualizar', $event['acao']);
        $this->assertArrayHasKey('email_principal', $event['dadosAntes']);
        // PiiMasker::maskEmail mantem '@' + dominio.
        $this->assertStringContainsString('@x.org', $event['dadosAntes']['email_principal']);
    }

    public function testDeferidoNaoPermiteEditarCpf(): void
    {
        $agente = $this->makeAgenteDeferido(7, StatusCadastro::deferido());
        $repo = $this->makeRepo($agente, $this->makePF());
        $handler = new AtualizarCadastroPosDeferimentoHandler($repo, $repo, $this->makeAuditSpy());

        $cmd = new AtualizarCadastroPosDeferimentoCommand(42, 7, ['cpf' => '12345678901']);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/cpf/');
        $handler->handle($cmd);
    }

    public function testDeferidoNaoPermiteEditarNomeCompleto(): void
    {
        $agente = $this->makeAgenteDeferido(7, StatusCadastro::deferido());
        $repo = $this->makeRepo($agente, $this->makePF());
        $handler = new AtualizarCadastroPosDeferimentoHandler($repo, $repo, $this->makeAuditSpy());

        $cmd = new AtualizarCadastroPosDeferimentoCommand(42, 7, ['nome_completo' => 'OUTRO']);
        $this->expectException(InvalidArgumentException::class);
        $handler->handle($cmd);
    }

    public function testDeferidoEmRetratacaoEDeferidoEmRecursoAceitamEdicaoLimitada(): void
    {
        foreach ([StatusCadastro::deferidoEmRetratacao(), StatusCadastro::deferidoEmRecurso()] as $status) {
            $this->auditEvents = [];
            $agente = $this->makeAgenteDeferido(7, $status, 'antigo@x.org', null);
            $repo = $this->makeRepo($agente, $this->makePF());
            $handler = new AtualizarCadastroPosDeferimentoHandler($repo, $repo, $this->makeAuditSpy());
            $cmd = new AtualizarCadastroPosDeferimentoCommand(42, 7, ['email_principal' => 'novo@x.org']);
            $result = $handler->handle($cmd);
            $this->assertContains('email_principal', $result['campos_alterados']);
        }
    }

    public function testDiffApenasComCamposModificados(): void
    {
        $agente = $this->makeAgenteDeferido(7, StatusCadastro::deferido(), 'mesmo@x.org', '+5511999');
        $repo = $this->makeRepo($agente, $this->makePF('SP', 'Sao Paulo'));
        $handler = new AtualizarCadastroPosDeferimentoHandler($repo, $repo, $this->makeAuditSpy());

        $cmd = new AtualizarCadastroPosDeferimentoCommand(42, 7, [
            'email_principal'   => 'mesmo@x.org',
            'cidade_residencia' => 'Campinas',
        ]);
        $result = $handler->handle($cmd);

        $this->assertContains('cidade_residencia', $result['campos_alterados']);
        $this->assertNotContains('email_principal', $result['campos_alterados']);

        $this->assertNotEmpty($this->auditEvents);
        $event = $this->auditEvents[0];
        $this->assertArrayHasKey('cidade_residencia', $event['dadosDepois']);
        $this->assertSame('Campinas', $event['dadosDepois']['cidade_residencia']);
        $this->assertArrayNotHasKey('email_principal', $event['dadosDepois']);
    }

    public function testSemMudancasNaoAuditaEnemDisparaHook(): void
    {
        $agente = $this->makeAgenteDeferido(7, StatusCadastro::deferido(), 'mesmo@x.org', '+551199');
        $repo = $this->makeRepo($agente, $this->makePF());
        $handler = new AtualizarCadastroPosDeferimentoHandler($repo, $repo, $this->makeAuditSpy());

        $cmd = new AtualizarCadastroPosDeferimentoCommand(42, 7, [
            'email_principal' => 'mesmo@x.org',
        ]);
        $result = $handler->handle($cmd);

        $this->assertSame([], $result['campos_alterados']);
        $this->assertCount(0, $this->auditEvents);
    }

    /* --------- helpers --------- */

    private function makeAgenteDeferido(
        int $userId,
        StatusCadastro $status,
        string $email = 'alvo@x.org',
        ?string $telefone = null
    ): Agente {
        $now = new DateTimeImmutable('now');
        $numero = null;
        if ($status->isDeferido()) {
            $numero = new NumeroRegistro('PI-PF-2025-000042');
        }
        return new Agente(
            42,
            TipoAgente::pf(),
            $numero,
            $status,
            $userId,
            $email,
            $telefone,
            $status->isDeferido() ? $now : null,
            $status->isDeferido() ? $now : null,
            null,
            $now,
            $now,
            null
        );
    }

    private function makePF(?string $uf = 'SP', ?string $cidade = 'Sao Paulo'): AgentePF
    {
        return new AgentePF(
            42,
            'Fulano de Tal',
            null,
            '12345678901',
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            AgentePF::PESSOA_DEFICIENCIA_PREFIRO_NAO_INFORMAR,
            null,
            null,
            null,
            null,
            $cidade,
            $uf,
            null,
            null,
            null
        );
    }

    /**
     * @param AgentePF|AgenteOR|AgenteSM $detalhes
     */
    private function makeRepo(Agente $agente, object $detalhes): object
    {
        return new class($agente, $detalhes) implements AgenteRepository, AgenteDetalhesLoader {
            private Agente $agente;
            /** @var AgentePF|AgenteOR|AgenteSM */
            private object $detalhes;
            public int $saveCount = 0;
            public function __construct(Agente $agente, object $detalhes)
            {
                $this->agente = $agente;
                $this->detalhes = $detalhes;
            }
            public function findById(int $id): ?Agente
            {
                return $this->agente->getId() === $id ? $this->agente : null;
            }
            public function findByNumeroRegistro(string $numero): ?Agente { return null; }
            public function findByCpf(string $cpfPlain): ?Agente { return null; }
            public function findByCnpj(string $cnpjPlain): ?Agente { return null; }
            public function findByUserId(int $userId): ?Agente { return null; }
            public function findByEmail(string $email): ?Agente { return null; }
            public function save(Agente $agente, object $detalhes, array $representantes = []): int
            {
                $this->saveCount++;
                $this->agente = $agente;
                $this->detalhes = $detalhes;
                return (int) $agente->getId();
            }
            public function softDelete(int $id): void {}
            public function listByStatus(string $status, int $page = 1, int $perPage = 25): array
            {
                return ['items' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage];
            }
            public function loadDetalhes(int $agenteId, string $tipoAgente): object
            {
                return $this->detalhes;
            }
            public function loadRepresentantes(int $agenteId): array
            {
                return [];
            }
        };
    }

    private function makeAuditSpy(): AuditLogger
    {
        $eventsRef = &$this->auditEvents;
        $spy = $this->getMockBuilder(AuditLogger::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['log'])
            ->getMock();
        $spy->method('log')->willReturnCallback(
            function (
                string $entidade,
                ?int $entidadeId,
                string $acao,
                ?array $dadosAntes,
                ?array $dadosDepois,
                ?int $atorId = null
            ) use (&$eventsRef): void {
                $eventsRef[] = compact('entidade', 'entidadeId', 'acao', 'dadosAntes', 'dadosDepois', 'atorId');
            }
        );

        return $spy;
    }
}
