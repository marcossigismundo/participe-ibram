<?php
/**
 * Unit tests for {@see Ibram\ParticipeIbram\Domain\Agente\NumeroRegistro}.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Domain\Agente
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Domain\Agente;

use Ibram\ParticipeIbram\Domain\Agente\NumeroRegistro;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class NumeroRegistroTest extends TestCase
{
    public function test_aceita_formato_valido(): void
    {
        $vo = new NumeroRegistro('PI-PF-2026-000123');
        $this->assertSame('PF', $vo->tipo());
        $this->assertSame(2026, $vo->ano());
        $this->assertSame(123, $vo->sequencia());
        $this->assertSame('PI-PF-2026-000123', $vo->value());
        $this->assertSame('PI-PF-2026-000123', (string) $vo);
    }

    /**
     * @dataProvider validProvider
     */
    public function test_aceita_variacoes_validas(string $input): void
    {
        $vo = new NumeroRegistro($input);
        $this->assertSame($input, $vo->value());
    }

    /**
     * @return iterable<array{string}>
     */
    public function validProvider(): iterable
    {
        yield ['PI-PF-2024-000001'];
        yield ['PI-OR-2025-000045'];
        yield ['PI-SM-2026-000007'];
        yield ['PI-PF-2099-999999'];
    }

    /**
     * @dataProvider invalidProvider
     */
    public function test_rejeita_formatos_invalidos(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);
        new NumeroRegistro($input);
    }

    /**
     * @return iterable<array{string}>
     */
    public function invalidProvider(): iterable
    {
        // Tamanho da sequência errado.
        yield 'sequencia-curta'         => ['PI-PF-2026-00001'];
        yield 'sequencia-longa'         => ['PI-PF-2026-0000001'];
        // Tipo desconhecido.
        yield 'tipo-invalido'           => ['PI-XX-2026-000001'];
        // Prefixo errado.
        yield 'prefixo-errado'          => ['XX-PF-2026-000001'];
        // Ano com 5 dígitos.
        yield 'ano-5-digitos'           => ['PI-PF-20260-000001'];
        // Ano fora do intervalo (sanidade).
        yield 'ano-anterior-2024'       => ['PI-PF-2023-000001'];
        yield 'ano-pos-2099'            => ['PI-PF-2100-000001'];
        // Sequência zero.
        yield 'sequencia-zero'          => ['PI-PF-2026-000000'];
        // String vazia.
        yield 'vazio'                   => [''];
        // Caracteres não-numéricos na sequência.
        yield 'sequencia-letras'        => ['PI-PF-2026-00012A'];
        // Tipo em minúscula.
        yield 'tipo-minusculo'          => ['PI-pf-2026-000001'];
    }

    public function test_from_parts_valida_e_serializa(): void
    {
        $vo = NumeroRegistro::fromParts('PF', 2026, 7);
        $this->assertSame('PI-PF-2026-000007', $vo->value());

        $vo2 = NumeroRegistro::fromParts(' or ', 2025, 999999);
        $this->assertSame('PI-OR-2025-999999', $vo2->value());
    }

    public function test_from_parts_rejeita_tipo_invalido(): void
    {
        $this->expectException(InvalidArgumentException::class);
        NumeroRegistro::fromParts('XX', 2026, 1);
    }

    public function test_from_parts_rejeita_sequencia_fora_de_range(): void
    {
        $this->expectException(InvalidArgumentException::class);
        NumeroRegistro::fromParts('PF', 2026, 0);
    }

    public function test_from_parts_rejeita_ano_fora_de_range(): void
    {
        $this->expectException(InvalidArgumentException::class);
        NumeroRegistro::fromParts('PF', 1999, 1);
    }

    public function test_equals_compara_por_valor(): void
    {
        $a = new NumeroRegistro('PI-PF-2026-000123');
        $b = NumeroRegistro::fromParts('PF', 2026, 123);
        $c = new NumeroRegistro('PI-PF-2026-000124');

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }
}
