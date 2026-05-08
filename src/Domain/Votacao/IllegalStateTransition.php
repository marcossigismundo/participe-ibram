<?php
/**
 * Exceção de violação da máquina de estados da votação (TD-06).
 *
 * @package Ibram\ParticipeIbram\Domain\Votacao
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Votacao;

use DomainException;

/**
 * Lançada quando uma transição de status proibida pela máquina de estados é
 * tentada (ex.: apurar uma votação ainda aberta, abrir uma já cancelada).
 */
final class IllegalStateTransition extends DomainException
{
    public static function between(StatusVotacao $from, StatusVotacao $to): self
    {
        return new self(sprintf(
            'Transicao de status proibida na votacao: %s -> %s.',
            $from->value(),
            $to->value()
        ));
    }
}
