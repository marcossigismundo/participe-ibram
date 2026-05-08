<?php
/**
 * Command: exportar relatório completo de apuração (ZIP).
 *
 * @package Ibram\ParticipeIbram\Application\Votacao
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Votacao;

use InvalidArgumentException;

/**
 * Imutável. Recebe id da votação a ser exportada e ator (auditoria).
 */
final class ExportarRelatorioApuracaoCommand
{
    private int $votacaoId;

    private ?int $atorId;

    public function __construct(int $votacaoId, ?int $atorId = null)
    {
        if ($votacaoId <= 0) {
            throw new InvalidArgumentException('votacaoId deve ser positivo.');
        }
        if ($atorId !== null && $atorId <= 0) {
            throw new InvalidArgumentException('atorId deve ser positivo quando informado.');
        }
        $this->votacaoId = $votacaoId;
        $this->atorId    = $atorId;
    }

    public function votacaoId(): int
    {
        return $this->votacaoId;
    }

    public function atorId(): ?int
    {
        return $this->atorId;
    }
}
