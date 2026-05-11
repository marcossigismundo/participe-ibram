<?php
/**
 * Command DTO — solicitar export de dados (portabilidade LGPD Art. 18 V).
 *
 * @package Ibram\ParticipeIbram\Application\Lgpd
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Lgpd;

use InvalidArgumentException;

final class SolicitarExportDadosCommand
{
    private int $agenteId;
    private int $userId;
    private ?string $ipHash;

    public function __construct(int $agenteId, int $userId, ?string $ipHash)
    {
        if ($agenteId < 1) {
            throw new InvalidArgumentException('agenteId deve ser >= 1.');
        }
        if ($userId < 1) {
            throw new InvalidArgumentException('userId deve ser >= 1.');
        }
        if ($ipHash !== null && !preg_match('/^[a-f0-9]{64}$/', $ipHash)) {
            throw new InvalidArgumentException('ipHash deve ser HMAC-SHA256 hex ou null.');
        }
        $this->agenteId = $agenteId;
        $this->userId   = $userId;
        $this->ipHash   = $ipHash;
    }

    public function agenteId(): int
    {
        return $this->agenteId;
    }

    public function userId(): int
    {
        return $this->userId;
    }

    public function ipHash(): ?string
    {
        return $this->ipHash;
    }
}
