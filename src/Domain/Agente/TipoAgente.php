<?php
/**
 * Tipologia de agente conforme TD-01 (3 tipos).
 *
 * @package Ibram\ParticipeIbram\Domain\Agente
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Agente;

use InvalidArgumentException;

/**
 * Enum-like (PHP 7.4 compatível) para o discriminador `tipo` da tabela
 * `wp_pi_agentes`.
 *
 *  - {@see TipoAgente::PF} Pessoa Física
 *  - {@see TipoAgente::OR} Organização (PJ formal ou Coletivo sem CNPJ)
 *  - {@see TipoAgente::SM} Sistema de Museu / Secretaria
 *
 * Imutável; instâncias são criadas via {@see fromString()}.
 */
final class TipoAgente
{
    public const PF = 'PF';
    public const OR = 'OR';
    public const SM = 'SM';

    /**
     * Lista canônica de valores permitidos.
     *
     * @var array<int,string>
     */
    private const ALLOWED = [self::PF, self::OR, self::SM];

    private string $value;

    /**
     * @param string $value Valor canônico (PF/OR/SM).
     *
     * @throws InvalidArgumentException Quando o valor não pertence ao enum.
     */
    private function __construct(string $value)
    {
        if (!in_array($value, self::ALLOWED, true)) {
            throw new InvalidArgumentException(sprintf(
                'TipoAgente invalido: "%s". Esperado: %s.',
                $value,
                implode(', ', self::ALLOWED)
            ));
        }
        $this->value = $value;
    }

    /**
     * Factory normalizadora.
     *
     * Aceita variações de caixa (`pf`, `Pf`, ` PF `) e devolve a instância
     * canônica em maiúsculas.
     *
     * @throws InvalidArgumentException Quando o valor é desconhecido.
     */
    public static function fromString(string $value): self
    {
        $normalized = strtoupper(trim($value));

        return new self($normalized);
    }

    /**
     * Atalhos para evitar `fromString` em código de domínio.
     */
    public static function pf(): self
    {
        return new self(self::PF);
    }

    public static function org(): self
    {
        return new self(self::OR);
    }

    public static function sm(): self
    {
        return new self(self::SM);
    }

    /**
     * Lista todos os valores válidos.
     *
     * @return array<int,string>
     */
    public static function all(): array
    {
        return self::ALLOWED;
    }

    /**
     * Valor canônico (PF/OR/SM).
     */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * Comparação estrutural por valor.
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
