<?php
/**
 * Integration tests — RecursoInabilitacaoPublicController (W5-C).
 *
 * Cobre:
 *  - Agente que não é dono da inscrição → 403
 *  - Após prazo expirado → 422 com mensagem clara (delegado ao handler)
 *  - Sucesso → recurso criado, action disparada
 *
 * @package Ibram\ParticipeIbram\Tests\Integration\Public
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Integration\Public;

use Ibram\ParticipeIbram\Application\Edital\ProtocolarRecursoInabilitacaoHandler;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Domain\Edital\Inscricao;
use Ibram\ParticipeIbram\Domain\Edital\StatusInscricao;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbInscricaoRepository;
use Ibram\ParticipeIbram\Presentation\Public\Controllers\RecursoInabilitacaoPublicController;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once __DIR__ . '/../../bootstrap.php';

if (!function_exists('Ibram\ParticipeIbram\Tests\Integration\Public\wp_send_json_success')) {
    // Guard — funções globais podem já existir de outra suite.
}

if (!function_exists('wp_send_json_success')) {
    /**
     * @param array<string,mixed> $data
     */
    function wp_send_json_success(array $data, int $status = 200): void
    {
        $GLOBALS['__pi_test_last_response'] = ['success' => true, 'status' => $status, 'data' => $data];
        throw new PubAjaxExitException('success ' . $status);
    }
}
if (!function_exists('wp_send_json_error')) {
    /**
     * @param array<string,mixed> $payload
     */
    function wp_send_json_error(array $payload, int $status = 400): void
    {
        $GLOBALS['__pi_test_last_response'] = ['success' => false, 'status' => $status, 'data' => $payload];
        throw new PubAjaxExitException('error ' . $status);
    }
}
if (!function_exists('check_ajax_referer')) {
    function check_ajax_referer(string $action, string $key = '_wpnonce', bool $die = true)
    {
        $expected = $GLOBALS['__pi_test_valid_nonce'] ?? null;
        $provided = $_REQUEST[$key] ?? '';
        return $expected !== null && $expected === $provided ? 1 : false;
    }
}
if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce(string $nonce, string $action): bool
    {
        $expected = $GLOBALS['__pi_test_valid_nonce'] ?? null;
        return $expected !== null && $expected === $nonce;
    }
}
if (!function_exists('do_action')) {
    function do_action(string $hook, ...$args): void
    {
        $GLOBALS['__pi_test_actions'][] = ['hook' => $hook, 'args' => $args];
    }
}
if (!function_exists('add_action')) {
    function add_action(string $hook, callable $callback, int $priority = 10, int $accepted = 1): bool
    {
        return true;
    }
}
if (!function_exists('status_header')) {
    function status_header(int $code): void { }
}

final class PubAjaxExitException extends RuntimeException {}

/**
 * @covers \Ibram\ParticipeIbram\Presentation\Public\Controllers\RecursoInabilitacaoPublicController
 */
final class RecursoInabilitacaoPublicTest extends TestCase
{
    /** @var ProtocolarRecursoInabilitacaoHandler&MockObject */
    private $handler;
    /** @var WpdbInscricaoRepository&MockObject */
    private $inscricoesRepo;
    /** @var AuditLogger&MockObject */
    private $audit;

    private RecursoInabilitacaoPublicController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['__pi_test_current_user_id'] = 0;
        $GLOBALS['__pi_test_actions']         = [];
        $GLOBALS['__pi_test_last_response']   = null;
        $GLOBALS['__pi_test_valid_nonce']     = null;
        $GLOBALS['__pi_test_transients']      = [];
        $_REQUEST = [];
        $_POST    = [];
        $_GET     = [];

        $this->handler        = $this->createMock(ProtocolarRecursoInabilitacaoHandler::class);
        $this->inscricoesRepo = $this->createMock(WpdbInscricaoRepository::class);
        $this->audit          = $this->createMock(AuditLogger::class);

