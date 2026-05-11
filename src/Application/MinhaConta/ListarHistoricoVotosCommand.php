<?php
/**
 * Command: listar histórico de votos do próprio agente (voto secreto).
 *
 * @package Ibram\ParticipeIbram\Application\MinhaConta
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\MinhaConta;

use InvalidArgumentException;

/**
 * Entrada do caso de uso {@see ListarHistoricoVotosHandler}.
 *
 * Carrega apenas o `agenteId` do titular cuja jornada de votos será listada.
 * Por design **não recebe** filtros que poderiam revelar o candidato escolhido
 * (ex.: `candidato_inscricao_id`) — voto secreto, anti-coerção.
 */
final class ListarHistoricoVotosCommand
{
    private int $agenteId;

    public function __construct(int $agenteId)
    {
        if ($agenteId <= 0) {
            throw new InvalidArgumentException('ListarHistoricoVotosCommand.agenteId deve ser positivo.');
        }
        $this->agenteId = $agenteId;
    }

    public function agenteId(): int
    {
        return $this->agenteId;
    }
}
