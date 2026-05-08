<?php
/**
 * Command DTO: deferir cadastro (em_analise -> deferido).
 *
 * @package Ibram\ParticipeIbram\Application\Cadastro
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Cadastro;

use InvalidArgumentException;

/**
 * @psalm-immutable
 */
final class DeferirCadastroCommand
{
    private int $agenteId;
    private int $analistaId;
    private string $parecerMd;

    public function __construct(int $agenteId, int $analistaId, string $parecerMd)
    {
        if ($agenteId <= 0) {
            throw new InvalidArgumentException('DeferirCadastroCommand: agenteId deve ser positivo.');
        }
        if ($analistaId <= 0) {
            throw new InvalidArgumentException('DeferirCadastroCommand: analistaId deve ser positivo.');
        }
        $parecer = trim($parecerMd);
        if ($parecer === '') {
            throw new InvalidArgumentException('DeferirCadastroCommand: parecerMd nao pode ser vazio.');
        }

        $this->agenteId    = $agenteId;
        $this->analistaId  = $analistaId;
        $this->parecerMd   = $parecer;
    }

    public function agenteId(): int
    {
        return $this->agenteId;
    }

    public function analistaId(): int
    {
        return $this->analistaId;
    }

    public function parecerMd(): string
    {
        return $this->parecerMd;
    }
}
