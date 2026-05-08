<?php
/**
 * Unit tests for {@see Ibram\ParticipeIbram\Domain\Edital\Edital}.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Domain\Edital
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Domain\Edital;

use DateTimeImmutable;
use DomainException;
use Ibram\ParticipeIbram\Domain\Edital\Edital;
use Ibram\ParticipeIbram\Domain\Edital\IllegalStateTransition;
use Ibram\ParticipeIbram\Domain\Edital\StatusEdital;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EditalTest extends TestCase
{
    public function test_novo_rascunho_starts_with_status_rascunho(): void
    {
        $edital = Edital::novoRascunho('Edital CCDEM 2026', 1);
        $this->assertSame(StatusEdital::RASCUNHO, $edital->status()->value());
        $this->assertNull($edital->id());
        $this->assertNull($edital->abertura());
    }

    public function test_titulo_vazio_e_rejeitado(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Edital::novoRascunho('   ', 1);
    }

    public function test_criado_por_deve_ser_positivo(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Edital::novoRascunho('Titulo', 0);
    }

    public function test_programar_datas_com_ordem_correta_funciona(): void
    {
        $edital = Edital::novoRascunho('Titulo', 1);
        $datas  = $this->datasValidas();

        $edital->programarDatas(...$datas);

        $this->assertSame($datas[0]->format('c'), $edital->abertura()->format('c'));
        $this->assertSame($datas[6]->format('c'), $edital->publicacaoResultado()->format('c'));
    }

    public function test_programar_datas_em_ordem_invalida_lanca(): void
    {
        $edital = Edital::novoRascunho('Titulo', 1);
        $abertura     = new DateTimeImmutable('2026-06-01 10:00');
        $encerrIns    = new DateTimeImmutable('2026-05-30 10:00'); // anterior à abertura
        $pubHab       = new DateTimeImmutable('2026-07-01 10:00');
        $prazoRec     = new DateTimeImmutable('2026-07-15 10:00');
        $abVot        = new DateTimeImmutable('2026-08-01 10:00');
        $encVot       = new DateTimeImmutable('2026-08-15 10:00');
        $pubResultado = new DateTimeImmutable('2026-09-01 10:00');

        $this->expectException(DomainException::class);
        $edital->programarDatas($abertura, $encerrIns, $pubHab, $prazoRec, $abVot, $encVot, $pubResultado);
    }

    public function test_publicar_exige_datas_completas(): void
    {
        $edital = Edital::novoRascunho('Titulo', 1);
        $this->expectException(DomainException::class);
        $edital->publicar();
    }

    public function test_publicar_apos_programar_datas_funciona(): void
    {
        $edital = Edital::novoRascunho('Titulo', 1);
        $edital->programarDatas(...$this->datasValidas());

        $edital->publicar();

        $this->assertSame(StatusEdital::PUBLICADO, $edital->status()->value());
    }

    public function test_fluxo_completo_de_transicoes(): void
    {
        $edital = Edital::novoRascunho('Titulo', 1);
        $edital->programarDatas(...$this->datasValidas());
        $edital->publicar();
        $edital->abrirInscricoes();
        $this->assertSame(StatusEdital::INSCRICOES_ABERTAS, $edital->status()->value());
        $edital->iniciarHabilitacao();
        $edital->abrirRecursoInabilitacao();
        $edital->abrirVotacao();
        $edital->encerrarVotacao();
        $edital->encerrar();
        $this->assertTrue($edital->status()->isFinal());
    }

    public function test_transicao_invalida_lanca(): void
    {
        $edital = Edital::novoRascunho('Titulo', 1);
        $edital->programarDatas(...$this->datasValidas());
        $this->expectException(IllegalStateTransition::class);
        $edital->abrirInscricoes(); // ainda em rascunho
    }

    public function test_programar_datas_so_em_rascunho(): void
    {
        $edital = Edital::novoRascunho('Titulo', 1);
        $edital->programarDatas(...$this->datasValidas());
        $edital->publicar();
        $this->expectException(DomainException::class);
        $edital->programarDatas(...$this->datasValidas());
    }

    public function test_with_id_retorna_clone_imutavel(): void
    {
        $edital = Edital::novoRascunho('Titulo', 1);
        $clone  = $edital->withId(42);
        $this->assertNull($edital->id());
        $this->assertSame(42, $clone->id());
    }

    /**
     * @return array<int,DateTimeImmutable>
     */
    private function datasValidas(): array
    {
        return [
            new DateTimeImmutable('2026-06-01 10:00'),
            new DateTimeImmutable('2026-06-30 23:59'),
            new DateTimeImmutable('2026-07-05 10:00'),
            new DateTimeImmutable('2026-07-15 23:59'),
            new DateTimeImmutable('2026-08-01 10:00'),
            new DateTimeImmutable('2026-08-15 23:59'),
            new DateTimeImmutable('2026-09-01 10:00'),
        ];
    }
}
