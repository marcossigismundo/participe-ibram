<?php
/**
 * UUID generator using cryptographically secure primitives.
 *
 * @package Ibram\ParticipeIbram\Core\Helpers
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Core\Helpers;

use RuntimeException;
use Throwable;

/**
 * Wraps `wp_generate_uuid4()` for v4 UUIDs and provides a base62 short-id
 * helper. Avoids the legacy `md5(uniqid(rand()))` pattern flagged in R5 B-18.
 */
final class UuidGenerator
{
    /**
     * Base62 alphabet (digits + uppercase + lowercase).
     */
    private const ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    /**
     * Generate a RFC 4122 v4 UUID.
     */
    public static function generate(): string
    {
        if (function_exists('wp_generate_uuid4')) {
            return wp_generate_uuid4();
        }

        // Fallback for non-WP test contexts.
        try {
            $bytes = random_bytes(16);
        } catch (Throwable $e) {
            throw new RuntimeException('Unable to generate UUID: random source unavailable.', 0, $e);
        }
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40); // version 4
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80); // variant 1

        return vsprintf(
            '%s%s-%s-%s-%s-%s%s%s',
            str_split(bin2hex($bytes), 4)
        );
    }

    /**
     * Generate a short, URL-safe random id encoded in base62.
     *
     * Suitable for tokens with collision tolerance (≥8 chars ≈ 47 bits of
     * entropy). NOT for security-critical secrets — use a longer length
     * (e.g. 22) or `random_bytes` directly for those.
     *
     * @param int $length Desired output length, must be >= 1.
     */
    public static function generateShort(int $length = 8): string
    {
        if ($length < 1) {
            $length = 1;
        }

        try {
            $bytes = random_bytes($length);
        } catch (Throwable $e) {
            throw new RuntimeException('Unable to generate short id: random source unavailable.', 0, $e);
        }

        $alphabet = self::ALPHABET;
        $size     = strlen($alphabet);
        $out      = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[ord($bytes[$i]) % $size];
        }

        return $out;
    }
}
