<?php
/**
 * Unit tests for {@see Ibram\ParticipeIbram\Core\Validation\CnpjValidator}.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Core\Validation
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Core\Validation;

use Ibram\ParticipeIbram\Core\Validation\CnpjValidator;
use PHPUnit\Framework\TestCase;

final class CnpjValidatorTest extends TestCase
{
    /**
     * @dataProvider validCnpjProvider
     */
    public function test_is_valid_returns_true_for_known_valid_cnpjs(string $cnpj): void
    {
        $this->assertTrue(CnpjValidator::isValid($cnpj));
    }

    /**
     * @return iterable<array{string}>
     */
    public function validCnpjProvider(): iterable
    {
        // Arithmetically-correct CNPJs (synthetic).
        yield ['11.222.333/0001-81'];
        yield ['11222333000181'];
        yield ['45.997.418/0001-53'];
        yield ['33.000.167/0001-01'];
        yield ['60.701.190/0001-04'];
        yield ['00.000.000/0001-91'];
    }

    /**
     * @dataProvider invalidCnpjProvider
     */
    public function test_is_valid_returns_false_for_invalid_cnpjs(string $cnpj): void
    {
        $this->assertFalse(CnpjValidator::isValid($cnpj));
    }

    /**
     * @return iterable<array{string}>
     */
    public function invalidCnpjProvider(): iterable
    {
        yield 'all zeros'    => ['00.000.000/0000-00'];
        yield 'all ones'     => ['11.111.111/1111-11'];
        yield 'all nines'    => ['99.999.999/9999-99'];
        yield 'too short'    => ['11.222.333/0001-8'];
        yield 'too long'     => ['11.222.333/0001-811'];
        yield 'wrong digit1' => ['11.222.333/0001-71'];
        yield 'wrong digit2' => ['11.222.333/0001-82'];
        yield 'letters'      => ['ab.cde.fgh/ijkl-mn'];
        yield 'empty'        => [''];
    }

    public function test_normalize_strips_punctuation(): void
    {
        $this->assertSame('11222333000181', CnpjValidator::normalize('11.222.333/0001-81'));
    }

    public function test_format_outputs_canonical_shape(): void
    {
        $this->assertSame('11.222.333/0001-81', CnpjValidator::format('11222333000181'));
    }

    public function test_format_returns_input_when_invalid_length(): void
    {
        $this->assertSame('123', CnpjValidator::format('123'));
    }

    public function test_mask_reveals_only_branch_segment(): void
    {
        $this->assertSame('**.***.***/0001-**', CnpjValidator::mask('11222333000181'));
    }
}
