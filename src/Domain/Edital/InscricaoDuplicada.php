<?php
/**
 * Exceção: tentativa de inscrição duplicada (UNIQUE edital+categoria+agente).
 *
 * @package Ibram\ParticipeIbram\Domain\Edital
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Edital;

use DomainException;

final class InscricaoDuplicada extends DomainException
{
    public static function for(int $editalId, int $categoriaId, int $agenteId): self
    {
        return new self(sprintf(
            'Inscricao duplicada para edital=%d, categoria=%d, agente=%d.',
            $editalId,
            $categoriaId,
            $agenteId
        ));
    }
}
