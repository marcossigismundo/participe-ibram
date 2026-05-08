<?php
/**
 * Unit tests for {@see Termo}.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Domain\Consentimento
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Domain\Consentimento;

use DateTimeImmutable;
use Ibram\ParticipeIbram\Domain\Consentimento\Termo;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Ibram\ParticipeIbram\Domain\Consentimento\Termo
 */
final class TermoTest extends TestCase
{
    public function test_create_calculates_sha256_of_content(): void
    {
        $conteudo = "# Política de Privacidade\n\nTexto exemplo.";
        $termo    = Termo::create('2026.05.01', $conteudo, 1);

        self::assertSame('2026.05.01', $termo->versao());
        self::assertSame(hash('sha256', $conteudo), $termo->hashConteudo());
        self::assertSame(64, strlen($termo->hashConteudo()));
    }

    public function test_create_marks_termo_active_now(): void
    {
        $termo = Termo::create('1.0', 'conteudo', 1);
        self::assertTrue($termo->isAtivo());
    }

    public function test_isAtivo_false_when_now_before_ativoEm(): void
    {
        $termo = Termo::fromState(
            10,
            '1.0',
            'c',
            hash('sha256', 'c'),
            new DateTimeImmutable('2030-01-01T00:00:00Z'),
            null,
            1
        );
        self::assertFalse($termo->isAtivo(new DateTimeImmutable('2026-01-01T00:00:00Z')));
    }

    public function test_isAtivo_false_when_now_after_inativoEm(): void
    {
        $termo = Termo::fromState(
            10,
            '1.0',
            'c',
            hash('sha256', 'c'),
            new DateTimeImmutable('2024-01-01T00:00:00Z'),
            new DateTimeImmutable('2025-01-01T00:00:00Z'),
            1
        );
        self::assertFalse($termo->isAtivo(new DateTimeImmutable('2026-01-01T00:00:00Z')));
    }

    public function test_isAtivo_true_in_window(): void
    {
        $termo = Termo::fromState(
            10,
            '1.0',
            'c',
            hash('sha256', 'c'),
            new DateTimeImmutable('2024-01-01T00:00:00Z'),
            new DateTimeImmutable('2027-01-01T00:00:00Z'),
            1
        );
        self::assertTrue($termo->isAtivo(new DateTimeImmutable('2026-05-01T00:00:00Z')));
    }

    public function test_constructor_rejects_empty_versao(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Termo::create('  ', 'algum conteudo', 1);
    }

    public function test_constructor_rejects_empty_content(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Termo::create('1.0', '   ', 1);
    }

    public function test_constructor_rejects_invalid_hash(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Termo(null, '1.0', 'c', 'not-a-hash', new DateTimeImmutable(), null, 1);
    }

    public function test_constructor_rejects_inativo_before_ativo(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Termo(
            null,
            '1.0',
            'c',
            hash('sha256', 'c'),
            new DateTimeImmutable('2026-05-01'),
            new DateTimeImmutable('2026-04-01'),
            1
        );
    }

    public function test_withInativoEm_returns_new_instance(): void
    {
        $termo  = Termo::create('1.0', 'conteudo', 1);
        $closed = $termo->withInativoEm(new DateTimeImmutable('+1 day'));

        self::assertNull($termo->inativoEm());
        self::assertNotNull($closed->inativoEm());
        self::assertNotSame($termo, $closed);
    }
}
