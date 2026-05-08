<?php
/**
 * Implementação `wpdb` do {@see AgenteDetalhesLoader}.
 *
 * @package Ibram\ParticipeIbram\Infrastructure\Repository
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Infrastructure\Repository;

use Ibram\ParticipeIbram\Application\Cadastro\AgenteDetalhesLoader;
use Ibram\ParticipeIbram\Domain\Agente\AgenteOR;
use Ibram\ParticipeIbram\Domain\Agente\AgentePF;
use Ibram\ParticipeIbram\Domain\Agente\AgenteSM;
use Ibram\ParticipeIbram\Domain\Agente\TipoAgente;
use RuntimeException;

/**
 * Carrega detalhes tipológicos + representantes de um agente persistido para
 * que handlers de transição de status possam re-`save()` o agregado completo
 * (contrato de `AgenteRepository::save()`).
 *
 * Reutiliza o {@see AgenteHydrator} já injetado no resto da infra.
 */
final class WpdbAgenteDetalhesLoader implements AgenteDetalhesLoader
{
    /** @var \wpdb */
    private $wpdb;

    private AgenteHydrator $hydrator;

    private string $tableAgentesPf;
    private string $tableAgentesOr;
    private string $tableAgentesSm;
    private string $tableRepresentantes;

    public function __construct($wpdb, AgenteHydrator $hydrator)
    {
        $this->wpdb     = $wpdb;
        $this->hydrator = $hydrator;
        $prefix         = isset($wpdb->prefix) && is_string($wpdb->prefix) ? $wpdb->prefix : 'wp_';
        $this->tableAgentesPf      = $prefix . 'pi_agentes_pf';
        $this->tableAgentesOr      = $prefix . 'pi_agentes_or';
        $this->tableAgentesSm      = $prefix . 'pi_agentes_sm';
        $this->tableRepresentantes = $prefix . 'pi_agente_representantes';
    }

    public function loadDetalhes(int $agenteId, string $tipoAgente): object
    {
        if ($agenteId <= 0) {
            throw new \InvalidArgumentException('loadDetalhes: agenteId deve ser positivo.');
        }
        $tipo = strtoupper(trim($tipoAgente));

        switch ($tipo) {
            case TipoAgente::PF:
                $row = $this->wpdb->get_row(
                    $this->wpdb->prepare("SELECT * FROM {$this->tableAgentesPf} WHERE agente_id = %d LIMIT 1", $agenteId),
                    ARRAY_A
                );
                if (!is_array($row)) {
                    throw new RuntimeException(sprintf('agentes_pf id=%d nao encontrado.', $agenteId));
                }
                return $this->hydrator->hydrateAgentePF($row, 0);

            case TipoAgente::OR:
                $row = $this->wpdb->get_row(
                    $this->wpdb->prepare("SELECT * FROM {$this->tableAgentesOr} WHERE agente_id = %d LIMIT 1", $agenteId),
                    ARRAY_A
                );
                if (!is_array($row)) {
                    throw new RuntimeException(sprintf('agentes_or id=%d nao encontrado.', $agenteId));
                }
                return $this->hydrator->hydrateAgenteOR($row, 0);

            case TipoAgente::SM:
                $row = $this->wpdb->get_row(
                    $this->wpdb->prepare("SELECT * FROM {$this->tableAgentesSm} WHERE agente_id = %d LIMIT 1", $agenteId),
                    ARRAY_A
                );
                if (!is_array($row)) {
                    throw new RuntimeException(sprintf('agentes_sm id=%d nao encontrado.', $agenteId));
                }
                return $this->hydrator->hydrateAgenteSM($row, 0);

            default:
                throw new \InvalidArgumentException(sprintf('Tipo de agente desconhecido: %s', $tipoAgente));
        }
    }

    public function loadRepresentantes(int $agenteId): array
    {
        if ($agenteId <= 0) {
            return [];
        }
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->tableRepresentantes} WHERE agente_id = %d ORDER BY ordem ASC, id ASC",
                $agenteId
            ),
            ARRAY_A
        );
        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->hydrator->hydrateRepresentante($row, 0);
        }

        return $out;
    }
}
