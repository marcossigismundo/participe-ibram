<?php
/**
 * Exceção: voto duplicado (UNIQUE(votacao_id, categoria_id, eleitor_hash)).
 *
 * @package Ibram\ParticipeIbram\Domain\Votacao
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Votacao;

use DomainException;

/**
 * Lançada quando o mesmo eleitor (identificado pelo `eleitor_hash` HMAC) tenta
 * votar duas vezes na mesma combinação votacao_id+categoria_id. A constraint
 * UNIQUE no banco protege a integridade — esta exception modela a situação no
 * domínio para que a camada de aplicação responda com mensagem amigável.
 *
 * Não armazena o `eleitor_hash` na mensagem — auditoria sem rastreabilidade.
 */
final class VotoDuplicado extends DomainException
{
    public static function paraVotacaoCategoria(int $votacaoId, int $categoriaId): self
    {
        return new self(sprintf(
            'Voto duplicado detectado para votacao_id=%d, categoria_id=%d.',
            $votacaoId,
            $categoriaId
        ));
    }
}
