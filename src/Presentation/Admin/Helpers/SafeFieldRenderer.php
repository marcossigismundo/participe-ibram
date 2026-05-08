<?php
/**
 * SafeFieldRenderer — renders sensitive fields with default masking.
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\Helpers
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\Helpers;

use Ibram\ParticipeIbram\Core\Audit\PiiMasker;

/**
 * Helpers used by the agente detalhes template/controller to render PII
 * fields. Every method takes a plaintext value (already decrypted by the
 * caller) AND a $reveal flag. When $reveal is false, the value is masked
 * via {@see PiiMasker} so accidental disclosure is impossible by default.
 *
 * The decision of whether the actor is allowed to reveal happens BEFORE
 * calling these helpers (capability check `pi_visualizar_dados_sensiveis`).
 * Even if a developer accidentally passes $reveal=true without the check,
 * the controller layer is responsible for the gating; this class merely
 * formats.
 *
 * Output is plain (non-escaped) string. Templates are responsible for
 * `esc_html()` before printing.
 */
final class SafeFieldRenderer
{
    /**
     * Render a CPF. When $reveal is false (default behaviour for PII), it
     * returns the masked form. When $reveal is true and the value is a valid
     * 11-digit CPF, returns the formatted "XXX.XXX.XXX-XX".
     *
     * Returns "—" when value is null/empty.
     */
    public static function cpf(?string $cpfPlain, bool $reveal = false): string
    {
        $cpf = $cpfPlain !== null ? trim($cpfPlain) : '';
        if ($cpf === '') {
            return '—';
        }
        if (!$reveal) {
            return PiiMasker::maskCpf($cpf);
        }
        return self::formatCpf($cpf);
    }

    /**
     * Render a CNPJ. Mirror of {@see cpf()}.
     */
    public static function cnpj(?string $cnpjPlain, bool $reveal = false): string
    {
        $cnpj = $cnpjPlain !== null ? trim($cnpjPlain) : '';
        if ($cnpj === '') {
            return '—';
        }
        if (!$reveal) {
            return PiiMasker::maskCnpj($cnpj);
        }
        return self::formatCnpj($cnpj);
    }

    /**
     * Render a generic identity (RG / passaporte). Masked by default.
     */
    public static function identidade(?string $valuePlain, bool $reveal = false): string
    {
        $v = $valuePlain !== null ? trim($valuePlain) : '';
        if ($v === '') {
            return '—';
        }
        if (!$reveal) {
            return PiiMasker::maskGeneric($v, 1, 2);
        }
        return $v;
    }

    /**
     * Render an e-mail. Masked by default; reveal returns the raw email.
     */
    public static function email(?string $email, bool $reveal = false): string
    {
        $value = $email !== null ? trim($email) : '';
        if ($value === '') {
            return '—';
        }
        if (!$reveal) {
            return PiiMasker::maskEmail($value);
        }
        return $value;
    }

    /**
     * Render a phone. Masked by default.
     */
    public static function phone(?string $phone, bool $reveal = false): string
    {
        $value = $phone !== null ? trim($phone) : '';
        if ($value === '') {
            return '—';
        }
        if (!$reveal) {
            return PiiMasker::maskPhone($value);
        }
        return $value;
    }

    /**
     * Format an 11-digit CPF as XXX.XXX.XXX-XX. Falls back to the original
     * input when the digit count is off (defensive — no exceptions in render).
     */
    private static function formatCpf(string $cpf): string
    {
        $digits = preg_replace('/\D+/', '', $cpf) ?? '';
        if (strlen($digits) !== 11) {
            return $cpf;
        }
        return sprintf(
            '%s.%s.%s-%s',
            substr($digits, 0, 3),
            substr($digits, 3, 3),
            substr($digits, 6, 3),
            substr($digits, 9, 2)
        );
    }

    /**
     * Format a 14-digit CNPJ as XX.XXX.XXX/XXXX-XX.
     */
    private static function formatCnpj(string $cnpj): string
    {
        $digits = preg_replace('/\D+/', '', $cnpj) ?? '';
        if (strlen($digits) !== 14) {
            return $cnpj;
        }
        return sprintf(
            '%s.%s.%s/%s-%s',
            substr($digits, 0, 2),
            substr($digits, 2, 3),
            substr($digits, 5, 3),
            substr($digits, 8, 4),
            substr($digits, 12, 2)
        );
    }
}
