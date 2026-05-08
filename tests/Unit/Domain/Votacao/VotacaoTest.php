<?php
/**
 * Unit tests for {@see Ibram\ParticipeIbram\Domain\Votacao\Votacao}.
 *
 * Cobertura: state machine + validação de datas.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Domain\Votacao
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Domain\Votacao;

use DateTimeImmutable;
use DateTimeZone;
use Ibram\ParticipeIbram\Domain\Votacao\IllegalStateTransition;
use Ibram\ParticipeIbram\Domain\Votacao\ModoVotacao;
use Ibram\ParticipeIbram\Domain\Votacao\StatusVotacao;
use Ibram\ParticipeIbram\Domain\Votacao\Votacao;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Ibram\ParticipeIbram\Domain\Votacao\Votacao
 */
final class VotacaoTest extends TestCase
{
    private const HASH_VALIDO = 'a1b2c3d4e5f607182930415263748596a1b2c3d4e5f607182930415263748596';

    public function testConstrutorRejeitaEncerramentoMenorQueAbertura(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Votacao(
            null,
            1,
            new DateTimeImmutable('2026-06-10 10:00:00'),
            new DateTimeImmutable('2026-06-09 10:00:00'),
            StatusVotacao::agendada(),
            ModoVotacao::porCategoria()
        );
    }

    public function testConstrutorRejeitaDatasIguais(): void
    {
        $when = new DateTimeImmutable('2026-06-10 10:00:00');
        $this->expectException(InvalidArgumentException::class);
        new Votacao(null, 1, $when, $when, StatusVotacao::agendada(), ModoVotacao::geral());
    }

    public function testAbrirSucessoComJanelaValida(): void
    {
        $now = new DateTimeImmutable('2026-06-10 12:00:00', new DateTimeZone('UTC'));
        $v   = new Votacao(
            1,
            1,
            new DateTimeImmutable('2026-06-10 10:00:00', new DateTimeZone('UTC')),
            new DateTimeImmutable('2026-06-10 18:00:00', new DateTimeZone('UTC')),
            StatusVotacao::agendada(),
            ModoVotacao::porCategoria(),
            null,
            null,
            static fn () => $now
        );

        $v->abrir();
        self::assertTrue($v->status()->isAberta());
    }

    public function testAbrirFalhaQuandoForaDaJanela(): void
    {
        $agora = new DateTimeImmutable('2026-06-10 09:00:00', new DateTimeZone('UTC')); // antes da abertura
        $v     = new Votacao(
            1,
            1,
            new DateTimeImmutable('2026-06-10 10:00:00', new DateTimeZone('UTC')),
            new DateTimeImmutable('2026-06-10 18:00:00', new DateTimeZone('UTC')),
            StatusVotacao::agendada(),
            ModoVotacao::porCategoria(),
            null,
            null,
            static fn () => $agora
        );

        $this->expectException(IllegalStateTransition::class);
        $v->abrir();
    }

    public function testAbrirFalhaSeJaAberta(): void
    {
        $now = new DateTimeImmutable('2026-06-10 12:00:00', new DateTimeZone('UTC'));
        $v   = new Votacao(
            1,
            1,
            new DateTimeImmutable('2026-06-10 10:00:00', new DateTimeZone('UTC')),
            new DateTimeImmutable('2026-06-10 18:00:00', new DateTimeZone('UTC')),
            StatusVotacao::aberta(),
            ModoVotacao::porCategoria(),
            null,
            null,
            static fn () => $now
        );

        $this->expectException(IllegalStateTransition::class);
        $v->abrir();
    }

    public function testEncerrarTransicionaEArmazenaHash(): void
    {
        $v = $this->novaAberta();
        $v->encerrar(self::HASH_VALIDO);

        self::assertTrue($v->status()->isEncerrada());
        self::assertSame(self::HASH_VALIDO, $v->hashPreApuracao());
    }

