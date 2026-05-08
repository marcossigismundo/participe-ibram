<?php
/**
 * Status de consentimento (aceito | negado | revogado).
 *
 * @package Ibram\ParticipeIbram\Domain\Consentimento
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Consentimento;

use InvalidArgumentException;

/**
 * Enum-like (PHP 7.4+) para a coluna `status` de `wp_pi_consentimentos`.
 */
final class StatusConsentimento
{
    public const ACEITO   = 'aceito';
    public const NEGADO   = 'negado';
    public const REVOGADO = 'revogado';

    /** @var array<int,string> */
    private const ALLOWED = [self::ACEITO, self::NEGADO, self::REVOGADO];

    private string $value;

    private function __construct(string $value)
    {
        if (!in_array($value, self::ALLOWED, true)) {
            throw new InvalidArgumentException(sprintf(
                'StatusConsentimento invalido: "%s". Esperado: %s.',
                $value,
                implode(', ', self::ALLOWED)
            ));
        }
        $this->value = $value;
    }

    public static function fromString(string $value): self
    {
        return new self(strtolower(trim($value)));
    }

    public static function aceito(): self
    {
        return new self(self::ACEITO);
    }

    public static function negado(): self
    {
        return new self(self::NEGADO);
    }

    public static function revogado(): self
    {
        return new self(self::REVOGADO);
    }

    /**
     * @return array<int,string>
     */
    public static function values(): array
    {
        return self::ALLOWED;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function isAceito(): bool
    {
        return $this->value === self::ACEITO;
    }

    public function isNegado(): bool
    {
        return $this->value === self::NEGADO;
    }

    public function isRevogado(): bool
    {
        return $this->value === self::REVOGADO;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
