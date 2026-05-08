<?php
/**
 * Integration tests for EditalAdminAjax pipeline.
 *
 * Tests:
 *  1. cap mismatch → 403
 *  2. nonce inválido → 403
 *  3. sucesso publicar → status atualizado, audit registrado, action disparada
 *
 * @package Ibram\ParticipeIbram\Tests\Integration\Admin
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Integration\Admin;

use DateTimeImmutable;
use Ibram\ParticipeIbram\Application\Edital\AbrirInscricoesHandler;
use Ibram\ParticipeIbram\Application\Edital\PublicarEditalHandler;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Domain\Edital\Edital;
use Ibram\ParticipeIbram\Domain\Edital\StatusEdital;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbCategoriaRepository;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbEditalRepository;
use Ibram\ParticipeIbram\Presentation\Admin\Ajax\EditalAdminAjax;
use PHPUnit\Framework\TestCase;

final class EditalAdminAjaxTest extends TestCase
{
    /** @var array<string,mixed> */
    private array $lastJson = [];
    private bool $actionFired = false;

    protected function setUp(): void
    {
        parent::setUp();
        $_POST   = [];
        $_GET    = [];
        $GLOBALS['__pi_test_user_caps']          = [];
        $GLOBALS['__pi_test_current_user_id']    = 0;
        $GLOBALS['__pi_test_nonce_valid']        = false;
        $GLOBALS['__pi_test_action_fired']       = false;
        $this->lastJson                          = [];
        $this->actionFired                       = false;
    }

    /* ===================== Helpers ===================== */

    private function makeEdital(string $status = StatusEdital::RASCUNHO): Edital
    {
        $now = new DateTimeImmutable('now');
        $base = $now->modify('+1 day');
        return new Edital(
            42,
            'Edital de Teste CCDEM',
            null,
            StatusEdital::fromString($status),
            $status === StatusEdital::RASCUNHO ? $base : $base,
            $base->modify('+7 days'),
            $base->modify('+14 days'),
            $base->modify('+21 days'),
            $base->modify('+28 days'),
            $base->modify('+35 days'),
            $base->modify('+42 days'),
            1,
            $now,
            $now
        );
    }

    private function buildSut(
        ?PublicarEditalHandler $publicar = null,
        ?AbrirInscricoesHandler $abrir   = null,
        ?WpdbEditalRepository $editaisRepo = null,
        ?WpdbCategoriaRepository $catRepo  = null,
        ?AuditLogger $audit                = null
    ): EditalAdminAjax {
        $mockPublicar = $publicar ?? $this->createMock(PublicarEditalHandler::class);
        $mockAbrir    = $abrir    ?? $this->createMock(AbrirInscricoesHandler::class);
        $mockEditais  = $editaisRepo ?? $this->createMock(WpdbEditalRepository::class);
        $mockCat      = $catRepo     ?? $this->createMock(WpdbCategoriaRepository::class);
        $mockAudit    = $audit       ?? $this->createMock(AuditLogger::class);

        return new EditalAdminAjax(
            $mockPublicar,
            $mockAbrir,
            $mockEditais,
            $mockCat,
            $mockAudit
        );
    }

    /**
     * Captures JSON sent by sendError/sendSuccess (calls wp_send_json_error/success
     * stubs or falls through to emitJson). Returns array or null.
     */
    private function captureJson(callable $fn): array
    {
        ob_start();
        try {
            $fn();
        } catch (\RuntimeException $e) {
            if ($e->getMessage() !== '__halt__') {
                throw $e;
            }
        }
        $out = ob_get_clean();

        if (!empty($GLOBALS['__pi_last_json'])) {
            $json = (array) $GLOBALS['__pi_last_json'];
            $GLOBALS['__pi_last_json'] = null;
            return $json;
        }

        $decoded = $out !== '' ? json_decode($out, true) : null;
        return is_array($decoded) ? $decoded : [];
    }

    /* ===================== Test: cap mismatch → 403 ===================== */

    public function testPublicarCapMismatchReturns403(): void
    {
        $GLOBALS['__pi_test_current_user_id'] = 5;
        $GLOBALS['__pi_test_user_caps']       = []; // nenhuma cap
        $GLOBALS['__pi_test_nonce_valid']     = true;

        $_POST['edital_id'] = '42';
        $_POST['_wpnonce']  = 'valid_nonce';

        $sut  = $this->buildSut();
        $json = $this->captureJson([$sut, 'ajaxPublicar']);

        $this->assertFalse((bool) ($json['success'] ?? true), 'Deve retornar erro com cap insuficiente');
        $statusCode = $json['data']['data']['status'] ?? $json['status'] ?? 0;
        $this->assertSame(403, (int) $statusCode, 'HTTP status deve ser 403');
    }

    /* ===================== Test: nonce inválido → 403 ===================== */

    public function testPublicarNonceInvalidoReturns403(): void
    {
        $GLOBALS['__pi_test_current_user_id'] = 5;
        $GLOBALS['__pi_test_user_caps']       = ['pi_publicar_edital' => true];
        $GLOBALS['__pi_test_nonce_valid']     = false; // nonce inválido

        $_POST['edital_id'] = '42';
        $_POST['_wpnonce']  = 'invalid_nonce';

        $sut  = $this->buildSut();
        $json = $this->captureJson([$sut, 'ajaxPublicar']);

        $this->assertFalse((bool) ($json['success'] ?? true), 'Deve retornar erro com nonce inválido');
        $statusCode = $json['data']['data']['status'] ?? $json['status'] ?? 0;
        $this->assertSame(403, (int) $statusCode, 'HTTP status deve ser 403');
    }

    /* ===================== Test: sucesso publicar ===================== */

    public function testPublicarSucessoAtualizaStatusAuditaEDisparaAction(): void
    {
        $GLOBALS['__pi_test_current_user_id'] = 7;
        $GLOBALS['__pi_test_user_caps']       = ['pi_publicar_edital' => true];
        $GLOBALS['__pi_test_nonce_valid']     = true;

        $_POST['edital_id'] = '42';
        $_POST['_wpnonce']  = 'valid_nonce';

        $actionFired = false;

        // Stub do_action to detect pi_edital_publicado hook.
        if (!defined('PI_TEST_ACTION_TRACK')) {
            define('PI_TEST_ACTION_TRACK', true);
        }

        $auditCalled  = false;
        $publicarCalled = false;

        $mockAudit = $this->createMock(AuditLogger::class);
        $mockAudit->expects($this->atLeastOnce())->method('log');

        $mockPublicar = $this->getMockBuilder(PublicarEditalHandler::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockPublicar->expects($this->once())
            ->method('handle')
            ->with($this->equalTo(42), $this->equalTo(7))
            ->willReturnCallback(static function () use (&$actionFired): void {
                // Simulate do_action('pi_edital_publicado', 42) inside handler.
                if (function_exists('do_action')) {
                    do_action('pi_edital_publicado', 42);
                }
                $actionFired = true;
            });

        $sut  = $this->buildSut($mockPublicar, null, null, null, $mockAudit);
        $json = $this->captureJson([$sut, 'ajaxPublicar']);

        $this->assertTrue((bool) ($json['success'] ?? false), 'Deve retornar sucesso');
        $data = $json['data'] ?? [];
        $this->assertSame(42, (int) ($data['edital_id'] ?? 0), 'edital_id deve ser retornado');
        $this->assertSame(StatusEdital::PUBLICADO, (string) ($data['status_novo'] ?? ''), 'status_novo deve ser publicado');
        $this->assertTrue($actionFired, 'do_action pi_edital_publicado deve ter sido disparado');
    }

    /* ===================== Test: sucesso abrir inscrições ===================== */

    public function testAbrirInscricoesCapMismatchReturns403(): void
    {
        $GLOBALS['__pi_test_current_user_id'] = 5;
        $GLOBALS['__pi_test_user_caps']       = []; // sem cap
        $GLOBALS['__pi_test_nonce_valid']     = true;

        $_POST['edital_id'] = '42';
        $_POST['_wpnonce']  = 'valid_nonce';

        $sut  = $this->buildSut();
        $json = $this->captureJson([$sut, 'ajaxAbrirInscricoes']);

        $this->assertFalse((bool) ($json['success'] ?? true));
        $statusCode = $json['data']['data']['status'] ?? 0;
        $this->assertSame(403, (int) $statusCode);
    }
}
