<?php
/**
 * Entidade Edital — espelha `wp_pi_editais` (SCHEMA §4, TD-06).
 *
 * @package Ibram\ParticipeIbram\Domain\Edital
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Edital;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

/**
 * Entidade de domínio para o agregado Edital.
 *
 * Regras (Despacho 98/2025 IBRAM, fluxo CCDEM):
 *  - As datas devem respeitar a ordem cronológica:
 *    abertura < encerramento_inscricoes < publicacao_habilitacao
 *    < prazo_recurso_inabilitacao < abertura_votacao
 *    < encerramento_votacao < publicacao_resultado.
 *  - As transições de status seguem {@see StatusEdital::TRANSITIONS}.
 *  - {@see publicar()} exige todas as datas preenchidas.
 *
 * Cross-domain: o `criadoPor` é WP user id (não objeto Agente). Não há
 * referência a outros domínios do bounded context.
 */
final class Edital
{
    private ?int $id;
    private string $titulo;
    private ?string $descricaoMd;
    private StatusEdital $status;
    private ?DateTimeImmutable $abertura;
    private ?DateTimeImmutable $encerramentoInscricoes;
    private ?DateTimeImmutable $publicacaoHabilitacao;
    private ?DateTimeImmutable $prazoRecursoInabilitacao;
    private ?DateTimeImmutable $aberturaVotacao;
    private ?DateTimeImmutable $encerramentoVotacao;
    private ?DateTimeImmutable $publicacaoResultado;
    private int $criadoPor;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    public function __construct(
        ?int $id,
        string $titulo,
        ?string $descricaoMd,
        StatusEdital $status,
        ?DateTimeImmutable $abertura,
        ?DateTimeImmutable $encerramentoInscricoes,
        ?DateTimeImmutable $publicacaoHabilitacao,
        ?DateTimeImmutable $prazoRecursoInabilitacao,
        ?DateTimeImmutable $aberturaVotacao,
        ?DateTimeImmutable $encerramentoVotacao,
        ?DateTimeImmutable $publicacaoResultado,
        int $criadoPor,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt
    ) {
        if ($id !== null && $id <= 0) {
            throw new InvalidArgumentException('Edital.id deve ser positivo quando informado.');
        }
        $tituloTrim = trim($titulo);
        if ($tituloTrim === '') {
            throw new InvalidArgumentException('Edital.titulo nao pode ser vazio.');
        }
        if (mb_strlen($tituloTrim) > 255) {
            throw new InvalidArgumentException('Edital.titulo excede 255 caracteres.');
        }
        if ($criadoPor <= 0) {
            throw new InvalidArgumentException('Edital.criadoPor deve ser positivo.');
        }

        self::guardCronologia(
            $abertura,
            $encerramentoInscricoes,
            $publicacaoHabilitacao,
            $prazoRecursoInabilitacao,
            $aberturaVotacao,
            $encerramentoVotacao,
            $publicacaoResultado
        );

        $this->id                       = $id;
        $this->titulo                   = $tituloTrim;
        $this->descricaoMd              = $descricaoMd !== null ? $descricaoMd : null;
        $this->status                   = $status;
        $this->abertura                 = $abertura;
        $this->encerramentoInscricoes   = $encerramentoInscricoes;
        $this->publicacaoHabilitacao    = $publicacaoHabilitacao;
        $this->prazoRecursoInabilitacao = $prazoRecursoInabilitacao;
        $this->aberturaVotacao          = $aberturaVotacao;
        $this->encerramentoVotacao      = $encerramentoVotacao;
        $this->publicacaoResultado      = $publicacaoResultado;
        $this->criadoPor                = $criadoPor;
        $this->createdAt                = $createdAt;
        $this->updatedAt                = $updatedAt;
    }

    /**
     * Construtor de conveniência para um edital novo (status rascunho, sem datas).
     */
    public static function novoRascunho(string $titulo, int $criadoPor, ?string $descricaoMd = null): self
    {
        $now = new DateTimeImmutable('now');

        return new self(
            null,
            $titulo,
            $descricaoMd,
            StatusEdital::rascunho(),
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $criadoPor,
            $now,
            $now
        );
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function titulo(): string
    {
        return $this->titulo;
    }

    public function descricaoMd(): ?string
    {
        return $this->descricaoMd;
    }

    public function status(): StatusEdital
    {
        return $this->status;
    }

    public function abertura(): ?DateTimeImmutable
    {
        return $this->abertura;
    }

    public function encerramentoInscricoes(): ?DateTimeImmutable
    {
        return $this->encerramentoInscricoes;
    }

    public function publicacaoHabilitacao(): ?DateTimeImmutable
    {
        return $this->publicacaoHabilitacao;
    }

    public function prazoRecursoInabilitacao(): ?DateTimeImmutable
    {
        return $this->prazoRecursoInabilitacao;
    }

    public function aberturaVotacao(): ?DateTimeImmutable
    {
        return $this->aberturaVotacao;
    }

    public function encerramentoVotacao(): ?DateTimeImmutable
    {
        return $this->encerramentoVotacao;
    }

    public function publicacaoResultado(): ?DateTimeImmutable
    {
        return $this->publicacaoResultado;
    }

    public function criadoPor(): int
    {
        return $this->criadoPor;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Atribui ID após persistência (chamado pelo repositório).
     */
    public function withId(int $id): self
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('ID deve ser positivo.');
        }
        $clone     = clone $this;
        $clone->id = $id;

        return $clone;
    }

