<?php
/**
 * Adapter cross-domain: implementa {@see AgenteLookupPort} consultando o
 * domínio Agente.
 *
 * @package Ibram\ParticipeIbram\Application\Edital\Adapters
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Edital\Adapters;

use Ibram\ParticipeIbram\Application\Edital\AgenteLookupPort;
use Ibram\ParticipeIbram\Domain\Agente\AgenteRepository;

/**
 * Único ponto que conecta o domínio Edital ao domínio Agente para a checagem
 * `{exists, deferido, tipo}` antes de aceitar uma inscrição.
 *
 * Mantém o domínio Edital ignorante das classes do domínio Agente — apenas
 * consome a interface `AgenteRepository` e devolve o tuple combinado.
 */
final class AgenteLookupAdapter implements AgenteLookupPort
{
    private AgenteRepository $repo;

    public function __construct(AgenteRepository $repo)
    {
        $this->repo = $repo;
    }

    public function lookup(int $agenteId): array
    {
        if ($agenteId <= 0) {
            return ['exists' => false, 'deferido' => false, 'tipo' => ''];
        }

        $agente = $this->repo->findById($agenteId);
        if ($agente === null) {
            return ['exists' => false, 'deferido' => false, 'tipo' => ''];
        }

        return [
            'exists'   => true,
            'deferido' => $agente->getStatusCadastro()->isDeferido(),
            'tipo'     => $agente->getTipo()->value(),
        ];
    }
}
