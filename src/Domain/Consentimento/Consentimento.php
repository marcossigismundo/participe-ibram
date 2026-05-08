<?php
/**
 * Consentimento — entidade de domínio (registro append-friendly).
 *
 * @package Ibram\ParticipeIbram\Domain\Consentimento
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Consentimento;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

/**
 * Representa uma linha de `wp_pi_consentimentos` (SCHEMA.md §6).
 *
 * Pattern de uso: cada decisão de aceite/negação/revogação produz um novo
 * registro (a tabela é "append-friendly"). O estado vigente para um
 * (agente, finalidade) é dado pelo registro mais recente.
 */
final class Consentimento
{
    private ?int $id;
    private int $agenteId;
    private int $termoId;
    private Finalidade $finalidade;
    private StatusConsentimento $status;
    private ?string $ipHash;
    private ?string $userAgent;
    private DateTimeImmutable $registradoEm;
    private ?DateTimeImmutable $revogadoEm;

    public function __construct(
        ?int $id,
        int $agenteId,
        int $termoId,
        Finalidade $finalidade,
        StatusConsentimento $status,
        ?string $ipHash,
        ?string $userAgent,
        DateTimeImmutable $registradoEm,
        ?DateTimeImmutable $revogadoEm
    ) {
        if ($agenteId < 1) {
            throw new InvalidArgumentException('agenteId deve ser positivo.');
        }
        if ($termoId < 1) {
            throw new InvalidArgumentException('termoId deve ser positivo.');
        }
        if ($ipHash !== null && !preg_match('/^[a-f0-9]{64}$/', $ipHash)) {
            throw new InvalidArgumentException('ipHash deve ser HMAC-SHA256 hex (64 chars) ou null.');
        }
        if ($status->isRevogado() && $revogadoEm === null) {
            throw new InvalidArgumentException('Status revogado exige revogadoEm.');
        }
        if ($revogadoEm !== null && $revogadoEm < $registradoEm) {
            throw new InvalidArgumentException('revogadoEm não pode ser anterior a registradoEm.');
        }

        $this->id           = $id;
        $this->agenteId     = $agenteId;
        $this->termoId      = $termoId;
        $this->finalidade   = $finalidade;
        $this->status       = $status;
        $this->ipHash       = $ipHash;
        $this->userAgent    = $userAgent !== null ? self::truncateUa($userAgent) : null;
        $this->registradoEm = $registradoEm;
        $this->revogadoEm   = $revogadoEm;
    }

    /**
     * Factory para registrar uma decisão (aceito/negado) num momento `now`.
     */
    public static function registrar(
        int $agenteId,
        int $termoId,
        Finalidade $finalidade,
        StatusConsentimento $status,
        ?string $ipHash,
        ?string $userAgent,
        ?DateTimeImmutable $now = null
    ): self {
        if ($status->isRevogado()) {
            throw new InvalidArgumentException('Use revogar() para criar registros de revogação.');
        }
        $now = $now ?? new DateTimeImmutable('now');

        return new self(null, $agenteId, $termoId, $finalidade, $status, $ipHash, $userAgent, $now, null);
    }

    /**
     * Reidratação a partir do banco.
     */
    public static function fromState(
        int $id,
        int $agenteId,
        int $termoId,
        Finalidade $finalidade,
        StatusConsentimento $status,
        ?string $ipHash,
        ?string $userAgent,
        DateTimeImmutable $registradoEm,
        ?DateTimeImmutable $revogadoEm
    ): self {
        return new self($id, $agenteId, $termoId, $finalidade, $status, $ipHash, $userAgent, $registradoEm, $revogadoEm);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function agenteId(): int
    {
        return $this->agenteId;
    }

    public function termoId(): int
    {
        return $this->termoId;
    }

    public function finalidade(): Finalidade
    {
        return $this->finalidade;
    }

    public function status(): StatusConsentimento
    {
        return $this->status;
    }

    public function ipHash(): ?string
    {
        return $this->ipHash;
    }

    public function userAgent(): ?string
    {
        return $this->userAgent;
    }

    public function registradoEm(): DateTimeImmutable
    {
        return $this->registradoEm;
    }

    public function revogadoEm(): ?DateTimeImmutable
    {
        return $this->revogadoEm;
    }

    /**
     * Marca o consentimento como revogado.
     *
     * Em vez de mutar o registro original, a operação muda o estado interno —
     * porém, na camada de aplicação, o pattern recomendado é INSERIR um novo
     * registro com status REVOGADO (preserva trilha probatória).
     *
     * Aqui o método é defensivo: só permite a transição se o status atual for
     * ACEITO. Tenta revogar negado ou já revogado lança {@see DomainException}.
     *
     * @throws DomainException Quando o status atual não permite revogação.
     */
    public function revogar(DateTimeImmutable $em): void
    {
        if ($this->status->isRevogado()) {
            throw new DomainException('Consentimento já está revogado.');
        }
        if ($this->status->isNegado()) {
            throw new DomainException('Consentimento negado não pode ser revogado.');
        }
        if ($em < $this->registradoEm) {
            throw new DomainException('Data de revogação não pode anteceder o registro.');
        }

        $this->status     = StatusConsentimento::revogado();
        $this->revogadoEm = $em;
    }

    public function withId(int $id): self
    {
        return new self(
            $id,
            $this->agenteId,
            $this->termoId,
            $this->finalidade,
            $this->status,
            $this->ipHash,
            $this->userAgent,
            $this->registradoEm,
            $this->revogadoEm
        );
    }

    /**
     * Limita o User-Agent armazenado para não estourar a coluna TEXT em casos
     * absurdos (ataque de payload-grande).
     */
    private static function truncateUa(string $ua): string
    {
        $ua = trim($ua);
        if ($ua === '') {
            return $ua;
        }
        if (strlen($ua) <= 1024) {
            return $ua;
        }

        return substr($ua, 0, 1024);
    }
}
