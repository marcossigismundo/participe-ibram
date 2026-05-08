<?php
/**
 * Unit tests for {@see Ibram\ParticipeIbram\Core\Validation\PhoneValidator}.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Core\Validation
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Core\Validation;

use Ibram\ParticipeIbram\Core\Validation\PhoneValidator;
use PHPUnit\Framework\TestCase;

final class PhoneValidatorTest extends TestCase
{
    /**
     * @dataProvider validProvider
     */
    public function test_is_valid_accepts_brazilian_numbers(string $phone): void
    {
        $this->assertTrue(PhoneValidator::isValid($phone));
    }

    /**
     * @return iterable<array{string}>
     */
    public function validProvider(): iterable
    {
        yield 'mobile sp'   => ['(11) 99999-1234'];
        yield 'mobile rj'   => ['21987654321'];
        yield 'landline sp' => ['(11) 3333-4444'];
        yield 'landline df' => ['6133334444'];
        yield 'with ddi 55' => ['+55 (11) 99999-1234'];
        yield 'mobile mg'   => ['31991234567'];
    }

    /**
     * @dataProvider invalidProvider
     */
    public function test_is_valid_rejects_invalid_numbers(string $phone): void
    {
        $this->assertFalse(PhoneValidator::isValid($phone));
    }

    /**
     * @return iterable<array{string}>
     */
    public function invalidProvider(): iterable
    {
        yield 'too short'         => ['1199991234'];
        yield 'too short9'        => ['199991234'];
        yield 'too long'          => ['119999123456'];
        yield 'invalid ddd 20'    => ['2099991234'];
        yield 'invalid ddd 23'    => ['2399991234'];
        yield 'invalid ddd 00'    => ['00999912345'];
        yield 'mobile no leading9'=> ['11899991234'];
        yield 'empty'             => [''];
    }

    public function test_normalize_strips_country_code(): void
    {
        $this->assertSame('11999991234', PhoneValidator::normalize('+55 (11) 99999-1234'));
        $this->assertSame('1133334444', PhoneValidator::normalize('+55 (11) 3333-4444'));
    }

    public function test_normalize_keeps_extra_digits_when_not_country_code(): void
    {
        $this->assertSame('123456789012', PhoneValidator::normalize('123456789012'));
    }

    public function test_format_eleven_digits(): void
    {
        $this->assertSame('(11) 99999-1234', PhoneValidator::format('11999991234'));
    }

    public function test_format_ten_digits(): void
    {
        $this->assertSame('(11) 3333-4444', PhoneValidator::format('1133334444'));
    }

    public function test_format_returns_input_when_unparseable(): void
    {
        $this->assertSame('abc', PhoneValidator::format('abc'));
    }
}
