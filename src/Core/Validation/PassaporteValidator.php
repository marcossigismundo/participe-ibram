<?php
/**
 * Brazilian passport number validator.
 *
 * @package Ibram\ParticipeIbram\Core\Validation
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Core\Validation;

/**
 * Validates Brazilian passport numbers.
 *
 * Brazilian passports issued since 2006 use the format `LL000000` (2 letters
 * followed by 6 digits) for the document number; the older "biometric" format
 * `LL0000000` (2 letters + 7 digits) is also still in circulation. We accept
 * both. Letters are uppercase Latin A-Z (no diacritics).
 *
 * Note: this is structural only — no MRZ checksum or central database query.
 */
final class PassaporteValidator
{
    /**
     * Whether the input matches an accepted passport format.
     *
     * @param string $passport Raw input.
     */
    public static function isValid(string $passport): bool
    {
        $value = self::normalize($passport);

        return preg_match('/^[A-Z]{2}\d{6,7}$/', $value) === 1;
    }

    /**
     * Strip whitespace/punctuation and uppercase the letters.
     *
     * @param string $passport Raw input.
     */
    public static function normalize(string $passport): string
    {
        $value = (string) preg_replace('/[^A-Za-z0-9]+/', '', $passport);

        return strtoupper($value);
    }
}
