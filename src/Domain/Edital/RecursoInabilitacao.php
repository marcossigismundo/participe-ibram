<?php
/**
 * Entidade RecursoInabilitacao — espelha `wp_pi_recursos_inabilitacao` (SCHEMA §4).
 *
 * @package Ibram\ParticipeIbram\Domain\Edital
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Edital;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

/**
 * Recurso protocolado contra uma decisão de inabilitação em inscrição.
 *
 * Decisão é uma string-enum: `deferir` (recurso provido → inscrição habilitada)
 * ou `manter` (recurso negado → inabilitação confirmada).
 */
final class RecursoInabilitacao
{
    public const DECISAO_DEFERIR = 'deferir';
    public const DECISAO_MANTER  = 'manter';

    private const DECISOES_VALIDAS = [self::DECISAO_DEFERIR, self::DECISAO_MANTER];

    private ?int $id;
    private int $inscricaoId;
    private string $fundamentacaoMd;
    private DateTimeImmutable $protocoladoEm;
    private ?string $decisao;
    private ?int $decisorId;
    private ?string $decisaoMd;
    private ?DateTimeImmutable $decididoEm;

    public function __construct(
        ?int $id,
        int $inscricaoId,
        string $fundamentacaoMd,
        DateTimeImmutable $protocoladoEm,
        ?string $decisao = null,
        ?int $decisorId = null,
        ?string $decisaoMd = null,
        ?DateTimeImmutable $decididoEm = null
    ) {
        if ($id !== null && $id <= 0) {
            throw new InvalidArgumentException('RecursoInabilitacao.id deve ser positivo quando informado.');
        }
        if ($inscricaoId <= 0) {
            throw new InvalidArgumentException('RecursoInabilitacao.inscricaoId deve ser positivo.');
        }
        $fund = trim($fundamentacaoMd);
        if ($fund === '') {
            throw new InvalidArgumentException('RecursoInabilitacao.fundamentacaoMd nao pode ser vazia.');
        }
        if ($decisao !== null) {
            $decisao = strtolower(trim($decisao));
            if (!in_array($decisao, self::DECISOES_VALIDAS, true)) {
                throw new InvalidArgumentException(sprintf(
                    'RecursoInabilitacao.decisao invalida: "%s". Esperado %s.',
                    $decisao,
                    implode(', ', self::DECISOES_VALIDAS)
                ));
            }
        }
        if ($decisorId !== null && $decisorId <= 0) {
            throw new InvalidArgumentException('RecursoInabilitacao.decisorId deve ser positivo quando informado.');
        }

        $this->id              = $id;
        $this->inscricaoId     = $inscricaoId;
        $this->fundamentacaoMd = $fund;
        $this->protocoladoEm   = $protocoladoEm;
        $this->decisao         = $decisao;
        $this->decisorId       = $decisorId;
        $this->decisaoMd       = $decisaoMd !== null ? $decisaoMd : null;
        $this->decididoEm      = $decididoEm;
    }

    /**
     * Construtor de conveniência para um recurso recém-protocolado.
     */
    public static function protocolar(int $inscricaoId, string $fundamentacaoMd): self
    {
        return new self(
            null,
            $inscricaoId,
            $fundamentacaoMd,
            new DateTimeImmutable('now')
        );
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function inscricaoId(): int
    {
        return $this->inscricaoId;
    }

    public function fundamentacaoMd(): string
    {
        return $this->fundamentacaoMd;
    }

    public function protocoladoEm(): DateTimeImmutable
    {
        return $this->protocoladoEm;
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

    public function isDecidido(): bool
    {
        return $this->decisao !== null;
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

    /**
     * Registra a decisão do recurso.
     *
     * @param string $decisao   `deferir` ou `manter`.
     * @param int    $decisorId WP user id (gestor de edital com `pi_decidir_habilitacao`).
     * @param string $decisaoMd Fundamentação Markdown da decisão (obrigatória).
     *
     * @throws DomainException Quando o recurso já foi decidido.
     */
    public function decidir(string $decisao, int $decisorId, string $decisaoMd): void
    {
        if ($this->isDecidido()) {
            throw new DomainException('RecursoInabilitacao ja foi decidido.');
        }
        $decisaoNorm = strtolower(trim($decisao));
        if (!in_array($decisaoNorm, self::DECISOES_VALIDAS, true)) {
            throw new InvalidArgumentException(sprintf(
                'RecursoInabilitacao.decidir: decisao invalida "%s". Esperado %s.',
                $decisao,
                implode(', ', self::DECISOES_VALIDAS)
            ));
        }
        if ($decisorId <= 0) {
            throw new InvalidArgumentException('RecursoInabilitacao.decidir: decisor invalido.');
        }
        $md = trim($decisaoMd);
        if ($md === '') {
            throw new InvalidArgumentException('RecursoInabilitacao.decidir: decisaoMd obrigatorio.');
        }

        $this->decisao    = $decisaoNorm;
        $this->decisorId  = $decisorId;
        $this->decisaoMd  = $md;
        $this->decididoEm = new DateTimeImmutable('now');
    }

    public function isDeferido(): bool
    {
        return $this->decisao === self::DECISAO_DEFERIR;
    }
}
