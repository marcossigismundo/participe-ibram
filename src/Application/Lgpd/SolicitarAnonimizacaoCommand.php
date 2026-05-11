<?php
/**
 * Command DTO — solicitar anonimização (passo 1 da dupla confirmação).
 *
 * @package Ibram\ParticipeIbram\Application\Lgpd
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Lgpd;

use InvalidArgumentException;

/**
 * Inicia o fluxo de anonimização (Art. 18, IV LGPD).
 *
 * O comando NÃO carrega senha em claro: a verificação de re-autenticação é
 * responsabilidade do endpoint REST, ANTES de despachar o comando. O motivo é
 * opcional (até 1000 chars) e é redigido para texto puro.
 */
final class SolicitarAnonimizacaoCommand
{
    public const MOTIVO_MAX_CHARS = 1000;

    private int $agenteId;
    private int $userId;
    private ?string $motivo;
    private ?string $ipHash;
    private ?string $userAgent;

    public function __construct(
        int $agenteId,
        int $userId,
        ?string $motivo,
        ?string $ipHash,
        ?string $userAgent
    ) {
        if ($agenteId < 1) {
            throw new InvalidArgumentException('agenteId deve ser >= 1.');
        }
        if ($userId < 1) {
            throw new InvalidArgumentException('userId deve ser >= 1.');
        }
        if ($ipHash !== null && !preg_match('/^[a-f0-9]{64}$/', $ipHash)) {
            throw new InvalidArgumentException('ipHash deve ser HMAC-SHA256 hex (64 chars) ou null.');
        }
        if ($motivo !== null) {
            $motivo = trim($motivo);
            if ($motivo === '') {
                $motivo = null;
            } elseif (mb_strlen($motivo) > self::MOTIVO_MAX_CHARS) {
                $motivo = mb_substr($motivo, 0, self::MOTIVO_MAX_CHARS);
            }
        }

        $this->agenteId  = $agenteId;
        $this->userId    = $userId;
        $this->motivo    = $motivo;
        $this->ipHash    = $ipHash;
        $this->userAgent = $userAgent !== null ? mb_substr($userAgent, 0, 1024) : null;
    }

    public function agenteId(): int
    {
        return $this->agenteId;
    }

    public function userId(): int
    {
        return $this->userId;
    }

    public function motivo(): ?string
    {
        return $this->motivo;
    }

    public function ipHash(): ?string
    {
        return $this->ipHash;
    }

    public function userAgent(): ?string
    {
        return $this->userAgent;
    }
}
