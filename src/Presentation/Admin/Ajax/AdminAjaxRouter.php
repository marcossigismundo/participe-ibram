<?php
/**
 * Roteador AJAX administrativo (`wp_ajax_pi_*`).
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\Ajax
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\Ajax;

use Ibram\ParticipeIbram\Core\Helpers\RateLimiter;
use Ibram\ParticipeIbram\Core\Helpers\RequestHelper;
use Throwable;

/**
 * Centraliza handlers AJAX administrativos.
 *
 *  - `pi_listar_cadastros`  (cap `pi_listar_cadastros`)  — STUB 501 (Wave 4).
 *  - `pi_assumir_analise`   (cap `pi_analisar_cadastro`) — invoca handler.
 *
 * Pipeline padrão de cada action:
 *  1. Verifica nonce (`check_ajax_referer` com action escopada por user).
 *  2. Verifica capability.
 *  3. Aplica rate limit (60/min por user — analista).
 *  4. Lê parâmetros via `RequestHelper` (com `wp_unslash`).
 *  5. Invoca handler de Application; converte exceções em JSON error.
 *
 * Nonces emitidos via `wp_create_nonce('pi_admin_<action>_' . $userId)`. Os
 * helpers públicos {@see nonceAction()} expõem essa convenção para o JS de
 * apoio.
 */
final class AdminAjaxRouter
{
    public const CAP_LISTAR_CADASTROS = 'pi_listar_cadastros';
    public const CAP_ANALISAR         = 'pi_analisar_cadastro';

    /**
     * Handler `AssumirAnaliseHandler` injetado como callable para evitar
     * dependência forte com sub-pacote ainda em construção.
     *
     * Recebe `(agenteId, atorId)` → `array<string,mixed>` com snapshot
     * atualizado (status_cadastro, analista_id, etc.).
     *
     * @var callable(int,int): array<string,mixed>|null
     */
    private $assumirAnaliseFactory;

    /**
     * @param callable|null $assumirAnaliseFactory  Quando null retorna 503.
     */
    public function __construct(?callable $assumirAnaliseFactory = null)
    {
        $this->assumirAnaliseFactory = $assumirAnaliseFactory;
    }

    /**
     * Convenção pública de nome de action de nonce (compartilhada com o JS).
     */
    public static function nonceAction(string $action, int $userId): string
    {
        return 'pi_admin_' . $action . '_' . $userId;
    }

    /**
     * Registra os hooks AJAX. Idempotente.
     */
    public function registerHooks(): void
    {
        if (!function_exists('add_action')) {
            return;
        }

        // Apenas usuários autenticados — sem `wp_ajax_nopriv_*`.
        \add_action('wp_ajax_pi_listar_cadastros', [$this, 'listarCadastros']);
        \add_action('wp_ajax_pi_assumir_analise', [$this, 'assumirAnalise']);
    }

    /**
     * STUB Wave 4. Sempre retorna 501.
     */
    public function listarCadastros(): void
    {
        try {
            $userId = $this->guardAuth(self::CAP_LISTAR_CADASTROS, 'listar_cadastros');
            unset($userId);

            $this->sendError(
                501,
                'pi_not_implemented',
                function_exists('__') ? \__('Listar cadastros está disponível na Wave 4.', 'participe-ibram') : 'Wave 4.'
            );
        } catch (Throwable $e) {
            $this->fromThrowable($e);
        }
    }

    /**
     * `pi_assumir_analise` — analista assume um cadastro para análise.
     */
    public function assumirAnalise(): void
    {
        try {
            $userId = $this->guardAuth(self::CAP_ANALISAR, 'assumir_analise');

            $agenteId = (int) RequestHelper::request('agente_id', 'absint', 0);
            if ($agenteId <= 0) {
                $this->sendError(
                    400,
                    'pi_validation',
                    function_exists('__') ? \__('agente_id obrigatório.', 'participe-ibram') : 'agente_id.'
                );
                return;
            }

            if ($this->assumirAnaliseFactory === null) {
                $this->sendError(
                    503,
                    'pi_not_ready',
                    function_exists('__') ? \__('Handler de análise indisponível.', 'participe-ibram') : 'Indisponível.'
                );
                return;
            }
            $factory = $this->assumirAnaliseFactory;
            $out     = (array) $factory($agenteId, $userId);

            $this->sendSuccess($out);
        } catch (Throwable $e) {
            $this->fromThrowable($e);
        }
    }

    /* =====================================================================
     * Internals
     * ===================================================================== */

    /**
     * Pipeline auth: nonce + capability + rate limit. Devolve userId.
     */
    private function guardAuth(string $capability, string $action): int
    {
        if (!function_exists('get_current_user_id')) {
            $this->sendError(401, 'pi_unauthorized', 'Auth indisponível.');
            exit;
        }
        $userId = (int) \get_current_user_id();
        if ($userId <= 0) {
            $this->sendError(401, 'pi_unauthorized', function_exists('__') ? \__('Autenticação requerida.', 'participe-ibram') : 'Auth.');
            exit;
        }

        $nonceAction = self::nonceAction($action, $userId);
        if (!$this->verifyNonce($nonceAction)) {
            $this->sendError(403, 'pi_invalid_nonce', function_exists('__') ? \__('Nonce inválido ou expirado.', 'participe-ibram') : 'Nonce.');
            exit;
        }
        if (!function_exists('current_user_can') || !\current_user_can($capability)) {
            $this->sendError(403, 'pi_forbidden', function_exists('__') ? \__('Permissão negada.', 'participe-ibram') : 'Forbidden.');
            exit;
        }

        $key = RateLimiter::keyForUser('admin_' . $action, $userId);
        if (!RateLimiter::check($key, 60, 60)) {
            $this->sendError(429, 'pi_rate_limited', function_exists('__') ? \__('Muitas requisições.', 'participe-ibram') : 'Rate limit.');
            exit;
        }

        return $userId;
    }

    private function verifyNonce(string $action): bool
    {
        if (function_exists('check_ajax_referer')) {
            // false = não dá die em falha; retorna 0/1.
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

    private function sendError(int $status, string $code, string $message, array $details = []): void
    {
        $payload = [
            'code'    => $code,
            'message' => $message,
            'data'    => ['status' => $status, 'details' => $details],
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
            $debug ? $e->getMessage() : (function_exists('__') ? \__('Erro interno.', 'participe-ibram') : 'Erro.')
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
