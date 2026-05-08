<?php
/**
 * Unit tests for {@see Ibram\ParticipeIbram\Domain\Edital\Categoria}.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Domain\Edital
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Domain\Edital;

use Ibram\ParticipeIbram\Domain\Edital\Categoria;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CategoriaTest extends TestCase
{
    public function test_construcao_minima_valida(): void
    {
        $cat = $this->fabrica('PF,OR,SM');
        $this->assertSame('PF,OR,SM', $cat->tiposAgenteElegivel());
        $this->assertSame(2, $cat->numVagas());
        $this->assertSame(1, $cat->numSuplentes());
        $this->assertSame([], $cat->documentosExigidos());
    }

    public function test_normaliza_tipos_agente_remove_duplicatas_e_caixa(): void
    {
        $cat = $this->fabrica(' pf , or, OR , sm ');
        $this->assertSame('PF,OR,SM', $cat->tiposAgenteElegivel());
    }

    public function test_tipo_invalido_em_csv_lanca(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->fabrica('PF,XX');
    }

    public function test_csv_vazio_lanca(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->fabrica('');
    }

    /**
     * @dataProvider aceitaTipoProvider
     */
    public function test_aceita_tipo_agente(string $csv, string $tipo, bool $esperado): void
    {
        $cat = $this->fabrica($csv);
        $this->assertSame($esperado, $cat->aceitaTipoAgente($tipo));
    }

    /**
     * @return iterable<array{string,string,bool}>
     */
    public function aceitaTipoProvider(): iterable
    {
        yield ['PF,OR,SM', 'PF', true];
        yield ['PF,OR,SM', 'pf', true];      // case insensitive
        yield ['PF,OR,SM', ' OR ', true];    // trim
        yield ['PF,OR,SM', 'SM', true];
        yield ['PF', 'OR', false];
        yield ['PF', 'PF', true];
        yield ['OR,SM', 'PF', false];        // categoria so-OR e SM
        yield ['OR', 'XX', false];           // invalid type returns false (not throws)
        yield ['SM', '', false];             // empty
    }

    public function test_documentos_exigidos_normaliza_lista(): void
    {
        $cat = new Categoria(
            null,
            1,
            'Cat',
            null,
            1,
            0,
            'PF',
            null,
            ['cnpj', 'estatuto', 'estatuto', '', '  ata_posse  '],
            0
        );
        $this->assertSame(['cnpj', 'estatuto', 'ata_posse'], $cat->documentosExigidos());
    }

    public function test_with_id_clona(): void
    {
        $cat = $this->fabrica('PF');
        $cl  = $cat->withId(99);
        $this->assertNull($cat->id());
        $this->assertSame(99, $cl->id());
    }

    private function fabrica(string $csv): Categoria
    {
        return new Categoria(
            null,
            1,
            'Cat A',
            null,
            2,
            1,
            $csv,
            null,
            [],
            0
        );
    }
}
