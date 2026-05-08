<?php
/**
 * Unit tests for {@see Ibram\ParticipeIbram\Core\Validation\EmailValidator}.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Core\Validation
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Core\Validation;

use Ibram\ParticipeIbram\Core\Validation\EmailValidator;
use PHPUnit\Framework\TestCase;

final class EmailValidatorTest extends TestCase
{
    /**
     * @dataProvider validEmailProvider
     */
    public function test_is_valid_accepts_well_formed_emails(string $email): void
    {
        $this->assertTrue(EmailValidator::isValid($email));
    }

    /**
     * @return iterable<array{string}>
     */
    public function validEmailProvider(): iterable
    {
        yield ['agente@museus.gov.br'];
        yield ['fulano.de.tal@example.com'];
        yield ['user+tag@example.co'];
        yield ['x@y.zz'];
        yield ['MARCOS@GMAIL.COM'];
    }

    /**
     * @dataProvider invalidEmailProvider
     */
    public function test_is_valid_rejects_malformed_emails(string $email): void
    {
        $this->assertFalse(EmailValidator::isValid($email));
    }

    /**
     * @return iterable<array{string}>
     */
    public function invalidEmailProvider(): iterable
    {
        yield 'no at'        => ['not-an-email'];
        yield 'has space'    => ['fulano @example.com'];
        yield 'has tab'      => ["a@b\t.com"];
        yield 'empty'        => [''];
        yield 'bare at'      => ['@example.com'];
        yield 'no domain'    => ['fulano@'];
        yield 'too long'     => [str_repeat('a', 250) . '@example.com'];
        yield 'long local'   => [str_repeat('a', 65) . '@example.com'];
    }

    public function test_normalize_lowercases_and_trims(): void
    {
        $this->assertSame('marcos@gmail.com', EmailValidator::normalize('  Marcos@GMail.COM  '));
    }
}