        $this->controller = new RecursoInabilitacaoPublicController(
            $this->handler,
            $this->inscricoesRepo,
            $this->audit
        );
    }

    /**
     * Agente que não é dono da inscrição → 403.
     */
    public function test_nao_dono_da_inscricao_retorna_403(): void
    {
        $userId = 10;
        $GLOBALS['__pi_test_current_user_id'] = $userId;
        $GLOBALS['__pi_test_valid_nonce']     = 'NONCE_OK';
        $_REQUEST['_wpnonce']                 = 'NONCE_OK';
        $_REQUEST['inscricao_id']             = '55';
        $_REQUEST['fundamentacao_md']         = str_repeat('a', 60);
        $_POST = $_REQUEST;

        // Inscrição pertence ao agente ID 999, não ao userId 10.
        $inscricaoMock = $this->createMockInscricao(55, 999);
        $this->inscricoesRepo->method('findById')->with(55)->willReturn($inscricaoMock);

        try {
            $this->controller->protocolar();
            $this->fail('expected exit');
        } catch (PubAjaxExitException $e) {
            // expected
        }

        $resp = $GLOBALS['__pi_test_last_response'];
        $this->assertNotNull($resp);
        $this->assertSame(403, $resp['status']);
        $this->assertSame('pi_forbidden', $resp['data']['code']);
    }

    /**
     * Handler lança DomainException (prazo expirado) → 422 com mensagem clara.
     */
    public function test_prazo_expirado_retorna_422_com_mensagem(): void
    {
        $userId = 10;
        $GLOBALS['__pi_test_current_user_id'] = $userId;
        $GLOBALS['__pi_test_valid_nonce']     = 'NONCE_OK';
        $_REQUEST['_wpnonce']                 = 'NONCE_OK';
        $_REQUEST['inscricao_id']             = '55';
        $_REQUEST['fundamentacao_md']         = str_repeat('a', 60);
        $_POST = $_REQUEST;

        $inscricaoMock = $this->createMockInscricao(55, $userId);
        $this->inscricoesRepo->method('findById')->with(55)->willReturn($inscricaoMock);

        $this->handler->method('handle')
            ->willThrowException(new \DomainException('Prazo para recurso de inabilitacao expirou em 2026-01-01 00:00.'));

        try {
            $this->controller->protocolar();
            $this->fail('expected exit');
        } catch (PubAjaxExitException $e) {
            // expected
        }

        $resp = $GLOBALS['__pi_test_last_response'];
        $this->assertNotNull($resp);
        $this->assertSame(422, $resp['status']);
        $this->assertSame('pi_domain', $resp['data']['code']);
        // A mensagem deve conter informação de prazo.
        $this->assertStringContainsString('Prazo', $resp['data']['message']);
    }

    /**
     * Sucesso → recurso criado, action pi_recurso_inabilitacao_protocolado disparada.
     */
    public function test_sucesso_cria_recurso_e_dispara_action(): void
    {
        $userId = 10;
        $GLOBALS['__pi_test_current_user_id'] = $userId;
        $GLOBALS['__pi_test_valid_nonce']     = 'NONCE_OK';
        $_REQUEST['_wpnonce']                 = 'NONCE_OK';
        $_REQUEST['inscricao_id']             = '55';
        $_REQUEST['fundamentacao_md']         = str_repeat('Fundamentacao detalhada do recurso. ', 3);
        $_POST = $_REQUEST;

        $inscricaoMock = $this->createMockInscricao(55, $userId);
        $this->inscricoesRepo->method('findById')->with(55)->willReturn($inscricaoMock);

        $this->handler->expects(self::once())
            ->method('handle')
            ->with(55, self::anything(), $userId)
            ->willReturn(77); // recurso ID criado

        $this->audit->expects(self::atLeast(1))
            ->method('log');

        try {
            $this->controller->protocolar();
        } catch (PubAjaxExitException $e) {
            // expected
        }

        $resp = $GLOBALS['__pi_test_last_response'];
        $this->assertNotNull($resp);
        $this->assertTrue($resp['success']);
        $this->assertSame(77, $resp['data']['recurso_id']);
        $this->assertSame(55, $resp['data']['inscricao_id']);

        $actions = $GLOBALS['__pi_test_actions'];
        $matched = array_filter(
            $actions,
            static fn (array $a): bool => $a['hook'] === 'pi_recurso_inabilitacao_protocolado'
        );
        $this->assertNotEmpty($matched, 'pi_recurso_inabilitacao_protocolado nao foi disparado');
        $hookCall = array_values($matched)[0];
        $this->assertSame(77, $hookCall['args'][0]);
        $this->assertSame(55, $hookCall['args'][1]);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function createMockInscricao(int $inscricaoId, int $agenteId): Inscricao
    {
        $now = new \DateTimeImmutable('now');
        return new Inscricao(
            $inscricaoId,
            1,   // editalId
            1,   // categoriaId
            $agenteId,
            null,
            StatusInscricao::fromString(StatusInscricao::INABILITADO),
            $now,
            null,
            $now,
            'Documentação incompleta conforme edital editado.',
            $now,
            $now
        );
    }
}
