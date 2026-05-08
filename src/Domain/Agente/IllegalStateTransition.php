<?php
/**
 * Exceção de violação da máquina de estados do cadastro (TD-05).
 *
 * @package Ibram\ParticipeIbram\Domain\Agente
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Agente;

use DomainException;

/**
 * Lançada quando uma transição de status proibida pela máquina de estados é
 * tentada (ex.: deferir um cadastro ainda em rascunho, indeferir um cadastro
 * já final).
 */
final class IllegalStateTransition extends DomainException
{
    /**
     * Construtor conveniente que monta a mensagem padrão.
     *
     * @param StatusCadastro $from Estado de origem.
     * @param StatusCadastro $to   Estado destino tentado.
     */
    public static function between(StatusCadastro $from, StatusCadastro $to): self
    {
        return new self(sprintf(
            'Transicao de status proibida: %s -> %s.',
            $from->value(),
            $to->value()
        ));
    }
}
