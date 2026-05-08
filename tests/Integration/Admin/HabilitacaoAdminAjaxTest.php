<?php
/**
 * Integration tests para HabilitacaoAdminAjax (W5-C).
 *
 * Cobre:
 *  - cap mismatch → 403
 *  - nonce inválido → 403
 *  - inabilitar sem motivo → 422
 *  - sucesso habilitar → status atualizado, audit registrado, action `pi_habilitacao_decidida` disparada
 *
 * @package Ibram\ParticipeIbram\Tests\Integration\Admin
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Integration\Admin;

use Ibram\ParticipeIbram\Application\Edital\AvaliarHabilitacaoHandler;
use Ibram\ParticipeIbram\Application\Edital\DecidirRecursoInabilitacaoHandler;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Presentation\Admin\Ajax\HabilitacaoAdminAjax;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once __DIR__ . '/../../bootstrap.php';

// Stubs específicos desta suite — guard para não redefinir o que o RecursoAdminAjaxTest já definiu.
if (!function_exists('Ibram\ParticipeIbram\Tests\Integration\Admin\wp_send_json_success_habilitacao')) {
    // reutiliza as funções globais já declaradas em RecursoAdminAjaxTest se rodarem na mesma run.
}

// Garante que as funções globais necessárias existam — idempotente com guards.
if (!function_exists('wp_send_json_success')) {
    /**
     * @param array<string,mixed> $data
     */
    function wp_send_json_success(array $data, int $status = 200): void
    {
        $GLOBALS['__pi_test_last_response'] = ['success' => true, 'status' => $status, 'data' => $data];
        throw new HabAjaxExitException('success ' . $status);
    }
}
if (!function_exists('wp_send_json_error')) {
    /**
     * @param array<string,mixed> $payload
     */
    function wp_send_json_error(array $payload, int $status = 400): void
    {
        $GLOBALS['__pi_test_last_response'] = ['success' => false, 'status' => $status, 'data' => $payload];
        throw new HabAjaxExitException('error ' . $status);
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
        $GLOBALS['__pi_test_added_actions'][] = $hook;
        return true;
    }
}
if (!function_exists('status_header')) {
    function status_header(int $code): void { }
}
if (!function_exists('apply_filters')) {
    /** @return mixed */
    function apply_filters(string $hook, $value, ...$args)
    {
        return $value;
    }
}

final class HabAjaxExitException extends RuntimeException {}

/**
 * @covers \Ibram\ParticipeIbram\Presentation\Admin\Ajax\HabilitacaoAdminAjax
 */
final class HabilitacaoAdminAjaxTest extends TestCase
{
    /** @var AvaliarHabilitacaoHandler&MockObject */
    private $habHandler;
    /** @var DecidirRecursoInabilitacaoHandler&MockObject */
    private $recursoHandler;
    /** @var AuditLogger&MockObject */
    private $audit;

    private HabilitacaoAdminAjax $ajax;

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['__pi_test_current_user_id'] = 0;
        $GLOBALS['__pi_test_user_caps']       = [];
        $GLOBALS['__pi_test_actions']         = [];
        $GLOBALS['__pi_test_last_response']   = null;
        $GLOBALS['__pi_test_valid_nonce']     = null;
        $GLOBALS['__pi_test_transients']      = [];
        $_REQUEST = [];
        $_POST    = [];
        $_GET     = [];

        $this->habHandler     = $this->createMock(AvaliarHabilitacaoHandler::class);
        $this->recursoHandler = $this->createMock(DecidirRecursoInabilitacaoHandler::class);
        $this->audit          = $this->createMock(AuditLogger::class);

