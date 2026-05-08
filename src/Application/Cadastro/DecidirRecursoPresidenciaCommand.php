<?php
/**
 * Command DTO: decidir recurso na fase de presidência.
 *
 * @package Ibram\ParticipeIbram\Application\Cadastro
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Cadastro;

use InvalidArgumentException;

/**
 * @psalm-immutable
 */
final class DecidirRecursoPresidenciaCommand
{
    private int $recursoId;
    private int $presidenteId;
    private bool $deferir;
    private string $decisaoMd;

    public function __construct(int $recursoId, int $presidenteId, bool $deferir, string $decisaoMd)
    {
        if ($recursoId <= 0) {
            throw new InvalidArgumentException('DecidirRecursoPresidenciaCommand: recursoId deve ser positivo.');
        }
        if ($presidenteId <= 0) {
            throw new InvalidArgumentException('DecidirRecursoPresidenciaCommand: presidenteId deve ser positivo.');
        }
        $md = trim($decisaoMd);
        if ($md === '') {
            throw new InvalidArgumentException('DecidirRecursoPresidenciaCommand: decisaoMd nao pode ser vazia.');
        }

        $this->recursoId    = $recursoId;
        $this->presidenteId = $presidenteId;
        $this->deferir      = $deferir;
        $this->decisaoMd    = $md;
    }

    public function recursoId(): int
    {
        return $this->recursoId;
    }

    public function presidenteId(): int
    {
        return $this->presidenteId;
    }

    public function deferir(): bool
    {
        return $this->deferir;
    }

    public function decisaoMd(): string
    {
        return $this->decisaoMd;
    }
}
