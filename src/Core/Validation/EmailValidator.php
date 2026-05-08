<?php
/**
 * Email validator (no DNS lookup, RFC length limits enforced).
 *
 * @package Ibram\ParticipeIbram\Core\Validation
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Core\Validation;

/**
 * Validates and normalizes email addresses without performing any network call.
 *
 * Per RFC 5321/5322, the maximum total address length is 254 octets and the
 * local part may not exceed 64 octets.
 */
final class EmailValidator
{
    /**
     * Maximum allowed length of the full email address (RFC 5321).
     */
    private const MAX_TOTAL_LENGTH = 254;

    /**
     * Maximum allowed length of the local part (RFC 5321).
     */
    private const MAX_LOCAL_LENGTH = 64;

    /**
     * Whether the given email is syntactically valid.
     *
     * Performs (1) length checks, (2) no whitespace, (3) `filter_var` syntax
     * validation. Does NOT verify deliverability or DNS records.
     *
     * @param string $email Raw email.
     */
    public static function isValid(string $email): bool
    {
        $email = trim($email);

        if ($email === '') {
            return false;
        }

        if (strlen($email) > self::MAX_TOTAL_LENGTH) {
            return false;
        }

        // Reject any whitespace (filter_var allows some via quoting).
        if (preg_match('/\s/', $email) === 1) {
            return false;
        }

        $atPos = strrpos($email, '@');
        if ($atPos === false || $atPos === 0) {
            return false;
        }

        $localPart = substr($email, 0, $atPos);
        if (strlen($localPart) > self::MAX_LOCAL_LENGTH) {
            return false;
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Lowercase + trim. Does not strip dots/plus from gmail-style addresses,
     * because that would change identity for non-Google providers.
     *
     * @param string $email Raw email.
     */
    public static function normalize(string $email): string
    {
        return strtolower(trim($email));
    }
}
