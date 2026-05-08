<?php
/**
 * Modo da votação: por_categoria ou geral (SCHEMA §5).
 *
 * @package Ibram\ParticipeIbram\Domain\Votacao
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Votacao;

use InvalidArgumentException;

/**
 * Enum-like (PHP 7.4 compatível) para o atributo `modo` de `wp_pi_votacoes`.
 *
 * - `POR_CATEGORIA` — voto por categoria do edital (cada eleitor escolhe um
 *   candidato em cada categoria elegível).
 * - `GERAL` — voto único geral, sem segmentação por categoria.
 */
final class ModoVotacao
{
    public const POR_CATEGORIA = 'por_categoria';
    public const GERAL         = 'geral';

    /**
     * @var array<int,string>
     */
    private const ALLOWED = [
        self::POR_CATEGORIA,
        self::GERAL,
    ];

    private string $value;

    /**
     * @throws InvalidArgumentException Quando o valor não é um modo válido.
     */
    private function __construct(string $value)
    {
        if (!in_array($value, self::ALLOWED, true)) {
            throw new InvalidArgumentException(sprintf(
                'ModoVotacao invalido: "%s".',
                $value
            ));
        }
        $this->value = $value;
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function fromString(string $value): self
    {
        return new self(strtolower(trim($value)));
    }

    public static function porCategoria(): self
    {
        return new self(self::POR_CATEGORIA);
    }

    public static function geral(): self
    {
        return new self(self::GERAL);
    }

    /**
     * @return array<int,string>
     */
    public static function all(): array
    {
        return self::ALLOWED;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isPorCategoria(): bool
    {
        return $this->value === self::POR_CATEGORIA;
    }

    public function isGeral(): bool
    {
        return $this->value === self::GERAL;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
