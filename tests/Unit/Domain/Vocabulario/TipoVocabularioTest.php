<?php
/**
 * Unit tests for TipoVocabulario.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Domain\Vocabulario
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Domain\Vocabulario;

use Ibram\ParticipeIbram\Domain\Vocabulario\TipoVocabulario;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Ibram\ParticipeIbram\Domain\Vocabulario\TipoVocabulario
 */
final class TipoVocabularioTest extends TestCase
{
    public function testAllReturnsThirteenCanonicalTypes(): void
    {
        $all = TipoVocabulario::all();
        self::assertCount(13, $all);
        self::assertContains(TipoVocabulario::TIPOS_COLETIVO, $all);
        self::assertContains(TipoVocabulario::ABRANGENCIAS, $all);
        self::assertContains(TipoVocabulario::NACIONALIDADES, $all);
        self::assertContains(TipoVocabulario::FAIXAS_ETARIAS, $all);
        self::assertContains(TipoVocabulario::IDENTIDADES_GENERO, $all);
        self::assertContains(TipoVocabulario::ORIENTACOES_SEXUAIS, $all);
        self::assertContains(TipoVocabulario::RACAS_COR, $all);
        self::assertContains(TipoVocabulario::POVOS_COMUNIDADES_TRADICIONAIS, $all);
        self::assertContains(TipoVocabulario::GRAUS_INSTRUCAO, $all);
        self::assertContains(TipoVocabulario::OCUPACOES, $all);
        self::assertContains(TipoVocabulario::AREAS_TEMATICAS, $all);
        self::assertContains(TipoVocabulario::INSTANCIAS_PARTICIPACAO, $all);
        self::assertContains(TipoVocabulario::TIPOS_DOCUMENTO, $all);
    }

    public function testFromStringNormalizesCasingAndTrim(): void
    {
        $tipo = TipoVocabulario::fromString('  Tipos_Coletivo  ');
        self::assertSame(TipoVocabulario::TIPOS_COLETIVO, $tipo->value());
        self::assertSame('tipos_coletivo', (string) $tipo);
    }

    public function testFromStringAcceptsEachCanonicalValue(): void
    {
        foreach (TipoVocabulario::all() as $value) {
            $instance = TipoVocabulario::fromString($value);
            self::assertSame($value, $instance->value());
        }
    }

    public function testFromStringRejectsUnknownType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        TipoVocabulario::fromString('cores_favoritas');
    }

    public function testIsValidReturnsBoolWithoutThrowing(): void
    {
        self::assertTrue(TipoVocabulario::isValid('racas_cor'));
        self::assertTrue(TipoVocabulario::isValid('  Racas_Cor  '));
        self::assertFalse(TipoVocabulario::isValid('inexistente'));
        self::assertFalse(TipoVocabulario::isValid(''));
    }

    public function testEqualsComparesByValue(): void
    {
        $a = TipoVocabulario::fromString('abrangencias');
        $b = TipoVocabulario::fromString('ABRANGENCIAS');
        $c = TipoVocabulario::fromString('nacionalidades');
        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }
}
