<?php
/**
 * Representante de coletivo colegiado / órgão (`wp_pi_agente_representantes`).
 *
 * @package Ibram\ParticipeIbram\Domain\Agente
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Agente;

use InvalidArgumentException;

/**
 * Linha individual de representante. CPF em claro (`cpfPlain`) usado apenas
 * antes da persistência ou após decifragem; o repositório descarta após save.
 */
final class Representante
{
    private ?int $id;
    private int $agenteId;
    private string $nome;
    private ?string $cpfPlain;
    private ?string $email;
    private ?string $telefone;
    private ?string $papel;
    private bool $principal;
    private int $ordem;

    /**
     * @throws InvalidArgumentException
     */
    public function __construct(
        ?int $id,
        int $agenteId,
        string $nome,
        ?string $cpfPlain = null,
        ?string $email = null,
        ?string $telefone = null,
        ?string $papel = null,
        bool $principal = false,
        int $ordem = 0
    ) {
        if ($id !== null && $id <= 0) {
            throw new InvalidArgumentException('Representante: id deve ser positivo ou null.');
        }
        if ($agenteId <= 0) {
            throw new InvalidArgumentException('Representante: agenteId deve ser positivo.');
        }
        $nome = trim($nome);
        if ($nome === '') {
            throw new InvalidArgumentException('Representante: nome nao pode ser vazio.');
        }
        if ($ordem < 0) {
            throw new InvalidArgumentException('Representante: ordem deve ser >= 0.');
        }

        $this->id        = $id;
        $this->agenteId  = $agenteId;
        $this->nome      = $nome;
        $this->cpfPlain  = $cpfPlain;
        $this->email     = $email;
        $this->telefone  = $telefone;
        $this->papel     = $papel;
        $this->principal = $principal;
        $this->ordem     = $ordem;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAgenteId(): int
    {
        return $this->agenteId;
    }

    public function getNome(): string
    {
        return $this->nome;
    }

    public function getCpfPlain(): ?string
    {
        return $this->cpfPlain;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getTelefone(): ?string
    {
        return $this->telefone;
    }

    public function getPapel(): ?string
    {
        return $this->papel;
    }

    public function isPrincipal(): bool
    {
        return $this->principal;
    }

    public function getOrdem(): int
    {
        return $this->ordem;
    }

    public function withoutPlainSecrets(): self
    {
        $copy = clone $this;
        $copy->cpfPlain = null;

        return $copy;
    }

    public function withAgenteId(int $agenteId): self
    {
        if ($agenteId <= 0) {
            throw new InvalidArgumentException('Representante: agenteId deve ser positivo.');
        }
        $copy = clone $this;
        $copy->agenteId = $agenteId;

        return $copy;
    }

    public function withId(int $id): self
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Representante: id deve ser positivo.');
        }
        $copy = clone $this;
        $copy->id = $id;

        return $copy;
    }
}
