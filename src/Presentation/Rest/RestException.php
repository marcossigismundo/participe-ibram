<?php
/**
 * Exceção REST: erro estruturado serializável em WP_REST_Response.
 *
 * @package Ibram\ParticipeIbram\Presentation\Rest
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Rest;

use RuntimeException;
use Throwable;

/**
 * Exceção transportável que um endpoint REST emite para sinalizar uma falha
 * de negócio ou de validação ao cliente.
 *
 * Diferente de WP_Error, mantém:
 *  - código HTTP explícito (`status`)
 *  - código de erro de domínio (`code`, ex.: `pi_validation`)
 *  - mensagem i18n-ready
 *  - payload extra (`details`) opcional, p.ex. lista de campos com erro
 *
 * Em produção (`WP_DEBUG=false`) NUNCA serialize $wpdb->last_error nem stack
 * trace — somente {@see code()}, {@see status()}, {@see message()} e
 * {@see details()} são expostos via {@see toResponse()}.
 *
 * Uso típico (Wave 3):
 *   throw RestException::validation('Campo obrigatório.', ['campo' => 'tipo']);
 *   throw RestException::unauthorized();
 *   throw RestException::forbidden();
 *   throw RestException::notFound('Agente não encontrado.');
 *   throw RestException::tooManyRequests(60);
 */
final class RestException extends RuntimeException
{
    /**
     * Código de erro de domínio (machine-readable).
     */
    private string $errorCode;

    /**
     * HTTP status (4xx/5xx).
     */
    private int $status;

    /**
     * Detalhes adicionais (campos com erro, retry-after, etc.).
     *
     * @var array<string,mixed>
     */
    private array $details;

    /**
     * Segundos sugeridos para Retry-After (apenas 429/503).
     */
    private ?int $retryAfter;

    /**
     * @param string                $message
     * @param string                $errorCode  Código machine-readable (`pi_*`).
     * @param int                   $status     Status HTTP.
     * @param array<string,mixed>   $details
     * @param int|null              $retryAfter Segundos.
     * @param Throwable|null        $previous
     */
    public function __construct(
        string $message,
        string $errorCode = 'pi_error',
        int $status = 400,
        array $details = [],
        ?int $retryAfter = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->errorCode  = $errorCode !== '' ? $errorCode : 'pi_error';
        $this->status     = ($status >= 400 && $status < 600) ? $status : 500;
        $this->details    = $details;
        $this->retryAfter = $retryAfter !== null && $retryAfter > 0 ? $retryAfter : null;
    }

    /**
     * @param array<string,mixed> $details
     */
    public static function validation(string $message, array $details = []): self
    {
        return new self($message, 'pi_validation', 400, $details);
    }

    public static function unauthorized(?string $message = null): self
    {
        $msg = $message ?? (function_exists('__')
            ? \__('Autenticação requerida.', 'participe-ibram')
            : 'Autenticação requerida.');

        return new self($msg, 'pi_unauthorized', 401);
    }

    public static function forbidden(?string $message = null): self
    {
        $msg = $message ?? (function_exists('__')
            ? \__('Permissão negada.', 'participe-ibram')
            : 'Permissão negada.');

        return new self($msg, 'pi_forbidden', 403);
    }

    public static function notFound(?string $message = null): self
    {
        $msg = $message ?? (function_exists('__')
            ? \__('Recurso não encontrado.', 'participe-ibram')
            : 'Recurso não encontrado.');

        return new self($msg, 'pi_not_found', 404);
    }

    public static function conflict(string $message): self
    {
        return new self($message, 'pi_conflict', 409);
    }

    public static function tooManyRequests(int $retryAfterSeconds): self
    {
        $msg = function_exists('__')
            ? \__('Muitas requisições. Tente novamente em instantes.', 'participe-ibram')
            : 'Muitas requisições. Tente novamente em instantes.';

        return new self($msg, 'pi_rate_limited', 429, [], max(1, $retryAfterSeconds));
    }

    public static function internal(?string $message = null): self
    {
        $msg = $message ?? (function_exists('__')
            ? \__('Erro interno. Tente novamente mais tarde.', 'participe-ibram')
            : 'Erro interno. Tente novamente mais tarde.');

        return new self($msg, 'pi_internal', 500);
    }

    public function code(): string
    {
        return $this->errorCode;
    }

    public function status(): int
    {
        return $this->status;
    }

    /**
     * @return array<string,mixed>
     */
    public function details(): array
    {
        return $this->details;
    }

    public function retryAfter(): ?int
    {
        return $this->retryAfter;
    }

    /**
     * Serializa em formato de resposta REST consistente.
     *
     * Estrutura:
     * ```
     * {
     *   "code": "pi_validation",
     *   "message": "...",
     *   "data": { "status": 400, "details": {...} }
     * }
     * ```
     *
     * @return \WP_REST_Response
     */
    public function toResponse()
    {
        $payload = [
            'code'    => $this->errorCode,
            'message' => $this->getMessage(),
            'data'    => [
                'status'  => $this->status,
                'details' => $this->details,
            ],
        ];

        if (class_exists(\WP_REST_Response::class)) {
            $response = new \WP_REST_Response($payload, $this->status);
            if ($this->retryAfter !== null) {
                $response->header('Retry-After', (string) $this->retryAfter);
            }

            return $response;
        }

        // Fallback (testes sem WP).
        return $payload;
    }
}
