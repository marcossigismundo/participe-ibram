<?php
/**
 * Unit tests for {@see Ibram\ParticipeIbram\Core\Validation\CpfValidator}.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Core\Validation
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Core\Validation;

use Ibram\ParticipeIbram\Core\Validation\CpfValidator;
use PHPUnit\Framework\TestCase;

final class CpfValidatorTest extends TestCase
{
    /**
     * @dataProvider validCpfProvider
     */
    public function test_is_valid_returns_true_for_known_valid_cpfs(string $cpf): void
    {
        $this->assertTrue(CpfValidator::isValid($cpf));
    }

    /**
     * @return iterable<array{string}>
     */
    public function validCpfProvider(): iterable
    {
        // Synthetic-but-arithmetically-correct CPFs (no real-life identifier reused).
        yield ['529.982.247-25'];
        yield ['52998224725'];
        yield ['111.444.777-35'];
        yield ['390.533.447-05'];
        yield ['248.438.034-80'];
        yield ['153.509.460-56'];
    }

    /**
     * @dataProvider invalidCpfProvider
     */
    public function test_is_valid_returns_false_for_invalid_cpfs(string $cpf): void
    {
        $this->assertFalse(CpfValidator::isValid($cpf));
    }

    /**
     * @return iterable<array{string}>
     */
    public function invalidCpfProvider(): iterable
    {
        yield 'all zeros'   => ['000.000.000-00'];
        yield 'all ones'    => ['111.111.111-11'];
        yield 'all nines'   => ['999.999.999-99'];
        yield 'too short'   => ['123.456.789-0'];
        yield 'too long'    => ['529.982.247-255'];
        yield 'wrong digit' => ['529.982.247-26'];
        yield 'letters'     => ['abc.def.ghi-jk'];
        yield 'empty'       => [''];
    }

    public function test_normalize_strips_punctuation(): void
    {
        $this->assertSame('52998224725', CpfValidator::normalize('529.982.247-25'));
        $this->assertSame('52998224725', CpfValidator::normalize(' 529 982 247 25 '));
    }

    public function test_format_outputs_canonical_shape(): void
    {
        $this->assertSame('529.982.247-25', CpfValidator::format('52998224725'));
    }

    public function test_format_returns_input_when_invalid_length(): void
    {
        $this->assertSame('123', CpfValidator::format('123'));
    }

    public function test_mask_reveals_only_seventh_eighth_ninth_digits(): void
    {
        $this->assertSame('***.***.247-**', CpfValidator::mask('52998224725'));
    }

    public function test_mask_returns_safe_default_for_invalid_input(): void
    {
        $this->assertSame('***.***.***-**', CpfValidator::mask('abc'));
    }
}
