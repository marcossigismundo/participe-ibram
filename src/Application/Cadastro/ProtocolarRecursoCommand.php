<?php
/**
 * Command DTO: protocolar recurso (Art. 7º Portaria 3230).
 *
 * @package Ibram\ParticipeIbram\Application\Cadastro
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Cadastro;

use InvalidArgumentException;

/**
 * @psalm-immutable
 */
final class ProtocolarRecursoCommand
{
    private int $agenteId;
    private int $userId;
    private string $fundamentacaoMd;

    public function __construct(int $agenteId, int $userId, string $fundamentacaoMd)
    {
        if ($agenteId <= 0) {
            throw new InvalidArgumentException('ProtocolarRecursoCommand: agenteId deve ser positivo.');
        }
        if ($userId <= 0) {
            throw new InvalidArgumentException('ProtocolarRecursoCommand: userId deve ser positivo.');
        }
        $fund = trim($fundamentacaoMd);
        if ($fund === '') {
            throw new InvalidArgumentException('ProtocolarRecursoCommand: fundamentacaoMd nao pode ser vazia.');
        }

        $this->agenteId        = $agenteId;
        $this->userId          = $userId;
        $this->fundamentacaoMd = $fund;
    }

    public function agenteId(): int
    {
        return $this->agenteId;
    }

    public function userId(): int
    {
        return $this->userId;
    }

    public function fundamentacaoMd(): string
    {
        return $this->fundamentacaoMd;
    }
}
