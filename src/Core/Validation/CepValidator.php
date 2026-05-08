<?php
/**
 * Brazilian postal code (CEP) validator.
 *
 * @package Ibram\ParticipeIbram\Core\Validation
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Core\Validation;

/**
 * Validates, normalizes, and formats Brazilian CEPs (Código de Endereçamento
 * Postal). Only the 8-digit structure is enforced; range/sector lookups
 * require an external service and are out of scope.
 */
final class CepValidator
{
    /**
     * Whether the input has exactly 8 digits after stripping punctuation.
     *
     * @param string $cep Raw input.
     */
    public static function isValid(string $cep): bool
    {
        $digits = self::normalize($cep);

        if (strlen($digits) !== 8) {
            return false;
        }

        // Reject all-zero CEP (00000-000) which is structurally valid but
        // semantically meaningless.
        if ($digits === '00000000') {
            return false;
        }

        return true;
    }

    /**
     * Strip non-digits from the input.
     *
     * @param string $cep Raw input.
     */
    public static function normalize(string $cep): string
    {
        return (string) preg_replace('/\D+/', '', $cep);
    }

    /**
     * Format CEP as `00000-000`.
     *
     * @param string $cep Raw input. Returned untouched if not 8 digits.
     */
    public static function format(string $cep): string
    {
        $digits = self::normalize($cep);

        if (strlen($digits) !== 8) {
            return $cep;
        }

        return substr($digits, 0, 5) . '-' . substr($digits, 5, 3);
    }
}
