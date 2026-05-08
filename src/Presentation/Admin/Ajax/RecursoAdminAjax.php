<?php
/**
 * AJAX administrativo — decisão de Recursos (retratação + presidência).
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\Ajax
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\Ajax;

use Ibram\ParticipeIbram\Application\Cadastro\DecidirRecursoPresidenciaCommand;
use Ibram\ParticipeIbram\Application\Cadastro\DecidirRecursoPresidenciaHandler;
use Ibram\ParticipeIbram\Application\Cadastro\DecidirRetratacaoCommand;
use Ibram\ParticipeIbram\Application\Cadastro\DecidirRetratacaoHandler;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Core\Helpers\RateLimiter;
use Ibram\ParticipeIbram\Core\Helpers\RequestHelper;
use Ibram\ParticipeIbram\Domain\Analise\Recurso;
use Throwable;

/**
 * Endpoints AJAX para decisão de recursos. Pipeline padrão idêntico ao
 * {@see AdminAjaxRouter}: nonce escopado por user, capability check, rate
 * limit (60/min), wp_unslash via {@see RequestHelper}.
 *
 * Action names registradas: `pi_admin_decidir_retratacao`,
 * `pi_admin_decidir_presidencia`. Nonce action segue convenção:
 * `pi_admin_<action>_<userId>`.
 */
final class RecursoAdminAjax
{
    public const ACTION_RETRATACAO  = 'decidir_retratacao';
    public const ACTION_PRESIDENCIA = 'decidir_presidencia';

    public const CAP_RETRATACAO  = 'pi_analisar_cadastro';
    public const CAP_PRESIDENCIA = 'pi_decidir_recurso_presidencia';

    private DecidirRetratacaoHandler $retratacaoHandler;
    private DecidirRecursoPresidenciaHandler $presidenciaHandler;
    private AuditLogger $audit;

    public function __construct(
        DecidirRetratacaoHandler $retratacaoHandler,
        DecidirRecursoPresidenciaHandler $presidenciaHandler,
        AuditLogger $audit
    ) {
        $this->retratacaoHandler  = $retratacaoHandler;
        $this->presidenciaHandler = $presidenciaHandler;
        $this->audit              = $audit;
    }

    public static function nonceAction(string $action, int $userId): string
    {
        return 'pi_admin_' . $action . '_' . $userId;
    }

    public function registerHooks(): void
    {
        if (!function_exists('add_action')) {
            return;
        }
        \add_action('wp_ajax_pi_admin_decidir_retratacao', [$this, 'decidirRetratacao']);
        \add_action('wp_ajax_pi_admin_decidir_presidencia', [$this, 'decidirPresidencia']);
    }

    public function decidirRetratacao(): void
    {
        try {
            $userId = $this->guardAuth(self::CAP_RETRATACAO, self::ACTION_RETRATACAO);

            $recursoId    = (int) RequestHelper::request('recurso_id', 'absint', 0);
            $reconsiderar = ((int) RequestHelper::request('reconsiderar', 'absint', 0)) === 1;
            $decisaoMd    = (string) RequestHelper::request('decisao_md', 'wp_kses_post', '');

            if ($recursoId <= 0) {
                $this->sendError(400, 'pi_validation', \__('recurso_id obrigatório.', 'participe-ibram'));
                return;
            }
            if (mb_strlen(trim(strip_tags($decisaoMd))) < 50) {
                $this->sendError(400, 'pi_validation', \__('decisão deve ter pelo menos 50 caracteres.', 'participe-ibram'));
                return;
            }

            $command = new DecidirRetratacaoCommand($recursoId, $userId, $reconsiderar, $decisaoMd);
            $this->retratacaoHandler->handle($command);

            $this->audit->log('recurso', $recursoId, 'admin_decidir_retratacao_ajax', null, [
                'reconsiderar' => $reconsiderar,
            ], $userId);

            if (function_exists('do_action')) {
                \do_action(
                    'pi_recurso_decidido',
                    $recursoId,
                    Recurso::FASE_RETRATACAO,
                    $reconsiderar ? Recurso::DECISAO_RECONSIDERAR : Recurso::DECISAO_MANTER
                );
            }

            $this->sendSuccess([
                'recurso_id'   => $recursoId,
                'reconsiderar' => $reconsiderar,
            ]);
        } catch (Throwable $e) {
            $this->fromThrowable($e);
        }
    }

