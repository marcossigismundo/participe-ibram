<?php
/**
 * Testes de integração: MinhaContaEndpoints (Wave 8-A).
 *
 * Cobertura crítica:
 *  - 401 sem auth
 *  - 403 ownership bypass (user A tentando ver dados de user B)
 *  - 423 PATCH em estado bloqueado (SUBMETIDO)
 *  - 400 PATCH em campo bloqueado (CPF pos-deferimento)
 *  - 200 PATCH em campo permitido + audit registrado
 *  - 200 reveal pelo proprio dono + AccessTracker registrado
 *
 * @package Ibram\ParticipeIbram\Tests\Integration\Rest
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Integration\Rest;

use DateTimeImmutable;
use Ibram\ParticipeIbram\Application\Cadastro\AgenteDetalhesLoader;
use Ibram\ParticipeIbram\Application\Cadastro\AtualizarCadastroPosDeferimentoHandler;
use Ibram\ParticipeIbram\Application\Cadastro\PendenciasCalculator;
use Ibram\ParticipeIbram\Core\Audit\AccessTracker;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Domain\Agente\Agente;
use Ibram\ParticipeIbram\Domain\Agente\AgentePF;
use Ibram\ParticipeIbram\Domain\Agente\AgenteRepository;
use Ibram\ParticipeIbram\Domain\Agente\NumeroRegistro;
use Ibram\ParticipeIbram\Domain\Agente\StatusCadastro;
use Ibram\ParticipeIbram\Domain\Agente\TipoAgente;
use Ibram\ParticipeIbram\Domain\Documento\DocumentoRepository;
use Ibram\ParticipeIbram\Domain\Documento\TipoDocumentoRepository;
use Ibram\ParticipeIbram\Presentation\Public\MinhaConta\OwnershipResolver;
use Ibram\ParticipeIbram\Presentation\Rest\MinhaContaEndpoints;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;
use WP_REST_Response;

/**
 * @covers \Ibram\ParticipeIbram\Presentation\Rest\MinhaContaEndpoints
 * @covers \Ibram\ParticipeIbram\Presentation\Public\MinhaConta\OwnershipResolver
 * @covers \Ibram\ParticipeIbram\Application\Cadastro\AtualizarCadastroPosDeferimentoHandler
 */
