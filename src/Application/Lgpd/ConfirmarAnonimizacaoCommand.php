<?php
/**
 * Command DTO — confirmar anonimização (passo 2 da dupla confirmação).
 *
 * @package Ibram\ParticipeIbram\Application\Lgpd
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Lgpd;

use InvalidArgumentException;

/**
 * Confirma uma solicitação de anonimização através do token enviado por email.
 *
 * Como o token já carrega `solicitacaoId` e `agenteId` assinados, o command
 * recebe apenas o token (e o ator, para auditoria — pode ser o próprio usuário
 * ou um DPO operando em nome do titular).
 */
final class ConfirmarAnonimizacaoCommand
{
    private string $token;
    private ?int $atorUserId;
    private ?string $ipHash;

    public function __construct(string $token, ?int $atorUserId, ?string $ipHash)
    {
        $token = trim($token);
        if ($token === '') {
            throw new InvalidArgumentException('token não pode ser vazio.');
        }
        if ($atorUserId !== null && $atorUserId < 1) {
            throw new InvalidArgumentException('atorUserId, se informado, deve ser >= 1.');
        }
        if ($ipHash !== null && !preg_match('/^[a-f0-9]{64}$/', $ipHash)) {
            throw new InvalidArgumentException('ipHash deve ser HMAC-SHA256 hex (64) ou null.');
        }

        $this->token      = $token;
        $this->atorUserId = $atorUserId;
        $this->ipHash     = $ipHash;
    }

    public function token(): string
    {
        return $this->token;
    }

    public function atorUserId(): ?int
    {
        return $this->atorUserId;
    }

    public function ipHash(): ?string
    {
        return $this->ipHash;
    }
}
