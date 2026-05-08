<?php
/**
 * Unit tests for {@see Ibram\ParticipeIbram\Domain\Edital\StatusInscricao}.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Domain\Edital
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Domain\Edital;

use Ibram\ParticipeIbram\Domain\Edital\StatusInscricao;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class StatusInscricaoTest extends TestCase
{
    public function test_from_string_normalises_value(): void
    {
        $s = StatusInscricao::fromString(' Habilitado ');
        $this->assertSame('habilitado', $s->value());
    }

    public function test_from_string_rejects_unknown_value(): void
    {
        $this->expectException(InvalidArgumentException::class);
        StatusInscricao::fromString('zumbi');
    }

    public function test_final_states(): void
    {
        $this->assertTrue(StatusInscricao::finalHabilitado()->isFinal());
        $this->assertTrue(StatusInscricao::finalInabilitado()->isFinal());
        $this->assertFalse(StatusInscricao::habilitado()->isFinal());
        $this->assertFalse(StatusInscricao::inabilitado()->isFinal());
    }

    public function test_all_returns_eight_states(): void
    {
        $this->assertCount(8, StatusInscricao::all());
    }

    /**
     * @dataProvider validTransitionsProvider
     */
    public function test_valid_transitions(string $from, string $to): void
    {
        $a = StatusInscricao::fromString($from);
        $b = StatusInscricao::fromString($to);
        $this->assertTrue($a->canTransitionTo($b));
    }

    /**
     * @return iterable<array{string,string}>
     */
    public function validTransitionsProvider(): iterable
    {
        yield ['rascunho', 'inscrito'];
        yield ['inscrito', 'em_habilitacao'];
        yield ['em_habilitacao', 'habilitado'];
        yield ['em_habilitacao', 'inabilitado'];
        yield ['habilitado', 'final_habilitado'];
        yield ['inabilitado', 'em_recurso'];
        yield ['inabilitado', 'final_inabilitado'];
        yield ['em_recurso', 'final_habilitado'];
        yield ['em_recurso', 'final_inabilitado'];
    }

    /**
     * @dataProvider invalidTransitionsProvider
     */
    public function test_invalid_transitions(string $from, string $to): void
    {
        $a = StatusInscricao::fromString($from);
        $b = StatusInscricao::fromString($to);
        $this->assertFalse($a->canTransitionTo($b));
    }

    /**
     * @return iterable<array{string,string}>
     */
    public function invalidTransitionsProvider(): iterable
    {
        yield ['rascunho', 'em_habilitacao'];        // skip inscrito
        yield ['rascunho', 'final_habilitado'];
        yield ['inscrito', 'habilitado'];            // skip em_habilitacao
        yield ['habilitado', 'em_recurso'];          // habilitado nao recorre
        yield ['final_habilitado', 'rascunho'];      // terminal
        yield ['final_inabilitado', 'em_recurso'];   // terminal
        yield ['em_recurso', 'habilitado'];          // depois de recurso vai a final
    }
}
