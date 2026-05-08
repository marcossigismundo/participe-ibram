<?php
/**
 * Unit tests for {@see Ibram\ParticipeIbram\Domain\Agente\Agente}.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Domain\Agente
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Domain\Agente;

use DateTimeImmutable;
use Ibram\ParticipeIbram\Domain\Agente\Agente;
use Ibram\ParticipeIbram\Domain\Agente\IllegalStateTransition;
use Ibram\ParticipeIbram\Domain\Agente\NumeroRegistro;
use Ibram\ParticipeIbram\Domain\Agente\StatusCadastro;
use Ibram\ParticipeIbram\Domain\Agente\TipoAgente;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AgenteTest extends TestCase
{
    public function test_novo_inicia_em_rascunho_sem_id_nem_numero(): void
    {
        $a = Agente::novo(TipoAgente::pf(), 'maria@example.org');

        $this->assertNull($a->getId());
        $this->assertFalse($a->isPersisted());
        $this->assertNull($a->getNumeroRegistro());
        $this->assertSame('rascunho', $a->getStatusCadastro()->value());
        $this->assertSame('maria@example.org', $a->getEmailPrincipal());
        $this->assertNull($a->getSubmetidoEm());
        $this->assertNull($a->getDeferidoEm());
    }

    public function test_construtor_rejeita_email_vazio(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Agente::novo(TipoAgente::pf(), '   ');
    }

    public function test_assign_id_so_pode_ser_chamado_uma_vez(): void
    {
        $a = Agente::novo(TipoAgente::pf(), 'a@b.com');
        $a->assignId(42);
        $this->assertSame(42, $a->getId());
        $this->assertTrue($a->isPersisted());

        $this->expectException(InvalidArgumentException::class);
        $a->assignId(99);
    }

    public function test_assign_id_rejeita_zero_ou_negativo(): void
    {
        $a = Agente::novo(TipoAgente::pf(), 'a@b.com');
        $this->expectException(InvalidArgumentException::class);
        $a->assignId(0);
    }

    // ---- Fluxo principal: rascunho -> submetido -> em_analise -> deferido ----

    public function test_fluxo_principal_ate_deferimento(): void
    {
        $now = new DateTimeImmutable('2026-05-06 10:00:00');
        $a = Agente::novo(TipoAgente::pf(), 'maria@example.org', null, null, $now);

        $a->submeter(new DateTimeImmutable('2026-05-06 10:05:00'));
        $this->assertSame('submetido', $a->getStatusCadastro()->value());
        $this->assertNotNull($a->getSubmetidoEm());

        $a->iniciarAnalise(new DateTimeImmutable('2026-05-06 11:00:00'));
        $this->assertSame('em_analise', $a->getStatusCadastro()->value());

        $numero = NumeroRegistro::fromParts('PF', 2026, 1);
        $a->deferir($numero, new DateTimeImmutable('2026-05-06 12:00:00'));

        $this->assertSame('deferido', $a->getStatusCadastro()->value());
        $this->assertSame('PI-PF-2026-000001', (string) $a->getNumeroRegistro());
        $this->assertNotNull($a->getDeferidoEm());
        $this->assertTrue($a->getStatusCadastro()->isFinal());
    }

    public function test_submeter_em_outro_estado_falha(): void
    {
        $a = Agente::novo(TipoAgente::pf(), 'a@b.com');
        $a->submeter();

        $this->expectException(IllegalStateTransition::class);
        $a->submeter();
    }

    public function test_deferir_a_partir_de_rascunho_falha(): void
    {
        $a = Agente::novo(TipoAgente::pf(), 'a@b.com');
        $this->expectException(IllegalStateTransition::class);
        $a->deferir(NumeroRegistro::fromParts('PF', 2026, 1));
    }

    public function test_deferir_com_numero_de_tipo_divergente_falha(): void
    {
        $a = Agente::novo(TipoAgente::pf(), 'a@b.com');
        $a->submeter();
        $a->iniciarAnalise();

        $this->expectException(InvalidArgumentException::class);
        // Agente é PF mas o número é OR.
        $a->deferir(NumeroRegistro::fromParts('OR', 2026, 1));
    }

    // ---- Fluxo de recurso completo ----

    public function test_fluxo_indeferimento_e_reconsideracao_em_retratacao(): void
    {
        $a = Agente::novo(TipoAgente::pf(), 'b@b.com');
        $a->submeter();
        $a->iniciarAnalise();
        $a->indeferir();
        $this->assertSame('indeferido_aguardando_recurso', $a->getStatusCadastro()->value());

        $a->protocolarRecurso();
        $this->assertSame('em_retratacao', $a->getStatusCadastro()->value());

        $a->reconsiderar(NumeroRegistro::fromParts('PF', 2026, 5));
        $this->assertSame('deferido_em_retratacao', $a->getStatusCadastro()->value());
        $this->assertSame('PI-PF-2026-000005', (string) $a->getNumeroRegistro());
        $this->assertTrue($a->getStatusCadastro()->isFinal());
        $this->assertTrue($a->getStatusCadastro()->isDeferido());
    }

    public function test_fluxo_indeferimento_recurso_presidencia_deferido(): void
    {
        $a = Agente::novo(TipoAgente::pf(), 'c@b.com');
        $a->submeter();
        $a->iniciarAnalise();
        $a->indeferir();
        $a->protocolarRecurso();
        $a->manterIndeferimento();
        $this->assertSame('em_recurso_presidencia', $a->getStatusCadastro()->value());

        $a->decidirRecursoPresidencia(true, NumeroRegistro::fromParts('PF', 2026, 7));
        $this->assertSame('deferido_em_recurso', $a->getStatusCadastro()->value());
        $this->assertSame('PI-PF-2026-000007', (string) $a->getNumeroRegistro());
    }

    public function test_fluxo_recurso_presidencia_mantido_termina_indeferido_final(): void
    {
        $a = Agente::novo(TipoAgente::pf(), 'd@b.com');
        $a->submeter();
        $a->iniciarAnalise();
        $a->indeferir();
        $a->protocolarRecurso();
        $a->manterIndeferimento();

        $a->decidirRecursoPresidencia(false);
        $this->assertSame('indeferido_final', $a->getStatusCadastro()->value());
        $this->assertTrue($a->getStatusCadastro()->isFinal());
        $this->assertFalse($a->getStatusCadastro()->isDeferido());
        $this->assertNull($a->getNumeroRegistro());
    }

    public function test_decidir_recurso_presidencia_sem_numero_quando_deferido_falha(): void
    {
        $a = Agente::novo(TipoAgente::pf(), 'e@b.com');
        $a->submeter();
        $a->iniciarAnalise();
        $a->indeferir();
        $a->protocolarRecurso();
        $a->manterIndeferimento();

        $this->expectException(InvalidArgumentException::class);
        $a->decidirRecursoPresidencia(true, null);
    }

    public function test_prazo_expirado_a_partir_de_aguardando_recurso(): void
    {
        $a = Agente::novo(TipoAgente::pf(), 'f@b.com');
        $a->submeter();
        $a->iniciarAnalise();
        $a->indeferir();

        $a->prazoExpirado();
        $this->assertSame('indeferido_final', $a->getStatusCadastro()->value());
        $this->assertTrue($a->getStatusCadastro()->isFinal());
    }

    public function test_prazo_expirado_em_outro_estado_falha(): void
    {
        $a = Agente::novo(TipoAgente::pf(), 'g@b.com');
        $a->submeter();

        $this->expectException(IllegalStateTransition::class);
        $a->prazoExpirado();
    }

    public function test_marcar_publicado_seta_timestamp_sem_mudar_status(): void
    {
        $a = Agente::novo(TipoAgente::pf(), 'h@b.com');
        $a->submeter();
        $a->iniciarAnalise();
        $a->deferir(NumeroRegistro::fromParts('PF', 2026, 9));

        $a->marcarPublicado(new DateTimeImmutable('2026-05-07 09:00:00'));
        $this->assertNotNull($a->getPublicadoEm());
        $this->assertSame('deferido', $a->getStatusCadastro()->value());
    }

    public function test_soft_delete_seta_deleted_at(): void
    {
        $a = Agente::novo(TipoAgente::pf(), 'i@b.com');
        $this->assertNull($a->getDeletedAt());
        $a->softDelete();
        $this->assertNotNull($a->getDeletedAt());
    }

    public function test_construtor_rejeita_deferido_sem_numero_de_registro(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Agente(
            null,
            TipoAgente::pf(),
            null,
            StatusCadastro::deferido(),
            null,
            'x@y.com',
            null,
            null,
            null,
            null,
            new DateTimeImmutable('now'),
            new DateTimeImmutable('now'),
            null
        );
    }
}
