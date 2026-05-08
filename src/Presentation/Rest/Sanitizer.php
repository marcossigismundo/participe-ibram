<?php
/**
 * Sanitização recursiva de payloads JSON aninhados (R5 V-08).
 *
 * @package Ibram\ParticipeIbram\Presentation\Rest
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Rest;

/**
 * Helper para sanitização de arrays aninhados recebidos por REST/AJAX.
 *
 * Diferente de `RequestHelper`, opera sobre payloads já decodificados (JSON),
 * onde o WordPress não aplica `wp_unslash`. As callbacks REST do core já
 * passam dados unslashed; o objetivo deste helper é:
 *
 *   1. Filtrar chaves não-whitelisted (defesa contra mass-assignment).
 *   2. Aplicar sanitizadores apropriados por tipo (`*_md` → `wp_kses_post`,
 *      `cpf|cnpj|telefone` → normalizadores específicos, demais →
 *      `sanitize_text_field`).
 *   3. Limitar profundidade para mitigar DoS de recursão (R5 V-04).
 *
 * Não decifra nem valida regras de negócio — isso é responsabilidade do
 * Domain.
 */
final class Sanitizer
{
    /**
     * Profundidade máxima de recursão (defesa contra payloads maliciosos).
     */
    private const MAX_DEPTH = 8;

    /**
     * Tipos de sanitização suportados (`kindMap`).
     */
    public const KIND_TEXT     = 'text';
    public const KIND_TEXTAREA = 'textarea';
    public const KIND_HTML_MD  = 'html_md';
    public const KIND_EMAIL    = 'email';
    public const KIND_KEY      = 'key';
    public const KIND_INT      = 'int';
    public const KIND_BOOL     = 'bool';
    public const KIND_CPF      = 'cpf';
    public const KIND_CNPJ     = 'cnpj';
    public const KIND_TELEFONE = 'telefone';
    public const KIND_URL      = 'url';
    public const KIND_RAW      = 'raw';

    /**
     * Sanitiza um array filtrando por whitelist de chaves e aplicando o
     * sanitizador adequado por chave.
     *
     * Regras automáticas:
     *  - Chaves terminadas em `_md` → `wp_kses_post` (markdown/HTML restrito).
     *  - Chaves contendo `cpf` → CPF (apenas dígitos, 11 chars).
     *  - Chaves contendo `cnpj` → CNPJ (apenas dígitos, 14 chars).
     *  - Chaves contendo `telefone`/`celular`/`fone` → apenas dígitos+separadores.
     *  - Chaves contendo `email`/`mail` → `sanitize_email`.
     *  - Demais → `sanitize_text_field`.
     *
     * `kindMap` permite override explícito (`['biografia' => 'textarea']`).
     *
     * @param array<string,mixed>  $data
     * @param array<int,string>    $allowedKeys Whitelist (chaves não listadas
     *                                          são silenciosamente removidas).
     * @param array<string,string> $kindMap     Override por chave (KIND_*).
     *
     * @return array<string,mixed>
     */
    public static function sanitizeNested(array $data, array $allowedKeys, array $kindMap = []): array
    {
        $allowed = array_flip(array_values(array_filter(
            $allowedKeys,
            static fn ($k): bool => is_string($k) && $k !== ''
        )));

        return self::walk($data, $allowed, $kindMap, 0);
    }

    /**
     * Sanitiza um valor escalar conforme um "kind".
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public static function sanitizeValue($value, string $kind = self::KIND_TEXT)
    {
        if (is_array($value)) {
            // Defensivo: array onde se espera escalar — drop.
            return null;
        }

        switch ($kind) {
            case self::KIND_RAW:
                return $value;
            case self::KIND_INT:
                return is_scalar($value) ? (int) $value : 0;
            case self::KIND_BOOL:
                if (is_bool($value)) {
                    return $value;
                }
                if (is_string($value)) {
                    $low = strtolower(trim($value));
                    if (in_array($low, ['1', 'true', 'yes', 'sim', 'on'], true)) {
                        return true;
                    }
                    if (in_array($low, ['0', 'false', 'no', 'nao', 'não', 'off', ''], true)) {
                        return false;
                    }
                }
                return (bool) $value;
            case self::KIND_EMAIL:
                $s = is_scalar($value) ? (string) $value : '';
                return function_exists('sanitize_email') ? (string) \sanitize_email($s) : trim($s);
            case self::KIND_KEY:
                $s = is_scalar($value) ? (string) $value : '';
                return function_exists('sanitize_key') ? (string) \sanitize_key($s) : preg_replace('/[^a-z0-9_\-]/', '', strtolower($s)) ?? '';
            case self::KIND_URL:
                $s = is_scalar($value) ? (string) $value : '';
                return function_exists('esc_url_raw') ? (string) \esc_url_raw($s) : trim($s);
            case self::KIND_HTML_MD:
                $s = is_scalar($value) ? (string) $value : '';
                if (function_exists('wp_kses_post')) {
                    return (string) \wp_kses_post($s);
                }
                // Fallback minimal: remove script/style.
                return trim((string) preg_replace('#<(script|style)[^>]*>.*?</\1>#is', '', $s));
            case self::KIND_TEXTAREA:
                $s = is_scalar($value) ? (string) $value : '';
                return function_exists('sanitize_textarea_field') ? (string) \sanitize_textarea_field($s) : trim($s);
            case self::KIND_CPF:
                return self::sanitizeCpf(is_scalar($value) ? (string) $value : '');
            case self::KIND_CNPJ:
                return self::sanitizeCnpj(is_scalar($value) ? (string) $value : '');
            case self::KIND_TELEFONE:
                return self::sanitizeTelefone(is_scalar($value) ? (string) $value : '');
            case self::KIND_TEXT:
            default:
                $s = is_scalar($value) ? (string) $value : '';
                return function_exists('sanitize_text_field') ? (string) \sanitize_text_field($s) : trim($s);
        }
    }

    /**
     * Heurística de "kind" baseada no nome da chave.
     */
    public static function inferKind(string $key): string
    {
        $lower = strtolower($key);

        if (substr($lower, -3) === '_md') {
            return self::KIND_HTML_MD;
        }
        if (strpos($lower, 'cpf') !== false) {
            return self::KIND_CPF;
        }
        if (strpos($lower, 'cnpj') !== false) {
            return self::KIND_CNPJ;
        }
        if (
            strpos($lower, 'telefone') !== false
            || strpos($lower, 'celular') !== false
            || strpos($lower, 'fone') !== false
        ) {
            return self::KIND_TELEFONE;
        }
        if (strpos($lower, 'email') !== false || strpos($lower, 'mail') !== false) {
            return self::KIND_EMAIL;
        }
        if (strpos($lower, 'url') !== false || strpos($lower, 'site') !== false) {
            return self::KIND_URL;
        }
        if (
            strpos($lower, 'biografia') !== false
            || strpos($lower, 'descricao') !== false
            || strpos($lower, 'observacao') !== false
        ) {
            return self::KIND_TEXTAREA;
        }
        if (substr($lower, -3) === '_id' || $lower === 'id') {
            return self::KIND_INT;
        }

        return self::KIND_TEXT;
    }

