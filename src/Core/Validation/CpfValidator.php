<?php
/**
 * CPF (Cadastro de Pessoa Física) validator and formatter.
 *
 * @package Ibram\ParticipeIbram\Core\Validation
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Core\Validation;

/**
 * Validates, normalizes, formats, and masks Brazilian CPF numbers.
 *
 * The CPF is composed of 11 digits, the last two being verification digits
 * computed using a weighted sum (mod 11) algorithm.
 */
final class CpfValidator
{
    /**
     * Verify that a CPF string is structurally and arithmetically valid.
     *
     * @param string $cpf Raw CPF (with or without punctuation).
     */
    public static function isValid(string $cpf): bool
    {
        $digits = self::normalize($cpf);

        if (strlen($digits) !== 11) {
            return false;
        }

        // Reject sequences of identical digits (000.000.000-00, 111..., etc.).
        if (preg_match('/^(\d)\1{10}$/', $digits) === 1) {
            return false;
        }

        // First check digit.
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += ((int) $digits[$i]) * (10 - $i);
        }
        $remainder = ($sum * 10) % 11;
        if ($remainder === 10) {
            $remainder = 0;
        }
        if ($remainder !== (int) $digits[9]) {
            return false;
        }

        // Second check digit.
        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $sum += ((int) $digits[$i]) * (11 - $i);
        }
        $remainder = ($sum * 10) % 11;
        if ($remainder === 10) {
            $remainder = 0;
        }

        return $remainder === (int) $digits[10];
    }

    /**
     * Strip all non-digit characters from a CPF string.
     *
     * @param string $cpf Raw input.
     * @return string Digits only (length is not enforced here).
     */
    public static function normalize(string $cpf): string
    {
        return (string) preg_replace('/\D+/', '', $cpf);
    }

    /**
     * Format a CPF as `000.000.000-00`.
     *
     * @param string $cpf Raw input. If invalid length, the input is returned
     *                    untouched (caller is expected to validate first).
     */
    public static function format(string $cpf): string
    {
        $digits = self::normalize($cpf);

        if (strlen($digits) !== 11) {
            return $cpf;
        }

        return substr($digits, 0, 3) . '.'
            . substr($digits, 3, 3) . '.'
            . substr($digits, 6, 3) . '-'
            . substr($digits, 9, 2);
    }

    /**
     * Mask a CPF for safe display in logs/UI: `***.***.789-**`.
     *
     * Only the 7th, 8th, and 9th digits are revealed. Returns `***.***.***-**`
     * when the input is not a valid 11-digit CPF.
     *
     * @param string $cpf Raw input.
     */
    public static function mask(string $cpf): string
    {
        $digits = self::normalize($cpf);

        if (strlen($digits) !== 11) {
            return '***.***.***-**';
        }

        return '***.***.' . substr($digits, 6, 3) . '-**';
    }
}
