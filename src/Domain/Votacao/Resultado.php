<?php
/**
 * Entidade Resultado — espelha `wp_pi_resultados` (SCHEMA §5).
 *
 * @package Ibram\ParticipeIbram\Domain\Votacao
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Votacao;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Linha de resultado da apuração para um candidato em uma categoria.
 *
 * Imutável. Construída exclusivamente pelo {@see \Ibram\ParticipeIbram\Application\Votacao\ApurarHandler}
 * a partir da contagem de votos.
 *
 * Convenções:
 *  - `posicao` é 1-based (1 = primeiro colocado).
 *  - `eleito` indica que ocupa uma das `num_vagas` da categoria.
 *  - `suplente` indica que ocupa uma das `num_suplentes` subsequentes.
 *  - As flags `eleito` e `suplente` são mutuamente exclusivas.
 */
final class Resultado
{
    private ?int $id;

    private int $votacaoId;

    private int $categoriaId;

    private int $candidatoInscricaoId;

    private int $totalVotos;

    private int $posicao;

    private bool $eleito;

    private bool $suplente;

    private DateTimeImmutable $apuradoEm;

    /**
     * @throws InvalidArgumentException
     */
    public function __construct(
        ?int $id,
        int $votacaoId,
        int $categoriaId,
        int $candidatoInscricaoId,
        int $totalVotos,
        int $posicao,
        bool $eleito,
        bool $suplente,
        DateTimeImmutable $apuradoEm
    ) {
        if ($id !== null && $id <= 0) {
            throw new InvalidArgumentException('Resultado.id deve ser positivo quando informado.');
        }
        if ($votacaoId <= 0) {
            throw new InvalidArgumentException('Resultado.votacaoId deve ser positivo.');
        }
        if ($categoriaId <= 0) {
            throw new InvalidArgumentException('Resultado.categoriaId deve ser positivo.');
        }
        if ($candidatoInscricaoId <= 0) {
            throw new InvalidArgumentException('Resultado.candidatoInscricaoId deve ser positivo.');
        }
        if ($totalVotos < 0) {
            throw new InvalidArgumentException('Resultado.totalVotos nao pode ser negativo.');
        }
        if ($posicao < 1) {
            throw new InvalidArgumentException('Resultado.posicao deve ser >= 1.');
        }
        if ($eleito && $suplente) {
            throw new InvalidArgumentException(
                'Resultado: eleito e suplente sao mutuamente exclusivos.'
            );
        }

        $this->id                   = $id;
        $this->votacaoId            = $votacaoId;
        $this->categoriaId          = $categoriaId;
        $this->candidatoInscricaoId = $candidatoInscricaoId;
        $this->totalVotos           = $totalVotos;
        $this->posicao              = $posicao;
        $this->eleito               = $eleito;
        $this->suplente             = $suplente;
        $this->apuradoEm            = $apuradoEm;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function votacaoId(): int
    {
        return $this->votacaoId;
    }

    public function categoriaId(): int
    {
        return $this->categoriaId;
    }

    public function candidatoInscricaoId(): int
    {
        return $this->candidatoInscricaoId;
    }

    public function totalVotos(): int
    {
        return $this->totalVotos;
    }

    public function posicao(): int
    {
        return $this->posicao;
    }

    public function eleito(): bool
    {
        return $this->eleito;
    }

    public function suplente(): bool
    {
        return $this->suplente;
    }

    public function apuradoEm(): DateTimeImmutable
    {
        return $this->apuradoEm;
    }

    public function withId(int $id): self
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('id deve ser positivo.');
        }
        return new self(
            $id,
            $this->votacaoId,
            $this->categoriaId,
            $this->candidatoInscricaoId,
            $this->totalVotos,
            $this->posicao,
            $this->eleito,
            $this->suplente,
            $this->apuradoEm
        );
    }
}
