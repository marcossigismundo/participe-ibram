<?php
/**
 * Unit tests for {@see Ibram\ParticipeIbram\Domain\Agente\TipoAgente}.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Domain\Agente
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Domain\Agente;

use Ibram\ParticipeIbram\Domain\Agente\TipoAgente;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TipoAgenteTest extends TestCase
{
    public function test_factories_devolvem_valores_canonicos(): void
    {
        $this->assertSame('PF', TipoAgente::pf()->value());
        $this->assertSame('OR', TipoAgente::org()->value());
        $this->assertSame('SM', TipoAgente::sm()->value());
    }

    /**
     * @dataProvider validProvider
     */
    public function test_from_string_normaliza_caixa(string $input, string $expected): void
    {
        $tipo = TipoAgente::fromString($input);
        $this->assertSame($expected, $tipo->value());
        $this->assertSame($expected, (string) $tipo);
    }

    /**
     * @return iterable<array{string,string}>
     */
    public function validProvider(): iterable
    {
        yield ['PF', 'PF'];
        yield ['pf', 'PF'];
        yield [' Pf ', 'PF'];
        yield ['OR', 'OR'];
        yield ['or', 'OR'];
        yield ['SM', 'SM'];
        yield ['sm', 'SM'];
    }

    public function test_from_string_rejeita_invalidos(): void
    {
        $this->expectException(InvalidArgumentException::class);
        TipoAgente::fromString('XX');
    }

    public function test_from_string_rejeita_string_vazia(): void
    {
        $this->expectException(InvalidArgumentException::class);
        TipoAgente::fromString('');
    }

    public function test_all_retorna_lista_canonica(): void
    {
        $this->assertSame(['PF', 'OR', 'SM'], TipoAgente::all());
    }

    public function test_equals_compara_por_valor(): void
    {
        $a = TipoAgente::pf();
        $b = TipoAgente::fromString('pf');
        $c = TipoAgente::sm();

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }
}
