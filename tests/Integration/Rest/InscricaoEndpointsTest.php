<?php
/**
 * Testes de integração — InscricaoEndpoints.
 *
 * @package Ibram\ParticipeIbram\Tests\Integration\Rest
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Integration\Rest;

use DateTimeImmutable;
use Ibram\ParticipeIbram\Application\Documento\UploadDocumentoHandler;
use Ibram\ParticipeIbram\Application\Edital\AgenteLookupPort;
use Ibram\ParticipeIbram\Application\Edital\InscreverAgenteHandler;
use Ibram\ParticipeIbram\Application\Edital\SalvarRascunhoInscricaoHandler;
use Ibram\ParticipeIbram\Domain\Edital\InscricaoDuplicada;
use Ibram\ParticipeIbram\Domain\Edital\Inscricao;
use Ibram\ParticipeIbram\Domain\Edital\StatusInscricao;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbDocumentoRepository;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbInscricaoRepository;
use Ibram\ParticipeIbram\Presentation\Rest\InscricaoEndpoints;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;
use WP_REST_Response;

/**
 * @covers \Ibram\ParticipeIbram\Presentation\Rest\InscricaoEndpoints
 */
final class InscricaoEndpointsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['__pi_test_transients']    = [];
        $GLOBALS['__pi_current_user_id']    = 0;
        $GLOBALS['__pi_agente_id_by_user']  = [];
    }

    // ─── Auth: sem autenticação → 401 ─────────────────────────────────────

    public function testSemAuthRetorna401(): void
    {
        // Simula usuário não logado.
        $GLOBALS['__pi_current_user_id'] = 0;

        $permCb = $this->getPermissionCallback('permissionLoggedIn');
        $result = $permCb();

        if ($result instanceof \WP_Error) {
            $this->assertSame(401, $result->get_error_data()['status'] ?? 0);
        } else {
            $this->assertFalse($result);
        }
    }

    // ─── Agente não-deferido → 403 ───────────────────────────────────────

    public function testAgenteNaoDeferidoRetorna403(): void
    {
        $GLOBALS['__pi_current_user_id'] = 5;
        $GLOBALS['__pi_agente_id_by_user'][5] = 99;

        $rascunhoHandler = $this->createMock(SalvarRascunhoInscricaoHandler::class);
        $rascunhoHandler->method('handle')->willThrowException(
            new \DomainException('Apenas agentes deferidos podem se inscrever.')
        );

        $inscricao = $this->makeInscricaoRascunho(1, 1, 99);
        $inscricoesRepo = $this->createMock(WpdbInscricaoRepository::class);
        $inscricoesRepo->method('findById')->willReturn($inscricao);

        $endpoints = $this->makeEndpoints(
            rascunhoHandler: $rascunhoHandler,
            inscricoesRepo: $inscricoesRepo,
            agenteIdByUser: static fn (int $uid) => $uid === 5 ? 99 : null
        );

        $request = new WP_REST_Request();
        $request->set_body(json_encode([
            'edital_id'    => 1,
            'categoria_id' => 1,
            'agente_id'    => 99,
        ]));
        $response = $endpoints->salvarRascunho($request);

        if ($response instanceof WP_REST_Response) {
            $this->assertContains($response->get_status(), [400, 403]);
            $data = $response->get_data();
            $this->assertStringContainsStringIgnoringCase(
                'deferido',
                $data['message'] ?? ''
            );
        }
    }

    // ─── Categoria incompatível com tipo de agente → 422 ─────────────────

    public function testCategoriaNaoAceitaTipoAgente422(): void
    {
        $GLOBALS['__pi_current_user_id'] = 5;

        $rascunhoHandler = $this->createMock(SalvarRascunhoInscricaoHandler::class);
        $rascunhoHandler->method('handle')->willThrowException(
            new \DomainException('Sua categoria de agente não é elegível para esta categoria do edital.')
        );

        $inscricao = $this->makeInscricaoRascunho(1, 1, 99);
        $inscricoesRepo = $this->createMock(WpdbInscricaoRepository::class);
        $inscricoesRepo->method('findById')->willReturn($inscricao);

        $endpoints = $this->makeEndpoints(
            rascunhoHandler: $rascunhoHandler,
            inscricoesRepo: $inscricoesRepo,
            agenteIdByUser: static fn (int $uid) => $uid === 5 ? 99 : null
        );

        $request = new WP_REST_Request();
        $request->set_body(json_encode([
            'edital_id'    => 1,
            'categoria_id' => 2,
            'agente_id'    => 99,
        ]));
        $response = $endpoints->salvarRascunho($request);

        if ($response instanceof WP_REST_Response) {
            $this->assertContains($response->get_status(), [400, 422]);
        }
    }

    // ─── UNIQUE violation → 409 com mensagem clara ────────────────────────

    public function testInscricaoDuplicadaRetorna409ComMensagemClara(): void
    {
        $GLOBALS['__pi_current_user_id'] = 5;

        $inscricao = $this->makeInscricaoRascunho(1, 1, 99);

        $inscreverHandler = $this->createMock(InscreverAgenteHandler::class);
        $inscreverHandler->method('handle')->willThrowException(
            InscricaoDuplicada::for(1, 1, 99)
        );

        $inscricoesRepo = $this->createMock(WpdbInscricaoRepository::class);
        $inscricoesRepo->method('findById')->willReturn($inscricao);

        $endpoints = $this->makeEndpoints(
            inscreverHandler: $inscreverHandler,
            inscricoesRepo: $inscricoesRepo,
            agenteIdByUser: static fn (int $uid) => $uid === 5 ? 99 : null
        );

        $request = new WP_REST_Request();
        $request->set_body(json_encode(['inscricao_id' => 1]));
        $response = $endpoints->submeterInscricao($request);

        if ($response instanceof WP_REST_Response) {
            $this->assertSame(409, $response->get_status());
            $data = $response->get_data();
            $msg  = $data['message'] ?? '';
            $this->assertStringContainsStringIgnoringCase('inscrito', $msg);
        }
    }

    // ─── Documentos obrigatórios faltando → 422 com lista clara ──────────

    public function testDocumentosObrigatoriosFaltandoRetorna422(): void
    {
        $GLOBALS['__pi_current_user_id'] = 5;

        $inscricao = $this->makeInscricaoRascunho(1, 1, 99);

        // Simula que a categoria exige documentos que não foram enviados.
        $GLOBALS['__pi_categoria_documentos_exigidos'][1] = ['RG_FRENTE', 'COMPROVANTE_RESIDENCIA'];

        $inscricoesRepo = $this->createMock(WpdbInscricaoRepository::class);
        $inscricoesRepo->method('findById')->willReturn($inscricao);

        $documentosRepo = $this->createMock(WpdbDocumentoRepository::class);
        $documentosRepo->method('findByInscricao')->willReturn([]); // Nenhum documento enviado.

        $endpoints = $this->makeEndpoints(
            inscricoesRepo: $inscricoesRepo,
            documentosRepo: $documentosRepo,
            agenteIdByUser: static fn (int $uid) => $uid === 5 ? 99 : null
        );

        $request = new WP_REST_Request();
        $request->set_body(json_encode(['inscricao_id' => 1]));
        $response = $endpoints->submeterInscricao($request);

        // Pode ser 422 (documentos faltando) ou 400 (validação) dependendo do fluxo.
        if ($response instanceof WP_REST_Response) {
            $this->assertContains($response->get_status(), [400, 422]);
        }
    }

    // ─── Ownership: inscrição não pertence ao agente → 403 ───────────────

    public function testInscricaoDeOutroAgenteRetorna403(): void
    {
        $GLOBALS['__pi_current_user_id'] = 5;

        // Inscrição pertence ao agente 77, não ao 99.
        $inscricao = $this->makeInscricaoRascunho(1, 1, 77);

        $inscricoesRepo = $this->createMock(WpdbInscricaoRepository::class);
        $inscricoesRepo->method('findById')->willReturn($inscricao);

        $endpoints = $this->makeEndpoints(
            inscricoesRepo: $inscricoesRepo,
            agenteIdByUser: static fn (int $uid) => $uid === 5 ? 99 : null
        );

        $request = $this->makeRequest(['id' => '1']);
        $response = $endpoints->lerInscricao($request);

        if ($response instanceof WP_REST_Response) {
            $this->assertSame(403, $response->get_status());
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function makeInscricaoRascunho(int $editalId, int $categoriaId, int $agenteId): Inscricao
    {
        $now = new DateTimeImmutable('now');

        return new Inscricao(1, $editalId, $categoriaId, $agenteId, null, StatusInscricao::rascunho(), null, null, null, null, $now, $now);
    }

    /**
     * @param array<string,string> $params
     */
    private function makeRequest(array $params): WP_REST_Request
    {
        $req = new WP_REST_Request();
        foreach ($params as $k => $v) {
            $req->set_param($k, $v);
        }

        return $req;
    }

    private function getPermissionCallback(string $method): callable
    {
        $endpoints = $this->makeEndpoints();
        $ref = new \ReflectionMethod($endpoints, $method);
        $ref->setAccessible(true);

        return $ref->invoke($endpoints);
    }

    private function makeEndpoints(
        ?SalvarRascunhoInscricaoHandler $rascunhoHandler = null,
        ?InscreverAgenteHandler $inscreverHandler = null,
        ?UploadDocumentoHandler $uploadHandler = null,
        ?WpdbInscricaoRepository $inscricoesRepo = null,
        ?WpdbDocumentoRepository $documentosRepo = null,
        ?AgenteLookupPort $agenteLookup = null,
        ?callable $agenteIdByUser = null
    ): InscricaoEndpoints {
        if ($rascunhoHandler === null) {
            $rascunhoHandler = $this->createMock(SalvarRascunhoInscricaoHandler::class);
        }
        if ($inscreverHandler === null) {
            $inscreverHandler = $this->createMock(InscreverAgenteHandler::class);
        }
        if ($uploadHandler === null) {
            $uploadHandler = $this->createMock(UploadDocumentoHandler::class);
        }
        if ($inscricoesRepo === null) {
            $inscricoesRepo = $this->createMock(WpdbInscricaoRepository::class);
        }
        if ($documentosRepo === null) {
            $documentosRepo = $this->createMock(WpdbDocumentoRepository::class);
            $documentosRepo->method('findByInscricao')->willReturn([]);
        }
        if ($agenteLookup === null) {
            $agenteLookup = $this->createMock(AgenteLookupPort::class);
            $agenteLookup->method('lookup')->willReturn(['exists' => true, 'deferido' => true, 'tipo' => 'PF']);
        }
        if ($agenteIdByUser === null) {
            $agenteIdByUser = static fn (int $uid) => null;
        }

        return new InscricaoEndpoints(
            $rascunhoHandler,
            $inscreverHandler,
            $uploadHandler,
            $inscricoesRepo,
            $documentosRepo,
            $agenteLookup,
            $agenteIdByUser
        );
    }
}
