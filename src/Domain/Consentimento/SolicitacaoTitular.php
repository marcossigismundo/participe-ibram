<?php
/**
 * Solicitação de direito do titular (LGPD Art. 18).
 *
 * @package Ibram\ParticipeIbram\Domain\Consentimento
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Consentimento;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

/**
 * Representa uma linha de `wp_pi_solicitacoes_titular` (SCHEMA.md §6).
 *
 * Implementa o ciclo de vida: aberta → em_atendimento → atendida | recusada.
 * O prazo legal é de 15 dias corridos (Art. 19 LGPD).
 */
final class SolicitacaoTitular
{
    public const TIPO_ACESSO                       = 'acesso';
    public const TIPO_RETIFICACAO                  = 'retificacao';
    public const TIPO_EXCLUSAO                     = 'exclusao';
    public const TIPO_PORTABILIDADE                = 'portabilidade';
    public const TIPO_OPOSICAO                     = 'oposicao';
    public const TIPO_ANONIMIZACAO                 = 'anonimizacao';
    public const TIPO_REVISAO_DECISAO_AUTOMATIZADA = 'revisao_decisao_automatizada';

    public const STATUS_ABERTA         = 'aberta';
    public const STATUS_EM_ATENDIMENTO = 'em_atendimento';
    public const STATUS_ATENDIDA       = 'atendida';
    public const STATUS_RECUSADA       = 'recusada';

    /** Prazo LGPD Art. 19: 15 dias corridos. */
    public const PRAZO_DIAS = 15;

    /** @var array<int,string> */
    private const TIPOS_VALIDOS = [
        self::TIPO_ACESSO,
        self::TIPO_RETIFICACAO,
        self::TIPO_EXCLUSAO,
        self::TIPO_PORTABILIDADE,
        self::TIPO_OPOSICAO,
        self::TIPO_ANONIMIZACAO,
        self::TIPO_REVISAO_DECISAO_AUTOMATIZADA,
    ];

    /** @var array<int,string> */
    private const STATUS_VALIDOS = [
        self::STATUS_ABERTA,
        self::STATUS_EM_ATENDIMENTO,
        self::STATUS_ATENDIDA,
        self::STATUS_RECUSADA,
    ];

    private ?int $id;
    private int $agenteId;
    private string $tipo;
    private ?string $detalhesMd;
    private string $status;
    private ?string $respostaMd;
    private DateTimeImmutable $protocoladaEm;
    private ?DateTimeImmutable $atendidaEm;
    private ?int $atendidaPor;

    public function __construct(
        ?int $id,
        int $agenteId,
        string $tipo,
        ?string $detalhesMd,
        string $status,
        ?string $respostaMd,
        DateTimeImmutable $protocoladaEm,
        ?DateTimeImmutable $atendidaEm,
        ?int $atendidaPor
    ) {
        if ($agenteId < 1) {
            throw new InvalidArgumentException('agenteId deve ser positivo.');
        }
        if (!in_array($tipo, self::TIPOS_VALIDOS, true)) {
            throw new InvalidArgumentException(sprintf(
                'Tipo de solicitação invalido: "%s".',
                $tipo
            ));
        }
        if (!in_array($status, self::STATUS_VALIDOS, true)) {
            throw new InvalidArgumentException(sprintf(
                'Status invalido: "%s".',
                $status
            ));
        }
        if ($atendidaPor !== null && $atendidaPor < 1) {
            throw new InvalidArgumentException('atendidaPor deve referenciar usuário válido.');
        }
        if ($atendidaEm !== null && $atendidaEm < $protocoladaEm) {
            throw new InvalidArgumentException('atendidaEm não pode ser anterior a protocoladaEm.');
        }

        $this->id            = $id;
        $this->agenteId      = $agenteId;
        $this->tipo          = $tipo;
        $this->detalhesMd    = $detalhesMd !== null ? trim($detalhesMd) : null;
        $this->status        = $status;
        $this->respostaMd    = $respostaMd !== null ? trim($respostaMd) : null;
        $this->protocoladaEm = $protocoladaEm;
        $this->atendidaEm    = $atendidaEm;
        $this->atendidaPor   = $atendidaPor;
    }

