<?php
/**
 * Exceção: edital não encontrado.
 *
 * @package Ibram\ParticipeIbram\Domain\Edital
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Edital;

use RuntimeException;

final class EditalNotFound extends RuntimeException
{
    public static function withId(int $id): self
    {
        return new self(sprintf('Edital nao encontrado: id=%d.', $id));
    }
}