    public function decidirPresidencia(): void
    {
        try {
            $userId = $this->guardAuth(self::CAP_PRESIDENCIA, self::ACTION_PRESIDENCIA);

            $recursoId = (int) RequestHelper::request('recurso_id', 'absint', 0);
            $deferir   = ((int) RequestHelper::request('deferir', 'absint', 0)) === 1;
            $decisaoMd = (string) RequestHelper::request('decisao_md', 'wp_kses_post', '');

            if ($recursoId <= 0) {
                $this->sendError(400, 'pi_validation', \__('recurso_id obrigatório.', 'participe-ibram'));
                return;
            }
            if (mb_strlen(trim(strip_tags($decisaoMd))) < 50) {
                $this->sendError(400, 'pi_validation', \__('decisão deve ter pelo menos 50 caracteres.', 'participe-ibram'));
                return;
            }

            $command = new DecidirRecursoPresidenciaCommand($recursoId, $userId, $deferir, $decisaoMd);
            $this->presidenciaHandler->handle($command);

            $this->audit->log('recurso', $recursoId, 'admin_decidir_presidencia_ajax', null, [
                'deferir' => $deferir,
            ], $userId);

            if (function_exists('do_action')) {
                \do_action(
                    'pi_recurso_decidido',
                    $recursoId,
                    Recurso::FASE_PRESIDENCIA,
                    $deferir ? Recurso::DECISAO_DEFERIR : Recurso::DECISAO_INDEFERIR
                );
            }

            $this->sendSuccess([
                'recurso_id' => $recursoId,
                'deferir'    => $deferir,
            ]);
        } catch (Throwable $e) {
            $this->fromThrowable($e);
        }
    }

    /* =====================================================================
     * Internals
     * ===================================================================== */

    private function guardAuth(string $capability, string $action): int
    {
        if (!function_exists('get_current_user_id')) {
            $this->sendError(401, 'pi_unauthorized', 'Auth indisponível.');
            exit;
        }
        $userId = (int) \get_current_user_id();
        if ($userId <= 0) {
            $this->sendError(401, 'pi_unauthorized', \__('Autenticação requerida.', 'participe-ibram'));
            exit;
        }

        $nonceAction = self::nonceAction($action, $userId);
        if (!$this->verifyNonce($nonceAction)) {
            $this->sendError(403, 'pi_invalid_nonce', \__('Nonce inválido ou expirado.', 'participe-ibram'));
            exit;
        }
        if (!function_exists('current_user_can') || !\current_user_can($capability)) {
            $this->sendError(403, 'pi_forbidden', \__('Permissão negada.', 'participe-ibram'));
            exit;
        }

        $key = RateLimiter::keyForUser('admin_' . $action, $userId);
        if (!RateLimiter::check($key, 60, 60)) {
            $this->sendError(429, 'pi_rate_limited', \__('Muitas requisições.', 'participe-ibram'));
            exit;
        }

        return $userId;
    }

    private function verifyNonce(string $action): bool
    {
        if (function_exists('check_ajax_referer')) {
            $ok = \check_ajax_referer($action, '_wpnonce', false);
            return $ok !== false && (int) $ok > 0;
        }
        $nonce = (string) RequestHelper::request('_wpnonce', 'sanitize_text_field', '');
        return $nonce !== '' && function_exists('wp_verify_nonce') && (bool) \wp_verify_nonce($nonce, $action);
    }

    /**
     * @param array<string,mixed> $data
     */
    private function sendSuccess(array $data, int $status = 200): void
    {
        if (function_exists('wp_send_json_success')) {
            \wp_send_json_success($data, $status);
            return;
        }
        $this->emitJson(['success' => true, 'data' => $data], $status);
    }

    private function sendError(int $status, string $code, string $message): void
    {
        $payload = [
            'code'    => $code,
            'message' => $message,
            'data'    => ['status' => $status],
        ];
        if (function_exists('wp_send_json_error')) {
            \wp_send_json_error($payload, $status);
            return;
        }
        $this->emitJson(['success' => false, 'data' => $payload], $status);
    }

    private function fromThrowable(Throwable $e): void
    {
        if ($e instanceof \InvalidArgumentException || $e instanceof \DomainException) {
            $this->sendError(400, 'pi_validation', $e->getMessage());
            return;
        }
        $debug = \defined('WP_DEBUG') && \WP_DEBUG;
        $this->sendError(
            500,
            'pi_internal',
            $debug ? $e->getMessage() : \__('Erro interno.', 'participe-ibram')
        );
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function emitJson(array $payload, int $status): void
    {
        if (function_exists('status_header')) {
            \status_header($status);
        } elseif (!headers_sent()) {
            header('HTTP/1.1 ' . $status);
        }
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
