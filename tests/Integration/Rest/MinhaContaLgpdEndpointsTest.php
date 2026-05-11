<?php
/**
 * Testes de integração — MinhaContaLgpdEndpoints (Wave 8 / W8-B).
 *
 * Foco do teste:
 *  - Ownership: user A NUNCA pode operar dados de user B.
 *  - Finalidade obrigatória → 422.
 *  - Reaceitar → registra novo consentimento.
 *  - Solicitação Art. 18 sem ownership → 403.
 *  - Export sem senha correta → 401.
 *  - Export rate-limit 1/dia → 2ª chamada retorna 429.
 *
 * @package Ibram\ParticipeIbram\Tests\Integration\Rest
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Integration\Rest;

use DateTimeImmutable;
use Ibram\ParticipeIbram\Application\Consentimento\RegistrarConsentimentoHandler;
use Ibram\ParticipeIbram\Application\Consentimento\RevogarConsentimentoHandler;
use Ibram\ParticipeIbram\Application\Lgpd\ConfirmarAnonimizacaoHandler;
use Ibram\ParticipeIbram\Application\Lgpd\SolicitarAnonimizacaoHandler;
use Ibram\ParticipeIbram\Application\Lgpd\SolicitarExportDadosHandler;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Core\Network\IpResolver;
use Ibram\ParticipeIbram\Domain\Agente\Agente;
use Ibram\ParticipeIbram\Domain\Agente\AgenteRepository;
use Ibram\ParticipeIbram\Domain\Agente\TipoAgente;
use Ibram\ParticipeIbram\Domain\Agente\StatusCadastro;
use Ibram\ParticipeIbram\Domain\Consentimento\ConsentimentoRepository;
use Ibram\ParticipeIbram\Domain\Consentimento\Finalidade;
use Ibram\ParticipeIbram\Domain\Consentimento\SolicitacaoTitular;
use Ibram\ParticipeIbram\Domain\Consentimento\SolicitacaoTitularRepository;
use Ibram\ParticipeIbram\Domain\Consentimento\Termo;
use Ibram\ParticipeIbram\Domain\Consentimento\TermoRepository;
use Ibram\ParticipeIbram\Presentation\Public\MinhaConta\OwnershipResolver;
use Ibram\ParticipeIbram\Presentation\Rest\MinhaContaLgpdEndpoints;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;
use WP_REST_Response;

/**
 * @covers \Ibram\ParticipeIbram\Presentation\Rest\MinhaContaLgpdEndpoints
 */
final class MinhaContaLgpdEndpointsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['__pi_test_transients']      = [];
        $GLOBALS['__pi_test_current_user_id'] = 0;
        $GLOBALS['__pi_test_user_caps']       = ['read'];
    }

    // ─── Ownership: user logado sem cadastro → 403 ───────────────────────

    public function testRevogarSemCadastroRetorna403(): void
    {
        $GLOBALS['__pi_test_current_user_id'] = 50;

        $endpoints = $this->makeEndpoints(['agenteIdForUser' => null]);

        $req = new WP_REST_Request(['finalidade' => 'mapeamento']);
        $resp = $endpoints->revogarConsentimento($req);

        $this->assertInstanceOf(WP_REST_Response::class, $resp);
        $this->assertSame(403, $resp->get_status());
    }

    // ─── Revogar finalidade obrigatória → 422 ────────────────────────────

    public function testRevogarFinalidadeObrigatoriaRetorna422(): void
    {
        $GLOBALS['__pi_test_current_user_id'] = 50;
        $endpoints = $this->makeEndpoints(['agenteIdForUser' => 99]);

        $req = new WP_REST_Request(['finalidade' => Finalidade::IDENTIFICACAO]);
        $resp = $endpoints->revogarConsentimento($req);

        $this->assertInstanceOf(WP_REST_Response::class, $resp);
        $this->assertSame(422, $resp->get_status());
        $data = $resp->get_data();
        $this->assertSame('pi_finalidade_obrigatoria', $data['code'] ?? '');
        $this->assertStringContainsStringIgnoringCase('obrig', $data['message'] ?? '');
    }

    // ─── Reaceitar invoca registrar com finalidade nova ──────────────────

    public function testReaceitarRegistraNovoConsentimento(): void
    {
        $GLOBALS['__pi_test_current_user_id'] = 50;

        $termo = new Termo(
            7,
            'v2.0',
            '# Termo v2',
            str_repeat('a', 64),
            new DateTimeImmutable('-1 day'),
            null,
            1
        );
        $termoRepo = $this->createMock(TermoRepository::class);
        $termoRepo->method('findAtivoCorrente')->willReturn($termo);
        $termoRepo->method('findById')->willReturn($termo);

        $captured = [];
        $registrar = $this->createMock(RegistrarConsentimentoHandler::class);
        $registrar->expects($this->once())
            ->method('handle')
            ->willReturnCallback(function ($cmd) use (&$captured) {
                $captured['command'] = $cmd;
                return [Finalidade::MAPEAMENTO => 999];
            });

        $endpoints = $this->makeEndpoints([
            'agenteIdForUser' => 99,
            'termoRepo'       => $termoRepo,
            'registrar'       => $registrar,
        ]);

        $req = new WP_REST_Request(['finalidade' => Finalidade::MAPEAMENTO]);
        $resp = $endpoints->reaceitarConsentimento($req);

        $this->assertInstanceOf(WP_REST_Response::class, $resp);
        $this->assertSame(200, $resp->get_status());
        $data = $resp->get_data();
        $this->assertSame(999, $data['consentimento_id'] ?? null);
        $this->assertSame('aceito', $data['status'] ?? '');

        $this->assertArrayHasKey('command', $captured, 'RegistrarConsentimentoHandler::handle deve ser chamado.');
    }

    // ─── Solicitação Art. 18 sem ownership → 403 ─────────────────────────

    public function testDetalheSolicitacaoOutroAgenteRetorna403(): void
    {
        $GLOBALS['__pi_test_current_user_id'] = 50;

        // Solicitação pertence ao agente 77, mas o usuário é dono do 99.
        $solic = SolicitacaoTitular::fromState(
            12,
            77,
            SolicitacaoTitular::TIPO_ACESSO,
            'detalhes',
            SolicitacaoTitular::STATUS_ABERTA,
            null,
            new DateTimeImmutable('-1 day'),
            null,
            null
        );

        $solicRepo = $this->createMock(SolicitacaoTitularRepository::class);
        $solicRepo->method('findById')->with(12)->willReturn($solic);

        $endpoints = $this->makeEndpoints([
            'agenteIdForUser' => 99,
            'solicRepo'       => $solicRepo,
        ]);

        $req = new WP_REST_Request(['id' => 12]);
        $resp = $endpoints->detalheSolicitacao($req);

        $this->assertInstanceOf(WP_REST_Response::class, $resp);
        $this->assertSame(403, $resp->get_status());
    }

    // ─── Export sem senha correta → 401 ─────────────────────────────────

    public function testExportSemSenhaCorretaRetorna401(): void
    {
        $GLOBALS['__pi_test_current_user_id'] = 50;

        // password checker que sempre falha.
        $endpoints = $this->makeEndpoints([
            'agenteIdForUser'    => 99,
            'passwordChecker'    => static fn (string $p, string $h, int $u): bool => false,
            'userLookup'         => static fn (int $u) => ['ID' => $u, 'user_login' => 'foo', 'user_pass' => 'irrelevant'],
        ]);

        $req = new WP_REST_Request([], ['confirmacao_senha' => 'errada']);
        $resp = $endpoints->exportarDados($req);

        $this->assertInstanceOf(WP_REST_Response::class, $resp);
        $this->assertSame(401, $resp->get_status());
    }

    // ─── Export rate-limit 1/dia → 2ª chamada retorna 429 ───────────────

    public function testExportRateLimit1PorDiaRetorna429NaSegundaChamada(): void
    {
        $GLOBALS['__pi_test_current_user_id'] = 50;

        $solicExport = $this->createMock(SolicitarExportDadosHandler::class);
        $solicExport->method('handle')->willReturn([
            'solicitacao_id' => 10,
            'download_url'   => 'https://x.test/?sig=abc',
            'expira_em'      => (new DateTimeImmutable('+1 day'))->format(DATE_ATOM),
        ]);

        $endpoints = $this->makeEndpoints([
            'agenteIdForUser'    => 99,
            'passwordChecker'    => static fn (string $p, string $h, int $u): bool => true,
            'userLookup'         => static fn (int $u) => ['ID' => $u, 'user_login' => 'foo', 'user_pass' => 'hash'],
            'solicExport'        => $solicExport,
        ]);

        // 1ª chamada — OK.
        $req1 = new WP_REST_Request([], ['confirmacao_senha' => 'correta']);
        $resp1 = $endpoints->exportarDados($req1);
        $this->assertInstanceOf(WP_REST_Response::class, $resp1);
        $this->assertSame(200, $resp1->get_status());

        // 2ª chamada na mesma janela — 429.
        $req2 = new WP_REST_Request([], ['confirmacao_senha' => 'correta']);
        $resp2 = $endpoints->exportarDados($req2);
        $this->assertInstanceOf(WP_REST_Response::class, $resp2);
        $this->assertSame(429, $resp2->get_status());
    }

    // ─── Listar consentimentos: whitelist (sem campos extras) ────────────

    public function testListarConsentimentosWhitelistOnly(): void
    {
        $GLOBALS['__pi_test_current_user_id'] = 50;

        $termo = new Termo(7, 'v2', '# t', str_repeat('a', 64), new DateTimeImmutable('-1 day'), null, 1);
        $termoRepo = $this->createMock(TermoRepository::class);
        $termoRepo->method('findById')->willReturn($termo);
        $termoRepo->method('findAtivoCorrente')->willReturn($termo);

        $consRepo = $this->createMock(ConsentimentoRepository::class);
        $consRepo->method('findTodosPorAgente')->willReturn([]);

        $endpoints = $this->makeEndpoints([
            'agenteIdForUser' => 99,
            'termoRepo'       => $termoRepo,
            'consRepo'        => $consRepo,
        ]);

        $resp = $endpoints->listarConsentimentos(new WP_REST_Request());
        $this->assertInstanceOf(WP_REST_Response::class, $resp);
        $this->assertSame(200, $resp->get_status());
        $data = $resp->get_data();
        $this->assertArrayHasKey('items', $data);
        $this->assertCount(count(Finalidade::values()), $data['items']);

        $allowedKeys = ['finalidade','label','descricao','status','registrado_em','termo_id','termo_versao','base_legal','obrigatoria','sensivel','revogavel','reaceitavel'];
        foreach ($data['items'] as $item) {
            foreach (array_keys($item) as $k) {
                $this->assertContains($k, $allowedKeys, sprintf('Campo "%s" não está na whitelist.', $k));
            }
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    /**
     * @param array<string,mixed> $opts
     */
    private function makeEndpoints(array $opts = []): MinhaContaLgpdEndpoints
    {
        $agenteIdForUser = $opts['agenteIdForUser'] ?? null;

        $ownership = $this->makeOwnershipResolver($agenteIdForUser);

        $consRepo  = $opts['consRepo']  ?? $this->createMock(ConsentimentoRepository::class);
        $termoRepo = $opts['termoRepo'] ?? $this->createMock(TermoRepository::class);
        $solicRepo = $opts['solicRepo'] ?? $this->createMock(SolicitacaoTitularRepository::class);
        $registrar = $opts['registrar'] ?? $this->createMock(RegistrarConsentimentoHandler::class);
        $revogar   = $opts['revogar']   ?? $this->createMock(RevogarConsentimentoHandler::class);
        $solicAnon = $opts['solicAnon'] ?? $this->createMock(SolicitarAnonimizacaoHandler::class);
        $confAnon  = $opts['confAnon']  ?? $this->createMock(ConfirmarAnonimizacaoHandler::class);
        $solicExport = $opts['solicExport'] ?? $this->createMock(SolicitarExportDadosHandler::class);

        $audit = $this->createMock(AuditLogger::class);
        $ipResolver = new IpResolver([], ['REMOTE_ADDR' => '127.0.0.1']);

        $passwordChecker = $opts['passwordChecker'] ?? static fn (string $p, string $h, int $u): bool => true;
        $userLookup = $opts['userLookup'] ?? static fn (int $u) => ['ID' => $u, 'user_login' => 'foo', 'user_pass' => 'hash'];

        return new MinhaContaLgpdEndpoints(
            $ownership,
            $consRepo,
            $termoRepo,
            $solicRepo,
            $registrar,
            $revogar,
            $solicAnon,
            $confAnon,
            $solicExport,
            $audit,
            $ipResolver,
            $passwordChecker,
            $userLookup
        );
    }

    private function makeOwnershipResolver(?int $agenteIdForUser): OwnershipResolver
    {
        $agenteRepo = $this->createMock(AgenteRepository::class);
        if ($agenteIdForUser === null) {
            $agenteRepo->method('findByUserId')->willReturn(null);
        } else {
            $now = new DateTimeImmutable('now');
            $agente = new Agente(
                $agenteIdForUser,
                TipoAgente::pf(),
                null,
                StatusCadastro::rascunho(),
                50,
                'titular@example.test',
                null,
                null,
                null,
                null,
                $now,
                $now,
                null
            );
            $agenteRepo->method('findByUserId')->willReturn($agente);
        }

        return new OwnershipResolver($agenteRepo, $this->createMock(AuditLogger::class));
    }
}