final class MinhaContaEndpointsTest extends TestCase
{
    /** @var array<int,array<string,mixed>> */
    private array $auditEvents = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->auditEvents = [];
        $GLOBALS['__pi_test_current_user_id'] = 0;
        $GLOBALS['__pi_test_user_caps']       = [];
        $GLOBALS['__pi_test_transients']      = [];
    }

    public function testGetCadastroSemAuthRetornaWpError(): void
    {
        $GLOBALS['__pi_test_current_user_id'] = 0;
        $ep = $this->makeEndpoints();
        $perm = $ep->permissionLoggedInPublic();
        $result = $perm();
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('pi_unauthorized', $result->code);
    }

    public function testUserSemCadastroRetornaHasCadastroFalse(): void
    {
        $GLOBALS['__pi_test_current_user_id'] = 99; // user sem cadastro
        $ep = $this->makeEndpoints();
        $resp = $ep->getCadastro(new WP_REST_Request());
        $this->assertInstanceOf(WP_REST_Response::class, $resp);
        $this->assertSame(200, $resp->get_status());
        $data = $resp->get_data();
        $this->assertFalse($data['has_cadastro']);
    }

    public function testUserDonoRetornaSeuPropioCadastro(): void
    {
        $GLOBALS['__pi_test_current_user_id'] = 7;
        $ep = $this->makeEndpoints();
        $resp = $ep->getCadastro(new WP_REST_Request());
        $this->assertSame(200, $resp->get_status());
        $data = $resp->get_data();
        $this->assertTrue($data['has_cadastro']);
        $this->assertSame(42, $data['agente_id']);
        // CPF deve vir mascarado por padrao.
        $this->assertTrue($data['cpf']['masked']);
        $this->assertStringContainsString('XXX', (string) $data['cpf']['value']);
    }

    public function testUserOutroNaoVeOCadastroDeUserA(): void
    {
        // user 8 nao tem cadastro; mesmo tentando forcar agente_id no body, endpoint ignora.
        $GLOBALS['__pi_test_current_user_id'] = 8;
        $ep = $this->makeEndpoints();
        $req = new WP_REST_Request(['agente_id' => 42]); // tentativa de bypass
        $resp = $ep->getCadastro($req);
        $data = $resp->get_data();
        $this->assertFalse($data['has_cadastro']);
    }

    public function testRevealCpfPeloDonoRetornaPlainEAuditaAccessTracker(): void
    {
        $GLOBALS['__pi_test_current_user_id'] = 7;
        $ep = $this->makeEndpoints();

        $req = new WP_REST_Request(['reveal' => 'cpf']);
        $resp = $ep->getCadastro($req);
        $this->assertSame(200, $resp->get_status());
        $data = $resp->get_data();
        $this->assertFalse($data['cpf']['masked']);
        $this->assertSame('12345678901', $data['cpf']['value']);

        $achou = false;
        foreach ($this->auditEvents as $ev) {
            if ($ev['acao'] === 'visualizar_dado_sensivel' && ($ev['dadosDepois']['campo'] ?? null) === 'cpf') {
                $achou = true;
                break;
            }
        }
        $this->assertTrue($achou, 'AccessTracker deveria registrar reveal de CPF.');
    }

    public function testPatchEmSubmetidoRetorna423Locked(): void
    {
        $GLOBALS['__pi_test_current_user_id'] = 11;
        $ep = $this->makeEndpoints();
        $req = new WP_REST_Request([], ['email_principal' => 'novo@x.org']);
        $resp = $ep->patchCadastro($req);
        $this->assertSame(423, $resp->get_status());
        $data = $resp->get_data();
        $this->assertSame('pi_locked', $data['code']);
    }

    public function testPatchCpfEmDeferidoRetorna400ValidationCampoBloqueado(): void
    {
        $GLOBALS['__pi_test_current_user_id'] = 7;
        $ep = $this->makeEndpoints();
        $req = new WP_REST_Request([], ['cpf' => '99999999999']);
        $resp = $ep->patchCadastro($req);
        $this->assertSame(400, $resp->get_status());
        $data = $resp->get_data();
        $this->assertSame('pi_validation', $data['code']);
    }

    public function testPatchTelefoneEmDeferidoRetorna200(): void
    {
        $GLOBALS['__pi_test_current_user_id'] = 7;
        $ep = $this->makeEndpoints();
        $req = new WP_REST_Request([], ['telefone' => '+551133221100']);
        $resp = $ep->patchCadastro($req);
        $this->assertSame(200, $resp->get_status());
        $data = $resp->get_data();
        $this->assertContains('telefone', $data['campos_alterados']);

        $achou = false;
        foreach ($this->auditEvents as $ev) {
            if ($ev['acao'] === 'minha_conta_atualizar') {
                $achou = true;
                break;
            }
        }
        $this->assertTrue($achou);
    }

    public function testGetDashboardDoDonoRetornaProximosPassos(): void
    {
        $GLOBALS['__pi_test_current_user_id'] = 7;
        $ep = $this->makeEndpoints();
        $resp = $ep->getDashboard(new WP_REST_Request());
        $this->assertSame(200, $resp->get_status());
        $data = $resp->get_data();
        $this->assertTrue($data['has_cadastro']);
        $this->assertSame('deferido', $data['status_cadastro']);
        $this->assertIsArray($data['proximos_passos']);
    }

    /* ---------------- helpers ---------------- */

    private function makeEndpoints(): _MinhaContaEndpointsTestable
    {
        $audit = $this->makeAuditSpy();

        $now = new DateTimeImmutable('now');
        $agente42 = new Agente(
            42,
            TipoAgente::pf(),
            new NumeroRegistro('PI-PF-2025-000042'),
            StatusCadastro::deferido(),
            7,
            'mesmo@x.org',
            '+551199997777',
            $now,
            $now,
            null,
            $now,
            $now,
            null
        );
        $pf42 = new AgentePF(
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
            'Sao Paulo',
            'SP',
            null,
            null,
            null
        );
        $agente50 = new Agente(
            50,
            TipoAgente::pf(),
            null,
            StatusCadastro::submetido(),
            11,
            'outro@x.org',
            null,
            $now,
            null,
            null,
            $now,
            $now,
            null
        );
        $pf50 = new AgentePF(
            50,
            'Outro',
            null,
            '22233344455',
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
            null,
            null,
            null,
            null,
            null
        );

        $repo = new _FakeMinhaContaRepo(
            [7 => $agente42, 11 => $agente50],
            [42 => $agente42, 50 => $agente50],
            [42 => $pf42, 50 => $pf50]
        );

        $owner = new OwnershipResolver($repo, $audit);
        $accessTracker = new AccessTracker($audit);
        $handler = new AtualizarCadastroPosDeferimentoHandler($repo, $repo, $audit);
        $pend = new PendenciasCalculator($this->makeFakeDocs(), $this->makeFakeTipos());

        return new _MinhaContaEndpointsTestable(
            $owner,
            $repo,
            $repo,
            $handler,
            $pend,
            $accessTracker
        );
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

    private function makeFakeDocs(): DocumentoRepository
    {
        return new class implements DocumentoRepository {
            public function findById(int $id): \Ibram\ParticipeIbram\Domain\Documento\Documento { throw new \RuntimeException('nope'); }
            public function findByAgente(int $agenteId): array { return []; }
            public function findByInscricao(int $inscricaoId): array { return []; }
            public function save(\Ibram\ParticipeIbram\Domain\Documento\Documento $documento): int { return 0; }
            public function delete(int $id): void {}
        };
    }

    private function makeFakeTipos(): TipoDocumentoRepository
    {
        return new class implements TipoDocumentoRepository {
            public function findById(int $id): \Ibram\ParticipeIbram\Domain\Documento\TipoDocumento { throw new \RuntimeException('nope'); }
            public function findByCodigo(string $codigo): \Ibram\ParticipeIbram\Domain\Documento\TipoDocumento { throw new \RuntimeException('nope'); }
            public function listAtivos(): array { return []; }
            public function findObrigatoriosPara(string $tipoAgente, bool $temCnpj = true): array { return []; }
        };
    }
}

