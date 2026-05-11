<?php
/**
 * Command: regenerar o `hash_voto` (recibo) de um voto do próprio agente.
 *
 * @package Ibram\ParticipeIbram\Application\MinhaConta
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\MinhaConta;

use InvalidArgumentException;

/**
 * Entrada do {@see RegerarReciboVotoHandler}. Recebe o agente (titular) e a
 * votação cujo recibo será reemitido. Não recebe candidato — voto secreto.
 */
final class RegerarReciboVotoCommand
{
    private int $agenteId;
    private int $votacaoId;

    public function __construct(int $agenteId, int $votacaoId)
    {
        if ($agenteId <= 0) {
            throw new InvalidArgumentException('RegerarReciboVotoCommand.agenteId deve ser positivo.');
        }
        if ($votacaoId <= 0) {
            throw new InvalidArgumentException('RegerarReciboVotoCommand.votacaoId deve ser positivo.');
        }
        $this->agenteId  = $agenteId;
        $this->votacaoId = $votacaoId;
    }

    public function agenteId(): int
    {
        return $this->agenteId;
    }

    public function votacaoId(): int
    {
        return $this->votacaoId;
    }
}
