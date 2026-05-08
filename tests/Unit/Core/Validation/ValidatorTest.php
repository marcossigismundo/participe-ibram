<?php
/**
 * Unit tests for {@see Ibram\ParticipeIbram\Core\Validation\Validator}.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Core\Validation
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Core\Validation;

use Ibram\ParticipeIbram\Core\Validation\Validator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

if (!function_exists(__NAMESPACE__ . '\\__')) {
    /**
     * Test stub for WP `__()` translation helper.
     */
    function __($text, $domain = null)
    {
        return $text;
    }
}

// Also alias to the global namespace if WP isn't loaded.
if (!function_exists('__')) {
    /**
     * Global `__` stub used by the SUT.
     */
    function __($text, $domain = null)
    {
        return $text;
    }
}

final class ValidatorTest extends TestCase
{
    public function test_required_rule_flags_missing_field(): void
    {
        $result = Validator::validate([], ['nome' => 'required|string']);
        $this->assertArrayHasKey('nome', $result['errors']);
    }

    public function test_required_rule_flags_empty_string(): void
    {
        $result = Validator::validate(['nome' => '   '], ['nome' => 'required|string']);
        $this->assertArrayHasKey('nome', $result['errors']);
    }

    public function test_optional_field_skips_format_rules_when_absent(): void
    {
        $result = Validator::validate([], ['email' => 'email']);
        $this->assertSame([], $result['errors']);
    }

    public function test_email_rule(): void
    {
        $ok  = Validator::validate(['e' => 'foo@bar.com'], ['e' => 'required|email']);
        $bad = Validator::validate(['e' => 'foo'],         ['e' => 'required|email']);

        $this->assertSame([], $ok['errors']);
        $this->assertArrayHasKey('e', $bad['errors']);
    }

    public function test_cpf_and_cnpj_rules(): void
    {
        $result = Validator::validate(
            [
                'cpf'  => '52998224725',
                'cnpj' => '11222333000181',
            ],
            [
                'cpf'  => 'required|cpf',
                'cnpj' => 'required|cnpj',
            ]
        );
        $this->assertSame([], $result['errors']);
    }

    public function test_min_max_rules_on_strings(): void
    {
        $result = Validator::validate(
            ['name' => 'ab'],
            ['name' => 'string|min:3|max:5']
        );
        $this->assertArrayHasKey('name', $result['errors']);

        $result = Validator::validate(
            ['name' => 'abcdef'],
            ['name' => 'string|min:3|max:5']
        );
        $this->assertArrayHasKey('name', $result['errors']);

        $result = Validator::validate(
            ['name' => 'abcd'],
            ['name' => 'string|min:3|max:5']
        );
        $this->assertSame([], $result['errors']);
    }

    public function test_in_rule(): void
    {
        $result = Validator::validate(
            ['estado' => 'XX'],
            ['estado' => 'in:DF,SP,RJ']
        );
        $this->assertArrayHasKey('estado', $result['errors']);

        $result = Validator::validate(
            ['estado' => 'SP'],
            ['estado' => 'in:DF,SP,RJ']
        );
        $this->assertSame([], $result['errors']);
    }

    public function test_int_and_bool_rules(): void
    {
        $result = Validator::validate(
            ['age' => '42', 'active' => 'true'],
            ['age' => 'int', 'active' => 'bool']
        );
        $this->assertSame([], $result['errors']);

        $result = Validator::validate(
            ['age' => 'abc'],
            ['age' => 'int']
        );
        $this->assertArrayHasKey('age', $result['errors']);
    }

    public function test_regex_rule(): void
    {
        $result = Validator::validate(
            ['code' => 'AB12'],
            ['code' => 'regex:/^[A-Z]{2}\d{2}$/']
        );
        $this->assertSame([], $result['errors']);

        $result = Validator::validate(
            ['code' => 'abc'],
            ['code' => 'regex:/^[A-Z]{2}\d{2}$/']
        );
        $this->assertArrayHasKey('code', $result['errors']);
    }

    public function test_unknown_rule_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Validator::validate(['x' => 'y'], ['x' => 'unknown_rule']);
    }

    public function test_error_message_does_not_leak_value(): void
    {
        $result = Validator::validate(
            ['cpf' => '12345678900'],
            ['cpf' => 'cpf']
        );
        $this->assertArrayHasKey('cpf', $result['errors']);
        // PII (the rejected value) must not appear in the message.
        $this->assertStringNotContainsString('12345678900', $result['errors']['cpf']);
    }

    public function test_first_error_per_field_only(): void
    {
        $result = Validator::validate(
            ['x' => 'ab'],
            ['x' => 'required|min:5|max:1']
        );
        // Only one error message per field is produced.
        $this->assertCount(1, $result['errors']);
    }
}
