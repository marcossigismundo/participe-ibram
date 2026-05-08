<?php
/**
 * Entidade Inscricao — espelha `wp_pi_inscricoes` (SCHEMA §4).
 *
 * @package Ibram\ParticipeIbram\Domain\Edital
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Edital;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

/**
 * Inscrição de um agente em uma categoria de um edital.
 *
 * Cross-domain: `agenteId` é apenas um inteiro (não há referência ao agregado
 * Agente). A regra de "agente deferido" é validada na camada de aplicação
 * (handler), via `\Ibram\ParticipeIbram\Domain\Agente\AgenteRepository`.
 *
 * Estado modelado por {@see StatusInscricao}; transições disponibilizadas
 * por métodos de domínio (submeter, iniciarHabilitacao, habilitar,
 * inabilitar, protocolarRecurso, decidirRecurso, tornarFinal).
 */
final class Inscricao
{
    private ?int $id;
    private int $editalId;
    private int $categoriaId;
    private int $agenteId;
    private ?string $portfolioMd;
    private StatusInscricao $status;
    private ?DateTimeImmutable $inscritoEm;
    private ?DateTimeImmutable $habilitadoEm;
    private ?DateTimeImmutable $inabilitadoEm;
    private ?string $motivoInabilitacaoMd;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    public function __construct(
        ?int $id,
        int $editalId,
        int $categoriaId,
        int $agenteId,
        ?string $portfolioMd,
        StatusInscricao $status,
        ?DateTimeImmutable $inscritoEm,
        ?DateTimeImmutable $habilitadoEm,
        ?DateTimeImmutable $inabilitadoEm,
        ?string $motivoInabilitacaoMd,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt
    ) {
        if ($id !== null && $id <= 0) {
            throw new InvalidArgumentException('Inscricao.id deve ser positivo quando informado.');
        }
        if ($editalId <= 0) {
            throw new InvalidArgumentException('Inscricao.editalId deve ser positivo.');
        }
        if ($categoriaId <= 0) {
            throw new InvalidArgumentException('Inscricao.categoriaId deve ser positivo.');
        }
        if ($agenteId <= 0) {
            throw new InvalidArgumentException('Inscricao.agenteId deve ser positivo.');
        }

        $this->id                   = $id;
        $this->editalId             = $editalId;
        $this->categoriaId          = $categoriaId;
        $this->agenteId             = $agenteId;
        $this->portfolioMd          = $portfolioMd !== null ? $portfolioMd : null;
        $this->status               = $status;
        $this->inscritoEm           = $inscritoEm;
        $this->habilitadoEm         = $habilitadoEm;
        $this->inabilitadoEm        = $inabilitadoEm;
        $this->motivoInabilitacaoMd = $motivoInabilitacaoMd !== null ? $motivoInabilitacaoMd : null;
        $this->createdAt            = $createdAt;
        $this->updatedAt            = $updatedAt;
    }

    /**
     * Cria uma inscrição em rascunho (estado inicial).
     */
    public static function novoRascunho(
        int $editalId,
        int $categoriaId,
        int $agenteId,
        ?string $portfolioMd = null
    ): self {
        $now = new DateTimeImmutable('now');

        return new self(
            null,
            $editalId,
            $categoriaId,
            $agenteId,
            $portfolioMd,
            StatusInscricao::rascunho(),
            null,
            null,
            null,
            null,
            $now,
            $now
        );
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function editalId(): int
    {
        return $this->editalId;
    }

    public function categoriaId(): int
    {
        return $this->categoriaId;
    }

    public function agenteId(): int
    {
        return $this->agenteId;
    }

    public function portfolioMd(): ?string
    {
        return $this->portfolioMd;
    }

    public function status(): StatusInscricao
    {
        return $this->status;
    }

    public function inscritoEm(): ?DateTimeImmutable
    {
        return $this->inscritoEm;
    }

    public function habilitadoEm(): ?DateTimeImmutable
    {
        return $this->habilitadoEm;
    }

    public function inabilitadoEm(): ?DateTimeImmutable
    {
        return $this->inabilitadoEm;
    }

    public function motivoInabilitacaoMd(): ?string
    {
        return $this->motivoInabilitacaoMd;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function withId(int $id): self
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('ID deve ser positivo.');
        }
        $clone     = clone $this;
        $clone->id = $id;

        return $clone;
    }

    /** rascunho → inscrito */
    public function submeter(): void
    {
        $this->changeStatus(StatusInscricao::inscrito());
        $this->inscritoEm = new DateTimeImmutable('now');
    }

    /** inscrito → em_habilitacao */
    public function iniciarHabilitacao(): void
    {
        $this->changeStatus(StatusInscricao::emHabilitacao());
    }

    /**
     * em_habilitacao → habilitado.
     *
     * @param int $atorId WP user id (analista/gestor de edital).
     */
    public function habilitar(int $atorId): void
    {
        if ($atorId <= 0) {
            throw new InvalidArgumentException('Inscricao.habilitar: ator invalido.');
        }
        $this->changeStatus(StatusInscricao::habilitado());
        $this->habilitadoEm = new DateTimeImmutable('now');
    }

    /**
     * em_habilitacao → inabilitado.
     *
     * @param string $motivo Motivo da inabilitação (Markdown, obrigatório).
     */
    public function inabilitar(string $motivo, int $atorId): void
    {
        if ($atorId <= 0) {
            throw new InvalidArgumentException('Inscricao.inabilitar: ator invalido.');
        }
        $motivoTrim = trim($motivo);
        if ($motivoTrim === '') {
            throw new InvalidArgumentException('Inscricao.inabilitar: motivo obrigatorio.');
        }
        $this->changeStatus(StatusInscricao::inabilitado());
        $this->inabilitadoEm        = new DateTimeImmutable('now');
        $this->motivoInabilitacaoMd = $motivoTrim;
    }

    /** inabilitado → em_recurso */
    public function protocolarRecurso(): void
    {
        $this->changeStatus(StatusInscricao::emRecurso());
    }

    /**
     * em_recurso → final_habilitado (se deferido) | final_inabilitado (mantido).
     */
    public function decidirRecurso(bool $deferido): void
    {
        $target = $deferido
            ? StatusInscricao::finalHabilitado()
            : StatusInscricao::finalInabilitado();
        $this->changeStatus($target);
        if ($deferido) {
            $this->habilitadoEm = new DateTimeImmutable('now');
        }
    }

    /**
     * habilitado → final_habilitado (após o prazo de recurso de inabilitação
     * expirar sem recurso).
     */
    public function tornarFinal(): void
    {
        if ($this->status->value() !== StatusInscricao::HABILITADO) {
            throw new DomainException(
                'tornarFinal so se aplica a inscricoes habilitadas (sem recurso pendente).'
            );
        }
        $this->changeStatus(StatusInscricao::finalHabilitado());
    }

    /**
     * @throws IllegalStateTransition
     */
    private function changeStatus(StatusInscricao $target): void
    {
        if (!$this->status->canTransitionTo($target)) {
            throw IllegalStateTransition::betweenInscricao($this->status, $target);
        }
        $this->status    = $target;
        $this->updatedAt = new DateTimeImmutable('now');
    }
}
