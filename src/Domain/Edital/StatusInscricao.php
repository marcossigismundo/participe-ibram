<?php
/**
 * Estados do ciclo de vida da inscrição em edital (SCHEMA §4).
 *
 * @package Ibram\ParticipeIbram\Domain\Edital
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Edital;

use InvalidArgumentException;

/**
 * Enum-like (PHP 7.4 compatível) com a máquina de estados da inscrição.
 *
 *   rascunho ─submeter─▶ inscrito ─iniciar_habilitacao─▶ em_habilitacao
 *   em_habilitacao ─habilitar─▶ habilitado
 *   em_habilitacao ─inabilitar─▶ inabilitado
 *   inabilitado ─protocolar_recurso─▶ em_recurso
 *   em_recurso ─deferir─▶ final_habilitado (final)
 *   em_recurso ─manter─▶ final_inabilitado (final)
 *   habilitado ─prazo_recurso_expira─▶ final_habilitado (final)
 */
final class StatusInscricao
{
    public const RASCUNHO          = 'rascunho';
    public const INSCRITO          = 'inscrito';
    public const EM_HABILITACAO    = 'em_habilitacao';
    public const HABILITADO        = 'habilitado';
    public const INABILITADO       = 'inabilitado';
    public const EM_RECURSO        = 'em_recurso';
    public const FINAL_HABILITADO  = 'final_habilitado';
    public const FINAL_INABILITADO = 'final_inabilitado';

    /**
     * @var array<int,string>
     */
    private const ALLOWED = [
        self::RASCUNHO,
        self::INSCRITO,
        self::EM_HABILITACAO,
        self::HABILITADO,
        self::INABILITADO,
        self::EM_RECURSO,
        self::FINAL_HABILITADO,
        self::FINAL_INABILITADO,
    ];

    /**
     * @var array<int,string>
     */
    private const FINAL_STATES = [
        self::FINAL_HABILITADO,
        self::FINAL_INABILITADO,
    ];

    /**
     * Matriz de transições válidas.
     *
     * Estados terminais (FINAL_*) são chaves omitidas — proibidos como origem.
     *
     * @var array<string,array<int,string>>
     */
    private const TRANSITIONS = [
        self::RASCUNHO       => [self::INSCRITO],
        self::INSCRITO       => [self::EM_HABILITACAO],
        self::EM_HABILITACAO => [self::HABILITADO, self::INABILITADO],
        self::HABILITADO     => [self::FINAL_HABILITADO],
        self::INABILITADO    => [self::EM_RECURSO, self::FINAL_INABILITADO],
        self::EM_RECURSO     => [self::FINAL_HABILITADO, self::FINAL_INABILITADO],
    ];

    private string $value;

    /**
     * @throws InvalidArgumentException
     */
    private function __construct(string $value)
    {
        if (!in_array($value, self::ALLOWED, true)) {
            throw new InvalidArgumentException(sprintf(
                'StatusInscricao invalido: "%s".',
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

    public static function rascunho(): self
    {
        return new self(self::RASCUNHO);
    }

    public static function inscrito(): self
    {
        return new self(self::INSCRITO);
    }

    public static function emHabilitacao(): self
    {
        return new self(self::EM_HABILITACAO);
    }

    public static function habilitado(): self
    {
        return new self(self::HABILITADO);
    }

    public static function inabilitado(): self
    {
        return new self(self::INABILITADO);
    }

    public static function emRecurso(): self
    {
        return new self(self::EM_RECURSO);
    }

    public static function finalHabilitado(): self
    {
        return new self(self::FINAL_HABILITADO);
    }

    public static function finalInabilitado(): self
    {
        return new self(self::FINAL_INABILITADO);
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

    public function isFinal(): bool
    {
        return in_array($this->value, self::FINAL_STATES, true);
    }

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
