<?php
/**
 * Entidade Recurso — espelha `wp_pi_recursos` (Portaria 3230 Arts. 7º e 8º).
 *
 * @package Ibram\ParticipeIbram\Domain\Analise
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Analise;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

/**
 * Recurso protocolado contra indeferimento de cadastro.
 *
 *  - Fase `retratacao`: recurso vai primeiro para o analista (reconsiderar/manter).
 *  - Fase `presidencia`: caso mantido, segue para a presidência (deferir/indeferir final).
 *
 * Prazos (Art. 7º + Art. 8º Portaria 3230/2024): 10 dias contínuos contados a
 * partir da publicação da decisão indeferitória. O cálculo é feito no caso de
 * uso ({@see \Ibram\ParticipeIbram\Application\Cadastro\ProtocolarRecursoHandler})
 * e os instantes resultantes são gravados em `prazoInicio`/`prazoFim`.
 */
final class Recurso
{
    public const FASE_RETRATACAO  = 'retratacao';
    public const FASE_PRESIDENCIA = 'presidencia';

    public const DECISAO_RECONSIDERAR = 'reconsiderar';
    public const DECISAO_MANTER       = 'manter';
    public const DECISAO_DEFERIR      = 'deferir';
    public const DECISAO_INDEFERIR    = 'indeferir';

    /** @var array<int,string> */
    private const FASES_VALIDAS = [self::FASE_RETRATACAO, self::FASE_PRESIDENCIA];

    /** @var array<int,string> */
    private const DECISOES_RETRATACAO = [self::DECISAO_RECONSIDERAR, self::DECISAO_MANTER];

    /** @var array<int,string> */
    private const DECISOES_PRESIDENCIA = [self::DECISAO_DEFERIR, self::DECISAO_INDEFERIR];

    private ?int $id;
    private int $analiseId;
    private string $fase;
    private int $recorrenteId;
    private string $fundamentacaoMd;
    private DateTimeImmutable $protocoladoEm;
    private DateTimeImmutable $prazoInicio;
    private DateTimeImmutable $prazoFim;
    private ?string $decisao;
    private ?int $decisorId;
    private ?string $decisaoMd;
    private ?DateTimeImmutable $decididoEm;
    private ?DateTimeImmutable $publicadoEm;

    /**
     * @throws InvalidArgumentException Quando algum invariante falha.
     */
    public function __construct(
        ?int $id,
        int $analiseId,
        string $fase,
        int $recorrenteId,
        string $fundamentacaoMd,
        DateTimeImmutable $protocoladoEm,
        DateTimeImmutable $prazoInicio,
        DateTimeImmutable $prazoFim,
        ?string $decisao = null,
        ?int $decisorId = null,
        ?string $decisaoMd = null,
        ?DateTimeImmutable $decididoEm = null,
        ?DateTimeImmutable $publicadoEm = null
    ) {
        if ($id !== null && $id <= 0) {
            throw new InvalidArgumentException('Recurso.id deve ser positivo quando informado.');
        }
        if ($analiseId <= 0) {
            throw new InvalidArgumentException('Recurso.analiseId deve ser positivo.');
        }
        $faseNorm = strtolower(trim($fase));
        if (!in_array($faseNorm, self::FASES_VALIDAS, true)) {
            throw new InvalidArgumentException(sprintf(
                'Recurso.fase invalida: "%s". Esperado %s.',
                $fase,
                implode(', ', self::FASES_VALIDAS)
            ));
        }
        if ($recorrenteId <= 0) {
            throw new InvalidArgumentException('Recurso.recorrenteId deve ser positivo.');
        }
        $fund = trim($fundamentacaoMd);
        if ($fund === '') {
            throw new InvalidArgumentException('Recurso.fundamentacaoMd nao pode ser vazia.');
        }
        if ($prazoFim < $prazoInicio) {
            throw new InvalidArgumentException('Recurso.prazoFim nao pode ser anterior a prazoInicio.');
        }
        if ($decisao !== null) {
            $decisao = self::guardDecisaoForFase($decisao, $faseNorm);
        }
        if ($decisorId !== null && $decisorId <= 0) {
            throw new InvalidArgumentException('Recurso.decisorId deve ser positivo quando informado.');
        }

        $this->id              = $id;
        $this->analiseId       = $analiseId;
        $this->fase            = $faseNorm;
        $this->recorrenteId    = $recorrenteId;
        $this->fundamentacaoMd = $fund;
        $this->protocoladoEm   = $protocoladoEm;
        $this->prazoInicio     = $prazoInicio;
        $this->prazoFim        = $prazoFim;
        $this->decisao         = $decisao;
        $this->decisorId       = $decisorId;
        $this->decisaoMd       = $decisaoMd !== null && trim($decisaoMd) !== '' ? trim($decisaoMd) : null;
        $this->decididoEm      = $decididoEm;
        $this->publicadoEm     = $publicadoEm;
    }

