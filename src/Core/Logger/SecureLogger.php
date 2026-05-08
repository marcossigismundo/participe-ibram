<?php
/**
 * PSR-3-ish wrapper over `error_log` that masks PII before writing.
 *
 * @package Ibram\ParticipeIbram\Core\Logger
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Core\Logger;

use Ibram\ParticipeIbram\Core\Audit\PiiMasker;

/**
 * Safe application logger.
 *
 * Always passes the `$context` array through {@see PiiMasker} for known
 * PII keys (e-mail, CPF, CNPJ, phone, etc.) and never serialises objects via
 * `print_r` / `var_export` (R5 V-01).
 *
 * Output line: `[participe-ibram] [LEVEL] message {json_context}`.
 *
 * In production (`WP_DEBUG` falsy) only ERROR and WARNING are written.
 */
final class SecureLogger
{
    private const LEVEL_ERROR   = 'ERROR';
    private const LEVEL_WARNING = 'WARNING';
    private const LEVEL_INFO    = 'INFO';
    private const LEVEL_DEBUG   = 'DEBUG';

    /**
     * Map of context key => masking strategy.
     *
     * Strategies:
     *   'email'    -> PiiMasker::maskEmail
     *   'cpf'      -> PiiMasker::maskCpf
     *   'cnpj'     -> PiiMasker::maskCnpj
     *   'phone'    -> PiiMasker::maskPhone
     *   'redact'   -> '[REDACTED]'
     *   'generic'  -> PiiMasker::maskGeneric
     */
    private const KEY_STRATEGY = [
        // Identifiers / PII
        'email'              => 'email',
        'e_mail'             => 'email',
        'mail'               => 'email',
        'email_principal'    => 'email',
        'destinatario'       => 'email',
        'cpf'                => 'cpf',
        'cnpj'               => 'cnpj',
        'phone'              => 'phone',
        'telefone'           => 'phone',
        'celular'            => 'phone',
        'rg'                 => 'generic',
        'passaporte'         => 'generic',
        'nome'               => 'generic',
        'nome_completo'      => 'generic',
        'nome_social'        => 'generic',
        'endereco'           => 'generic',
        'address'            => 'generic',
        // Secrets
        'password'           => 'redact',
        'pass'               => 'redact',
        'pwd'                => 'redact',
        'senha'              => 'redact',
        'token'              => 'redact',
        'access_token'       => 'redact',
        'refresh_token'      => 'redact',
        'id_token'           => 'redact',
        'authorization'      => 'redact',
        'client_secret'      => 'redact',
        'api_key'            => 'redact',
        'code'               => 'redact',
        // Encrypted blobs
        'cpf_enc'            => 'redact',
        'rg_enc'             => 'redact',
        'passaporte_enc'     => 'redact',
        'cnpj_enc'           => 'redact',
    ];

    /**
     * Optional sink override (for tests). Signature: `function(string $line): void`.
     *
     * @var callable|null
     */
    private $sink;

    /**
     * @param callable|null $sink Optional override of the underlying writer.
     */
    public function __construct(?callable $sink = null)
    {
        $this->sink = $sink;
    }

    /**
     * Log at ERROR level.
     *
     * @param array<string,mixed> $context
     */
    public function error(string $message, array $context = []): void
    {
        $this->write(self::LEVEL_ERROR, $message, $context);
    }

    /**
     * Log at WARNING level.
     *
     * @param array<string,mixed> $context
     */
    public function warning(string $message, array $context = []): void
    {
        $this->write(self::LEVEL_WARNING, $message, $context);
    }

    /**
     * Log at INFO level (silenced in production).
     *
     * @param array<string,mixed> $context
     */
    public function info(string $message, array $context = []): void
    {
        if (!self::isVerbose()) {
            return;
        }
        $this->write(self::LEVEL_INFO, $message, $context);
    }

    /**
     * Log at DEBUG level (silenced in production).
     *
     * @param array<string,mixed> $context
     */
    public function debug(string $message, array $context = []): void
    {
        if (!self::isVerbose()) {
            return;
        }
        $this->write(self::LEVEL_DEBUG, $message, $context);
    }

    /**
     * @param array<string,mixed> $context
     */
    private function write(string $level, string $message, array $context): void
    {
        $safeMessage = self::sanitiseMessage($message);
        $safeContext = self::sanitiseContext($context);
        $json        = self::encodeContext($safeContext);

        $line = $json === null
            ? sprintf('[participe-ibram] [%s] %s', $level, $safeMessage)
            : sprintf('[participe-ibram] [%s] %s %s', $level, $safeMessage, $json);

        if ($this->sink !== null) {
            ($this->sink)($line);

            return;
        }

        if (function_exists('error_log')) {
            error_log($line);
        }
    }

    /**
     * Recursively walk the context array and replace PII / secrets with masked values.
     *
     * @param array<mixed,mixed> $context
     *
     * @return array<mixed,mixed>
     */
    private static function sanitiseContext(array $context): array
    {
        $out = [];
        foreach ($context as $key => $value) {
            $keyName = is_string($key) ? strtolower($key) : (string) $key;

            if (is_array($value)) {
                $out[$key] = self::sanitiseContext($value);
                continue;
            }
            if (is_object($value) || is_resource($value)) {
                // R5 V-01: never dump objects.
                $out[$key] = '[OBJECT]';
                continue;
            }
            if (is_string($key) && isset(self::KEY_STRATEGY[$keyName])) {
                $strategy = self::KEY_STRATEGY[$keyName];
                $out[$key] = self::applyStrategy($strategy, is_string($value) ? $value : (string) $value);
                continue;
            }
            // Strings: strip control chars but keep value.
            if (is_string($value)) {
                $out[$key] = self::stripControl($value);
                continue;
            }
            $out[$key] = $value;
        }

        return $out;
    }

    private static function applyStrategy(string $strategy, string $value): string
    {
        switch ($strategy) {
            case 'email':
                return PiiMasker::maskEmail($value);
            case 'cpf':
                return PiiMasker::maskCpf($value);
            case 'cnpj':
                return PiiMasker::maskCnpj($value);
            case 'phone':
                return PiiMasker::maskPhone($value);
            case 'redact':
                return '[REDACTED]';
            case 'generic':
            default:
                return PiiMasker::maskGeneric($value, 1, 1);
        }
    }

    /**
     * @param array<mixed,mixed> $context
     */
    private static function encodeContext(array $context): ?string
    {
        if ($context === []) {
            return null;
        }
        $flags  = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        $json   = function_exists('wp_json_encode') ? wp_json_encode($context) : json_encode($context, $flags);

        return is_string($json) ? $json : null;
    }

    private static function sanitiseMessage(string $message): string
    {
        return self::stripControl($message);
    }

    private static function stripControl(string $value): string
    {
        $clean = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $value) ?? '';

        return trim($clean);
    }

    private static function isVerbose(): bool
    {
        return \defined('WP_DEBUG') && \WP_DEBUG;
    }
}
