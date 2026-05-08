<?php
/**
 * Query: verificar elegibilidade de um WP user para votar em uma votação.
 *
 * @package Ibram\ParticipeIbram\Application\Votacao
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Votacao;

use InvalidArgumentException;

/**
 * DTO imutável: "este WP user pode votar nesta votação?".
 *
 * NÃO carrega `agente_id` — o handler resolve internamente via gateway. Isso
 * evita que o caller (ex.: endpoint REST) precise expor essa relação para o
 * cliente.
 */
final class VerificarElegibilidadeQuery
{
    private int $wpUserId;

    private int $votacaoId;

    /**
     * @throws InvalidArgumentException
     */
    public function __construct(int $wpUserId, int $votacaoId)
    {
        if ($wpUserId <= 0) {
            throw new InvalidArgumentException('wpUserId deve ser positivo.');
        }
        if ($votacaoId <= 0) {
            throw new InvalidArgumentException('votacaoId deve ser positivo.');
        }
        $this->wpUserId  = $wpUserId;
        $this->votacaoId = $votacaoId;
    }

    public function wpUserId(): int
    {
        return $this->wpUserId;
    }

    public function votacaoId(): int
    {
        return $this->votacaoId;
    }
}