    /**
     * Construtor para um recurso recém-protocolado.
     */
    public static function protocolar(
        int $analiseId,
        string $fase,
        int $recorrenteId,
        string $fundamentacaoMd,
        DateTimeImmutable $protocoladoEm,
        DateTimeImmutable $prazoInicio,
        DateTimeImmutable $prazoFim
    ): self {
        return new self(
            null,
            $analiseId,
            $fase,
            $recorrenteId,
            $fundamentacaoMd,
            $protocoladoEm,
            $prazoInicio,
            $prazoFim
        );
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function analiseId(): int
    {
        return $this->analiseId;
    }

    public function fase(): string
    {
        return $this->fase;
    }

    public function recorrenteId(): int
    {
        return $this->recorrenteId;
    }

    public function fundamentacaoMd(): string
    {
        return $this->fundamentacaoMd;
    }

    public function protocoladoEm(): DateTimeImmutable
    {
        return $this->protocoladoEm;
    }

    public function prazoInicio(): DateTimeImmutable
    {
        return $this->prazoInicio;
    }

    public function prazoFim(): DateTimeImmutable
    {
        return $this->prazoFim;
    }

    public function decisao(): ?string
    {
        return $this->decisao;
    }

    public function decisorId(): ?int
    {
        return $this->decisorId;
    }

    public function decisaoMd(): ?string
    {
        return $this->decisaoMd;
    }

    public function decididoEm(): ?DateTimeImmutable
    {
        return $this->decididoEm;
    }

    public function publicadoEm(): ?DateTimeImmutable
    {
        return $this->publicadoEm;
    }

    public function isDecidido(): bool
    {
        return $this->decisao !== null;
    }

    public function isFaseRetratacao(): bool
    {
        return $this->fase === self::FASE_RETRATACAO;
    }

    public function isFasePresidencia(): bool
    {
        return $this->fase === self::FASE_PRESIDENCIA;
    }

    /**
     * Indica se o prazo de protocolo expirou (now > prazoFim).
     */
    public function prazoExpirado(?DateTimeImmutable $now = null): bool
    {
        $now = $now ?? new DateTimeImmutable('now');

        return $now > $this->prazoFim;
    }

    public function withId(int $id): self
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Recurso.withId: id deve ser positivo.');
        }
        $clone     = clone $this;
        $clone->id = $id;

        return $clone;
    }

    /**
     * Registra a decisão tomada pelo decisor (analista para fase retratação;
     * presidente para fase presidência).
     *
     * @throws DomainException        Quando o recurso já foi decidido.
     * @throws InvalidArgumentException Quando a decisão é inválida para a fase.
     */
    public function decidir(
        string $decisao,
        int $decisorId,
        string $decisaoMd,
        DateTimeImmutable $em
    ): void {
        if ($this->isDecidido()) {
            throw new DomainException('Recurso ja foi decidido.');
        }
        if ($decisorId <= 0) {
            throw new InvalidArgumentException('Recurso.decidir: decisorId invalido.');
        }
        $md = trim($decisaoMd);
        if ($md === '') {
            throw new InvalidArgumentException('Recurso.decidir: decisaoMd obrigatorio.');
        }

        $this->decisao    = self::guardDecisaoForFase($decisao, $this->fase);
        $this->decisorId  = $decisorId;
        $this->decisaoMd  = $md;
        $this->decididoEm = $em;
    }

    public function marcarPublicado(DateTimeImmutable $em): void
    {
        if (!$this->isDecidido()) {
            throw new DomainException('Recurso ainda nao foi decidido.');
        }
        $this->publicadoEm = $em;
    }

    /**
     * @throws InvalidArgumentException
     */
    private static function guardDecisaoForFase(string $decisao, string $fase): string
    {
        $norm = strtolower(trim($decisao));
        $allowed = $fase === self::FASE_RETRATACAO ? self::DECISOES_RETRATACAO : self::DECISOES_PRESIDENCIA;
        if (!in_array($norm, $allowed, true)) {
            throw new InvalidArgumentException(sprintf(
                'Recurso.decisao "%s" invalida para fase "%s". Esperado %s.',
                $decisao,
                $fase,
                implode(', ', $allowed)
            ));
        }

        return $norm;
    }
}
