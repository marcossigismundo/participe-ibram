<?php
/**
 * Unit tests for {@see Ibram\ParticipeIbram\Domain\Edital\Inscricao}.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Domain\Edital
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Domain\Edital;

use DomainException;
use Ibram\ParticipeIbram\Domain\Edital\IllegalStateTransition;
use Ibram\ParticipeIbram\Domain\Edital\Inscricao;
use Ibram\ParticipeIbram\Domain\Edital\StatusInscricao;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class InscricaoTest extends TestCase
{
    public function test_novo_rascunho_tem_status_rascunho(): void
    {
        $i = Inscricao::novoRascunho(1, 2, 3);
        $this->assertSame(StatusInscricao::RASCUNHO, $i->status()->value());
        $this->assertNull($i->id());
        $this->assertNull($i->inscritoEm());
    }

    public function test_submeter_avanca_para_inscrito_e_marca_data(): void
    {
        $i = Inscricao::novoRascunho(1, 2, 3);
        $i->submeter();
        $this->assertSame(StatusInscricao::INSCRITO, $i->status()->value());
        $this->assertNotNull($i->inscritoEm());
    }

    public function test_fluxo_habilitado_completo(): void
    {
        $i = Inscricao::novoRascunho(1, 2, 3);
        $i->submeter();
        $i->iniciarHabilitacao();
        $i->habilitar(7);
        $this->assertSame(StatusInscricao::HABILITADO, $i->status()->value());
        $this->assertNotNull($i->habilitadoEm());

        $i->tornarFinal();
        $this->assertSame(StatusInscricao::FINAL_HABILITADO, $i->status()->value());
        $this->assertTrue($i->status()->isFinal());
    }

    public function test_fluxo_inabilitado_e_recurso_provido(): void
    {
        $i = Inscricao::novoRascunho(1, 2, 3);
        $i->submeter();
        $i->iniciarHabilitacao();
        $i->inabilitar('Faltou ata de fundacao', 7);
        $this->assertSame(StatusInscricao::INABILITADO, $i->status()->value());
        $this->assertSame('Faltou ata de fundacao', $i->motivoInabilitacaoMd());

        $i->protocolarRecurso();
        $this->assertSame(StatusInscricao::EM_RECURSO, $i->status()->value());

        $i->decidirRecurso(true);
        $this->assertSame(StatusInscricao::FINAL_HABILITADO, $i->status()->value());
    }

    public function test_fluxo_recurso_negado_vai_a_final_inabilitado(): void
    {
        $i = Inscricao::novoRascunho(1, 2, 3);
        $i->submeter();
        $i->iniciarHabilitacao();
        $i->inabilitar('Documentos faltando', 7);
        $i->protocolarRecurso();
        $i->decidirRecurso(false);
        $this->assertSame(StatusInscricao::FINAL_INABILITADO, $i->status()->value());
    }

    public function test_inabilitar_sem_motivo_lanca(): void
    {
        $i = Inscricao::novoRascunho(1, 2, 3);
        $i->submeter();
        $i->iniciarHabilitacao();
        $this->expectException(InvalidArgumentException::class);
        $i->inabilitar('   ', 7);
    }

    public function test_habilitar_com_ator_invalido_lanca(): void
    {
        $i = Inscricao::novoRascunho(1, 2, 3);
        $i->submeter();
        $i->iniciarHabilitacao();
        $this->expectException(InvalidArgumentException::class);
        $i->habilitar(0);
    }

    public function test_pular_etapas_lanca_illegal_state_transition(): void
    {
        $i = Inscricao::novoRascunho(1, 2, 3);
        $this->expectException(IllegalStateTransition::class);
        $i->habilitar(7);
    }

    public function test_tornar_final_so_se_aplica_a_habilitado(): void
    {
        $i = Inscricao::novoRascunho(1, 2, 3);
        $i->submeter();
        $this->expectException(DomainException::class);
        $i->tornarFinal();
    }

    public function test_construtor_rejeita_ids_invalidos(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Inscricao::novoRascunho(0, 2, 3);
    }
}
