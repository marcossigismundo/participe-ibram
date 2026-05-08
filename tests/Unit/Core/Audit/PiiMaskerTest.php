<?php
/**
 * Unit tests for PiiMasker.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Core\Audit
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Core\Audit;

use Ibram\ParticipeIbram\Core\Audit\PiiMasker;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Ibram\ParticipeIbram\Core\Audit\PiiMasker
 */
final class PiiMaskerTest extends TestCase
{
    /**
     * @dataProvider emailProvider
     */
    public function testMaskEmail(string $input, string $expected): void
    {
        self::assertSame($expected, PiiMasker::maskEmail($input));
    }

    /**
     * @return array<string, array{0:string,1:string}>
     */
    public static function emailProvider(): array
    {
        return [
            'standard'   => ['fulano@example.org', 'f***@example.org'],
            'short'      => ['ab@x.io', 'a***@x.io'],
            'invalid'    => ['no-at-sign', '[REDACTED]'],
            'empty'      => ['', '[REDACTED]'],
        ];
    }

    /**
     * @dataProvider cpfProvider
     */
    public function testMaskCpf(string $input, string $expected): void
    {
        self::assertSame($expected, PiiMasker::maskCpf($input));
    }

    /**
     * @return array<string, array{0:string,1:string}>
     */
    public static function cpfProvider(): array
    {
        return [
            'formatted'   => ['123.456.789-09', 'XXX.XXX.789-XX'],
            'unformatted' => ['12345678909', 'XXX.XXX.789-XX'],
            'too short'   => ['123', '[REDACTED]'],
        ];
    }

    /**
     * @dataProvider cnpjProvider
     */
    public function testMaskCnpj(string $input, string $expected): void
    {
        self::assertSame($expected, PiiMasker::maskCnpj($input));
    }

    /**
     * @return array<string, array{0:string,1:string}>
     */
    public static function cnpjProvider(): array
    {
        return [
            'formatted'   => ['12.345.678/0001-95', 'XX.XXX.XXX/0001-XX'],
            'unformatted' => ['12345678000195', 'XX.XXX.XXX/0001-XX'],
            'too short'   => ['1234', '[REDACTED]'],
        ];
    }

    /**
     * @dataProvider phoneProvider
     */
    public function testMaskPhone(string $input, string $expected): void
    {
        self::assertSame($expected, PiiMasker::maskPhone($input));
    }

    /**
     * @return array<string, array{0:string,1:string}>
     */
    public static function phoneProvider(): array
    {
        return [
            'br formatted' => ['+55 (11) 99999-1234', '(XX) 9XXXX-1234'],
            'digits only'  => ['1199991234', '(XX) 9XXXX-1234'],
            'too short'    => ['12', '[REDACTED]'],
        ];
    }

    public function testMaskGenericKeepsOnlyEdges(): void
    {
        self::assertSame('J***o', PiiMasker::maskGeneric('Joao', 1, 1));
        self::assertSame('Fu***al', PiiMasker::maskGeneric('Fulano de Tal', 2, 2));
    }

    public function testMaskGenericReturnsRedactedWhenTooShort(): void
    {
        self::assertSame('[REDACTED]', PiiMasker::maskGeneric('ab', 1, 1));
        self::assertSame('[REDACTED]', PiiMasker::maskGeneric(''));
    }
}
