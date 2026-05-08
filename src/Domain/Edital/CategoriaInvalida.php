<?php
/**
 * Exceção: categoria inválida (não pertence ao edital ou não aceita tipo de agente).
 *
 * @package Ibram\ParticipeIbram\Domain\Edital
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Edital;

use DomainException;

final class CategoriaInvalida extends DomainException
{
    public static function notInEdital(int $categoriaId, int $editalId): self
    {
        return new self(sprintf(
            'Categoria %d nao pertence ao edital %d.',
            $categoriaId,
            $editalId
        ));
    }

    public static function tipoAgenteNaoAceito(int $categoriaId, string $tipo): self
    {
        return new self(sprintf(
            'Categoria %d nao aceita agentes do tipo "%s".',
            $categoriaId,
            $tipo
        ));
    }

    public static function withId(int $categoriaId): self
    {
        return new self(sprintf('Categoria nao encontrada: id=%d.', $categoriaId));
    }
}
