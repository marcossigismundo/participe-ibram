<?php
/**
 * Comando para inscrição de agente em edital.
 *
 * @package Ibram\ParticipeIbram\Application\Edital
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Edital;

use InvalidArgumentException;

/**
 * DTO imutável com os parâmetros de uma inscrição.
 */
final class InscreverAgenteCommand
{
    private int $editalId;
    private int $categoriaId;
    private int $agenteId;
    private ?string $portfolioMd;

    public function __construct(int $editalId, int $categoriaId, int $agenteId, ?string $portfolioMd = null)
    {
        if ($editalId <= 0) {
            throw new InvalidArgumentException('InscreverAgenteCommand.editalId deve ser positivo.');
        }
        if ($categoriaId <= 0) {
            throw new InvalidArgumentException('InscreverAgenteCommand.categoriaId deve ser positivo.');
        }
        if ($agenteId <= 0) {
            throw new InvalidArgumentException('InscreverAgenteCommand.agenteId deve ser positivo.');
        }
        $this->editalId    = $editalId;
        $this->categoriaId = $categoriaId;
        $this->agenteId    = $agenteId;
        $this->portfolioMd = $portfolioMd;
    }

    public function editalId(): int
    {
        return $this->editalId;
    }

    public function categoriaId(): int
    {
        return $this->categoriaId;
    }

    public function agenteId(): int
    {
        return $this->agenteId;
    }

    public function portfolioMd(): ?string
    {
        return $this->portfolioMd;
    }
}
