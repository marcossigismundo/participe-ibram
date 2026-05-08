<?php
/**
 * Controller público — protocolar recurso de inabilitação (W5-C).
 *
 * @package Ibram\ParticipeIbram\Presentation\Public\Controllers
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Public\Controllers;

use Ibram\ParticipeIbram\Application\Edital\ProtocolarRecursoInabilitacaoHandler;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Core\Helpers\RateLimiter;
use Ibram\ParticipeIbram\Core\Helpers\RequestHelper;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbInscricaoRepository;
use Throwable;

/**
 * Registra o endpoint AJAX público `pi_recurso_inabilitacao_protocolar`.
 *
 * Validações (R5 V-06):
 *  1. Nonce escopado `pi_pub_recurso_inabilitacao_<userId>`.
 *  2. Usuário logado e é o dono da inscrição (agente_id = get_current_user_id()).
 *  3. Status inscrição = INABILITADO.
 *  4. Prazo recursal não expirado (via Edital::prazoRecursoInabilitacao — delegado ao handler).
 *  5. Rate limit: 3/min por usuário.
 */
final class RecursoInabilitacaoPublicController
{
    public const AJAX_ACTION = 'pi_recurso_inabilitacao_protocolar';

    private const NONCE_ACTION_PREFIX = 'pi_pub_recurso_inabilitacao_';
    private const RATE_LIMIT_MAX      = 3;
    private const RATE_LIMIT_SECONDS  = 60;

    private ProtocolarRecursoInabilitacaoHandler $handler;
    private WpdbInscricaoRepository $inscricoesRepo;
    private AuditLogger $audit;

    public function __construct(
        ProtocolarRecursoInabilitacaoHandler $handler,
        WpdbInscricaoRepository $inscricoesRepo,
        AuditLogger $audit
    ) {
        $this->handler        = $handler;
        $this->inscricoesRepo = $inscricoesRepo;
        $this->audit          = $audit;
    }

    public static function nonceAction(int $userId): string
    {
        return self::NONCE_ACTION_PREFIX . $userId;
    }

    public function registerHooks(): void
    {
        if (!function_exists('add_action')) {
            return;
        }
        \add_action('wp_ajax_' . self::AJAX_ACTION, [$this, 'protocolar']);
        // Não exposto para usuários não logados.
    }

    public function protocolar(): void
    {
        try {
            $userId = $this->guardAuth();

            $inscricaoId    = (int) RequestHelper::request('inscricao_id', 'absint', 0);
            $fundamentacaoMd = (string) RequestHelper::request('fundamentacao_md', 'wp_kses_post', '');

            if ($inscricaoId <= 0) {
                $this->sendError(400, 'pi_validation', \__('inscricao_id obrigatório.', 'participe-ibram'));
                return;
            }
            if (mb_strlen(trim(strip_tags($fundamentacaoMd))) < 50) {
                $this->sendError(422, 'pi_validation', \__('fundamentacao_md deve ter pelo menos 50 caracteres.', 'participe-ibram'));
                return;
            }

            // Verificar que o agente logado é o dono da inscrição — 403 caso contrário.
            $inscricao = $this->inscricoesRepo->findById($inscricaoId);
            if ($inscricao === null) {
                $this->sendError(404, 'pi_not_found', \__('Inscrição não encontrada.', 'participe-ibram'));
                return;
            }
            if ($inscricao->agenteId() !== $userId) {
                $this->audit->log('recurso_inabilitacao', null, 'protocolar_acesso_negado', null, [
                    'inscricao_id' => $inscricaoId,
                    'agente_id'    => $inscricao->agenteId(),
                ], $userId);
                $this->sendError(403, 'pi_forbidden', \__('Você não é o dono desta inscrição.', 'participe-ibram'));
                return;
            }

            // ProtocolarRecursoInabilitacaoHandler valida status INABILITADO e prazo.
            $recursoId = $this->handler->handle($inscricaoId, $fundamentacaoMd, $userId);

            // Audit adicional da camada de apresentação.
            $this->audit->log(
                'recurso_inabilitacao',
                $recursoId,
                'protocolar_public_ajax',
                null,
                ['inscricao_id' => $inscricaoId],
                $userId
            );

            if (function_exists('do_action')) {
                \do_action('pi_recurso_inabilitacao_protocolado', $recursoId, $inscricaoId);
            }

            $this->sendSuccess([
                'recurso_id'   => $recursoId,
                'inscricao_id' => $inscricaoId,
            ]);
        } catch (Throwable $e) {
            $this->fromThrowable($e);
        }
    }

    /* =====================================================================
     * Internals
     * ===================================================================== */

    private function guardAuth(): int
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

        if (!$this->verifyNonce(self::nonceAction($userId))) {
            $this->sendError(403, 'pi_invalid_nonce', \__('Nonce inválido ou expirado.', 'participe-ibram'));
            exit;
        }

        $key = RateLimiter::keyForUser(self::AJAX_ACTION, $userId);
        if (!RateLimiter::check($key, self::RATE_LIMIT_MAX, self::RATE_LIMIT_SECONDS)) {
            $this->sendError(429, 'pi_rate_limited', \__('Muitas requisições. Aguarde um minuto.', 'participe-ibram'));
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
        if ($e instanceof \DomainException) {
            // Mensagens de domínio como "prazo expirado" são user-safe.
            $this->sendError(422, 'pi_domain', $e->getMessage());
            return;
        }
        if ($e instanceof \InvalidArgumentException) {
            $this->sendError(400, 'pi_validation', $e->getMessage());
            return;
        }
        $debug = \defined('WP_DEBUG') && \WP_DEBUG;
        $this->sendError(500, 'pi_internal', $debug ? $e->getMessage() : \__('Erro interno.', 'participe-ibram'));
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