    /**
     * Factory para protocolar uma nova solicitação (status = aberta).
     */
    public static function protocolar(
        int $agenteId,
        string $tipo,
        ?string $detalhesMd,
        ?DateTimeImmutable $now = null
    ): self {
        $now = $now ?? new DateTimeImmutable('now');

        return new self(
            null,
            $agenteId,
            $tipo,
            $detalhesMd,
            self::STATUS_ABERTA,
            null,
            $now,
            null,
            null
        );
    }

    public static function fromState(
        int $id,
        int $agenteId,
        string $tipo,
        ?string $detalhesMd,
        string $status,
        ?string $respostaMd,
        DateTimeImmutable $protocoladaEm,
        ?DateTimeImmutable $atendidaEm,
        ?int $atendidaPor
    ): self {
        return new self(
            $id,
            $agenteId,
            $tipo,
            $detalhesMd,
            $status,
            $respostaMd,
            $protocoladaEm,
            $atendidaEm,
            $atendidaPor
        );
    }

    /**
     * @return array<int,string>
     */
    public static function tiposValidos(): array
    {
        return self::TIPOS_VALIDOS;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function agenteId(): int
    {
        return $this->agenteId;
    }

    public function tipo(): string
    {
        return $this->tipo;
    }

    public function detalhesMd(): ?string
    {
        return $this->detalhesMd;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function respostaMd(): ?string
    {
        return $this->respostaMd;
    }

    public function protocoladaEm(): DateTimeImmutable
    {
        return $this->protocoladaEm;
    }

    public function atendidaEm(): ?DateTimeImmutable
    {
        return $this->atendidaEm;
    }

    public function atendidaPor(): ?int
    {
        return $this->atendidaPor;
    }

    /**
     * DPO assume a solicitação (aberta → em_atendimento).
     *
     * @throws DomainException Quando a solicitação não está aberta.
     */
    public function assumir(int $atorId): void
    {
        if ($atorId < 1) {
            throw new InvalidArgumentException('atorId deve ser positivo.');
        }
        if ($this->status !== self::STATUS_ABERTA) {
            throw new DomainException(sprintf(
                'Solicitação não pode ser assumida no status "%s".',
                $this->status
            ));
        }
        $this->status = self::STATUS_EM_ATENDIMENTO;
    }

    /**
     * Responde a solicitação (encerrando-a como atendida ou recusada).
     *
     * @param string $resposta  Texto markdown da resposta ao titular.
     * @param int    $atorId    DPO que respondeu.
     * @param bool   $atendida  true → STATUS_ATENDIDA; false → STATUS_RECUSADA.
     *
     * @throws DomainException Quando já encerrada.
     */
    public function responder(string $resposta, int $atorId, bool $atendida = true): void
    {
        if ($atorId < 1) {
            throw new InvalidArgumentException('atorId deve ser positivo.');
        }
        $resposta = trim($resposta);
        if ($resposta === '') {
            throw new InvalidArgumentException('Resposta não pode ser vazia.');
        }
        if (in_array($this->status, [self::STATUS_ATENDIDA, self::STATUS_RECUSADA], true)) {
            throw new DomainException(sprintf(
                'Solicitação já está encerrada (%s).',
                $this->status
            ));
        }

        $this->status      = $atendida ? self::STATUS_ATENDIDA : self::STATUS_RECUSADA;
        $this->respostaMd  = $resposta;
        $this->atendidaPor = $atorId;
        $this->atendidaEm  = new DateTimeImmutable('now');
    }

    /**
     * Prazo final (protocoladaEm + 15 dias). Não considera dias úteis: o Art.
     * 19 LGPD fala em "imediato ou em prazo razoável", a ANPD orienta 15 dias
     * corridos por padrão.
     */
    public function prazoFinal(): DateTimeImmutable
    {
        return $this->protocoladaEm->modify('+' . self::PRAZO_DIAS . ' days');
    }

    public function isEncerrada(): bool
    {
        return in_array($this->status, [self::STATUS_ATENDIDA, self::STATUS_RECUSADA], true);
    }

    public function withId(int $id): self
    {
        return new self(
            $id,
            $this->agenteId,
            $this->tipo,
            $this->detalhesMd,
            $this->status,
            $this->respostaMd,
            $this->protocoladaEm,
            $this->atendidaEm,
            $this->atendidaPor
        );
    }
}
