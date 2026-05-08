<?php
/**
 * Testes de integração — VotacaoEndpoints.
 *
 * Foco em anti-rastreio: nenhum response, audit log ou exceção pode revelar
 * `agente_id` ↔ `eleitor_hash` ↔ `voto`.
 *
 * @package Ibram\ParticipeIbram\Tests\Integration\Rest
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Integration\Rest;

use DateTimeImmutable;
use DateTimeZone;
use Ibram\ParticipeIbram\Application\Votacao\RegistrarVotoHandler;
use Ibram\ParticipeIbram\Application\Votacao\VerificarElegibilidadeHandler;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Core\Network\IpResolver;
use Ibram\ParticipeIbram\Domain\Votacao\EleitorHasher;
use Ibram\ParticipeIbram\Domain\Votacao\ModoVotacao;
use Ibram\ParticipeIbram\Domain\Votacao\StatusVotacao;
use Ibram\ParticipeIbram\Domain\Votacao\Votacao;
use Ibram\ParticipeIbram\Presentation\Rest\VotacaoEndpoints;
use Ibram\ParticipeIbram\Tests\Unit\Application\Votacao\FakeAgenteVotanteGateway;
use Ibram\ParticipeIbram\Tests\Unit\Application\Votacao\FakeCategoriaConsultaGateway;
use Ibram\ParticipeIbram\Tests\Unit\Application\Votacao\FakeInscricaoConsultaGateway;
use Ibram\ParticipeIbram\Tests\Unit\Application\Votacao\FakeVotacaoRepository;
use Ibram\ParticipeIbram\Tests\Unit\Application\Votacao\FakeVotoRepository;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;
use WP_REST_Response;

require_once __DIR__ . '/../../Unit/Application/Votacao/Fakes.php';

/**
 * @covers \Ibram\ParticipeIbram\Presentation\Rest\VotacaoEndpoints
 */
final class VotacaoEndpointsTest extends TestCase
{
    private FakeVotacaoRepository $votacaoRepo;
    private FakeVotoRepository $votoRepo;
    private FakeAgenteVotanteGateway $agenteGateway;
    private FakeCategoriaConsultaGateway $categoriaGateway;
    private FakeInscricaoConsultaGateway $inscricaoGateway;
    private EleitorHasher $hasher;
    private AuditLogger $audit;
    /** @var array<int,array<string,mixed>> */
    private array $auditCalls = [];

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['__pi_test_transients']        = [];
        $GLOBALS['__pi_test_current_user_id']   = 5;
        $GLOBALS['__pi_test_options']           = [];
        $GLOBALS['__pi_audit_inserts']          = [];

        $this->votacaoRepo      = new FakeVotacaoRepository();
        $this->votoRepo         = new FakeVotoRepository();
        $this->agenteGateway    = new FakeAgenteVotanteGateway();
        $this->categoriaGateway = new FakeCategoriaConsultaGateway();
        $this->inscricaoGateway = new FakeInscricaoConsultaGateway();

        $this->hasher = new EleitorHasher(
            base64_encode(str_repeat("\x42", SODIUM_CRYPTO_GENERICHASH_KEYBYTES))
        );

        $wpdb        = $this->createWpdbStub();
        $this->audit = new AuditLogger($wpdb, new IpResolver([], []));

        // Cenário base: votação aberta para edital 7, categoria 11.
        $votacao = new Votacao(
            null,
            7,
            new DateTimeImmutable('2026-06-10 10:00:00', new DateTimeZone('UTC')),
            new DateTimeImmutable('2030-06-10 18:00:00', new DateTimeZone('UTC')),
            StatusVotacao::aberta(),
            ModoVotacao::porCategoria()
        );
        $this->votacaoRepo->seed($votacao);

        $this->categoriaGateway->editalDe[11]                 = 7;
        $this->categoriaGateway->tiposAceitos[11]             = ['PF', 'OR'];
        $this->categoriaGateway->categoriasDoEdital[7]        = [11];
        $this->categoriaGateway->vagas[11]                    = 1;
        $this->categoriaGateway->suplentes[11]                = 1;

