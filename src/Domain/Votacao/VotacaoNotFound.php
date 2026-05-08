<?php
/**
 * Exceção: votação não encontrada.
 *
 * @package Ibram\ParticipeIbram\Domain\Votacao
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Votacao;

use RuntimeException;

/**
 * Lançada por casos de uso/repositório quando uma operação demanda uma votação
 * existente e nenhuma corresponde aos critérios.
 */
final class VotacaoNotFound extends RuntimeException
{
    public static function withId(int $id): self
    {
        return new self(sprintf('Votacao nao encontrada: id=%d.', $id));
    }

    public static function forEdital(int $editalId): self
    {
        return new self(sprintf('Votacao nao encontrada para edital_id=%d.', $editalId));
    }
}
