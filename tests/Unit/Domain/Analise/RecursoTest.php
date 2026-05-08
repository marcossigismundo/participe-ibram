<?php
/**
 * Unit tests for {@see Ibram\ParticipeIbram\Domain\Analise\Recurso}.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Domain\Analise
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Domain\Analise;

use DateTimeImmutable;
use DomainException;
use Ibram\ParticipeIbram\Domain\Analise\Recurso;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Ibram\ParticipeIbram\Domain\Analise\Recurso
 */
final class RecursoTest extends TestCase
{
    public function test_protocolar_cria_recurso_em_aberto(): void
    {
        $now    = new DateTimeImmutable('2026-05-01T10:00:00Z');
        $fim    = $now->modify('+10 days');
        $recurso = Recurso::protocolar(99, Recurso::FASE_RETRATACAO, 7, 'Fundamento.', $now, $now, $fim);

        self::assertSame(99, $recurso->analiseId());
        self::assertSame(Recurso::FASE_RETRATACAO, $recurso->fase());
        self::assertSame(7, $recurso->recorrenteId());
        self::assertFalse($recurso->isDecidido());
        self::assertNull($recurso->decisao());
    }

    public function test_protocolar_rejeita_fase_invalida(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Recurso::protocolar(99, 'qualquer_coisa', 7, 'fund', new DateTimeImmutable('now'), new DateTimeImmutable('now'), new DateTimeImmutable('now'));
    }

    public function test_protocolar_rejeita_prazoFim_anterior_ao_inicio(): void
    {
        $now = new DateTimeImmutable('now');
        $this->expectException(InvalidArgumentException::class);
        Recurso::protocolar(99, Recurso::FASE_RETRATACAO, 7, 'fund', $now, $now->modify('+5 days'), $now);
    }

    public function test_decidir_retratacao_aceita_decisoes_validas(): void
    {
        $now     = new DateTimeImmutable('now');
        $recurso = Recurso::protocolar(99, Recurso::FASE_RETRATACAO, 7, 'f', $now, $now, $now->modify('+10 days'));

        $recurso->decidir(Recurso::DECISAO_RECONSIDERAR, 5, 'Decisao.', $now);

        self::assertTrue($recurso->isDecidido());
        self::assertSame(Recurso::DECISAO_RECONSIDERAR, $recurso->decisao());
        self::assertSame(5, $recurso->decisorId());
        self::assertSame('Decisao.', $recurso->decisaoMd());
    }

    public function test_decidir_retratacao_rejeita_decisao_de_presidencia(): void
    {
        $now     = new DateTimeImmutable('now');
        $recurso = Recurso::protocolar(99, Recurso::FASE_RETRATACAO, 7, 'f', $now, $now, $now->modify('+10 days'));

        $this->expectException(InvalidArgumentException::class);
        $recurso->decidir(Recurso::DECISAO_DEFERIR, 5, 'd', $now); // deferir não é válido em retratacao
    }

    public function test_decidir_presidencia_aceita_deferir_indeferir(): void
    {
        $now     = new DateTimeImmutable('now');
        $recurso = Recurso::protocolar(99, Recurso::FASE_PRESIDENCIA, 7, 'f', $now, $now, $now->modify('+10 days'));

        $recurso->decidir(Recurso::DECISAO_DEFERIR, 5, 'ok', $now);

        self::assertTrue($recurso->isDecidido());
        self::assertSame(Recurso::DECISAO_DEFERIR, $recurso->decisao());
    }

    public function test_decidir_rejeita_segunda_decisao(): void
    {
        $now     = new DateTimeImmutable('now');
        $recurso = Recurso::protocolar(99, Recurso::FASE_RETRATACAO, 7, 'f', $now, $now, $now->modify('+10 days'));
        $recurso->decidir(Recurso::DECISAO_MANTER, 5, 'd', $now);

        $this->expectException(DomainException::class);
        $recurso->decidir(Recurso::DECISAO_RECONSIDERAR, 5, 'd', $now);
    }

    public function test_prazoExpirado_compara_com_now(): void
    {
        $inicio  = new DateTimeImmutable('2026-01-01T00:00:00Z');
        $fim     = new DateTimeImmutable('2026-01-11T00:00:00Z'); // +10 dias
        $recurso = Recurso::protocolar(1, Recurso::FASE_RETRATACAO, 7, 'f', $inicio, $inicio, $fim);

        self::assertFalse($recurso->prazoExpirado(new DateTimeImmutable('2026-01-05T12:00:00Z')));
        self::assertTrue($recurso->prazoExpirado(new DateTimeImmutable('2026-01-12T00:00:00Z')));
    }

    public function test_marcarPublicado_exige_decisao_previa(): void
    {
        $now     = new DateTimeImmutable('now');
        $recurso = Recurso::protocolar(99, Recurso::FASE_RETRATACAO, 7, 'f', $now, $now, $now->modify('+10 days'));

        $this->expectException(DomainException::class);
        $recurso->marcarPublicado($now);
    }
}
