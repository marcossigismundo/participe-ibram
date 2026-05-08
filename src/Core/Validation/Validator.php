<?php
/**
 * Facade orchestrator for field-level validation.
 *
 * @package Ibram\ParticipeIbram\Core\Validation
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Core\Validation;

use InvalidArgumentException;

/**
 * Runs a Laravel-style ruleset against an associative array of input data.
 *
 * Rules are passed as `'campo' => 'required|cpf'` or as an array of strings.
 * Error messages are returned in pt_BR via `__()`, never echoing back the
 * invalid value (LGPD: avoid leaking PII into log lines or response bodies).
 *
 * Supported rules:
 * - `required`            : value present and non-empty (after trim for strings).
 * - `email`               : delegates to {@see EmailValidator::isValid()}.
 * - `cpf`                 : delegates to {@see CpfValidator::isValid()}.
 * - `cnpj`                : delegates to {@see CnpjValidator::isValid()}.
 * - `phone`               : delegates to {@see PhoneValidator::isValid()}.
 * - `cep`                 : delegates to {@see CepValidator::isValid()}.
 * - `passaporte`          : delegates to {@see PassaporteValidator::isValid()}.
 * - `string`              : value is a string.
 * - `int`                 : value is an integer or numeric string of an integer.
 * - `bool`                : value is a boolean or boolean-coercible scalar.
 * - `min:N`               : string length / int value >= N.
 * - `max:N`               : string length / int value <= N.
 * - `in:a,b,c`            : value belongs to the comma-separated allowlist.
 * - `regex:/pattern/`     : value matches the supplied PCRE pattern.
 *
 * Note: rules following `required` are only enforced when the value is
 * non-empty, so optional fields can carry format rules without false errors.
 */
final class Validator
{
    /**
     * Validate `$data` against `$rules`.
     *
     * @param array<string, mixed>            $data  Input keyed by field name.
     * @param array<string, string|string[]>  $rules Rules per field, pipe-separated string or array of strings.
     *
     * @return array{errors: array<string, string>}
     */
    public static function validate(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $fieldRules) {
            $rulesList = self::parseRulesList($fieldRules);
            $value     = $data[$field] ?? null;
            $isPresent = self::isPresent($value);
            $isRequired = in_array('required', array_map([self::class, 'ruleName'], $rulesList), true);

            if ($isRequired && !$isPresent) {
                $errors[$field] = self::messageFor($field, 'required');
                continue;
            }

            if (!$isPresent) {
                // Optional and absent: skip remaining rules.
                continue;
            }

            foreach ($rulesList as $rule) {
                if ($rule === 'required') {
                    continue;
                }

                $error = self::applyRule($field, $rule, $value);
                if ($error !== null) {
                    $errors[$field] = $error;
                    break; // First error per field is enough.
                }
            }
        }

