<?php
/**
 * Exceção: tentativa de criar/persistir agente PF com CPF já cadastrado.
 *
 * @package Ibram\ParticipeIbram\Domain\Agente
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Agente;

use DomainException;

/**
 * Detectada via colisão em `wp_pi_agentes_pf.cpf_hash` (HMAC) — nunca pelo
 * cipher direto. Mensagem genérica para evitar enumeração (R5 lição #18).
 */
final class DuplicateCpfException extends DomainException
{
    public static function create(): self
    {
        return new self('Ja existe um cadastro com este CPF.');
    }
}
