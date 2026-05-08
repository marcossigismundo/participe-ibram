<?php
/**
 * Tests for AgenteSummary.
 *
 * @package Ibram\ParticipeIbram\Tests\Unit\Presentation\Admin\Helpers
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Tests\Unit\Presentation\Admin\Helpers;

use DateTimeImmutable;
use Ibram\ParticipeIbram\Domain\Agente\Agente;
use Ibram\ParticipeIbram\Domain\Agente\AgenteOR;
use Ibram\ParticipeIbram\Domain\Agente\AgentePF;
use Ibram\ParticipeIbram\Domain\Agente\AgenteSM;
use Ibram\ParticipeIbram\Domain\Agente\NumeroRegistro;
use Ibram\ParticipeIbram\Domain\Agente\StatusCadastro;
use Ibram\ParticipeIbram\Domain\Agente\TipoAgente;
use Ibram\ParticipeIbram\Presentation\Admin\Helpers\AgenteSummary;
use PHPUnit\Framework\TestCase;

final class AgenteSummaryTest extends TestCase
{
    public function test_pf_uses_nome_social_when_present(): void
    {
        $pf = new AgentePF(1, 'Maria de Souza', 'Mari');
        $agente = $this->makeAgente(TipoAgente::pf(), StatusCadastro::submetido());
        $agente->assignId(1);
        $this->assertSame('Mari', AgenteSummary::nomeAgente($agente, $pf));
    }

    public function test_pf_falls_back_to_nome_completo(): void
    {
        $pf     = new AgentePF(1, 'Maria de Souza');
        $agente = $this->makeAgente(TipoAgente::pf(), StatusCadastro::submetido());
        $agente->assignId(1);
        $this->assertSame('Maria de Souza', AgenteSummary::nomeAgente($agente, $pf));
    }

    public function test_or_uses_nome_organizacao(): void
    {
        $or = new AgenteOR(2, 'Coletivo X', AgenteOR::TEM_CNPJ_NAO);
        $agente = $this->makeAgente(TipoAgente::org(), StatusCadastro::submetido());
        $agente->assignId(2);
        $this->assertSame('Coletivo X', AgenteSummary::nomeAgente($agente, $or));
    }

    public function test_sm_uses_nome_orgao(): void
    {
        $sm = new AgenteSM(
            3,
            'Sistema Estadual de Museus',
            AgenteSM::ESFERA_ESTADUAL,
            AgenteSM::TIPO_ORGAO_SISTEMA_MUSEUS,
            'Fulano de Tal'
        );
        $agente = $this->makeAgente(TipoAgente::sm(), StatusCadastro::submetido());
        $agente->assignId(3);
        $this->assertSame('Sistema Estadual de Museus', AgenteSummary::nomeAgente($agente, $sm));
    }

    public function test_tipo_label_maps_known_codes(): void
    {
        $this->assertNotEmpty(AgenteSummary::tipoLabel('PF'));
        $this->assertNotEmpty(AgenteSummary::tipoLabel('OR'));
        $this->assertNotEmpty(AgenteSummary::tipoLabel('SM'));
    }

    public function test_status_badge_returns_variants(): void
    {
        $b = AgenteSummary::statusBadge(StatusCadastro::deferido());
        $this->assertSame('success', $b['variant']);
        $this->assertSame(StatusCadastro::DEFERIDO, $b['code']);

        $b = AgenteSummary::statusBadge(StatusCadastro::indeferidoFinal());
        $this->assertSame('danger', $b['variant']);

        $b = AgenteSummary::statusBadge(StatusCadastro::rascunho());
        $this->assertSame('draft', $b['variant']);
    }

    public function test_tempo_em_analise_dias_returns_null_for_non_em_analise(): void
    {
        $agente = $this->makeAgente(TipoAgente::pf(), StatusCadastro::deferido(), new NumeroRegistro('1/PF/2024'));
        $agente->assignId(1);
        $this->assertNull(AgenteSummary::tempoEmAnaliseDias($agente));
    }

    public function test_tempo_em_analise_dias_calculates_diff(): void
    {
        // Constrói diretamente um agente em análise com submetidoEm = -10 dias.
        $now      = new DateTimeImmutable('2024-01-15 10:00:00');
        $submeted = new DateTimeImmutable('2024-01-05 10:00:00');
        $agente   = new Agente(
            5,
            TipoAgente::pf(),
            null,
            StatusCadastro::emAnalise(),
            null,
            'a@b.com',
            null,
            $submeted,
            null,
            null,
            $submeted,
            $submeted,
            null
        );
        $this->assertSame(10, AgenteSummary::tempoEmAnaliseDias($agente, $now));
    }

    public function test_numero_registro_or_dash_returns_dash_when_null(): void
    {
        $agente = $this->makeAgente(TipoAgente::pf(), StatusCadastro::submetido());
        $agente->assignId(1);
        $this->assertSame('—', AgenteSummary::numeroRegistroOrDash($agente));
    }

    public function test_status_labels_covers_every_status(): void
    {
        $labels = AgenteSummary::statusLabels();
        foreach (StatusCadastro::all() as $status) {
            $this->assertArrayHasKey($status, $labels);
            $this->assertNotEmpty($labels[$status]);
        }
    }

    private function makeAgente(TipoAgente $tipo, StatusCadastro $status, ?NumeroRegistro $numero = null): Agente
    {
        $now = new DateTimeImmutable('2024-01-01 00:00:00');
        return new Agente(
            null,
            $tipo,
            $numero,
            $status,
            null,
            'a@b.com',
            null,
            null,
            null,
            null,
            $now,
            $now,
            null
        );
    }
}
