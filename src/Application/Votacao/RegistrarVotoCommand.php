<?php
/**
 * Command: registrar um voto.
 *
 * @package Ibram\ParticipeIbram\Application\Votacao
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Votacao;

use InvalidArgumentException;

/**
 * DTO imutável com os dados necessários para registrar um voto.
 *
 * NÃO carrega `eleitor_hash` — esse é calculado pelo handler a partir de
 * `agenteId` + `votacaoId` via {@see EleitorHasher}, garantindo que a fórmula
 * canônica seja sempre a mesma.
 */
final class RegistrarVotoCommand
{
    private int $votacaoId;

    private int $categoriaId;

    private int $agenteId;

    private int $candidatoInscricaoId;

    /**
     * @throws InvalidArgumentException
     */
    public function __construct(
        int $votacaoId,
        int $categoriaId,
        int $agenteId,
        int $candidatoInscricaoId
    ) {
        if ($votacaoId <= 0) {
            throw new InvalidArgumentException('votacaoId deve ser positivo.');
        }
        if ($categoriaId <= 0) {
            throw new InvalidArgumentException('categoriaId deve ser positivo.');
        }
        if ($agenteId <= 0) {
            throw new InvalidArgumentException('agenteId deve ser positivo.');
        }
        if ($candidatoInscricaoId <= 0) {
            throw new InvalidArgumentException('candidatoInscricaoId deve ser positivo.');
        }

        $this->votacaoId            = $votacaoId;
        $this->categoriaId          = $categoriaId;
        $this->agenteId             = $agenteId;
        $this->candidatoInscricaoId = $candidatoInscricaoId;
    }

    public function votacaoId(): int
    {
        return $this->votacaoId;
    }

    public function categoriaId(): int
    {
        return $this->categoriaId;
    }

    public function agenteId(): int
    {
        return $this->agenteId;
    }

    public function candidatoInscricaoId(): int
    {
        return $this->candidatoInscricaoId;
    }
}