        return ['errors' => $errors];
    }

    /**
     * Apply a single rule, returning an error message or null if the value
     * passes.
     *
     * @param string $field
     * @param string $rule
     * @param mixed  $value
     */
    private static function applyRule(string $field, string $rule, $value): ?string
    {
        [$name, $arg] = self::splitRule($rule);

        switch ($name) {
            case 'email':
                return EmailValidator::isValid((string) $value)
                    ? null
                    : self::messageFor($field, 'email');

            case 'cpf':
                return CpfValidator::isValid((string) $value)
                    ? null
                    : self::messageFor($field, 'cpf');

            case 'cnpj':
                return CnpjValidator::isValid((string) $value)
                    ? null
                    : self::messageFor($field, 'cnpj');

            case 'phone':
                return PhoneValidator::isValid((string) $value)
                    ? null
                    : self::messageFor($field, 'phone');

            case 'cep':
                return CepValidator::isValid((string) $value)
                    ? null
                    : self::messageFor($field, 'cep');

            case 'passaporte':
                return PassaporteValidator::isValid((string) $value)
                    ? null
                    : self::messageFor($field, 'passaporte');

            case 'string':
                return is_string($value)
                    ? null
                    : self::messageFor($field, 'string');

            case 'int':
                if (is_int($value)) {
                    return null;
                }
                if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
                    return null;
                }
                return self::messageFor($field, 'int');

            case 'bool':
                if (is_bool($value)) {
                    return null;
                }
                if (in_array($value, [0, 1, '0', '1', 'true', 'false', 'on', 'off'], true)) {
                    return null;
                }
                return self::messageFor($field, 'bool');

            case 'min':
                $min = (int) ($arg ?? '0');
                if (is_string($value) && strlen($value) < $min) {
                    return self::messageFor($field, 'min', ['min' => $min]);
                }
                if (is_int($value) && $value < $min) {
                    return self::messageFor($field, 'min', ['min' => $min]);
                }
                return null;

            case 'max':
                $max = (int) ($arg ?? '0');
                if (is_string($value) && strlen($value) > $max) {
                    return self::messageFor($field, 'max', ['max' => $max]);
                }
                if (is_int($value) && $value > $max) {
                    return self::messageFor($field, 'max', ['max' => $max]);
                }
                return null;

            case 'in':
                $allowed = $arg !== null ? explode(',', $arg) : [];
                if (!in_array((string) $value, $allowed, true)) {
                    return self::messageFor($field, 'in');
                }
                return null;

            case 'regex':
                if ($arg === null || $arg === '') {
                    throw new InvalidArgumentException('regex rule requires a pattern argument');
                }
                if (!is_string($value) || preg_match($arg, $value) !== 1) {
                    return self::messageFor($field, 'regex');
                }
                return null;

            default:
                throw new InvalidArgumentException(sprintf('Unknown validation rule "%s"', $name));
        }
    }

    /**
     * Whether a value should be considered present for `required` purposes.
     *
     * @param mixed $value
     */
    private static function isPresent($value): bool
    {
        if ($value === null) {
            return false;
        }
        if (is_string($value) && trim($value) === '') {
            return false;
        }
        if (is_array($value) && $value === []) {
            return false;
        }
        return true;
    }

    /**
     * Build the i18n error message for a given field and rule.
     *
     * Never includes the offending value (LGPD: PII must not leak into
     * messages that may be logged or displayed).
     *
     * @param string                      $field
     * @param string                      $rule
     * @param array<string, int|string>   $params
     */
    private static function messageFor(string $field, string $rule, array $params = []): string
    {
        switch ($rule) {
            case 'required':
                /* translators: %s: field name */
                $template = __('O campo %s é obrigatório.', 'participe-ibram');
                return sprintf($template, $field);
            case 'email':
                /* translators: %s: field name */
                $template = __('O campo %s deve ser um e-mail válido.', 'participe-ibram');
                return sprintf($template, $field);
            case 'cpf':
                /* translators: %s: field name */
                $template = __('O campo %s deve conter um CPF válido.', 'participe-ibram');
                return sprintf($template, $field);
            case 'cnpj':
                /* translators: %s: field name */
                $template = __('O campo %s deve conter um CNPJ válido.', 'participe-ibram');
                return sprintf($template, $field);
            case 'phone':
                /* translators: %s: field name */
                $template = __('O campo %s deve conter um telefone brasileiro válido.', 'participe-ibram');
                return sprintf($template, $field);
            case 'cep':
                /* translators: %s: field name */
                $template = __('O campo %s deve conter um CEP válido.', 'participe-ibram');
                return sprintf($template, $field);
            case 'passaporte':
                /* translators: %s: field name */
                $template = __('O campo %s deve conter um número de passaporte válido.', 'participe-ibram');
                return sprintf($template, $field);
            case 'string':
                /* translators: %s: field name */
                $template = __('O campo %s deve ser um texto.', 'participe-ibram');
                return sprintf($template, $field);
            case 'int':
                /* translators: %s: field name */
                $template = __('O campo %s deve ser um número inteiro.', 'participe-ibram');
                return sprintf($template, $field);
            case 'bool':
                /* translators: %s: field name */
                $template = __('O campo %s deve ser verdadeiro ou falso.', 'participe-ibram');
                return sprintf($template, $field);
            case 'min':
                /* translators: 1: field name, 2: minimum */
                $template = __('O campo %1$s deve ter no mínimo %2$d.', 'participe-ibram');
                return sprintf($template, $field, (int) ($params['min'] ?? 0));
            case 'max':
                /* translators: 1: field name, 2: maximum */
                $template = __('O campo %1$s deve ter no máximo %2$d.', 'participe-ibram');
                return sprintf($template, $field, (int) ($params['max'] ?? 0));
            case 'in':
                /* translators: %s: field name */
                $template = __('O campo %s contém um valor não permitido.', 'participe-ibram');
                return sprintf($template, $field);
            case 'regex':
                /* translators: %s: field name */
                $template = __('O campo %s está em formato inválido.', 'participe-ibram');
                return sprintf($template, $field);
            default:
                /* translators: %s: field name */
                $template = __('O campo %s é inválido.', 'participe-ibram');
                return sprintf($template, $field);
        }
    }

    /**
     * Normalize the rules input (string or array of strings) into a flat list.
     *
     * @param string|string[] $rules
     * @return string[]
     */
    private static function parseRulesList($rules): array
    {
        if (is_array($rules)) {
            $list = [];
            foreach ($rules as $entry) {
                if (!is_string($entry)) {
                    continue;
                }
                foreach (explode('|', $entry) as $r) {
                    $r = trim($r);
                    if ($r !== '') {
                        $list[] = $r;
                    }
                }
            }
            return $list;
        }

        if (is_string($rules)) {
            $list = [];
            foreach (explode('|', $rules) as $r) {
                $r = trim($r);
                if ($r !== '') {
                    $list[] = $r;
                }
            }
            return $list;
        }

        return [];
    }

    /**
     * Extract the rule name (everything before the first colon).
     */
    private static function ruleName(string $rule): string
    {
        $pos = strpos($rule, ':');
        return $pos === false ? $rule : substr($rule, 0, $pos);
    }

    /**
     * Split `name:argument` into a [name, argument|null] tuple.
     *
     * @return array{0: string, 1: ?string}
     */
    private static function splitRule(string $rule): array
    {
        $pos = strpos($rule, ':');
        if ($pos === false) {
            return [$rule, null];
        }
        return [substr($rule, 0, $pos), substr($rule, $pos + 1)];
    }
}
