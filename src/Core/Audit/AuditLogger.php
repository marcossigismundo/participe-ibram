<?php
/**
 * Append-only audit log writer (TD-14, SCHEMA §7).
 *
 * @package Ibram\ParticipeIbram\Core\Audit
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Core\Audit;

use Ibram\ParticipeIbram\Core\Network\IpResolver;

/**
 * Persists audit events into `{$wpdb->prefix}pi_audit_log`.
 *
 * Contract:
 *  - APPEND-ONLY: this class only INSERTs. UPDATE/DELETE is forbidden by design.
 *  - Sensitive fields are redacted from `dados_antes` / `dados_depois` BEFORE
 *    serialisation so the log itself never stores PII or secrets.
 *  - IP is captured via {@see IpResolver} and stored as HMAC-SHA256
 *    (`PI_IP_PEPPER`); the raw IP is never persisted (LGPD §6).
 *  - In `WP_DEBUG=true` environments, persistence failures throw to surface bugs;
 *    in production the failure is silenced so it cannot block business writes.
 */
final class AuditLogger
{
    /**
     * Field names that must never be stored in the audit log payload.
     */
    private const REDACT_KEYS = [
        'cpf',
        'rg',
        'passaporte',
        'cnpj',
        'cpf_enc',
        'rg_enc',
        'passaporte_enc',
        'cnpj_enc',
        'password',
        'pass',
        'pwd',
        'senha',
        'client_secret',
        'access_token',
        'refresh_token',
        'id_token',
        'code',
        'authorization',
        'api_key',
        'token',
    ];

    private IpResolver $ipResolver;

    /** @var \wpdb */
    private $wpdb;

    private string $tableName;

    /**
     * @param \wpdb       $wpdb       WordPress DB facade.
     * @param IpResolver  $ipResolver Resolves and hashes the client IP.
     * @param string|null $tableName  Override (defaults to `{prefix}pi_audit_log`).
     */
    public function __construct($wpdb, IpResolver $ipResolver, ?string $tableName = null)
    {
        $this->wpdb       = $wpdb;
        $this->ipResolver = $ipResolver;
        $prefix           = isset($wpdb->prefix) && is_string($wpdb->prefix) ? $wpdb->prefix : 'wp_';
        $this->tableName  = $tableName ?? ($prefix . 'pi_audit_log');
    }

    /**
     * Persist a single audit event.
     *
     * @param string                    $entidade    Logical entity (`agente`, `edital`, ...).
     * @param int|null                  $entidadeId  Primary key of the entity (when applicable).
     * @param string                    $acao        Action verb (`criar`, `atualizar`, `visualizar_dado_sensivel`, ...).
     * @param array<string,mixed>|null  $dadosAntes  State before the action (will be redacted).
     * @param array<string,mixed>|null  $dadosDepois State after the action (will be redacted).
     * @param int|null                  $atorId      WordPress user id; auto-detected when null.
     *
     * @throws \RuntimeException When persistence fails AND `WP_DEBUG` is true.
     */
    public function log(
        string $entidade,
        ?int $entidadeId,
        string $acao,
        ?array $dadosAntes,
        ?array $dadosDepois,
        ?int $atorId = null
    ): void {
        if ($atorId === null) {
            $atorId = self::detectActorId();
        }

        $ipHash    = $this->ipResolver->resolveHash();
        $userAgent = self::captureUserAgent();

        $row = [
            'entidade'     => self::truncate($entidade, 50),
            'entidade_id'  => $entidadeId,
            'acao'         => self::truncate($acao, 50),
            'ator_id'      => $atorId,
            'dados_antes'  => self::encodePayload($dadosAntes),
            'dados_depois' => self::encodePayload($dadosDepois),
            'ip_hash'      => $ipHash,
            'user_agent'   => $userAgent,
            'ocorrido_em'  => self::nowMysql(),
        ];

        $formats = [
            '%s',                                  // entidade
            $entidadeId === null ? null : '%d',    // entidade_id
            '%s',                                  // acao
            $atorId === null ? null : '%d',        // ator_id
            '%s',                                  // dados_antes (NULL -> %s with NULL also fine)
            '%s',                                  // dados_depois
            '%s',                                  // ip_hash
            '%s',                                  // user_agent
            '%s',                                  // ocorrido_em
        ];

        // Strip NULL formats — wpdb::insert accepts NULL when format is omitted.
        $cleanFormats = array_values(array_filter($formats, static fn ($f) => $f !== null));

        try {
            $result = $this->wpdb->insert($this->tableName, $row, $cleanFormats);
        } catch (\Throwable $e) {
            $this->handleFailure('Audit log insert threw: ' . self::sanitiseMessage($e->getMessage()));

            return;
        }

        if ($result === false) {
            $this->handleFailure('Audit log insert returned false.');
        }
    }

