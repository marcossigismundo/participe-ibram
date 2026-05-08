<?php
/**
 * Testes de integração: WizardEndpoints (camada Presentation/Rest).
 *
 * @package Ibram\ParticipeIbram\Tests\Integration\Rest
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Integration\Rest;

use Ibram\ParticipeIbram\Application\Vocabulario\ListarVocabularioHandler;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Core\Network\IpResolver;
use Ibram\ParticipeIbram\Domain\Agente\Agente;
use Ibram\ParticipeIbram\Domain\Agente\AgenteRepository;
use Ibram\ParticipeIbram\Domain\Agente\StatusCadastro;
use Ibram\ParticipeIbram\Domain\Agente\TipoAgente;
use Ibram\ParticipeIbram\Domain\Documento\Documento;
use Ibram\ParticipeIbram\Domain\Documento\DocumentoRepository;
use Ibram\ParticipeIbram\Domain\Vocabulario\ItemVocabulario;
use Ibram\ParticipeIbram\Domain\Vocabulario\TipoVocabulario;
use Ibram\ParticipeIbram\Domain\Vocabulario\VocabularioRepository;
use Ibram\ParticipeIbram\Presentation\Rest\WizardEndpoints;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;
use WP_REST_Response;

/**
 * @covers \Ibram\ParticipeIbram\Presentation\Rest\WizardEndpoints
 * @covers \Ibram\ParticipeIbram\Presentation\Rest\Sanitizer
 * @covers \Ibram\ParticipeIbram\Presentation\Rest\RestException
 */
