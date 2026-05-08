<?php
/**
 * Exceção de violação de máquina de estados (edital ou inscrição).
 *
 * @package Ibram\ParticipeIbram\Domain\Edital
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Edital;

use DomainException;

/**
 * Lançada quando uma transição de status proibida pela máquina de estados é
 * tentada — em editais ou inscrições. Renomeie via fábricas estáticas para
 * preservar a mensagem padrão.
 */
final class IllegalStateTransition extends DomainException
{
    public static function betweenEdital(StatusEdital $from, StatusEdital $to): self
    {
        return new self(sprintf(
            'Transicao de StatusEdital proibida: %s -> %s.',
            $from->value(),
            $to->value()
        ));
    }

    public static function betweenInscricao(StatusInscricao $from, StatusInscricao $to): self
    {
        return new self(sprintf(
            'Transicao de StatusInscricao proibida: %s -> %s.',
            $from->value(),
            $to->value()
        ));
    }
}
