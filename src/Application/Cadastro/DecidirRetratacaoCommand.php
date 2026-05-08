<?php
/**
 * Command DTO: decidir recurso na fase de retratação (analista reconsidera ou
 * mantém o indeferimento).
 *
 * @package Ibram\ParticipeIbram\Application\Cadastro
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Cadastro;

use InvalidArgumentException;

/**
 * @psalm-immutable
 */
final class DecidirRetratacaoCommand
{
    private int $recursoId;
    private int $analistaId;
    private bool $reconsiderar;
    private string $decisaoMd;

    public function __construct(int $recursoId, int $analistaId, bool $reconsiderar, string $decisaoMd)
    {
        if ($recursoId <= 0) {
            throw new InvalidArgumentException('DecidirRetratacaoCommand: recursoId deve ser positivo.');
        }
        if ($analistaId <= 0) {
            throw new InvalidArgumentException('DecidirRetratacaoCommand: analistaId deve ser positivo.');
        }
        $md = trim($decisaoMd);
        if ($md === '') {
            throw new InvalidArgumentException('DecidirRetratacaoCommand: decisaoMd nao pode ser vazia.');
        }

        $this->recursoId    = $recursoId;
        $this->analistaId   = $analistaId;
        $this->reconsiderar = $reconsiderar;
        $this->decisaoMd    = $md;
    }

    public function recursoId(): int
    {
        return $this->recursoId;
    }

    public function analistaId(): int
    {
        return $this->analistaId;
    }

    public function reconsiderar(): bool
    {
        return $this->reconsiderar;
    }

    public function decisaoMd(): string
    {
        return $this->decisaoMd;
    }
}
