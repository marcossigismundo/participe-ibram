<?php
/**
 * Command: editar datas/modo de uma votação agendada.
 *
 * @package Ibram\ParticipeIbram\Application\Votacao
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Votacao;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * DTO imutável com a intenção "editar esta votação agendada".
 *
 * Só votações em status `agendada` podem ser editadas.
 * As invariantes de data (encerramento > abertura) são verificadas
 * no Handler e preservadas pela entidade Votacao.
 */
final class EditarVotacaoCommand
{
    private int $votacaoId;

    private DateTimeImmutable $abertura;

    private DateTimeImmutable $encerramento;

    private string $modo;

    private ?int $atorId;

    /**
     * @throws InvalidArgumentException
     */
    public function __construct(
        int $votacaoId,
        DateTimeImmutable $abertura,
        DateTimeImmutable $encerramento,
        string $modo = 'por_categoria',
        ?int $atorId = null
    ) {
        if ($votacaoId <= 0) {
            throw new InvalidArgumentException('votacaoId deve ser positivo.');
        }
        if ($encerramento <= $abertura) {
            throw new InvalidArgumentException('encerramento deve ser estritamente maior que abertura.');
        }
        if ($atorId !== null && $atorId <= 0) {
            throw new InvalidArgumentException('atorId deve ser positivo quando informado.');
        }

        $this->votacaoId    = $votacaoId;
        $this->abertura     = $abertura;
        $this->encerramento = $encerramento;
        $this->modo         = $modo;
        $this->atorId       = $atorId;
    }

    public function votacaoId(): int
    {
        return $this->votacaoId;
    }

    public function abertura(): DateTimeImmutable
    {
        return $this->abertura;
    }

    public function encerramento(): DateTimeImmutable
    {
        return $this->encerramento;
    }

    public function modo(): string
    {
        return $this->modo;
    }

    public function atorId(): ?int
    {
        return $this->atorId;
    }
}
