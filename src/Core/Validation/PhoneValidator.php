<?php
/**
 * Brazilian phone number validator and formatter.
 *
 * @package Ibram\ParticipeIbram\Core\Validation
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Core\Validation;

/**
 * Validates 10/11-digit Brazilian phone numbers (without country code) and
 * formats them in the canonical pt_BR shape `(11) 99999-1234`.
 *
 * Mobile numbers begin with the digit `9` after the DDD; landlines do not.
 * This class accepts both, requiring a valid Brazilian DDD.
 */
final class PhoneValidator
{
    /**
     * Set of valid Brazilian DDD codes (subscriber area codes).
     *
     * Sourced from Anatel public allocation. Codes 20, 23, 25, 26, 29, 30, 36,
     * 39, 40, 50, 56-60, 70, 72, 76, 78, 80, 90 are NOT assigned and are
     * therefore excluded.
     *
     * @var int[]
     */
    private const VALID_DDDS = [
        11, 12, 13, 14, 15, 16, 17, 18, 19,
        21, 22, 24,
        27, 28,
        31, 32, 33, 34, 35, 37, 38,
        41, 42, 43, 44, 45, 46, 47, 48, 49,
        51, 53, 54, 55,
        61, 62, 63, 64, 65, 66, 67, 68, 69,
        71, 73, 74, 75, 77, 79,
        81, 82, 83, 84, 85, 86, 87, 88, 89,
        91, 92, 93, 94, 95, 96, 97, 98, 99,
    ];

    /**
     * Whether the phone has 10 or 11 digits and a valid DDD.
     *
     * @param string $phone Raw phone (any punctuation tolerated).
     */
    public static function isValid(string $phone): bool
    {
        $digits = self::normalize($phone);
        $length = strlen($digits);

        if ($length !== 10 && $length !== 11) {
            return false;
        }

        $ddd = (int) substr($digits, 0, 2);
        if (!in_array($ddd, self::VALID_DDDS, true)) {
            return false;
        }

        // 11-digit numbers (mobile) must start with 9 after DDD.
        if ($length === 11 && $digits[2] !== '9') {
            return false;
        }

        return true;
    }

    /**
     * Strip all non-digit characters. Discards a leading 55 country code only
     * if the remainder has exactly 10 or 11 digits (otherwise leaves intact
     * for the validator to reject downstream).
     *
     * @param string $phone Raw input.
     */
    public static function normalize(string $phone): string
    {
        $digits = (string) preg_replace('/\D+/', '', $phone);

        if (strlen($digits) > 11 && strpos($digits, '55') === 0) {
            $stripped = substr($digits, 2);
            if (strlen($stripped) === 10 || strlen($stripped) === 11) {
                return $stripped;
            }
        }

        return $digits;
    }

    /**
     * Format as `(11) 99999-1234` (11 digits) or `(11) 9999-1234` (10 digits).
     *
     * @param string $phone Raw input. Returned as-is if length differs from
     *                      10/11 digits after normalization.
     */
    public static function format(string $phone): string
    {
        $digits = self::normalize($phone);
        $length = strlen($digits);

        if ($length === 11) {
            return '(' . substr($digits, 0, 2) . ') '
                . substr($digits, 2, 5) . '-'
                . substr($digits, 7, 4);
        }

        if ($length === 10) {
            return '(' . substr($digits, 0, 2) . ') '
                . substr($digits, 2, 4) . '-'
                . substr($digits, 6, 4);
        }

        return $phone;
    }
}
