<?php
/**
 * CNPJ (Cadastro Nacional de Pessoa Jurídica) validator and formatter.
 *
 * @package Ibram\ParticipeIbram\Core\Validation
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Core\Validation;

/**
 * Validates, normalizes, formats, and masks Brazilian CNPJ numbers.
 *
 * The CNPJ is composed of 14 digits, the last two being verification digits
 * computed using two weight vectors (mod 11) algorithm.
 */
final class CnpjValidator
{
    /**
     * Weights for the first verification digit.
     *
     * @var int[]
     */
    private const WEIGHTS_FIRST = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

    /**
     * Weights for the second verification digit.
     *
     * @var int[]
     */
    private const WEIGHTS_SECOND = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

    /**
     * Verify that a CNPJ string is structurally and arithmetically valid.
     *
     * @param string $cnpj Raw CNPJ (with or without punctuation).
     */
    public static function isValid(string $cnpj): bool
    {
        $digits = self::normalize($cnpj);

        if (strlen($digits) !== 14) {
            return false;
        }

        if (preg_match('/^(\d)\1{13}$/', $digits) === 1) {
            return false;
        }

        // First check digit.
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += ((int) $digits[$i]) * self::WEIGHTS_FIRST[$i];
        }
        $remainder = $sum % 11;
        $digit1    = $remainder < 2 ? 0 : 11 - $remainder;

        if ($digit1 !== (int) $digits[12]) {
            return false;
        }

        // Second check digit.
        $sum = 0;
        for ($i = 0; $i < 13; $i++) {
            $sum += ((int) $digits[$i]) * self::WEIGHTS_SECOND[$i];
        }
        $remainder = $sum % 11;
        $digit2    = $remainder < 2 ? 0 : 11 - $remainder;

        return $digit2 === (int) $digits[13];
    }

    /**
     * Strip all non-digit characters from a CNPJ string.
     *
     * @param string $cnpj Raw input.
     */
    public static function normalize(string $cnpj): string
    {
        return (string) preg_replace('/\D+/', '', $cnpj);
    }

    /**
     * Format a CNPJ as `00.000.000/0000-00`.
     *
     * @param string $cnpj Raw input. If invalid length, the input is returned
     *                     untouched.
     */
    public static function format(string $cnpj): string
    {
        $digits = self::normalize($cnpj);

        if (strlen($digits) !== 14) {
            return $cnpj;
        }

        return substr($digits, 0, 2) . '.'
            . substr($digits, 2, 3) . '.'
            . substr($digits, 5, 3) . '/'
            . substr($digits, 8, 4) . '-'
            . substr($digits, 12, 2);
    }

    /**
     * Mask a CNPJ for safe display in logs/UI: `**.***.***/0001-**`.
     *
     * Reveals the order suffix (positions 9-12) which identifies the branch
     * unit, hiding the registered company root number.
     *
     * @param string $cnpj Raw input.
     */
    public static function mask(string $cnpj): string
    {
        $digits = self::normalize($cnpj);

        if (strlen($digits) !== 14) {
            return '**.***.***/****-**';
        }

        return '**.***.***/' . substr($digits, 8, 4) . '-**';
    }
}
