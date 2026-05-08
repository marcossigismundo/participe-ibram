<?php
/**
 * Entidade Análise — espelha `wp_pi_analises` (decisão administrativa de cadastro).
 *
 * @package Ibram\ParticipeIbram\Domain\Analise
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Analise;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

/**
 * Decisão de análise (deferimento ou indeferimento) de um cadastro de agente.
 *
 * Cada decisão administrativa relevante (TD-05) gera UM registro de análise,
 * contendo o parecer do analista, o ator que decidiu e — após publicação no
 * site Ibram (Art. 8º Portaria 3230/2024) — a URL e o hash de evidência.
 *
 * Imutável quanto à decisão; permite apenas marcar `publicada` posteriormente
 * (cron / job manual de publicação).
 */
final class Analise
{
    public const DECISAO_DEFERIMENTO   = 'deferimento';
    public const DECISAO_INDEFERIMENTO = 'indeferimento';

    /** @var array<int,string> */
    private const DECISOES_VALIDAS = [
        self::DECISAO_DEFERIMENTO,
        self::DECISAO_INDEFERIMENTO,
    ];

    private ?int $id;
    private int $agenteId;
    private int $analistaId;
    private string $decisao;
    private string $parecerMd;
    private ?string $fundamentacaoMd;
    private DateTimeImmutable $decididoEm;
    private ?DateTimeImmutable $publicadoEm;
    private ?string $urlPublicacao;
    private ?string $hashPublicacao;

    /**
     * @throws InvalidArgumentException Quando invariantes básicos falham.
     */
    public function __construct(
        ?int $id,
        int $agenteId,
        int $analistaId,
        string $decisao,
        string $parecerMd,
        ?string $fundamentacaoMd,
        DateTimeImmutable $decididoEm,
        ?DateTimeImmutable $publicadoEm = null,
        ?string $urlPublicacao = null,
        ?string $hashPublicacao = null
    ) {
        if ($id !== null && $id <= 0) {
            throw new InvalidArgumentException('Analise.id deve ser positivo quando informado.');
        }
        if ($agenteId <= 0) {
            throw new InvalidArgumentException('Analise.agenteId deve ser positivo.');
        }
        if ($analistaId <= 0) {
            throw new InvalidArgumentException('Analise.analistaId deve ser positivo.');
        }
        $decisaoNorm = strtolower(trim($decisao));
        if (!in_array($decisaoNorm, self::DECISOES_VALIDAS, true)) {
            throw new InvalidArgumentException(sprintf(
                'Analise.decisao invalida: "%s". Esperado %s.',
                $decisao,
                implode(', ', self::DECISOES_VALIDAS)
            ));
        }
        $parecer = trim($parecerMd);
        if ($parecer === '') {
            throw new InvalidArgumentException('Analise.parecerMd nao pode ser vazio.');
        }
        if ($decisaoNorm === self::DECISAO_INDEFERIMENTO) {
            $fund = $fundamentacaoMd !== null ? trim($fundamentacaoMd) : '';
            if ($fund === '') {
                throw new InvalidArgumentException(
                    'Analise: indeferimento exige fundamentacaoMd (Art. 7 Portaria 3230).'
                );
            }
            $fundamentacaoMd = $fund;
        } else {
            $fundamentacaoMd = $fundamentacaoMd !== null ? trim($fundamentacaoMd) : null;
            if ($fundamentacaoMd === '') {
                $fundamentacaoMd = null;
            }
        }
        if ($publicadoEm !== null) {
            if ($urlPublicacao === null || trim($urlPublicacao) === '') {
                throw new InvalidArgumentException(
                    'Analise: publicadoEm requer urlPublicacao.'
                );
            }
            if ($hashPublicacao === null || trim($hashPublicacao) === '') {
                throw new InvalidArgumentException(
                    'Analise: publicadoEm requer hashPublicacao.'
                );
            }
        }

        $this->id              = $id;
        $this->agenteId        = $agenteId;
        $this->analistaId      = $analistaId;
        $this->decisao         = $decisaoNorm;
        $this->parecerMd       = $parecer;
        $this->fundamentacaoMd = $fundamentacaoMd;
        $this->decididoEm      = $decididoEm;
        $this->publicadoEm     = $publicadoEm;
        $this->urlPublicacao   = $urlPublicacao !== null ? trim($urlPublicacao) : null;
        $this->hashPublicacao  = $hashPublicacao !== null ? trim($hashPublicacao) : null;
    }

