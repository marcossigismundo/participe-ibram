<?php
/**
 * Port: gateway para checar elegibilidade de um agente como eleitor.
 *
 * @package Ibram\ParticipeIbram\Application\Votacao\Ports
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Votacao\Ports;

/**
 * Anti-corruption layer: o domínio Votação NÃO depende diretamente de
 * `\Ibram\ParticipeIbram\Domain\Agente\AgenteRepository` (forward reference
 * por namespace, conforme spec da Onda 2). Esta porta é o único contrato que
 * Votação conhece sobre agentes; um adapter concreto (Wave Agente / Onda 3)
 * implementa.
 */
interface AgenteVotanteGateway
{
    /**
     * Indica se o agente está em status deferido (qualquer das 3 variações de
     * deferimento — vide {@see \Ibram\ParticipeIbram\Domain\Agente\StatusCadastro}).
     */
    public function estaDeferido(int $agenteId): bool;

    /**
     * Retorna o tipo do agente (`PF`, `OR` ou `SM`), ou null se inexistente.
     */
    public function tipoAgente(int $agenteId): ?string;
}