        $this->agenteGateway->deferidos[101]                  = true;
        $this->agenteGateway->tipos[101]                      = 'PF';
        $this->inscricaoGateway->habilitadas['202|11']        = true;
    }

    public function testRegistroDeVotoBemSucedidoNaoVazaAgenteId(): void
    {
        $endpoints = $this->makeEndpoints();

        $request = new WP_REST_Request(
            [],
            ['votacao_id' => 1, 'categoria_id' => 11, 'candidato_inscricao_id' => 202]
        );
        $response = $endpoints->registrarVoto($request);

        self::assertInstanceOf(WP_REST_Response::class, $response);
        self::assertSame(201, $response->get_status());

        $data = $response->get_data();
        self::assertSame(1, $data['votacao_id']);
        self::assertSame(11, $data['categoria_id']);
        self::assertNotEmpty($data['hash_voto']);
        self::assertSame(64, strlen($data['hash_voto']));

        // ANTI-RASTREIO: nenhuma chave do response pode revelar o eleitor.
        $jsonResponse = (string) json_encode($data);
        self::assertStringNotContainsString('"agente_id"', $jsonResponse);
        self::assertStringNotContainsString('"user_id"', $jsonResponse);
        self::assertStringNotContainsString('"eleitor_hash"', $jsonResponse);
        // Especificamente: o hash do recibo NÃO bate com o eleitor_hash interno
        // (isso comprovaria que dados de IP/segredo não vazaram).
        $eleitorHash = $this->hasher->hash(101, 1);
        self::assertNotSame($eleitorHash, $data['hash_voto']);
    }

    public function testSemAuthRetorna401(): void
    {
        $GLOBALS['__pi_test_current_user_id'] = 0;

        $endpoints = $this->makeEndpoints();

        $request = new WP_REST_Request(
            [],
            ['votacao_id' => 1, 'categoria_id' => 11, 'candidato_inscricao_id' => 202]
        );
        $response = $endpoints->registrarVoto($request);

        self::assertInstanceOf(WP_REST_Response::class, $response);
        self::assertSame(401, $response->get_status());
    }

    public function testAgenteNaoDeferidoRetorna403(): void
    {
        $this->agenteGateway->deferidos[101] = false;

        $endpoints = $this->makeEndpoints();
        $request   = new WP_REST_Request(
            [],
            ['votacao_id' => 1, 'categoria_id' => 11, 'candidato_inscricao_id' => 202]
        );
        $response = $endpoints->registrarVoto($request);

        self::assertSame(403, $response->get_status());
        $data = $response->get_data();
        // Mensagem genérica — não revela "está em análise" nem "é o usuário X".
        self::assertStringNotContainsString('agente_id', (string) json_encode($data));
    }

    public function testCategoriaQueNaoAceitaTipoRetorna403(): void
    {
        $this->categoriaGateway->tiposAceitos[11] = ['SM']; // só SM
        $this->agenteGateway->tipos[101] = 'PF';

        $endpoints = $this->makeEndpoints();
        $request   = new WP_REST_Request(
            [],
            ['votacao_id' => 1, 'categoria_id' => 11, 'candidato_inscricao_id' => 202]
        );
        $response = $endpoints->registrarVoto($request);

        self::assertSame(403, $response->get_status());
    }

    public function testVotoDuplicadoRetorna409ComMensagemGenerica(): void
    {
        $endpoints = $this->makeEndpoints();

        // Primeiro voto.
        $req1 = new WP_REST_Request(
            [],
            ['votacao_id' => 1, 'categoria_id' => 11, 'candidato_inscricao_id' => 202]
        );
        $endpoints->registrarVoto($req1);

        // Reseta rate limit (transient).
        $GLOBALS['__pi_test_transients'] = [];

        // Segundo voto — duplicado.
        $req2     = new WP_REST_Request(
            [],
            ['votacao_id' => 1, 'categoria_id' => 11, 'candidato_inscricao_id' => 202]
        );
        $response = $endpoints->registrarVoto($req2);

        self::assertSame(409, $response->get_status());
        $data = $response->get_data();
        // Mensagem genérica — NÃO revela eleitor_hash.
        self::assertStringContainsString(
            'já registrou voto',
            $data['message'] ?? ''
        );
        self::assertStringNotContainsString('eleitor_hash', (string) json_encode($data));
        self::assertStringNotContainsString('agente_id', (string) json_encode($data));
    }

    public function testVotacaoForaDaJanelaRetorna410(): void
    {
        // Substitui votação por uma com janela passada.
        $expirada = new Votacao(
            1,
            7,
            new DateTimeImmutable('2020-01-01 00:00:00', new DateTimeZone('UTC')),
            new DateTimeImmutable('2020-01-02 00:00:00', new DateTimeZone('UTC')),
            StatusVotacao::aberta(),
            ModoVotacao::porCategoria()
        );
        $this->votacaoRepo->save($expirada);

        $endpoints = $this->makeEndpoints();
        $request   = new WP_REST_Request(
            [],
            ['votacao_id' => 1, 'categoria_id' => 11, 'candidato_inscricao_id' => 202]
        );
        $response = $endpoints->registrarVoto($request);

        self::assertSame(410, $response->get_status());
    }

    public function testVotacaoEncerradaRetorna410(): void
    {
        $encerrada = new Votacao(
            1,
            7,
            new DateTimeImmutable('2026-06-10 10:00:00', new DateTimeZone('UTC')),
            new DateTimeImmutable('2030-06-10 18:00:00', new DateTimeZone('UTC')),
            StatusVotacao::encerrada(),
            ModoVotacao::porCategoria(),
            str_repeat('a', 64)
        );
        $this->votacaoRepo->save($encerrada);

        $endpoints = $this->makeEndpoints();
        $request   = new WP_REST_Request(
            [],
            ['votacao_id' => 1, 'categoria_id' => 11, 'candidato_inscricao_id' => 202]
        );
        $response = $endpoints->registrarVoto($request);

        self::assertSame(410, $response->get_status());
    }

    public function testElegibilidadeNaoVazaAgenteId(): void
    {
        $endpoints = $this->makeEndpoints();
        $request   = new WP_REST_Request(['id' => 1]);
        $response  = $endpoints->verificarElegibilidade($request);

        self::assertSame(200, $response->get_status());
        $data = $response->get_data();

        self::assertTrue($data['elegivel']);
        self::assertNull($data['motivo']);
        self::assertSame('aberta', $data['votacao_status']);
        self::assertCount(1, $data['categorias_elegiveis']);
        self::assertSame(11, $data['categorias_elegiveis'][0]['id']);
        self::assertFalse($data['categorias_elegiveis'][0]['ja_votou']);

        // ANTI-RASTREIO: response inteira não pode conter agente_id nem eleitor_hash.
        $payload = (string) json_encode($data);
        self::assertStringNotContainsString('"agente_id"', $payload);
        self::assertStringNotContainsString('"eleitor_hash"', $payload);
        self::assertStringNotContainsString('agente_id', array_keys($data['categorias_elegiveis'][0])[0]);
    }

    public function testElegibilidadeAgenteNaoDeferidoRetornaMotivo(): void
    {
        $this->agenteGateway->deferidos[101] = false;

        $endpoints = $this->makeEndpoints();
        $request   = new WP_REST_Request(['id' => 1]);
        $response  = $endpoints->verificarElegibilidade($request);

        self::assertSame(200, $response->get_status());
        $data = $response->get_data();

        self::assertFalse($data['elegivel']);
        self::assertSame(
            VerificarElegibilidadeHandler::MOTIVO_CADASTRO_NAO_DEFERIDO,
            $data['motivo']
        );
    }

    public function testStatusVotacaoNaoExpoeListaDeEleitores(): void
    {
        $endpoints = $this->makeEndpoints();
        $request   = new WP_REST_Request(['id' => 1]);
        $response  = $endpoints->statusVotacao($request);

        self::assertSame(200, $response->get_status());
        $data = $response->get_data();

        self::assertSame(1, $data['votacao_id']);
        self::assertSame('aberta', $data['status']);
        self::assertSame(0, $data['total_votos_registrados']);
        // Cache 30s.
        $headers = $response->get_headers();
        self::assertSame('public, max-age=30', $headers['Cache-Control']);

        // Sem PII.
        $payload = (string) json_encode($data);
        self::assertStringNotContainsString('"eleitor_hash"', $payload);
        self::assertStringNotContainsString('"agente_id"', $payload);
        self::assertStringNotContainsString('"votos"', $payload);
    }

    private function makeEndpoints(): VotacaoEndpoints
    {
        $registrarHandler = new RegistrarVotoHandler(
            $this->votacaoRepo,
            $this->votoRepo,
            $this->hasher,
            $this->agenteGateway,
            $this->categoriaGateway,
            $this->inscricaoGateway,
            $this->audit,
            null,
            static fn () => new DateTimeImmutable('2026-06-10 12:00:00', new DateTimeZone('UTC'))
        );

        $elegibilidadeHandler = new VerificarElegibilidadeHandler(
            $this->votacaoRepo,
            $this->votoRepo,
            $this->agenteGateway,
            $this->categoriaGateway,
            $this->hasher,
            static function (int $userId): ?int {
                return $userId === 5 ? 101 : null;
            },
            static function (int $catId): ?string {
                return $catId === 11 ? 'Categoria PF/OR' : null;
            }
        );

        return new VotacaoEndpoints(
            $registrarHandler,
            $elegibilidadeHandler,
            $this->votacaoRepo,
            $this->votoRepo,
            static function (int $userId): ?int {
                return $userId === 5 ? 101 : null;
            },
            static fn (int $vid): int => 100
        );
    }

    /**
     * @return object stub minimal de wpdb para AuditLogger.
     */
    private function createWpdbStub()
    {
        return new class {
            public string $prefix = 'wp_';
            public string $last_error = '';
            public int $insert_id = 0;
            /**
             * @param array<string,mixed> $data
             * @param array<int,string|null> $formats
             */
            public function insert(string $table, array $data, array $formats): bool
            {
                $GLOBALS['__pi_audit_inserts'][] = ['table' => $table, 'data' => $data];
                return true;
            }
        };
    }
}