    /**
     * Atualiza a programação de datas do edital (apenas em rascunho).
     *
     * @throws DomainException Quando o edital não está em rascunho.
     */
    public function programarDatas(
        DateTimeImmutable $abertura,
        DateTimeImmutable $encerramentoInscricoes,
        DateTimeImmutable $publicacaoHabilitacao,
        DateTimeImmutable $prazoRecursoInabilitacao,
        DateTimeImmutable $aberturaVotacao,
        DateTimeImmutable $encerramentoVotacao,
        DateTimeImmutable $publicacaoResultado
    ): void {
        if ($this->status->value() !== StatusEdital::RASCUNHO) {
            throw new DomainException('Datas so podem ser programadas em rascunho.');
        }
        self::guardCronologia(
            $abertura,
            $encerramentoInscricoes,
            $publicacaoHabilitacao,
            $prazoRecursoInabilitacao,
            $aberturaVotacao,
            $encerramentoVotacao,
            $publicacaoResultado
        );

        $this->abertura                 = $abertura;
        $this->encerramentoInscricoes   = $encerramentoInscricoes;
        $this->publicacaoHabilitacao    = $publicacaoHabilitacao;
        $this->prazoRecursoInabilitacao = $prazoRecursoInabilitacao;
        $this->aberturaVotacao          = $aberturaVotacao;
        $this->encerramentoVotacao      = $encerramentoVotacao;
        $this->publicacaoResultado      = $publicacaoResultado;
        $this->touch();
    }

    /** rascunho → publicado */
    public function publicar(): void
    {
        $this->mustHaveAllDatesScheduled();
        $this->changeStatus(StatusEdital::publicado());
    }

    /** publicado → inscricoes_abertas */
    public function abrirInscricoes(): void
    {
        $this->changeStatus(StatusEdital::inscricoesAbertas());
    }

    /** inscricoes_abertas → em_habilitacao */
    public function iniciarHabilitacao(): void
    {
        $this->changeStatus(StatusEdital::emHabilitacao());
    }

    /** em_habilitacao → em_recurso */
    public function abrirRecursoInabilitacao(): void
    {
        $this->changeStatus(StatusEdital::emRecurso());
    }

    /** em_recurso → votacao_aberta */
    public function abrirVotacao(): void
    {
        $this->changeStatus(StatusEdital::votacaoAberta());
    }

    /** votacao_aberta → votacao_encerrada */
    public function encerrarVotacao(): void
    {
        $this->changeStatus(StatusEdital::votacaoEncerrada());
    }

    /** votacao_encerrada → encerrado (final) */
    public function encerrar(): void
    {
        $this->changeStatus(StatusEdital::encerrado());
    }

    /**
     * Aplica a transição validando a máquina de estados.
     *
     * @throws IllegalStateTransition
     */
    private function changeStatus(StatusEdital $target): void
    {
        if (!$this->status->canTransitionTo($target)) {
            throw IllegalStateTransition::betweenEdital($this->status, $target);
        }
        $this->status = $target;
        $this->touch();
    }

    private function mustHaveAllDatesScheduled(): void
    {
        if (
            $this->abertura === null
            || $this->encerramentoInscricoes === null
            || $this->publicacaoHabilitacao === null
            || $this->prazoRecursoInabilitacao === null
            || $this->aberturaVotacao === null
            || $this->encerramentoVotacao === null
            || $this->publicacaoResultado === null
        ) {
            throw new DomainException('Edital nao pode ser publicado sem programacao completa de datas.');
        }
    }

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable('now');
    }

    /**
     * Garante a ordem cronológica estrita entre as 7 datas do fluxo CCDEM.
     *
     * Como podem ser nulas (rascunho), só comparamos pares quando ambos
     * estão definidos. Em transições terminais ({@see publicar()}) exigimos
     * todas via {@see mustHaveAllDatesScheduled()}.
     *
     * @throws DomainException
     */
    private static function guardCronologia(
        ?DateTimeImmutable $abertura,
        ?DateTimeImmutable $encerramentoInscricoes,
        ?DateTimeImmutable $publicacaoHabilitacao,
        ?DateTimeImmutable $prazoRecursoInabilitacao,
        ?DateTimeImmutable $aberturaVotacao,
        ?DateTimeImmutable $encerramentoVotacao,
        ?DateTimeImmutable $publicacaoResultado
    ): void {
        $sequence = [
            'abertura'                     => $abertura,
            'encerramento_inscricoes'      => $encerramentoInscricoes,
            'publicacao_habilitacao'       => $publicacaoHabilitacao,
            'prazo_recurso_inabilitacao'   => $prazoRecursoInabilitacao,
            'abertura_votacao'             => $aberturaVotacao,
            'encerramento_votacao'         => $encerramentoVotacao,
            'publicacao_resultado'         => $publicacaoResultado,
        ];

        $previousLabel = null;
        $previousValue = null;
        foreach ($sequence as $label => $value) {
            if ($value === null) {
                continue;
            }
            if ($previousValue !== null && $value <= $previousValue) {
                throw new DomainException(sprintf(
                    'Cronologia invalida: %s (%s) deve ser estritamente posterior a %s (%s).',
                    $label,
                    $value->format('c'),
                    (string) $previousLabel,
                    $previousValue->format('c')
                ));
            }
            $previousLabel = $label;
            $previousValue = $value;
        }
    }
}
