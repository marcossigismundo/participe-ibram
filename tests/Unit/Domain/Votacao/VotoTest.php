<?php
/**
 * Unit tests for {@see Ibram\ParticipeIbram\Domain\Votacao\Voto}.
 *
 * Foco: imutabilidade, validação de hex64, ausência de setters.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Domain\Votacao
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Domain\Votacao;

use DateTimeImmutable;
use DateTimeZone;
use Ibram\ParticipeIbram\Domain\Votacao\Voto;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @covers \Ibram\ParticipeIbram\Domain\Votacao\Voto
 */
final class VotoTest extends TestCase
{
    private const HASH_VALIDO = 'a1b2c3d4e5f607182930415263748596a1b2c3d4e5f607182930415263748596';

    public function testConstrutorAceitaInputsValidos(): void
    {
        $when = new DateTimeImmutable('2026-05-06 12:00:00', new DateTimeZone('UTC'));
        $voto = new Voto(null, 1, 2, self::HASH_VALIDO, 3, $when);

        self::assertNull($voto->id());
        self::assertSame(1, $voto->votacaoId());
        self::assertSame(2, $voto->categoriaId());
        self::assertSame(self::HASH_VALIDO, $voto->eleitorHash());
        self::assertSame(3, $voto->candidatoInscricaoId());
        self::assertSame($when, $voto->votadoEm());
        self::assertNull($voto->ipHash());
    }

    public function testEleitorHashPrecisaSerHex64(): void
    {
        $when = new DateTimeImmutable('now');
        $this->expectException(InvalidArgumentException::class);
        new Voto(null, 1, 2, 'curto', 3, $when);
    }

    public function testEleitorHashRejeitaCharsNaoHex(): void
    {
        $when = new DateTimeImmutable('now');
        $this->expectException(InvalidArgumentException::class);
        new Voto(null, 1, 2, str_repeat('z', 64), 3, $when);
    }

    public function testIpHashPrecisaSerHex64QuandoInformado(): void
    {
        $when = new DateTimeImmutable('now');
        $this->expectException(InvalidArgumentException::class);
        new Voto(null, 1, 2, self::HASH_VALIDO, 3, $when, 'invalid');
    }

    public function testRejeitaIdsNaoPositivos(): void
    {
        $when = new DateTimeImmutable('now');
        $this->expectException(InvalidArgumentException::class);
        new Voto(null, 0, 2, self::HASH_VALIDO, 3, $when);
    }

    public function testWithIdRetornaNovaInstanciaSemMutarOriginal(): void
    {
        $when     = new DateTimeImmutable('now');
        $voto     = new Voto(null, 1, 2, self::HASH_VALIDO, 3, $when);
        $comId    = $voto->withId(42);

        self::assertNull($voto->id());
        self::assertSame(42, $comId->id());
        self::assertNotSame($voto, $comId);
    }

    public function testWithIpHashRetornaNovaInstanciaSemMutarOriginal(): void
    {
        $when    = new DateTimeImmutable('now');
        $voto    = new Voto(null, 1, 2, self::HASH_VALIDO, 3, $when);
        $comIp   = $voto->withIpHash(self::HASH_VALIDO);

        self::assertNull($voto->ipHash());
        self::assertSame(self::HASH_VALIDO, $comIp->ipHash());
        self::assertNotSame($voto, $comIp);
    }

    public function testNaoExpoeSettersDeMutacao(): void
    {
        // Reflection: nenhuma propriedade pública nem setter público (além de
        // `withId` / `withIpHash` que retornam novas instâncias).
        $rc = new ReflectionClass(Voto::class);

        foreach ($rc->getProperties() as $prop) {
            self::assertTrue(
                $prop->isPrivate(),
                "Propriedade {$prop->getName()} deveria ser privada (Voto e imutavel)."
            );
        }

        $methodNames = array_map(
            static fn ($m) => $m->getName(),
            $rc->getMethods(\ReflectionMethod::IS_PUBLIC)
        );

        // Métodos permitidos (getters + with* + __construct).
        $permitidos = [
            '__construct',
            'id',
            'votacaoId',
            'categoriaId',
            'eleitorHash',
            'candidatoInscricaoId',
            'votadoEm',
            'ipHash',
            'withId',
            'withIpHash',
        ];
        foreach ($methodNames as $name) {
            self::assertContains(
                $name,
                $permitidos,
                "Voto NAO deve expor metodo publico {$name} (imutabilidade)."
            );
        }
    }

    public function testClasseEhFinal(): void
    {
        $rc = new ReflectionClass(Voto::class);
        self::assertTrue($rc->isFinal(), 'Voto deve ser final.');
    }
}
