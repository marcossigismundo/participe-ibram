<?php
/**
 * Command DTO: indeferir cadastro.
 *
 * @package Ibram\ParticipeIbram\Application\Cadastro
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Cadastro;

use InvalidArgumentException;

/**
 * @psalm-immutable
 */
final class IndeferirCadastroCommand
{
    private int $agenteId;
    private int $analistaId;
    private string $parecerMd;
    private string $fundamentacaoMd;

    public function __construct(
        int $agenteId,
        int $analistaId,
        string $parecerMd,
        string $fundamentacaoMd
    ) {
        if ($agenteId <= 0) {
            throw new InvalidArgumentException('IndeferirCadastroCommand: agenteId deve ser positivo.');
        }
        if ($analistaId <= 0) {
            throw new InvalidArgumentException('IndeferirCadastroCommand: analistaId deve ser positivo.');
        }
        $parecer = trim($parecerMd);
        if ($parecer === '') {
            throw new InvalidArgumentException('IndeferirCadastroCommand: parecerMd nao pode ser vazio.');
        }
        $fund = trim($fundamentacaoMd);
        if ($fund === '') {
            throw new InvalidArgumentException(
                'IndeferirCadastroCommand: fundamentacaoMd e obrigatoria (Art. 7 Portaria 3230).'
            );
        }

        $this->agenteId        = $agenteId;
        $this->analistaId      = $analistaId;
        $this->parecerMd       = $parecer;
        $this->fundamentacaoMd = $fund;
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

    public function fundamentacaoMd(): string
    {
        return $this->fundamentacaoMd;
    }
}