/**
 * Subclasse para expor `permissionLoggedIn` (helper protected do trait).
 */
final class _MinhaContaEndpointsTestable extends MinhaContaEndpoints
{
    public function permissionLoggedInPublic(): callable
    {
        return $this->permissionLoggedIn();
    }
}

/**
 * Fake combinado: AgenteRepository + AgenteDetalhesLoader.
 */
final class _FakeMinhaContaRepo implements AgenteRepository, AgenteDetalhesLoader
{
    /** @var array<int,Agente> */
    private array $byUserId;
    /** @var array<int,Agente> */
    private array $byId;
    /** @var array<int,object> */
    private array $detalhes;

    /**
     * @param array<int,Agente> $byUserId
     * @param array<int,Agente> $byId
     * @param array<int,object> $detalhes
     */
    public function __construct(array $byUserId, array $byId, array $detalhes)
    {
        $this->byUserId = $byUserId;
        $this->byId     = $byId;
        $this->detalhes = $detalhes;
    }
    public function findById(int $id): ?Agente { return $this->byId[$id] ?? null; }
    public function findByNumeroRegistro(string $numero): ?Agente { return null; }
    public function findByCpf(string $cpfPlain): ?Agente { return null; }
    public function findByCnpj(string $cnpjPlain): ?Agente { return null; }
    public function findByUserId(int $userId): ?Agente { return $this->byUserId[$userId] ?? null; }
    public function findByEmail(string $email): ?Agente { return null; }
    public function save(Agente $agente, object $detalhes, array $representantes = []): int
    {
        $id = (int) $agente->getId();
        $this->byId[$id] = $agente;
        $this->detalhes[$id] = $detalhes;
        return $id;
    }
    public function softDelete(int $id): void {}
    public function listByStatus(string $status, int $page = 1, int $perPage = 25): array
    {
        return ['items' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage];
    }
    public function loadDetalhes(int $agenteId, string $tipoAgente): object
    {
        return $this->detalhes[$agenteId];
    }
    public function loadRepresentantes(int $agenteId): array { return []; }
}
