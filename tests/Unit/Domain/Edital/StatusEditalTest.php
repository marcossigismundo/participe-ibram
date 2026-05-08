<?php
/**
 * Unit tests for {@see Ibram\ParticipeIbram\Domain\Edital\StatusEdital}.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Domain\Edital
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Domain\Edital;

use Ibram\ParticipeIbram\Domain\Edital\StatusEdital;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class StatusEditalTest extends TestCase
{
    public function test_from_string_normalises_case_and_whitespace(): void
    {
        $s = StatusEdital::fromString(' Inscricoes_Abertas ');
        $this->assertSame('inscricoes_abertas', $s->value());
    }

    public function test_from_string_rejects_unknown_value(): void
    {
        $this->expectException(InvalidArgumentException::class);
        StatusEdital::fromString('totalmente_invalido');
    }

    public function test_all_returns_canonical_eight_states(): void
    {
        $this->assertCount(8, StatusEdital::all());
    }

    public function test_encerrado_is_final_and_other_states_are_not(): void
    {
        $this->assertTrue(StatusEdital::encerrado()->isFinal());
        $this->assertFalse(StatusEdital::rascunho()->isFinal());
        $this->assertFalse(StatusEdital::publicado()->isFinal());
    }

    /**
     * @dataProvider validTransitionsProvider
     */
    public function test_can_transition_to_returns_true_for_canonical_path(string $from, string $to): void
    {
        $a = StatusEdital::fromString($from);
        $b = StatusEdital::fromString($to);
        $this->assertTrue($a->canTransitionTo($b), sprintf('%s -> %s deveria ser permitido.', $from, $to));
    }

    /**
     * @return iterable<array{string,string}>
     */
    public function validTransitionsProvider(): iterable
    {
        yield ['rascunho', 'publicado'];
        yield ['publicado', 'inscricoes_abertas'];
        yield ['inscricoes_abertas', 'em_habilitacao'];
        yield ['em_habilitacao', 'em_recurso'];
        yield ['em_recurso', 'votacao_aberta'];
        yield ['votacao_aberta', 'votacao_encerrada'];
        yield ['votacao_encerrada', 'encerrado'];
    }

    /**
     * @dataProvider invalidTransitionsProvider
     */
    public function test_can_transition_to_returns_false_for_invalid_jumps(string $from, string $to): void
    {
        $a = StatusEdital::fromString($from);
        $b = StatusEdital::fromString($to);
        $this->assertFalse($a->canTransitionTo($b), sprintf('%s -> %s deveria ser proibido.', $from, $to));
    }

    /**
     * @return iterable<array{string,string}>
     */
    public function invalidTransitionsProvider(): iterable
    {
        yield ['rascunho', 'inscricoes_abertas']; // skip publicado
        yield ['rascunho', 'encerrado'];
        yield ['publicado', 'em_habilitacao'];
        yield ['inscricoes_abertas', 'votacao_aberta'];
        yield ['encerrado', 'rascunho'];          // estado terminal
        yield ['encerrado', 'votacao_aberta'];
        yield ['votacao_encerrada', 'rascunho'];
    }

    public function test_equals_is_value_based(): void
    {
        $a = StatusEdital::publicado();
        $b = StatusEdital::fromString('publicado');
        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals(StatusEdital::rascunho()));
    }
}
