<?php
/**
 * Unit tests for {@see Ibram\ParticipeIbram\Core\Validation\PassaporteValidator}.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Core\Validation
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Core\Validation;

use Ibram\ParticipeIbram\Core\Validation\PassaporteValidator;
use PHPUnit\Framework\TestCase;

final class PassaporteValidatorTest extends TestCase
{
    public function test_is_valid_accepts_two_letters_six_digits(): void
    {
        $this->assertTrue(PassaporteValidator::isValid('AB123456'));
    }

    public function test_is_valid_accepts_two_letters_seven_digits(): void
    {
        $this->assertTrue(PassaporteValidator::isValid('AB1234567'));
    }

    public function test_is_valid_lowercase_input_is_normalized(): void
    {
        $this->assertTrue(PassaporteValidator::isValid('ab123456'));
    }

    public function test_is_valid_rejects_short_or_long(): void
    {
        $this->assertFalse(PassaporteValidator::isValid('AB12345'));
        $this->assertFalse(PassaporteValidator::isValid('AB12345678'));
    }

    public function test_is_valid_rejects_missing_letters(): void
    {
        $this->assertFalse(PassaporteValidator::isValid('12345678'));
    }

    public function test_normalize_uppercases_and_strips(): void
    {
        $this->assertSame('AB123456', PassaporteValidator::normalize('ab 123-456'));
    }
}
