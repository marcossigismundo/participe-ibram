<?php
/**
 * Value object para o número de registro do agente (TD-02).
 *
 * @package Ibram\ParticipeIbram\Domain\Agente
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Agente;

use InvalidArgumentException;

/**
 * Imutável; representa `PI-{TIPO}-{AAAA}-{NNNNNN}`.
 *
 * Exemplos válidos:
 *   PI-PF-2026-000123
 *   PI-OR-2025-000045
 *   PI-SM-2026-000007
 *
 * Regras (TD-02):
 *  - tipo ∈ {PF, OR, SM}
 *  - ano em [2024..2099] (sanidade; sequência inicia em 2024 com a Portaria 3230)
 *  - sequência em [1..999_999]
 *
 * Construção via:
 *  - `new NumeroRegistro('PI-PF-2026-000123')`
 *  - `NumeroRegistro::fromParts('PF', 2026, 123)`
 */
final class NumeroRegistro
{
    /**
     * Regex canônica: PI-{TIPO}-{AAAA}-{NNNNNN}.
     */
    private const PATTERN = '/^PI-(PF|OR|SM)-(\d{4})-(\d{6})$/';

    private const MIN_YEAR = 2024;
    private const MAX_YEAR = 2099;

    private const MIN_SEQ = 1;
    private const MAX_SEQ = 999999;

    private string $tipo;
    private int $ano;
    private int $sequencia;
    private string $valueCache;

    /**
     * @param string $value Número de registro completo.
     *
     * @throws InvalidArgumentException Quando o formato é inválido.
     */
    public function __construct(string $value)
    {
        $value = trim($value);
        $matches = [];
        if (preg_match(self::PATTERN, $value, $matches) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'NumeroRegistro com formato invalido: "%s". Esperado PI-{PF|OR|SM}-{AAAA}-{NNNNNN}.',
                $value
            ));
        }

        $tipo = $matches[1];
        $ano  = (int) $matches[2];
        $seq  = (int) $matches[3];

        self::guardYear($ano);
        self::guardSequence($seq);

        $this->tipo       = $tipo;
        $this->ano        = $ano;
        $this->sequencia  = $seq;
        $this->valueCache = $value;
    }

    /**
     * Constrói a partir de partes.
     *
     * @param string $tipo Discriminador (PF/OR/SM).
     * @param int    $ano  Ano (4 dígitos).
     * @param int    $seq  Sequência [1..999_999].
     *
     * @throws InvalidArgumentException Quando alguma parte é inválida.
     */
    public static function fromParts(string $tipo, int $ano, int $seq): self
    {
        $tipoNorm = strtoupper(trim($tipo));
        if ($tipoNorm !== TipoAgente::PF && $tipoNorm !== TipoAgente::OR && $tipoNorm !== TipoAgente::SM) {
            throw new InvalidArgumentException(sprintf(
                'NumeroRegistro: tipo invalido "%s". Esperado PF, OR ou SM.',
                $tipo
            ));
        }

        self::guardYear($ano);
        self::guardSequence($seq);

        return new self(sprintf('PI-%s-%04d-%06d', $tipoNorm, $ano, $seq));
    }

    /**
     * Discriminador do tipo (PF/OR/SM).
     */
    public function tipo(): string
    {
        return $this->tipo;
    }

    /**
     * Ano (4 dígitos).
     */
    public function ano(): int
    {
        return $this->ano;
    }

    /**
     * Número sequencial inteiro (1..999999).
     */
    public function sequencia(): int
    {
        return $this->sequencia;
    }

    /**
     * Igualdade estrutural por valor.
     */
    public function equals(self $other): bool
    {
        return $this->valueCache === $other->valueCache;
    }

    /**
     * Forma serializada canônica (`PI-PF-2026-000123`).
     */
    public function value(): string
    {
        return $this->valueCache;
    }

    public function __toString(): string
    {
        return $this->valueCache;
    }

    private static function guardYear(int $ano): void
    {
        if ($ano < self::MIN_YEAR || $ano > self::MAX_YEAR) {
            throw new InvalidArgumentException(sprintf(
                'NumeroRegistro: ano %d fora do intervalo permitido [%d..%d].',
                $ano,
                self::MIN_YEAR,
                self::MAX_YEAR
            ));
        }
    }

    private static function guardSequence(int $seq): void
    {
        if ($seq < self::MIN_SEQ || $seq > self::MAX_SEQ) {
            throw new InvalidArgumentException(sprintf(
                'NumeroRegistro: sequencia %d fora do intervalo permitido [%d..%d].',
                $seq,
                self::MIN_SEQ,
                self::MAX_SEQ
            ));
        }
    }
}
