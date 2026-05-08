<?php
/**
 * Exceção: tentativa de criar/persistir organização com CNPJ já cadastrado.
 *
 * @package Ibram\ParticipeIbram\Domain\Agente
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Agente;

use DomainException;

/**
 * Detectada via colisão em `wp_pi_agentes_or.cnpj_hash` (HMAC).
 */
final class DuplicateCnpjException extends DomainException
{
    public static function create(): self
    {
        return new self('Ja existe um cadastro com este CNPJ.');
    }
}