    /**
     * Detect the current actor id, defaulting to NULL when no WP user context.
     */
    private static function detectActorId(): ?int
    {
        if (!function_exists('get_current_user_id')) {
            return null;
        }
        $id = (int) get_current_user_id();

        return $id > 0 ? $id : null;
    }

    /**
     * Capture the User-Agent (with `wp_unslash` + sanitisation when WP is loaded).
     */
    private static function captureUserAgent(): ?string
    {
        if (!isset($_SERVER['HTTP_USER_AGENT'])) {
            return null;
        }
        $raw = (string) $_SERVER['HTTP_USER_AGENT'];
        if (function_exists('wp_unslash')) {
            $raw = (string) wp_unslash($raw);
        }
        if (function_exists('sanitize_text_field')) {
            $raw = (string) sanitize_text_field($raw);
        } else {
            $raw = trim(preg_replace('/[\x00-\x1F\x7F]+/', ' ', $raw) ?? '');
        }
        if ($raw === '') {
            return null;
        }

        return self::truncate($raw, 1024);
    }

    /**
     * Recursive redaction + JSON encoding.
     *
     * @param array<string,mixed>|null $payload
     */
    private static function encodePayload(?array $payload): ?string
    {
        if ($payload === null) {
            return null;
        }
        $sanitised = self::redact($payload);
        $json      = function_exists('wp_json_encode')
            ? wp_json_encode($sanitised)
            : json_encode($sanitised, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($json) ? $json : null;
    }

    /**
     * Recursively replace sensitive values with [REDACTED].
     *
     * @param array<string,mixed> $data
     *
     * @return array<string,mixed>
     */
    private static function redact(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            $keyName = is_string($key) ? strtolower($key) : (string) $key;

            if (is_string($key) && in_array($keyName, self::REDACT_KEYS, true)) {
                $out[$key] = '[REDACTED]';
                continue;
            }
            if (is_array($value)) {
                $out[$key] = self::redact($value);
                continue;
            }
            // Drop binary blobs we don't want to log.
            if (is_object($value) || is_resource($value)) {
                $out[$key] = '[OBJECT]';
                continue;
            }
            $out[$key] = $value;
        }

        return $out;
    }

    /**
     * Decide what to do when an insert fails: in debug, throw; in prod, swallow.
     */
    private function handleFailure(string $message): void
    {
        if (\defined('WP_DEBUG') && \WP_DEBUG) {
            throw new \RuntimeException($message);
        }
        // Production: write a single sanitised error to PHP error log.
        // Avoid coupling to SecureLogger to keep this class self-contained.
        if (function_exists('error_log')) {
            error_log('[participe-ibram][audit] ' . $message);
        }
    }

    /**
     * Strip control characters and limit length so stack traces don't leak details.
     */
    private static function sanitiseMessage(string $message): string
    {
        $clean = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $message) ?? '';

        return self::truncate(trim($clean), 500);
    }

    /**
     * UTC timestamp in MySQL DATETIME format.
     */
    private static function nowMysql(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    /**
     * Hard-cap a string to avoid blowing up VARCHAR columns.
     */
    private static function truncate(string $value, int $max): string
    {
        if (strlen($value) <= $max) {
            return $value;
        }

        return substr($value, 0, $max);
    }
}