    /**
     * Recursivo: percorre o array respeitando whitelist e profundidade máxima.
     *
     * @param array<mixed,mixed>   $data
     * @param array<string,int>    $allowed flip da whitelist
     * @param array<string,string> $kindMap
     *
     * @return array<string,mixed>
     */
    private static function walk(array $data, array $allowed, array $kindMap, int $depth): array
    {
        if ($depth > self::MAX_DEPTH) {
            return [];
        }

        $out = [];
        foreach ($data as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            // Filtra por whitelist somente no nível raiz para preservar
            // estruturas internas (p.ex. lista de representantes).
            if ($depth === 0 && !isset($allowed[$key])) {
                continue;
            }

            if (is_array($value)) {
                // Lista numérica: sanitiza cada item escalar com KIND_TEXT
                // (ou KIND_INT se a chave indica id), preservando ordem.
                if (self::isNumericList($value)) {
                    $itemKind = $kindMap[$key] ?? self::inferKind($key);
                    $out[$key] = array_values(array_map(
                        static function ($item) use ($itemKind) {
                            if (is_array($item)) {
                                // Lista de objetos: descer recursivamente sem whitelist.
                                return self::walk($item, [], [], 999); // sentinela: bloqueado
                            }
                            return self::sanitizeValue($item, $itemKind);
                        },
                        $value
                    ));
                    continue;
                }
                // Objeto aninhado: recurse sem whitelist (chaves do nível raiz já filtradas).
                $out[$key] = self::walkNested($value, $kindMap, $depth + 1);
                continue;
            }

            $kind = $kindMap[$key] ?? self::inferKind($key);
            $out[$key] = self::sanitizeValue($value, $kind);
        }

        return $out;
    }

    /**
     * Recursão para níveis aninhados (sem whitelist — preserva campos opcionais
     * do sub-payload, como detalhes_pf, detalhes_or).
     *
     * @param array<mixed,mixed>   $data
     * @param array<string,string> $kindMap
     *
     * @return array<string,mixed>
     */
    private static function walkNested(array $data, array $kindMap, int $depth): array
    {
        if ($depth > self::MAX_DEPTH) {
            return [];
        }

        $out = [];
        foreach ($data as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            if (is_array($value)) {
                if (self::isNumericList($value)) {
                    $itemKind = $kindMap[$key] ?? self::inferKind($key);
                    $out[$key] = array_values(array_map(
                        static function ($item) use ($itemKind, $kindMap, $depth) {
                            if (is_array($item)) {
                                return self::walkNested($item, $kindMap, $depth + 1);
                            }
                            return self::sanitizeValue($item, $itemKind);
                        },
                        $value
                    ));
                    continue;
                }
                $out[$key] = self::walkNested($value, $kindMap, $depth + 1);
                continue;
            }
            $kind = $kindMap[$key] ?? self::inferKind($key);
            $out[$key] = self::sanitizeValue($value, $kind);
        }

        return $out;
    }

    /**
     * Heurística: lista numérica indexada (0,1,2,...).
     *
     * @param array<mixed,mixed> $arr
     */
    private static function isNumericList(array $arr): bool
    {
        if ($arr === []) {
            return true;
        }
        $i = 0;
        foreach ($arr as $k => $_) {
            if ($k !== $i) {
                return false;
            }
            $i++;
        }

        return true;
    }

    /**
     * CPF: extrai dígitos; rejeita comprimentos diferentes de 11 (mas devolve
     * o string original digit-only para o validador de domínio rejeitar com
     * mensagem específica).
     */
    private static function sanitizeCpf(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return substr($digits, 0, 14); // tolerância p/ casos com prefixo erroneamente colado.
    }

    private static function sanitizeCnpj(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return substr($digits, 0, 18);
    }

    /**
     * Telefone: preserva dígitos e o `+` opcional; descarta caracteres
     * decorativos exceto separadores comuns.
     */
    private static function sanitizeTelefone(string $value): string
    {
        $cleaned = preg_replace('/[^\d+\-\s()]/', '', $value) ?? '';
        $cleaned = trim($cleaned);

        return mb_substr($cleaned, 0, 32);
    }
}
