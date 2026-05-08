<?php
/**
 * Transient-backed rate limiter.
 *
 * @package Ibram\ParticipeIbram\Core\Helpers
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Core\Helpers;

/**
 * Simple fixed-window rate limiter using WordPress transients.
 *
 * Limitation: WordPress transients are NOT atomic. Under heavy concurrent
 * traffic to the same key, racing increments can let the counter exceed the
 * configured maximum slightly. For the use cases here (5 req/min on public
 * forms — see R5 V-09) the slack is acceptable. For stricter guarantees,
 * back this with Redis/Memcached or a row-level UPDATE in `wp_options`.
 */
final class RateLimiter
{
    /**
     * Transient key prefix.
     */
    private const PREFIX = 'pi_ratelimit_';

    /**
     * Whether a request under `$key` is allowed and increment the counter.
     *
     * @param string $key            Caller-supplied identity (use the key*()
     *                               builders below for IP/user keys).
     * @param int    $maxRequests    Maximum requests allowed inside the window.
     * @param int    $windowSeconds  Window length in seconds.
     *
     * @return bool True if the request is allowed; false if the limit is hit.
     */
    public static function check(string $key, int $maxRequests, int $windowSeconds): bool
    {
        if ($maxRequests < 1 || $windowSeconds < 1) {
            return true;
        }

        $transientKey = self::PREFIX . md5($key);
        $now          = time();

        $bucket = get_transient($transientKey);
        if (!is_array($bucket) || !isset($bucket['count'], $bucket['window_start'])) {
            $bucket = ['count' => 0, 'window_start' => $now];
        }

        // Reset when the window has expired.
        if (($now - (int) $bucket['window_start']) >= $windowSeconds) {
            $bucket = ['count' => 0, 'window_start' => $now];
        }

        if ((int) $bucket['count'] >= $maxRequests) {
            return false;
        }

        $bucket['count'] = (int) $bucket['count'] + 1;

        // Store with the remaining window so WP can evict it.
        $remaining = max(1, $windowSeconds - ($now - (int) $bucket['window_start']));
        set_transient($transientKey, $bucket, $remaining);

        return true;
    }

    /**
     * Build a per-IP key for an action.
     *
     * The IP is hashed with the WP `AUTH_SALT` (when available) so the raw IP
     * never lands in `wp_options` (R5 B-16).
     *
     * @param string $action Action name, e.g. `cadastro_submit`.
     */
    public static function keyForIp(string $action): string
    {
        $ip = self::detectIp();
        return $action . ':ip:' . self::hashIdentity($ip);
    }

    /**
     * Build a per-user key for an action.
     *
     * @param string $action
     * @param int    $userId WP user id.
     */
    public static function keyForUser(string $action, int $userId): string
    {
        return $action . ':user:' . $userId;
    }

    /**
     * Best-effort client IP. Honors the first non-empty entry in
     * X-Forwarded-For but does not blindly trust it — callers expecting
     * proxied traffic should validate trusted proxies separately.
     */
    private static function detectIp(): string
    {
        $candidates = ['HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($candidates as $key) {
            if (empty($_SERVER[$key])) {
                continue;
            }
            $value = (string) wp_unslash($_SERVER[$key]);
            // X-Forwarded-For may be a comma-list — take the first.
            $first = trim((string) strtok($value, ','));
            if ($first !== '') {
                return $first;
            }
        }
        return 'unknown';
    }

    /**
     * HMAC-SHA256 of a sensitive identifier with the WP auth salt.
     */
    private static function hashIdentity(string $value): string
    {
        $salt = defined('AUTH_SALT') ? (string) AUTH_SALT : 'pibram_default_salt';
        return hash_hmac('sha256', $value, $salt);
    }
}
