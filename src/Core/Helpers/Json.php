<?php
/**
 * Safe JSON encoder/decoder wrappers.
 *
 * @package Ibram\ParticipeIbram\Core\Helpers
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Core\Helpers;

use JsonException;
use RuntimeException;

/**
 * Wraps `wp_json_encode` to enforce script-context safe flags (R5 V-05).
 *
 * For data inlined into a `<script>` block (e.g., `wp_localize_script` data
 * passed verbatim or `<script type="application/json">`), use
 * {@see encodeForScript()}, which sets `JSON_HEX_TAG | JSON_HEX_AMP |
 * JSON_HEX_APOS | JSON_HEX_QUOT` to prevent the encoded payload from breaking
 * out of its surrounding tag or quoted attribute.
 *
 * For JSON used as response body (`Content-Type: application/json`), use
 * {@see encode()} — those flags would unnecessarily inflate the payload.
 */
final class Json
{
    /**
     * Encode a value for safe inclusion inside a `<script>` block or attribute.
     *
     * @param mixed $value
     *
     * @throws RuntimeException When `wp_json_encode` returns false.
     */
    public static function encodeForScript($value): string
    {
        $flags = JSON_HEX_TAG
            | JSON_HEX_AMP
            | JSON_HEX_APOS
            | JSON_HEX_QUOT
            | JSON_UNESCAPED_UNICODE;

        $encoded = wp_json_encode($value, $flags);
        if ($encoded === false) {
            throw new RuntimeException('Failed to encode value for script context.');
        }
        return $encoded;
    }

    /**
     * Encode a value for use as an HTTP response body.
     *
     * @param mixed $value
     *
     * @throws RuntimeException When `wp_json_encode` returns false.
     */
    public static function encode($value): string
    {
        $encoded = wp_json_encode($value, JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new RuntimeException('Failed to encode value as JSON.');
        }
        return $encoded;
    }

    /**
     * Decode a JSON string into an associative array.
     *
     * @param string $json
     *
     * @return array<int|string, mixed>
     *
     * @throws RuntimeException When the input is not valid JSON or does not
     *                          decode to an array.
     */
    public static function decode(string $json): array
    {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('Invalid JSON: ' . $e->getMessage(), 0, $e);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('Decoded JSON is not an array/object.');
        }

        return $decoded;
    }
}
