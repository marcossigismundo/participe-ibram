<?php
/**
 * Unit tests for {@see Ibram\ParticipeIbram\Core\Validation\CepValidator}.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Core\Validation
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Core\Validation;

use Ibram\ParticipeIbram\Core\Validation\CepValidator;
use PHPUnit\Framework\TestCase;

final class CepValidatorTest extends TestCase
{
    public function test_is_valid_accepts_canonical_and_compact_forms(): void
    {
        $this->assertTrue(CepValidator::isValid('70040-010'));
        $this->assertTrue(CepValidator::isValid('70040010'));
        $this->assertTrue(CepValidator::isValid('01310-100'));
    }

    public function test_is_valid_rejects_wrong_length(): void
    {
        $this->assertFalse(CepValidator::isValid('1234567'));
        $this->assertFalse(CepValidator::isValid('123456789'));
    }

    public function test_is_valid_rejects_all_zeros(): void
    {
        $this->assertFalse(CepValidator::isValid('00000-000'));
    }

    public function test_format(): void
    {
        $this->assertSame('70040-010', CepValidator::format('70040010'));
    }

    public function test_normalize(): void
    {
        $this->assertSame('70040010', CepValidator::normalize('70040-010'));
    }
}
