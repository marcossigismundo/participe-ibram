<?php
/**
 * Static helpers to mask PII before it reaches a log line.
 *
 * @package Ibram\ParticipeIbram\Core\Audit
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Core\Audit;

/**
 * PII masking utilities.
 *
 * All methods are pure and side-effect-free. They preserve enough of the
 * original value to allow a human operator to recognise the record while
 * preventing the full PII from being persisted in logs (R5 V-01, AP-05).
 */
final class PiiMasker
{
    /**
     * Mask an e-mail address: keep first character of local part and full domain.
     *
     * Examples:
     *   "fulano@example.org"  -> "f***@example.org"
     *   "ab@x"                -> "a***@x"
     *   "" / invalid          -> "[REDACTED]"
     */
    public static function maskEmail(string $email): string
    {
        $email = trim($email);
        if ($email === '' || strpos($email, '@') === false) {
            return '[REDACTED]';
        }
        [$local, $domain] = explode('@', $email, 2);
        if ($local === '' || $domain === '') {
            return '[REDACTED]';
        }
        $head = self::substrSafe($local, 0, 1);

        return $head . '***@' . $domain;
    }

    /**
     * Mask a CPF: keep digits 7-9 (3-digit block immediately before the check digits).
     *
     * Output is always normalised to "XXX.XXX.999-XX" (no formatting required as input).
     */
    public static function maskCpf(string $cpf): string
    {
        $digits = preg_replace('/\D+/', '', $cpf) ?? '';
        if (strlen($digits) !== 11) {
            return '[REDACTED]';
        }
        $block = substr($digits, 6, 3);

        return 'XXX.XXX.' . $block . '-XX';
    }

    /**
     * Mask a CNPJ: keep digits 8-11 (the 4-digit "filial" block).
     *
     * Output normalised to "XX.XXX.NNNN/0001-XX" style irrespective of input formatting.
     */
    public static function maskCnpj(string $cnpj): string
    {
        $digits = preg_replace('/\D+/', '', $cnpj) ?? '';
        if (strlen($digits) !== 14) {
            return '[REDACTED]';
        }
        $branch = substr($digits, 8, 4);

        return 'XX.XXX.XXX/' . $branch . '-XX';
    }

    /**
     * Mask a phone number: keep the last 4 digits.
     *
     * Examples:
     *   "+55 (11) 99999-1234" -> "(XX) 9XXXX-1234"
     *   "1199991234"          -> "(XX) 9XXXX-1234"
     */
    public static function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($digits) < 4) {
            return '[REDACTED]';
        }
        $tail = substr($digits, -4);

        return '(XX) 9XXXX-' . $tail;
    }

    /**
     * Generic mask: preserve `$keepStart` leading and `$keepEnd` trailing characters.
     *
     * Returns "[REDACTED]" when the value is too short to mask meaningfully.
     */
    public static function maskGeneric(string $value, int $keepStart = 1, int $keepEnd = 1): string
    {
        $keepStart = max(0, $keepStart);
        $keepEnd   = max(0, $keepEnd);
        $length    = self::strlenSafe($value);

        if ($length === 0 || $length <= ($keepStart + $keepEnd)) {
            return '[REDACTED]';
        }

        $head = self::substrSafe($value, 0, $keepStart);
        $tail = $keepEnd > 0 ? self::substrSafe($value, $length - $keepEnd, $keepEnd) : '';

        return $head . '***' . $tail;
    }

    /**
     * mb-aware strlen with ASCII fallback.
     */
    private static function strlenSafe(string $value): int
    {
        return function_exists('mb_strlen') ? (int) mb_strlen($value, 'UTF-8') : strlen($value);
    }

    /**
     * mb-aware substr with ASCII fallback.
     */
    private static function substrSafe(string $value, int $offset, ?int $length = null): string
    {
        if (function_exists('mb_substr')) {
            $result = mb_substr($value, $offset, $length, 'UTF-8');

            return $result;
        }

        $result = $length === null ? substr($value, $offset) : substr($value, $offset, $length);

        return $result === false ? '' : $result;
    }
}
