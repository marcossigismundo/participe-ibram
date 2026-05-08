<?php
/**
 * Unit tests for {@see Finalidade}.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Domain\Consentimento
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Domain\Consentimento;

use Ibram\ParticipeIbram\Domain\Consentimento\Finalidade;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Ibram\ParticipeIbram\Domain\Consentimento\Finalidade
 */
final class FinalidadeTest extends TestCase
{
    public function test_fromString_normalizes_case_and_whitespace(): void
    {
        $f = Finalidade::fromString(' IDENTIFICACAO ');
        self::assertSame(Finalidade::IDENTIFICACAO, $f->value());
    }

    public function test_fromString_rejects_unknown(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Finalidade::fromString('finalidade_inexistente');
    }

    public function test_all_returns_ten_finalidades(): void
    {
        $list = Finalidade::all();
        self::assertCount(10, $list);
    }

    public function test_values_contain_canonical_names(): void
    {
        $values = Finalidade::values();
        self::assertContains('identificacao', $values);
        self::assertContains('comunicacao', $values);
        self::assertContains('mapeamento', $values);
        self::assertContains('reconhecimento_pct', $values);
        self::assertContains('votacao', $values);
        self::assertContains('candidatura', $values);
        self::assertContains('dados_sensiveis_genero', $values);
        self::assertContains('dados_sensiveis_orientacao', $values);
        self::assertContains('dados_sensiveis_saude', $values);
        self::assertContains('dados_sensiveis_raca', $values);
    }

    public function test_isObrigatoria_for_identificacao_and_comunicacao(): void
    {
        self::assertTrue(Finalidade::fromString(Finalidade::IDENTIFICACAO)->isObrigatoria());
        self::assertTrue(Finalidade::fromString(Finalidade::COMUNICACAO)->isObrigatoria());
    }

    public function test_isObrigatoria_false_for_optional(): void
    {
        self::assertFalse(Finalidade::fromString(Finalidade::MAPEAMENTO)->isObrigatoria());
        self::assertFalse(Finalidade::fromString(Finalidade::VOTACAO)->isObrigatoria());
        self::assertFalse(Finalidade::fromString(Finalidade::DADOS_SENSIVEIS_RACA)->isObrigatoria());
    }

    public function test_isSensivel_true_for_sensitive_categories(): void
    {
        self::assertTrue(Finalidade::fromString(Finalidade::DADOS_SENSIVEIS_GENERO)->isSensivel());
        self::assertTrue(Finalidade::fromString(Finalidade::DADOS_SENSIVEIS_ORIENTACAO)->isSensivel());
        self::assertTrue(Finalidade::fromString(Finalidade::DADOS_SENSIVEIS_SAUDE)->isSensivel());
        self::assertTrue(Finalidade::fromString(Finalidade::DADOS_SENSIVEIS_RACA)->isSensivel());
        self::assertTrue(Finalidade::fromString(Finalidade::RECONHECIMENTO_PCT)->isSensivel());
    }

    public function test_isSensivel_false_for_cadastrais(): void
    {
        self::assertFalse(Finalidade::fromString(Finalidade::IDENTIFICACAO)->isSensivel());
        self::assertFalse(Finalidade::fromString(Finalidade::COMUNICACAO)->isSensivel());
        self::assertFalse(Finalidade::fromString(Finalidade::VOTACAO)->isSensivel());
    }

    public function test_baseLegal_for_policy_finalities(): void
    {
        $base = Finalidade::fromString(Finalidade::IDENTIFICACAO)->baseLegal();
        self::assertStringContainsString('Art. 7º, III', $base);
    }

    public function test_baseLegal_for_raca_references_law_14553(): void
    {
        $base = Finalidade::fromString(Finalidade::DADOS_SENSIVEIS_RACA)->baseLegal();
        self::assertStringContainsString('14.553/2023', $base);
        self::assertStringContainsString('Art. 11', $base);
    }

    public function test_baseLegal_for_pct_references_decree_8750(): void
    {
        $base = Finalidade::fromString(Finalidade::RECONHECIMENTO_PCT)->baseLegal();
        self::assertStringContainsString('8.750/2016', $base);
    }

    public function test_baseLegal_for_sensitive_other(): void
    {
        $base = Finalidade::fromString(Finalidade::DADOS_SENSIVEIS_GENERO)->baseLegal();
        self::assertStringContainsString('Art. 11', $base);
    }

    public function test_label_returns_non_empty_string(): void
    {
        foreach (Finalidade::all() as $f) {
            self::assertNotSame('', trim($f->label()));
        }
    }

    public function test_descricao_returns_non_empty_string(): void
    {
        foreach (Finalidade::all() as $f) {
            self::assertNotSame('', trim($f->descricao()));
        }
    }

    public function test_equals(): void
    {
        $a = Finalidade::fromString(Finalidade::VOTACAO);
        $b = Finalidade::fromString('votacao');
        $c = Finalidade::fromString(Finalidade::CANDIDATURA);

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }
}
