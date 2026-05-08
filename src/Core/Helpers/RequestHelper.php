<?php
/**
 * Safe accessors for HTTP superglobals (POST/GET/REQUEST/headers/JSON body).
 *
 * @package Ibram\ParticipeIbram\Core\Helpers
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Core\Helpers;

use InvalidArgumentException;

/**
 * Centralizes reads from `$_POST`, `$_GET`, `$_REQUEST`, `$_SERVER`, and
 * `php://input`, applying `wp_unslash()` and a whitelisted sanitizer.
 *
 * Background (R5 V-08, AP-02): `wp_unslash()` is the most frequently omitted
 * step in WordPress code. WordPress slashes superglobals at boot. Sanitizing
 * before unslashing yields double-escaped artifacts that bypass downstream
 * filters or surface in stored data.
 *
 * Nonce verification is intentionally NOT done here — that is the caller's
 * responsibility, and depends on the action context.
 */
final class RequestHelper
{
    /**
     * Allowed sanitizer callbacks. Anything else is rejected to prevent the
     * helper from being used as an arbitrary callable invoker.
     *
     * @var string[]
     */
    private const ALLOWED_SANITIZERS = [
        'sanitize_text_field',
        'sanitize_email',
        'sanitize_textarea_field',
        'sanitize_key',
        'absint',
        'floatval',
        'wp_kses_post',
    ];

    /**
     * Read a key from `$_POST`.
     *
     * @param string      $key
     * @param string|null $sanitizer Whitelisted sanitizer or null for raw.
     * @param mixed       $default   Returned when key is absent.
     *
     * @return mixed
     *
     * @throws InvalidArgumentException If `$sanitizer` is not in the allowlist.
     */
    public static function post(string $key, ?string $sanitizer = 'sanitize_text_field', $default = null)
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if (!isset($_POST[$key])) {
            return $default;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        return self::sanitizeScalar(wp_unslash($_POST[$key]), $sanitizer);
    }

    /**
     * Read a key from `$_GET`.
     *
     * @param string      $key
     * @param string|null $sanitizer
     * @param mixed       $default
     *
     * @return mixed
     */
    public static function get(string $key, ?string $sanitizer = 'sanitize_text_field', $default = null)
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (!isset($_GET[$key])) {
            return $default;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return self::sanitizeScalar(wp_unslash($_GET[$key]), $sanitizer);
    }

    /**
     * Read a key from POST first, then GET, then default.
     *
     * @param string      $key
     * @param string|null $sanitizer
     * @param mixed       $default
     *
     * @return mixed
     */
    public static function request(string $key, ?string $sanitizer = 'sanitize_text_field', $default = null)
    {
        $fromPost = self::post($key, $sanitizer, null);
        if ($fromPost !== null) {
            return $fromPost;
        }

        return self::get($key, $sanitizer, $default);
    }

    /**
     * Read an array value from `$_POST` and sanitize each item.
     *
     * @param string      $key
     * @param string|null $itemSanitizer
     *
     * @return array<int|string, mixed>
     *
     * @throws InvalidArgumentException If `$itemSanitizer` is not allowed.
     */
    public static function postArray(string $key, ?string $itemSanitizer = 'sanitize_text_field'): array
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if (!isset($_POST[$key]) || !is_array($_POST[$key])) {
            return [];
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $raw = wp_unslash($_POST[$key]);
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $k => $v) {
            $out[$k] = self::sanitizeScalar($v, $itemSanitizer);
        }
        return $out;
    }

    /**
     * Read raw JSON from `php://input` and decode it.
     *
     * Returns null if the body is missing, not valid JSON, or does not decode
     * to an array/object. Note: the value is NOT slashed by WordPress because
     * it bypasses the magic-quotes-style boot handling.
     *
     * @param string $key Reserved for future scoping; currently ignored. The
     *                    full request body is decoded.
     *
     * @return array<string, mixed>|null
     */
    public static function postJson(string $key = ''): ?array
    {
        unset($key);

        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    /**
     * Read an HTTP request header from `$_SERVER` (HTTP_* convention).
     *
     * @param string      $key       e.g. `Authorization` or `X-Forwarded-For`.
     * @param string|null $sanitizer
     *
     * @return string|null
     */
    public static function header(string $key, ?string $sanitizer = null): ?string
    {
        $server = 'HTTP_' . strtoupper(str_replace('-', '_', $key));

        if (!isset($_SERVER[$server])) {
            return null;
        }

        $value = wp_unslash($_SERVER[$server]);
        if (!is_string($value)) {
            return null;
        }

        $sanitized = self::sanitizeScalar($value, $sanitizer);
        return is_string($sanitized) ? $sanitized : null;
    }

    /**
     * Apply a whitelisted sanitizer to a single scalar value.
     *
     * @param mixed       $value
     * @param string|null $sanitizer
     *
     * @return mixed
     *
     * @throws InvalidArgumentException When the sanitizer is not allowed.
     */
    private static function sanitizeScalar($value, ?string $sanitizer)
    {
        if ($sanitizer === null) {
            return $value;
        }

        if (!in_array($sanitizer, self::ALLOWED_SANITIZERS, true)) {
            throw new InvalidArgumentException(sprintf(
                'Sanitizer "%s" is not in the RequestHelper allowlist.',
                $sanitizer
            ));
        }

        if (is_array($value)) {
            // Defensive: callers should use postArray() for arrays; flatten to
            // a comma-joined string would be lossy, so coerce to empty string.
            return '';
        }

        return $sanitizer($value);
    }
}
