<?php
/**
 * Command: criar uma votação.
 *
 * @package Ibram\ParticipeIbram\Application\Votacao
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Votacao;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * DTO imutável com a intenção "criar votação para este edital".
 */
final class CriarVotacaoCommand
{
    private int $editalId;

    private DateTimeImmutable $abertura;

    private DateTimeImmutable $encerramento;

    private string $modo;

    private ?int $atorId;

    /**
     * @throws InvalidArgumentException
     */
    public function __construct(
        int $editalId,
        DateTimeImmutable $abertura,
        DateTimeImmutable $encerramento,
        string $modo = 'por_categoria',
        ?int $atorId = null
    ) {
        if ($editalId <= 0) {
            throw new InvalidArgumentException('editalId deve ser positivo.');
        }
        if ($encerramento <= $abertura) {
            throw new InvalidArgumentException('encerramento deve ser estritamente maior que abertura.');
        }
        if ($atorId !== null && $atorId <= 0) {
            throw new InvalidArgumentException('atorId deve ser positivo quando informado.');
        }

        $this->editalId     = $editalId;
        $this->abertura     = $abertura;
        $this->encerramento = $encerramento;
        $this->modo         = $modo;
        $this->atorId       = $atorId;
    }

    public function editalId(): int
    {
        return $this->editalId;
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