final class WizardEndpointsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['__pi_test_current_user_id'] = 0;
        $GLOBALS['__pi_test_user_caps']       = [];
        $GLOBALS['__pi_test_transients']      = [];
        $GLOBALS['__pi_test_rest_routes']     = [];
    }

    public function testSalvarRascunhoSemAuthRetorna401(): void
    {
        $endpoints = $this->makeEndpoints();
        $request   = new WP_REST_Request([], ['tipo' => 'PF', 'dados' => ['nome' => 'X']]);

        $response = $endpoints->salvarRascunho($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertSame(401, $response->get_status());
        $body = $response->get_data();
        $this->assertSame('pi_unauthorized', $body['code']);
    }

    public function testSalvarRascunhoTipoInvalidoRetorna400(): void
    {
        $GLOBALS['__pi_test_current_user_id'] = 7;
        $endpoints = $this->makeEndpoints(static fn (): int => 1);

        $request  = new WP_REST_Request([], ['tipo' => 'XX', 'dados' => ['x' => 1]]);
        $response = $endpoints->salvarRascunho($request);

        $this->assertSame(400, $response->get_status());
        $body = $response->get_data();
        $this->assertSame('pi_validation', $body['code']);
        $this->assertSame('tipo', $body['data']['details']['campo']);
    }

    public function testSalvarRascunhoSemHandlerRetorna503(): void
    {
        $GLOBALS['__pi_test_current_user_id'] = 7;
        $endpoints = $this->makeEndpoints(); // sem factories

        $request  = new WP_REST_Request([], ['tipo' => 'PF', 'dados' => ['nome' => 'X']]);
        $response = $endpoints->salvarRascunho($request);

        $this->assertSame(503, $response->get_status());
        $body = $response->get_data();
        $this->assertSame('pi_not_ready', $body['code']);
    }

    public function testSalvarRascunhoFelizRetornaAgenteId(): void
    {
        $GLOBALS['__pi_test_current_user_id'] = 7;
        $endpoints = $this->makeEndpoints(static fn (array $payload, int $userId): int => 42);

        $request  = new WP_REST_Request([], [
            'tipo'        => 'pf',
            'etapa_atual' => 2,
            'dados'       => ['nome_completo' => 'Maria', 'biografia' => 'Texto'],
        ]);
        $response = $endpoints->salvarRascunho($request);

        $this->assertSame(200, $response->get_status());
        $body = $response->get_data();
        $this->assertSame(42, $body['agente_id']);
        $this->assertSame('rascunho', $body['status']);
        $this->assertNotEmpty($body['rascunho_atualizado_em']);
    }

    public function testListarVocabularioRetornaCacheHeader(): void
    {
        $GLOBALS['__pi_test_current_user_id'] = 7;
        $endpoints = $this->makeEndpoints();

        $request  = new WP_REST_Request(['tipo' => TipoVocabulario::TIPOS_COLETIVO]);
        $response = $endpoints->listarVocabulario($request);

        $this->assertSame(200, $response->get_status());
        $headers = $response->get_headers();
        $this->assertArrayHasKey('Cache-Control', $headers);
        $this->assertSame('public, max-age=3600', $headers['Cache-Control']);
        $body = $response->get_data();
        $this->assertSame(TipoVocabulario::TIPOS_COLETIVO, $body['tipo']);
        $this->assertCount(1, $body['items']);
    }

    public function testListarVocabularioTipoInvalidoRetorna400(): void
    {
        $GLOBALS['__pi_test_current_user_id'] = 7;
        $endpoints = $this->makeEndpoints();

        $request  = new WP_REST_Request(['tipo' => 'inexistente']);
        $response = $endpoints->listarVocabulario($request);

        $this->assertSame(400, $response->get_status());
        $this->assertSame('pi_validation', $response->get_data()['code']);
    }

    /* =====================================================================
     * Fixtures
     * ===================================================================== */

    /**
     * @param callable|null $salvarRascunhoFactory
     * @param callable|null $submeterFactory
     */
    private function makeEndpoints(
        ?callable $salvarRascunhoFactory = null,
        ?callable $submeterFactory = null
    ): WizardEndpoints {
        $vocabRepo = new class implements VocabularioRepository {
            public function findById(int $id): ?ItemVocabulario { return null; }
            public function findByValor(string $tipo, string $valor): ?ItemVocabulario { return null; }
            public function listByTipo(string $tipo, bool $apenasAtivos = true): array
            {
                return [
                    new ItemVocabulario(1, $tipo, 'rede', 'Rede', null, 1, true, null),
                ];
            }
            public function save(ItemVocabulario $item): int { return 1; }
            public function delete(int $id): void { }
        };

        $agentesRepo = new class implements AgenteRepository {
            public function findById(int $id): ?Agente { return null; }
            public function findByNumeroRegistro(string $numero): ?Agente { return null; }
            public function findByCpf(string $cpfPlain): ?Agente { return null; }
            public function findByCnpj(string $cnpjPlain): ?Agente { return null; }
            public function findByUserId(int $userId): ?Agente { return null; }
            public function findByEmail(string $email): ?Agente { return null; }
            public function save(Agente $agente, object $detalhes, array $representantes = []): int { return 1; }
            public function softDelete(int $id): void { }
            public function listByStatus(string $status, int $page = 1, int $perPage = 25): array
            {
                return ['items' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage];
            }
        };

        $documentosRepo = new class implements DocumentoRepository {
            public function findById(int $id): Documento
            {
                throw new \Ibram\ParticipeIbram\Domain\Documento\DocumentoNotFound('not found');
            }
            public function findByAgente(int $agenteId): array { return []; }
            public function findByInscricao(int $inscricaoId): array { return []; }
            public function save(Documento $documento): int { return 1; }
            public function delete(int $id): void { }
        };

        // PrivateFileStorage e UploadDocumentoHandler são `final` — instanciamos
        // reais com base temporária. Os testes deste arquivo NÃO exercitam
        // upload, mas construir os objetos exige dependências reais.
        $tmpDir = sys_get_temp_dir() . '/pi-rest-test-' . uniqid('', true);
        @mkdir($tmpDir, 0750, true);

        $wpdbStub = new class {
            public string $prefix = 'wp_';
            /** @return false */
            public function insert(string $table, array $row, $formats = null)
            {
                return false;
            }
        };
        $audit = new AuditLogger($wpdbStub, new IpResolver([], []));
        $storage = new \Ibram\ParticipeIbram\Infrastructure\Storage\PrivateFileStorage($audit, $tmpDir);

        $tipoDocsRepo = new class implements \Ibram\ParticipeIbram\Domain\Documento\TipoDocumentoRepository {
            public function findById(int $id): \Ibram\ParticipeIbram\Domain\Documento\TipoDocumento
            {
                throw new \Ibram\ParticipeIbram\Domain\Documento\DocumentoNotFound('not used in test');
            }
            public function findByCodigo(string $codigo): \Ibram\ParticipeIbram\Domain\Documento\TipoDocumento
            {
                throw new \Ibram\ParticipeIbram\Domain\Documento\DocumentoNotFound('not used in test');
            }
            public function listAtivos(): array { return []; }
            public function findObrigatoriosPara(string $tipoAgente, bool $temCnpj = true): array { return []; }
        };
        $uploadHandler = new \Ibram\ParticipeIbram\Application\Documento\UploadDocumentoHandler(
            $tipoDocsRepo,
            $documentosRepo,
            $storage,
            $audit
        );

        return new WizardEndpoints(
            $uploadHandler,
            $documentosRepo,
            $agentesRepo,
            new ListarVocabularioHandler($vocabRepo),
            $storage,
            new IpResolver([], ['REMOTE_ADDR' => '127.0.0.1']),
            $audit,
            $salvarRascunhoFactory,
            $submeterFactory
        );
    }
}