    /**
     * Construtor de conveniência para deferimento.
     */
    public static function deferir(int $agenteId, int $analistaId, string $parecerMd, ?DateTimeImmutable $now = null): self
    {
        return new self(
            null,
            $agenteId,
            $analistaId,
            self::DECISAO_DEFERIMENTO,
            $parecerMd,
            null,
            $now ?? new DateTimeImmutable('now')
        );
    }

    /**
     * Construtor de conveniência para indeferimento.
     */
    public static function indeferir(
        int $agenteId,
        int $analistaId,
        string $parecerMd,
        string $fundamentacaoMd,
        ?DateTimeImmutable $now = null
    ): self {
        return new self(
            null,
            $agenteId,
            $analistaId,
            self::DECISAO_INDEFERIMENTO,
            $parecerMd,
            $fundamentacaoMd,
            $now ?? new DateTimeImmutable('now')
        );
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function agenteId(): int
    {
        return $this->agenteId;
    }

    public function analistaId(): int
    {
        return $this->analistaId;
    }

    public function decisao(): string
    {
        return $this->decisao;
    }

    public function parecerMd(): string
    {
        return $this->parecerMd;
    }

    public function fundamentacaoMd(): ?string
    {
        return $this->fundamentacaoMd;
    }

    public function decididoEm(): DateTimeImmutable
    {
        return $this->decididoEm;
    }

    public function publicadoEm(): ?DateTimeImmutable
    {
        return $this->publicadoEm;
    }

    public function urlPublicacao(): ?string
    {
        return $this->urlPublicacao;
    }

    public function hashPublicacao(): ?string
    {
        return $this->hashPublicacao;
    }

    public function isDeferimento(): bool
    {
        return $this->decisao === self::DECISAO_DEFERIMENTO;
    }

    public function isIndeferimento(): bool
    {
        return $this->decisao === self::DECISAO_INDEFERIMENTO;
    }

    public function isPublicada(): bool
    {
        return $this->publicadoEm !== null;
    }

    /**
     * Atribui id após primeira persistência.
     *
     * @throws InvalidArgumentException Quando id já estiver atribuído.
     */
    public function withId(int $id): self
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Analise.withId: id deve ser positivo.');
        }
        $clone     = clone $this;
        $clone->id = $id;

        return $clone;
    }

    /**
     * Marca a análise como publicada no site Ibram (Art. 8º Portaria 3230).
     *
     * Devolve nova instância imutável (preserva semântica de domínio).
     *
     * @throws DomainException Quando já está publicada.
     */
    public function marcarPublicada(string $url, string $hash, DateTimeImmutable $em): self
    {
        if ($this->isPublicada()) {
            throw new DomainException('Analise ja esta publicada.');
        }
        $url  = trim($url);
        $hash = trim($hash);
        if ($url === '') {
            throw new InvalidArgumentException('Analise.marcarPublicada: url obrigatorio.');
        }
        if ($hash === '') {
            throw new InvalidArgumentException('Analise.marcarPublicada: hash obrigatorio.');
        }

        return new self(
            $this->id,
            $this->agenteId,
            $this->analistaId,
            $this->decisao,
            $this->parecerMd,
            $this->fundamentacaoMd,
            $this->decididoEm,
            $em,
            $url,
            $hash
        );
    }
}
