<?php
/**
 * Entidade de domínio: item de vocabulário controlado.
 *
 * @package Ibram\ParticipeIbram\Domain\Vocabulario
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Vocabulario;

use InvalidArgumentException;

/**
 * Linha de `wp_pi_vocabularios` em forma de objeto.
 *
 * Imutável: alterações de estado retornam novas instâncias (ver
 * {@see desativar()}). Não há setters — qualquer mutação implica nova
 * instância para preservar invariantes em camadas superiores.
 */
final class ItemVocabulario
{
    private ?int $id;

    private string $tipo;

    private string $valor;

    private string $rotulo;

    private ?string $descricao;

    private int $ordem;

    private bool $ativo;

    /**
     * Metadata livre (JSON). Usado por `instancias_participacao` (recorrência)
     * e potencialmente por outros tipos no futuro.
     *
     * @var array<string,mixed>|null
     */
    private ?array $metadata;

    /**
     * @param array<string,mixed>|null $metadata
     *
     * @throws InvalidArgumentException Quando o tipo não está no whitelist
     *                                  ou campos obrigatórios estão vazios.
     */
    public function __construct(
        ?int $id,
        string $tipo,
        string $valor,
        string $rotulo,
        ?string $descricao,
        int $ordem,
        bool $ativo,
        ?array $metadata
    ) {
        if (!TipoVocabulario::isValid($tipo)) {
            throw new InvalidArgumentException(sprintf(
                'ItemVocabulario: tipo "%s" nao e um TipoVocabulario valido.',
                $tipo
            ));
        }
        $valor  = trim($valor);
        $rotulo = trim($rotulo);
        if ($valor === '') {
            throw new InvalidArgumentException('ItemVocabulario: valor nao pode ser vazio.');
        }
        if ($rotulo === '') {
            throw new InvalidArgumentException('ItemVocabulario: rotulo nao pode ser vazio.');
        }

        $this->id        = $id;
        $this->tipo      = strtolower($tipo);
        $this->valor     = $valor;
        $this->rotulo    = $rotulo;
        $this->descricao = $descricao !== null ? trim($descricao) : null;
        $this->ordem     = $ordem;
        $this->ativo     = $ativo;
        $this->metadata  = $metadata;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function tipo(): string
    {
        return $this->tipo;
    }

    public function valor(): string
    {
        return $this->valor;
    }

    public function rotulo(): string
    {
        return $this->rotulo;
    }

    public function descricao(): ?string
    {
        return $this->descricao;
    }

    public function ordem(): int
    {
        return $this->ordem;
    }

    public function ativo(): bool
    {
        return $this->ativo;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function metadata(): ?array
    {
        return $this->metadata;
    }

    /**
     * Retorna nova instância com `ativo=false` (soft-disable). Mantém id,
     * tipo, valor, rótulo, etc. — apenas o flag muda.
     */
    public function desativar(): self
    {
        if (!$this->ativo) {
            return $this;
        }

        return new self(
            $this->id,
            $this->tipo,
            $this->valor,
            $this->rotulo,
            $this->descricao,
            $this->ordem,
            false,
            $this->metadata
        );
    }
}
