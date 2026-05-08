<?php
/**
 * Estados do ciclo de vida do edital (SCHEMA §4, TD-06).
 *
 * @package Ibram\ParticipeIbram\Domain\Edital
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Edital;

use InvalidArgumentException;

/**
 * Enum-like (PHP 7.4 compatível) com a máquina de estados do edital
 * conforme Despacho 98/2025 IBRAM (fluxo CCDEM).
 *
 *   rascunho ─publicar─▶ publicado ─abrir_inscricoes─▶ inscricoes_abertas
 *   inscricoes_abertas ─encerrar─▶ em_habilitacao
 *   em_habilitacao ─publicar_habilitacao─▶ em_recurso
 *   em_recurso ─encerrar_recursos─▶ votacao_aberta
 *   votacao_aberta ─encerrar─▶ votacao_encerrada
 *   votacao_encerrada ─publicar_resultado─▶ encerrado (final)
 */
final class StatusEdital
{
    public const RASCUNHO            = 'rascunho';
    public const PUBLICADO           = 'publicado';
    public const INSCRICOES_ABERTAS  = 'inscricoes_abertas';
    public const EM_HABILITACAO      = 'em_habilitacao';
    public const EM_RECURSO          = 'em_recurso';
    public const VOTACAO_ABERTA      = 'votacao_aberta';
    public const VOTACAO_ENCERRADA   = 'votacao_encerrada';
    public const ENCERRADO           = 'encerrado';

    /**
     * @var array<int,string>
     */
    private const ALLOWED = [
        self::RASCUNHO,
        self::PUBLICADO,
        self::INSCRICOES_ABERTAS,
        self::EM_HABILITACAO,
        self::EM_RECURSO,
        self::VOTACAO_ABERTA,
        self::VOTACAO_ENCERRADA,
        self::ENCERRADO,
    ];

    /**
     * Matriz de transições válidas (linear, sem ramificações no fluxo CCDEM).
     *
     * Estado terminal {@see ENCERRADO} omitido — qualquer transição é proibida.
     *
     * @var array<string,array<int,string>>
     */
    private const TRANSITIONS = [
        self::RASCUNHO            => [self::PUBLICADO],
        self::PUBLICADO           => [self::INSCRICOES_ABERTAS],
        self::INSCRICOES_ABERTAS  => [self::EM_HABILITACAO],
        self::EM_HABILITACAO      => [self::EM_RECURSO],
        self::EM_RECURSO          => [self::VOTACAO_ABERTA],
        self::VOTACAO_ABERTA      => [self::VOTACAO_ENCERRADA],
        self::VOTACAO_ENCERRADA   => [self::ENCERRADO],
    ];

    private string $value;

    /**
     * @throws InvalidArgumentException
     */
    private function __construct(string $value)
    {
        if (!in_array($value, self::ALLOWED, true)) {
            throw new InvalidArgumentException(sprintf(
                'StatusEdital invalido: "%s".',
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

    public static function publicado(): self
    {
        return new self(self::PUBLICADO);
    }

    public static function inscricoesAbertas(): self
    {
        return new self(self::INSCRICOES_ABERTAS);
    }

    public static function emHabilitacao(): self
    {
        return new self(self::EM_HABILITACAO);
    }

    public static function emRecurso(): self
    {
        return new self(self::EM_RECURSO);
    }

    public static function votacaoAberta(): self
    {
        return new self(self::VOTACAO_ABERTA);
    }

    public static function votacaoEncerrada(): self
    {
        return new self(self::VOTACAO_ENCERRADA);
    }

    public static function encerrado(): self
    {
        return new self(self::ENCERRADO);
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
        return $this->value === self::ENCERRADO;
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
