<?php
/**
 * Adapter cross-domain: implementa {@see AgenteVotanteGateway}.
 *
 * @package Ibram\ParticipeIbram\Application\Votacao\Adapters
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Votacao\Adapters;

use Ibram\ParticipeIbram\Application\Votacao\Ports\AgenteVotanteGateway;
use Ibram\ParticipeIbram\Domain\Agente\AgenteRepository;

/**
 * Anti-corruption layer: traduz consultas necessárias ao domínio Votação em
 * chamadas ao `AgenteRepository`. O domínio Votação NÃO importa classes do
 * domínio Agente diretamente — depende somente desta porta.
 */
final class AgenteVotanteGatewayAdapter implements AgenteVotanteGateway
{
    private AgenteRepository $repo;

    public function __construct(AgenteRepository $repo)
    {
        $this->repo = $repo;
    }

    public function estaDeferido(int $agenteId): bool
    {
        if ($agenteId <= 0) {
            return false;
        }
        $agente = $this->repo->findById($agenteId);
        if ($agente === null) {
            return false;
        }

        return $agente->getStatusCadastro()->isDeferido();
    }

    public function tipoAgente(int $agenteId): ?string
    {
        if ($agenteId <= 0) {
            return null;
        }
        $agente = $this->repo->findById($agenteId);
        if ($agente === null) {
            return null;
        }

        return $agente->getTipo()->value();
    }
}
