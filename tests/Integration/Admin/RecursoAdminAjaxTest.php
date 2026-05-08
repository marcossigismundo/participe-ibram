<?php
/**
 * Integration tests for {@see Ibram\ParticipeIbram\Presentation\Admin\Ajax\RecursoAdminAjax}.
 *
 * Cobre:
 *  - cap retratacao tentando acessar endpoint de presidencia => 403
 *  - nonce invalido => 403
 *  - sucesso retratacao => audit registrado, action `pi_recurso_decidido`
 *    disparada com fase=retratacao
 *
 * @package Ibram\ParticipeIbram\Tests\Integration\Admin
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Integration\Admin;

use Ibram\ParticipeIbram\Application\Cadastro\DecidirRecursoPresidenciaCommand;
use Ibram\ParticipeIbram\Application\Cadastro\DecidirRecursoPresidenciaHandler;
use Ibram\ParticipeIbram\Application\Cadastro\DecidirRetratacaoCommand;
use Ibram\ParticipeIbram\Application\Cadastro\DecidirRetratacaoHandler;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Presentation\Admin\Ajax\RecursoAdminAjax;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once __DIR__ . '/../../bootstrap.php';

if (!function_exists('wp_send_json_success')) {
    /**
     * @param array<string,mixed> $data
     */
    function wp_send_json_success(array $data, int $status = 200): void
    {
        $GLOBALS['__pi_test_last_response'] = ['success' => true, 'status' => $status, 'data' => $data];
        throw new \Ibram\ParticipeIbram\Tests\Integration\Admin\AjaxExitException('success ' . $status);
    }
}
if (!function_exists('wp_send_json_error')) {
    /**
     * @param array<string,mixed> $payload
     */
    function wp_send_json_error(array $payload, int $status = 400): void
    {
        $GLOBALS['__pi_test_last_response'] = ['success' => false, 'status' => $status, 'data' => $payload];
        throw new \Ibram\ParticipeIbram\Tests\Integration\Admin\AjaxExitException('error ' . $status);
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

final class AjaxExitException extends RuntimeException {}

/**
 * @covers \Ibram\ParticipeIbram\Presentation\Admin\Ajax\RecursoAdminAjax
 */
final class RecursoAdminAjaxTest extends TestCase
{
    /** @var DecidirRetratacaoHandler&MockObject */
    private $retratacaoHandler;
    /** @var DecidirRecursoPresidenciaHandler&MockObject */
    private $presidenciaHandler;
    /** @var AuditLogger&MockObject */
    private $audit;

    private RecursoAdminAjax $ajax;

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

        $this->retratacaoHandler  = $this->createMock(DecidirRetratacaoHandler::class);
        $this->presidenciaHandler = $this->createMock(DecidirRecursoPresidenciaHandler::class);
        $this->audit              = $this->createMock(AuditLogger::class);

        $this->ajax = new RecursoAdminAjax(
            $this->retratacaoHandler,
            $this->presidenciaHandler,
            $this->audit
        );
    }

    public function test_capability_retratacao_nao_autoriza_endpoint_presidencia(): void
    {
        $GLOBALS['__pi_test_current_user_id'] = 7;
        $GLOBALS['__pi_test_user_caps']       = ['pi_analisar_cadastro']; // sem cap presidencia
        $nonceAction = RecursoAdminAjax::nonceAction(RecursoAdminAjax::ACTION_PRESIDENCIA, 7);
        $GLOBALS['__pi_test_valid_nonce']     = 'NONCE_OK';
        $_REQUEST['_wpnonce']  = 'NONCE_OK';
        $_REQUEST['recurso_id'] = '10';
        $_REQUEST['deferir']   = '1';
        $_REQUEST['decisao_md'] = str_repeat('a', 60);

        try {
            $this->ajax->decidirPresidencia();
            $this->fail('expected exit');
        } catch (AjaxExitException $e) {
            // expected
        }

        $resp = $GLOBALS['__pi_test_last_response'];
        $this->assertNotNull($resp);
        $this->assertSame(403, $resp['status']);
        $this->assertSame('pi_forbidden', $resp['data']['code']);
    }

    public function test_nonce_invalido_retorna_403(): void
    {
        $GLOBALS['__pi_test_current_user_id'] = 7;
        $GLOBALS['__pi_test_user_caps']       = ['pi_analisar_cadastro'];
        $GLOBALS['__pi_test_valid_nonce']     = 'CORRETO';
        $_REQUEST['_wpnonce']  = 'ERRADO';
        $_REQUEST['recurso_id'] = '10';
        $_REQUEST['reconsiderar'] = '1';
        $_REQUEST['decisao_md'] = str_repeat('a', 60);

        try {
            $this->ajax->decidirRetratacao();
            $this->fail('expected exit');
        } catch (AjaxExitException $e) {
            // expected
        }

        $resp = $GLOBALS['__pi_test_last_response'];
        $this->assertSame(403, $resp['status']);
        $this->assertSame('pi_invalid_nonce', $resp['data']['code']);
    }

    public function test_sucesso_retratacao_dispara_action_e_audita(): void
    {
        $GLOBALS['__pi_test_current_user_id'] = 7;
        $GLOBALS['__pi_test_user_caps']       = ['pi_analisar_cadastro'];
        $GLOBALS['__pi_test_valid_nonce']     = 'NONCE_OK';
        $_REQUEST['_wpnonce']  = 'NONCE_OK';
        $_REQUEST['recurso_id'] = '42';
        $_REQUEST['reconsiderar'] = '1';
        $_REQUEST['decisao_md'] = str_repeat('a', 60);
        // Para POST tambem (RequestHelper::request olha POST primeiro).
        $_POST = $_REQUEST;

        $this->retratacaoHandler->expects(self::once())
            ->method('handle')
            ->with(self::callback(static function (DecidirRetratacaoCommand $cmd): bool {
                return $cmd->recursoId() === 42 && $cmd->reconsiderar() === true && $cmd->analistaId() === 7;
            }))
            ->willReturn(42);

        $this->audit->expects(self::once())
            ->method('log')
            ->with('recurso', 42, 'admin_decidir_retratacao_ajax');

        try {
            $this->ajax->decidirRetratacao();
        } catch (AjaxExitException $e) {
            // expected after wp_send_json_success
        }

        $resp = $GLOBALS['__pi_test_last_response'];
        $this->assertNotNull($resp);
        $this->assertTrue($resp['success']);
        $this->assertSame(42, $resp['data']['recurso_id']);
        $this->assertTrue($resp['data']['reconsiderar']);

        $actions = $GLOBALS['__pi_test_actions'];
        $matched = array_filter($actions, static fn (array $a): bool => $a['hook'] === 'pi_recurso_decidido');
        $this->assertNotEmpty($matched, 'pi_recurso_decidido nao foi disparado');
        $hookCall = array_values($matched)[0];
        $this->assertSame(42, $hookCall['args'][0]);
        $this->assertSame('retratacao', $hookCall['args'][1]);
        $this->assertSame('reconsiderar', $hookCall['args'][2]);
    }
}
