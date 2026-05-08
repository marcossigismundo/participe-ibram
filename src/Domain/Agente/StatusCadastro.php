<?php
/**
 * Estados do ciclo de vida do cadastro de agente (TD-05, Portaria 3230/2024 Arts. 5º/7º/8º).
 *
 * @package Ibram\ParticipeIbram\Domain\Agente
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Agente;

use InvalidArgumentException;

/**
 * Enum-like (PHP 7.4 compatível) com a máquina de estados completa do cadastro.
 *
 *   rascunho ─submeter─▶ submetido ─atribuir_analista─▶ em_analise
 *   em_analise ─deferir─▶ deferido (final)
 *   em_analise ─indeferir─▶ indeferido_aguardando_recurso
 *   indeferido_aguardando_recurso ─prazo_expira─▶ indeferido_final
 *   indeferido_aguardando_recurso ─protocolar_recurso─▶ em_retratacao
 *   em_retratacao ─reconsiderar─▶ deferido_em_retratacao (final)
 *   em_retratacao ─manter─▶ em_recurso_presidencia
 *   em_recurso_presidencia ─deferir─▶ deferido_em_recurso (final)
 *   em_recurso_presidencia ─manter─▶ indeferido_final (final)
 */
final class StatusCadastro
{
    public const RASCUNHO                       = 'rascunho';
    public const SUBMETIDO                      = 'submetido';
    public const EM_ANALISE                     = 'em_analise';
    public const DEFERIDO                       = 'deferido';
    public const DEFERIDO_EM_RETRATACAO         = 'deferido_em_retratacao';
    public const DEFERIDO_EM_RECURSO            = 'deferido_em_recurso';
    public const INDEFERIDO_AGUARDANDO_RECURSO  = 'indeferido_aguardando_recurso';
    public const EM_RETRATACAO                  = 'em_retratacao';
    public const EM_RECURSO_PRESIDENCIA         = 'em_recurso_presidencia';
    public const INDEFERIDO_FINAL               = 'indeferido_final';

    /**
     * Lista canônica completa.
     *
     * @var array<int,string>
     */
    private const ALLOWED = [
        self::RASCUNHO,
        self::SUBMETIDO,
        self::EM_ANALISE,
        self::DEFERIDO,
        self::DEFERIDO_EM_RETRATACAO,
        self::DEFERIDO_EM_RECURSO,
        self::INDEFERIDO_AGUARDANDO_RECURSO,
        self::EM_RETRATACAO,
        self::EM_RECURSO_PRESIDENCIA,
        self::INDEFERIDO_FINAL,
    ];

    /**
     * Estados terminais — não podem transicionar.
     *
     * @var array<int,string>
     */
    private const FINAL_STATES = [
        self::DEFERIDO,
        self::DEFERIDO_EM_RETRATACAO,
        self::DEFERIDO_EM_RECURSO,
        self::INDEFERIDO_FINAL,
    ];

    /**
     * Estados que representam deferimento (cadastro válido).
     *
     * @var array<int,string>
     */
    private const DEFERIDO_STATES = [
        self::DEFERIDO,
        self::DEFERIDO_EM_RETRATACAO,
        self::DEFERIDO_EM_RECURSO,
    ];

    /**
     * Matriz de transições válidas: [origem => [destinos permitidos...]].
     *
     * Estados terminais (DEFERIDO*, INDEFERIDO_FINAL) intencionalmente não
     * aparecem como chaves — qualquer transição a partir deles é proibida.
     *
     * @var array<string,array<int,string>>
     */
    private const TRANSITIONS = [
        self::RASCUNHO => [
            self::SUBMETIDO,
        ],
        self::SUBMETIDO => [
            self::EM_ANALISE,
        ],
        self::EM_ANALISE => [
            self::DEFERIDO,
            self::INDEFERIDO_AGUARDANDO_RECURSO,
        ],
        self::INDEFERIDO_AGUARDANDO_RECURSO => [
            self::INDEFERIDO_FINAL,
            self::EM_RETRATACAO,
        ],
        self::EM_RETRATACAO => [
            self::DEFERIDO_EM_RETRATACAO,
            self::EM_RECURSO_PRESIDENCIA,
        ],
        self::EM_RECURSO_PRESIDENCIA => [
            self::DEFERIDO_EM_RECURSO,
            self::INDEFERIDO_FINAL,
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
                'StatusCadastro invalido: "%s".',
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

    /** Estado inicial canônico. */
    public static function rascunho(): self
    {
        return new self(self::RASCUNHO);
    }

    public static function submetido(): self
    {
        return new self(self::SUBMETIDO);
    }

    public static function emAnalise(): self
    {
        return new self(self::EM_ANALISE);
    }

    public static function deferido(): self
    {
        return new self(self::DEFERIDO);
    }

    public static function deferidoEmRetratacao(): self
    {
        return new self(self::DEFERIDO_EM_RETRATACAO);
    }

    public static function deferidoEmRecurso(): self
    {
        return new self(self::DEFERIDO_EM_RECURSO);
    }

    public static function indeferidoAguardandoRecurso(): self
    {
        return new self(self::INDEFERIDO_AGUARDANDO_RECURSO);
    }

    public static function emRetratacao(): self
    {
        return new self(self::EM_RETRATACAO);
    }

    public static function emRecursoPresidencia(): self
    {
        return new self(self::EM_RECURSO_PRESIDENCIA);
    }

    public static function indeferidoFinal(): self
    {
        return new self(self::INDEFERIDO_FINAL);
    }

    /**
     * @return array<int,string>
     */
    public static function all(): array
    {
        return self::ALLOWED;
    }

    /**
     * Valor canônico (string).
     */
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

    /**
     * Cadastro deferido (em qualquer das três variações).
     */
    public function isDeferido(): bool
    {
        return in_array($this->value, self::DEFERIDO_STATES, true);
    }

    /**
     * Cadastro indeferido (qualquer fase: aguardando recurso ou final).
     */
    public function isIndeferido(): bool
    {
        return $this->value === self::INDEFERIDO_AGUARDANDO_RECURSO
            || $this->value === self::INDEFERIDO_FINAL;
    }

    /**
     * Verifica se a transição é permitida pela máquina de estados (TD-05).
     *
     * Não atualiza nenhum estado — função pura.
     */
    public function canTransitionTo(self $target): bool
    {
        if (!isset(self::TRANSITIONS[$this->value])) {
            return false;
        }

        return in_array($target->value, self::TRANSITIONS[$this->value], true);
    }

    /**
     * Comparação estrutural.
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
