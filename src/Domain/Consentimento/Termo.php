<?php
/**
 * Termo (versão da política de privacidade) — entidade de domínio.
 *
 * @package Ibram\ParticipeIbram\Domain\Consentimento
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Consentimento;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Representa um registro da tabela `wp_pi_termos`.
 *
 * Cada versão do termo é imutável após publicação. O hash SHA-256 do conteúdo
 * é a "prova de versão" referenciada pelos consentimentos.
 *
 * @see SCHEMA.md §6 wp_pi_termos
 * @see R2-lgpd.md §3.2
 */
final class Termo
{
    private ?int $id;
    private string $versao;
    private string $conteudoMd;
    private string $hashConteudo;
    private DateTimeImmutable $ativoEm;
    private ?DateTimeImmutable $inativoEm;
    private int $publicadoPor;

    /**
     * @throws InvalidArgumentException Quando versão / conteúdo / hash são inválidos.
     */
    public function __construct(
        ?int $id,
        string $versao,
        string $conteudoMd,
        string $hashConteudo,
        DateTimeImmutable $ativoEm,
        ?DateTimeImmutable $inativoEm,
        int $publicadoPor
    ) {
        $versao = trim($versao);
        if ($versao === '') {
            throw new InvalidArgumentException('Versão do termo não pode ser vazia.');
        }
        if (mb_strlen($versao) > 20) {
            throw new InvalidArgumentException('Versão do termo excede 20 caracteres.');
        }
        if (trim($conteudoMd) === '') {
            throw new InvalidArgumentException('Conteúdo do termo não pode ser vazio.');
        }
        if (!preg_match('/^[a-f0-9]{64}$/', $hashConteudo)) {
            throw new InvalidArgumentException('Hash do conteúdo deve ser SHA-256 hex (64 chars).');
        }
        if ($publicadoPor < 1) {
            throw new InvalidArgumentException('publicadoPor deve referenciar um usuário válido.');
        }
        if ($inativoEm !== null && $inativoEm < $ativoEm) {
            throw new InvalidArgumentException('inativoEm não pode ser anterior a ativoEm.');
        }

        $this->id           = $id;
        $this->versao       = $versao;
        $this->conteudoMd   = $conteudoMd;
        $this->hashConteudo = $hashConteudo;
        $this->ativoEm      = $ativoEm;
        $this->inativoEm    = $inativoEm;
        $this->publicadoPor = $publicadoPor;
    }

    /**
     * Factory: cria um novo termo já com hash SHA-256 calculado e ativo agora.
     *
     * O texto markdown é escapado apenas em camadas de apresentação (não aqui).
     */
    public static function create(string $versao, string $conteudoMd, int $publicadoPor): self
    {
        $hash = hash('sha256', $conteudoMd);

        return new self(
            null,
            $versao,
            $conteudoMd,
            $hash,
            new DateTimeImmutable('now'),
            null,
            $publicadoPor
        );
    }

    /**
     * Reidratação a partir do banco (preserva id e timestamps).
     */
    public static function fromState(
        int $id,
        string $versao,
        string $conteudoMd,
        string $hashConteudo,
        DateTimeImmutable $ativoEm,
        ?DateTimeImmutable $inativoEm,
        int $publicadoPor
    ): self {
        return new self($id, $versao, $conteudoMd, $hashConteudo, $ativoEm, $inativoEm, $publicadoPor);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function versao(): string
    {
        return $this->versao;
    }

    public function conteudoMd(): string
    {
        return $this->conteudoMd;
    }

    public function hashConteudo(): string
    {
        return $this->hashConteudo;
    }

    public function ativoEm(): DateTimeImmutable
    {
        return $this->ativoEm;
    }

    public function inativoEm(): ?DateTimeImmutable
    {
        return $this->inativoEm;
    }

    public function publicadoPor(): int
    {
        return $this->publicadoPor;
    }

    /**
     * Regra: ativo se `now` ∈ [ativoEm, inativoEm) (ou inativoEm == null).
     */
    public function isAtivo(?DateTimeImmutable $now = null): bool
    {
        $now = $now ?? new DateTimeImmutable('now');
        if ($now < $this->ativoEm) {
            return false;
        }
        if ($this->inativoEm !== null && $now >= $this->inativoEm) {
            return false;
        }

        return true;
    }

    /**
     * Marca o termo como inativo a partir de uma data dada (retorna nova instância).
     */
    public function withInativoEm(DateTimeImmutable $inativoEm): self
    {
        return new self(
            $this->id,
            $this->versao,
            $this->conteudoMd,
            $this->hashConteudo,
            $this->ativoEm,
            $inativoEm,
            $this->publicadoPor
        );
    }

    /**
     * Útil para repositórios após INSERT.
     */
    public function withId(int $id): self
    {
        return new self(
            $id,
            $this->versao,
            $this->conteudoMd,
            $this->hashConteudo,
            $this->ativoEm,
            $this->inativoEm,
            $this->publicadoPor
        );
    }
}
