<?php
/**
 * Command: publicar resultado de uma votação apurada.
 *
 * @package Ibram\ParticipeIbram\Application\Votacao
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Votacao;

use InvalidArgumentException;

final class PublicarResultadoCommand
{
    private int $votacaoId;

    private ?int $atorId;

    public function __construct(int $votacaoId, ?int $atorId = null)
    {
        if ($votacaoId <= 0) {
            throw new InvalidArgumentException('votacaoId deve ser positivo.');
        }
        if ($atorId !== null && $atorId <= 0) {
            throw new InvalidArgumentException('atorId deve ser positivo quando informado.');
        }
        $this->votacaoId = $votacaoId;
        $this->atorId    = $atorId;
    }

    public function votacaoId(): int
    {
        return $this->votacaoId;
    }

    public function atorId(): ?int
    {
        return $this->atorId;
    }
}
