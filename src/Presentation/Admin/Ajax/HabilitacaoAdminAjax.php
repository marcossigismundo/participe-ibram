<?php
/**
 * AJAX administrativo — Habilitação + Decisão de Recurso de Inabilitação (W5-C).
 *
 * Pipeline idêntico ao RecursoAdminAjax (W4-B):
 *  nonce escopado por user+cap → capability check → rate limit (5/min destrutivos)
 *  → audit → do_action.
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\Ajax
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\Ajax;

use Ibram\ParticipeIbram\Application\Edital\AvaliarHabilitacaoHandler;
use Ibram\ParticipeIbram\Application\Edital\DecidirRecursoInabilitacaoHandler;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Core\Helpers\RateLimiter;
use Ibram\ParticipeIbram\Core\Helpers\RequestHelper;
use Ibram\ParticipeIbram\Domain\Edital\RecursoInabilitacao;
use Throwable;

/**
 * Endpoints AJAX:
 *  - `pi_admin_habilitar_inscricao`        (cap `pi_decidir_habilitacao`)
 *  - `pi_admin_inabilitar_inscricao`       (cap `pi_decidir_habilitacao`)
 *  - `pi_admin_decidir_recurso_inabilitacao` (cap `pi_decidir_habilitacao`)
 *
 * Nonce escopado: `pi_admin_<action>_<userId>` (igual ao padrão W4-B).
 */
final class HabilitacaoAdminAjax
{
    public const ACTION_HABILITAR        = 'habilitar_inscricao';
    public const ACTION_INABILITAR       = 'inabilitar_inscricao';
    public const ACTION_DECIDIR_RECURSO  = 'decidir_recurso_inabilitacao';

    public const CAP = 'pi_decidir_habilitacao';

    /** Rate limit: máx. 5 ações destrutivas por minuto por usuário. */
    private const RATE_LIMIT_MAX     = 5;
    private const RATE_LIMIT_SECONDS = 60;

    private AvaliarHabilitacaoHandler $habilitacaoHandler;
    private DecidirRecursoInabilitacaoHandler $recursoHandler;
    private AuditLogger $audit;

    public function __construct(
        AvaliarHabilitacaoHandler $habilitacaoHandler,
        DecidirRecursoInabilitacaoHandler $recursoHandler,
        AuditLogger $audit
    ) {
        $this->habilitacaoHandler = $habilitacaoHandler;
        $this->recursoHandler     = $recursoHandler;
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
        \add_action('wp_ajax_pi_admin_habilitar_inscricao', [$this, 'habilitarInscricao']);
        \add_action('wp_ajax_pi_admin_inabilitar_inscricao', [$this, 'inabilitarInscricao']);
        \add_action('wp_ajax_pi_admin_decidir_recurso_inabilitacao', [$this, 'decidirRecursoInabilitacao']);
    }

    public function habilitarInscricao(): void
    {
        try {
            $userId      = $this->guardAuth(self::CAP, self::ACTION_HABILITAR);
            $inscricaoId = (int) RequestHelper::request('inscricao_id', 'absint', 0);

            if ($inscricaoId <= 0) {
                $this->sendError(400, 'pi_validation', \__('inscricao_id obrigatório.', 'participe-ibram'));
                return;
            }

            $this->habilitacaoHandler->handle($inscricaoId, true, null, $userId);

            $this->audit->log('inscricao', $inscricaoId, 'admin_habilitar_ajax', null, ['decisao' => 'habilitar'], $userId);

            if (function_exists('do_action')) {
                \do_action('pi_habilitacao_decidida', $inscricaoId, 'habilitar');
            }

            $this->sendSuccess(['inscricao_id' => $inscricaoId, 'decisao' => 'habilitar']);
        } catch (Throwable $e) {
            $this->fromThrowable($e);
        }
    }

    public function inabilitarInscricao(): void
    {
        try {
            $userId      = $this->guardAuth(self::CAP, self::ACTION_INABILITAR);
            $inscricaoId = (int) RequestHelper::request('inscricao_id', 'absint', 0);
            $motivoMd    = (string) RequestHelper::request('motivo_inabilitacao_md', 'wp_kses_post', '');

            if ($inscricaoId <= 0) {
                $this->sendError(400, 'pi_validation', \__('inscricao_id obrigatório.', 'participe-ibram'));
                return;
            }
            if (mb_strlen(trim(strip_tags($motivoMd))) < 50) {
                $this->sendError(422, 'pi_validation', \__('motivo_inabilitacao_md deve ter pelo menos 50 caracteres.', 'participe-ibram'));
                return;
            }

            $this->habilitacaoHandler->handle($inscricaoId, false, $motivoMd, $userId);

            $this->audit->log('inscricao', $inscricaoId, 'admin_inabilitar_ajax', null, ['decisao' => 'inabilitar'], $userId);

            if (function_exists('do_action')) {
                \do_action('pi_habilitacao_decidida', $inscricaoId, 'inabilitar');
            }

            $this->sendSuccess(['inscricao_id' => $inscricaoId, 'decisao' => 'inabilitar']);
        } catch (Throwable $e) {
            $this->fromThrowable($e);
        }
    }

    public function decidirRecursoInabilitacao(): void
    {
        try {
            $userId    = $this->guardAuth(self::CAP, self::ACTION_DECIDIR_RECURSO);
            $recursoId = (int) RequestHelper::request('recurso_id', 'absint', 0);
            $decisao   = (string) RequestHelper::request('decisao', 'sanitize_key', '');
            $decisaoMd = (string) RequestHelper::request('decisao_md', 'wp_kses_post', '');

            if ($recursoId <= 0) {
                $this->sendError(400, 'pi_validation', \__('recurso_id obrigatório.', 'participe-ibram'));
                return;
            }
            if (!in_array($decisao, [RecursoInabilitacao::DECISAO_DEFERIR, RecursoInabilitacao::DECISAO_MANTER], true)) {
                $this->sendError(400, 'pi_validation', \__('decisao deve ser "deferir" ou "manter".', 'participe-ibram'));
                return;
            }
            if (mb_strlen(trim(strip_tags($decisaoMd))) < 50) {
                $this->sendError(400, 'pi_validation', \__('decisao_md deve ter pelo menos 50 caracteres.', 'participe-ibram'));
                return;
            }

            $this->recursoHandler->handle($recursoId, $decisao, $decisaoMd, $userId);

            $deferido = $decisao === RecursoInabilitacao::DECISAO_DEFERIR;
            $this->audit->log(
                'recurso_inabilitacao',
                $recursoId,
                'admin_decidir_recurso_inabilitacao_ajax',
                null,
                ['decisao' => $decisao],
                $userId
            );

            if (function_exists('do_action')) {
                \do_action('pi_recurso_inabilitacao_decidido', $recursoId, $deferido);
            }

            $this->sendSuccess(['recurso_id' => $recursoId, 'decisao' => $decisao]);
        } catch (Throwable $e) {
            $this->fromThrowable($e);
        }
    }

    /* =====================================================================
     * Internals — idêntico ao pipeline do RecursoAdminAjax (W4-B)
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
        if (!RateLimiter::check($key, self::RATE_LIMIT_MAX, self::RATE_LIMIT_SECONDS)) {
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
        // Nunca expor $wpdb->last_error — pode conter dados de schema/PII.
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