        $this->ajax = new HabilitacaoAdminAjax(
            $this->habHandler,
            $this->recursoHandler,
            $this->audit
        );
    }

    /** Cap ausente → 403. */
    public function test_cap_mismatch_retorna_403(): void
    {
        $GLOBALS['__pi_test_current_user_id'] = 5;
        $GLOBALS['__pi_test_user_caps']       = []; // sem pi_decidir_habilitacao
        $nonceAction = HabilitacaoAdminAjax::nonceAction(HabilitacaoAdminAjax::ACTION_HABILITAR, 5);
        $GLOBALS['__pi_test_valid_nonce'] = 'NONCE_OK';
        $_REQUEST['_wpnonce']             = 'NONCE_OK';
        $_REQUEST['inscricao_id']         = '10';
        $_POST = $_REQUEST;

        try {
            $this->ajax->habilitarInscricao();
            $this->fail('expected exit');
        } catch (HabAjaxExitException $e) {
            // expected
        }

        $resp = $GLOBALS['__pi_test_last_response'];
        $this->assertNotNull($resp);
        $this->assertSame(403, $resp['status']);
        $this->assertSame('pi_forbidden', $resp['data']['code']);
    }

    /** Nonce inválido → 403. */
    public function test_nonce_invalido_retorna_403(): void
    {
        $GLOBALS['__pi_test_current_user_id'] = 5;
        $GLOBALS['__pi_test_user_caps']       = [HabilitacaoAdminAjax::CAP];
        $GLOBALS['__pi_test_valid_nonce']     = 'CORRETO';
        $_REQUEST['_wpnonce']                 = 'ERRADO';
        $_REQUEST['inscricao_id']             = '10';
        $_POST = $_REQUEST;

        try {
            $this->ajax->habilitarInscricao();
            $this->fail('expected exit');
        } catch (HabAjaxExitException $e) {
            // expected
        }

        $resp = $GLOBALS['__pi_test_last_response'];
        $this->assertSame(403, $resp['status']);
        $this->assertSame('pi_invalid_nonce', $resp['data']['code']);
    }

    /** Inabilitar sem motivo (ou motivo curto) → 422. */
    public function test_inabilitar_sem_motivo_retorna_422(): void
    {
        $userId = 5;
        $GLOBALS['__pi_test_current_user_id'] = $userId;
        $GLOBALS['__pi_test_user_caps']       = [HabilitacaoAdminAjax::CAP];
        $GLOBALS['__pi_test_valid_nonce']     = 'NONCE_OK';
        $_REQUEST['_wpnonce']                 = 'NONCE_OK';
        $_REQUEST['inscricao_id']             = '20';
        $_REQUEST['motivo_inabilitacao_md']   = 'curto'; // < 50 chars
        $_POST = $_REQUEST;

        try {
            $this->ajax->inabilitarInscricao();
            $this->fail('expected exit');
        } catch (HabAjaxExitException $e) {
            // expected
        }

        $resp = $GLOBALS['__pi_test_last_response'];
        $this->assertNotNull($resp);
        $this->assertSame(422, $resp['status']);
        $this->assertSame('pi_validation', $resp['data']['code']);
    }

    /** Sucesso habilitar → handler chamado, audit registrado, action disparada. */
    public function test_sucesso_habilitar_audita_e_dispara_action(): void
    {
        $userId = 7;
        $GLOBALS['__pi_test_current_user_id'] = $userId;
        $GLOBALS['__pi_test_user_caps']       = [HabilitacaoAdminAjax::CAP];
        $GLOBALS['__pi_test_valid_nonce']     = 'NONCE_OK';
        $_REQUEST['_wpnonce']                 = 'NONCE_OK';
        $_REQUEST['inscricao_id']             = '42';
        $_POST = $_REQUEST;

        $this->habHandler->expects(self::once())
            ->method('handle')
            ->with(42, true, null, $userId);

        $this->audit->expects(self::once())
            ->method('log')
            ->with('inscricao', 42, 'admin_habilitar_ajax');

        try {
            $this->ajax->habilitarInscricao();
        } catch (HabAjaxExitException $e) {
            // expected
        }

        $resp = $GLOBALS['__pi_test_last_response'];
        $this->assertNotNull($resp);
        $this->assertTrue($resp['success']);
        $this->assertSame(42, $resp['data']['inscricao_id']);
        $this->assertSame('habilitar', $resp['data']['decisao']);

        $actions = $GLOBALS['__pi_test_actions'];
        $matched = array_filter($actions, static fn (array $a): bool => $a['hook'] === 'pi_habilitacao_decidida');
        $this->assertNotEmpty($matched, 'pi_habilitacao_decidida nao foi disparado');
        $hookCall = array_values($matched)[0];
        $this->assertSame(42, $hookCall['args'][0]);
        $this->assertSame('habilitar', $hookCall['args'][1]);
    }
}