    public function testEncerrarRejeitaHashInvalido(): void
    {
        $v = $this->novaAberta();
        $this->expectException(InvalidArgumentException::class);
        $v->encerrar('curto');
    }

    public function testEncerrarFalhaSeJaEncerrada(): void
    {
        $v = $this->novaAberta();
        $v->encerrar(self::HASH_VALIDO);

        $this->expectException(IllegalStateTransition::class);
        $v->encerrar(self::HASH_VALIDO);
    }

    public function testApurarTransicionaQuandoEncerrada(): void
    {
        $v = $this->novaAberta();
        $v->encerrar(self::HASH_VALIDO);
        $v->apurar();

        self::assertTrue($v->status()->isApurada());
        self::assertNotNull($v->apuradoEm());
    }

    public function testApurarFalhaSemHashPreApuracao(): void
    {
        // Constrói direto no estado encerrada SEM hash — cenário sintetizado.
        $v = new Votacao(
            1,
            1,
            new DateTimeImmutable('2026-06-10 10:00:00'),
            new DateTimeImmutable('2026-06-10 18:00:00'),
            StatusVotacao::encerrada(),
            ModoVotacao::porCategoria(),
            null
        );
        $this->expectException(IllegalStateTransition::class);
        $v->apurar();
    }

    public function testApurarFalhaSeAgendada(): void
    {
        $v = new Votacao(
            1,
            1,
            new DateTimeImmutable('2026-06-10 10:00:00'),
            new DateTimeImmutable('2026-06-10 18:00:00'),
            StatusVotacao::agendada(),
            ModoVotacao::porCategoria()
        );
        $this->expectException(IllegalStateTransition::class);
        $v->apurar();
    }

    public function testCancelarPermitidoEmAgendada(): void
    {
        $v = new Votacao(
            1,
            1,
            new DateTimeImmutable('2026-06-10 10:00:00'),
            new DateTimeImmutable('2026-06-10 18:00:00'),
            StatusVotacao::agendada(),
            ModoVotacao::porCategoria()
        );
        $v->cancelar();
        self::assertTrue($v->status()->isCancelada());
    }

    public function testCancelarPermitidoEmAberta(): void
    {
        $v = $this->novaAberta();
        $v->cancelar();
        self::assertTrue($v->status()->isCancelada());
    }

    public function testCancelarFalhaSeApurada(): void
    {
        $v = $this->novaAberta();
        $v->encerrar(self::HASH_VALIDO);
        $v->apurar();

        $this->expectException(IllegalStateTransition::class);
        $v->cancelar();
    }

    public function testCancelarFalhaSeJaCancelada(): void
    {
        $v = $this->novaAberta();
        $v->cancelar();

        $this->expectException(IllegalStateTransition::class);
        $v->cancelar();
    }

    public function testDentroDaJanela(): void
    {
        $v = new Votacao(
            1,
            1,
            new DateTimeImmutable('2026-06-10 10:00:00'),
            new DateTimeImmutable('2026-06-10 18:00:00'),
            StatusVotacao::aberta(),
            ModoVotacao::porCategoria()
        );

        self::assertTrue($v->dentroDaJanela(new DateTimeImmutable('2026-06-10 12:00:00')));
        self::assertFalse($v->dentroDaJanela(new DateTimeImmutable('2026-06-10 09:00:00')));
        self::assertFalse($v->dentroDaJanela(new DateTimeImmutable('2026-06-10 18:00:00'))); // limite superior é exclusivo
    }

    private function novaAberta(): Votacao
    {
        $now = new DateTimeImmutable('2026-06-10 12:00:00', new DateTimeZone('UTC'));
        $v   = new Votacao(
            1,
            1,
            new DateTimeImmutable('2026-06-10 10:00:00', new DateTimeZone('UTC')),
            new DateTimeImmutable('2026-06-10 18:00:00', new DateTimeZone('UTC')),
            StatusVotacao::agendada(),
            ModoVotacao::porCategoria(),
            null,
            null,
            static fn () => $now
        );
        $v->abrir();
        return $v;
    }
}
