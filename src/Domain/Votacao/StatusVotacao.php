<?php
/**
 * Estados do ciclo de vida da votação (TD-06, SCHEMA §5).
 *
 * @package Ibram\ParticipeIbram\Domain\Votacao
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Votacao;

use InvalidArgumentException;

/**
 * Enum-like (PHP 7.4 compatível) com a máquina de estados da votação.
 *
 *   agendada ──abrir──▶ aberta ──encerrar──▶ encerrada ──apurar──▶ apurada (final)
 *   agendada ──cancelar──▶ cancelada (final)
 *   aberta   ──cancelar──▶ cancelada (final)
 *
 * Estados terminais: `apurada` e `cancelada`. Após apuração ou cancelamento,
 * a votação não admite novas transições.
 */
final class StatusVotacao
{
    public const AGENDADA  = 'agendada';
    public const ABERTA    = 'aberta';
    public const ENCERRADA = 'encerrada';
    public const APURADA   = 'apurada';
    public const CANCELADA = 'cancelada';

    /**
     * Lista canônica completa.
     *
     * @var array<int,string>
     */
    private const ALLOWED = [
        self::AGENDADA,
        self::ABERTA,
        self::ENCERRADA,
        self::APURADA,
        self::CANCELADA,
    ];

    /**
     * Estados terminais — não podem transicionar.
     *
     * @var array<int,string>
     */
    private const FINAL_STATES = [
        self::APURADA,
        self::CANCELADA,
    ];

    /**
     * Matriz de transições válidas: [origem => [destinos permitidos...]].
     *
     * @var array<string,array<int,string>>
     */
    private const TRANSITIONS = [
        self::AGENDADA => [
            self::ABERTA,
            self::CANCELADA,
        ],
        self::ABERTA => [
            self::ENCERRADA,
            self::CANCELADA,
        ],
        self::ENCERRADA => [
            self::APURADA,
        ],
    ];

    private string $value;

    /**
     * @throws InvalidArgumentException Quando o valor não é um status válido.
     */
    private function __construct(string $value)
    {
        if (!in_array($value, self::ALLOWED, true)) {
            throw new InvalidArgumentException(sprintf(
                'StatusVotacao invalido: "%s".',
                $value
            ));
        }
        $this->value = $value;
    }

    /**
     * Factory normalizadora.
     *
     * @throws InvalidArgumentException Quando o valor é desconhecido.
     */
    public static function fromString(string $value): self
    {
        return new self(strtolower(trim($value)));
    }

    public static function agendada(): self
    {
        return new self(self::AGENDADA);
    }

    public static function aberta(): self
    {
        return new self(self::ABERTA);
    }

    public static function encerrada(): self
    {
        return new self(self::ENCERRADA);
    }

    public static function apurada(): self
    {
        return new self(self::APURADA);
    }

    public static function cancelada(): self
    {
        return new self(self::CANCELADA);
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

    /**
     * Estado terminal — qualquer transição é proibida.
     */
    public function isFinal(): bool
    {
        return in_array($this->value, self::FINAL_STATES, true);
    }

    public function isAgendada(): bool
    {
        return $this->value === self::AGENDADA;
    }

    public function isAberta(): bool
    {
        return $this->value === self::ABERTA;
    }

    public function isEncerrada(): bool
    {
        return $this->value === self::ENCERRADA;
    }

    public function isApurada(): bool
    {
        return $this->value === self::APURADA;
    }

    public function isCancelada(): bool
    {
        return $this->value === self::CANCELADA;
    }

    /**
     * Verifica se a transição é permitida pela máquina de estados.
     *
     * Função pura — não atualiza nenhum estado.
     */
    public function canTransitionTo(self $target): bool
    {
        if (!isset(self::TRANSITIONS[$this->value])) {
            return false;
        }

        return in_array($target->value, self::TRANSITIONS[$this->value], true);
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
