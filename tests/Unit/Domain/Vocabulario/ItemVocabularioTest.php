<?php
/**
 * Unit tests for ItemVocabulario.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Domain\Vocabulario
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Domain\Vocabulario;

use Ibram\ParticipeIbram\Domain\Vocabulario\ItemVocabulario;
use Ibram\ParticipeIbram\Domain\Vocabulario\TipoVocabulario;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Ibram\ParticipeIbram\Domain\Vocabulario\ItemVocabulario
 */
final class ItemVocabularioTest extends TestCase
{
    public function testConstructorExposesAllAccessors(): void
    {
        $item = new ItemVocabulario(
            42,
            TipoVocabulario::TIPOS_COLETIVO,
            'rede',
            'Rede',
            'Rede de coletivos',
            1,
            true,
            ['recorrente' => true]
        );
        self::assertSame(42, $item->id());
        self::assertSame(TipoVocabulario::TIPOS_COLETIVO, $item->tipo());
        self::assertSame('rede', $item->valor());
        self::assertSame('Rede', $item->rotulo());
        self::assertSame('Rede de coletivos', $item->descricao());
        self::assertSame(1, $item->ordem());
        self::assertTrue($item->ativo());
        self::assertSame(['recorrente' => true], $item->metadata());
    }

    public function testConstructorRejectsUnknownTipo(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ItemVocabulario(null, 'inexistente', 'foo', 'Foo', null, 0, true, null);
    }

    public function testConstructorRejectsEmptyValor(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ItemVocabulario(null, TipoVocabulario::ABRANGENCIAS, '   ', 'Local', null, 0, true, null);
    }

    public function testConstructorRejectsEmptyRotulo(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ItemVocabulario(null, TipoVocabulario::ABRANGENCIAS, 'local', '', null, 0, true, null);
    }

    public function testDesativarReturnsNewInstanceWithAtivoFalse(): void
    {
        $original = new ItemVocabulario(
            10,
            TipoVocabulario::ABRANGENCIAS,
            'local',
            'Local',
            null,
            1,
            true,
            null
        );

        $disabled = $original->desativar();

        self::assertNotSame($original, $disabled, 'desativar() deve retornar nova instancia');
        self::assertTrue($original->ativo(), 'instancia original mantem ativo=true');
        self::assertFalse($disabled->ativo());
        // Demais campos preservados.
        self::assertSame($original->id(), $disabled->id());
        self::assertSame($original->tipo(), $disabled->tipo());
        self::assertSame($original->valor(), $disabled->valor());
        self::assertSame($original->rotulo(), $disabled->rotulo());
        self::assertSame($original->ordem(), $disabled->ordem());
    }

    public function testDesativarOnAlreadyInactiveReturnsSameInstance(): void
    {
        $inactive = new ItemVocabulario(
            10,
            TipoVocabulario::ABRANGENCIAS,
            'local',
            'Local',
            null,
            1,
            false,
            null
        );
        self::assertSame($inactive, $inactive->desativar());
    }
}
