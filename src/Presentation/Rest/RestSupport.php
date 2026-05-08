<?php
/**
 * Trait com utilidades compartilhadas pelos endpoints REST.
 *
 * @package Ibram\ParticipeIbram\Presentation\Rest
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Rest;

use Ibram\ParticipeIbram\Core\Helpers\RateLimiter;
use Throwable;

/**
 * Helpers comuns: respostas, rate limit, captura de erros, callbacks de auth.
 *
 * Todo endpoint deve:
 *  1. Garantir auth via `permissionLoggedIn()`/`permissionCapability()`.
 *  2. Aplicar `enforceRateLimit()` no início do callback.
 *  3. Sanitizar input via {@see Sanitizer::sanitizeNested()}.
 *  4. Em caso de erro, usar `errorResponse()` (delega `RestException::toResponse()`).
 */
trait RestSupport
{
    /**
     * `permission_callback` para "qualquer usuário autenticado".
     *
     * @return callable(): (bool|\WP_Error)
     */
    protected function permissionLoggedIn(): callable
    {
        return static function () {
            if (!function_exists('is_user_logged_in') || !\is_user_logged_in()) {
                if (class_exists(\WP_Error::class)) {
                    return new \WP_Error(
                        'pi_unauthorized',
                        function_exists('__') ? \__('Autenticação requerida.', 'participe-ibram') : 'Autenticação requerida.',
                        ['status' => 401]
                    );
                }
                return false;
            }

            return true;
        };
    }

    /**
     * `permission_callback` para uma capability específica (R5 V-06).
     *
     * @return callable(): (bool|\WP_Error)
     */
    protected function permissionCapability(string $capability): callable
    {
        return static function () use ($capability) {
            if (!function_exists('current_user_can') || !\current_user_can($capability)) {
                if (class_exists(\WP_Error::class)) {
                    return new \WP_Error(
                        'pi_forbidden',
                        function_exists('__') ? \__('Permissão negada.', 'participe-ibram') : 'Permissão negada.',
                        ['status' => 403]
                    );
                }
                return false;
            }

            return true;
        };
    }

    /**
     * `permission_callback` aberta — uso APENAS para endpoints estritamente
     * públicos sem PII (ex.: agentes deferidos).
     *
     * @return callable(): bool
     */
    protected function permissionPublic(): callable
    {
        return static fn (): bool => true;
    }

    /**
     * Aplica rate limit por usuário (ou IP-hash quando anônimo).
     *
     * Em violação, lança {@see RestException::tooManyRequests()}.
     *
     * @throws RestException
     */
    protected function enforceRateLimit(string $action, int $maxRequests, int $windowSeconds = 60): void
    {
        $userId = function_exists('get_current_user_id') ? (int) \get_current_user_id() : 0;
        if ($userId > 0) {
            $key = RateLimiter::keyForUser($action, $userId);
        } else {
            $key = RateLimiter::keyForIp($action);
        }

        if (!RateLimiter::check($key, $maxRequests, $windowSeconds)) {
            throw RestException::tooManyRequests($windowSeconds);
        }
    }

    /**
     * Empacota uma resposta bem-sucedida em `WP_REST_Response` com cache opcional.
     *
     * @param mixed $payload
     *
     * @return \WP_REST_Response|array<string,mixed>
     */
    protected function ok($payload, int $status = 200, int $cacheSeconds = 0)
    {
        if (class_exists(\WP_REST_Response::class)) {
            $response = new \WP_REST_Response($payload, $status);
            if ($cacheSeconds > 0) {
                $response->header('Cache-Control', 'public, max-age=' . $cacheSeconds);
            } else {
                $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
                $response->header('Pragma', 'no-cache');
            }

            return $response;
        }

        return ['data' => $payload, 'status' => $status];
    }

    /**
     * Converte qualquer Throwable em resposta REST consistente.
     *
     * Mensagens internas (DB, fs) são mascaradas em produção (R5 V-16).
     *
     * @return \WP_REST_Response|array<string,mixed>
     */
    protected function handleThrowable(Throwable $e)
    {
        if ($e instanceof RestException) {
            return $e->toResponse();
        }

        if (
            $e instanceof \InvalidArgumentException
            || $e instanceof \DomainException
        ) {
            return RestException::validation($e->getMessage())->toResponse();
        }

        // Em debug expomos a mensagem; em produção, mensagem genérica.
        $debug = \defined('WP_DEBUG') && \WP_DEBUG;
        $msg   = $debug ? $e->getMessage() : (function_exists('__')
            ? \__('Erro interno. Tente novamente mais tarde.', 'participe-ibram')
            : 'Erro interno.');

        return RestException::internal($msg)->toResponse();
    }

    /**
     * Lê o body JSON de WP_REST_Request com fallback para `get_params`.
     *
     * @param object $request WP_REST_Request (typed loose para testabilidade).
     *
     * @return array<string,mixed>
     */
    protected function readJsonBody(object $request): array
    {
        if (method_exists($request, 'get_json_params')) {
            $params = $request->get_json_params();
            if (is_array($params)) {
                return $params;
            }
        }
        if (method_exists($request, 'get_body_params')) {
            $params = $request->get_body_params();
            if (is_array($params)) {
                return $params;
            }
        }
        if (method_exists($request, 'get_params')) {
            $params = $request->get_params();
            if (is_array($params)) {
                return $params;
            }
        }

        return [];
    }
}
