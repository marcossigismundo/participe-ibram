<?php
/**
 * Exceção: agente não encontrado.
 *
 * @package Ibram\ParticipeIbram\Domain\Agente
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Agente;

use RuntimeException;

/**
 * Lançada por casos de uso/repositório quando uma operação demanda um agente
 * existente e nenhum corresponde aos critérios.
 */
final class AgenteNotFound extends RuntimeException
{
    public static function withId(int $id): self
    {
        return new self(sprintf('Agente nao encontrado: id=%d.', $id));
    }

    public static function withNumeroRegistro(string $numero): self
    {
        return new self(sprintf('Agente nao encontrado: numero_registro="%s".', $numero));
    }
}
