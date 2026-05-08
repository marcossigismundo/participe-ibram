<?php
/**
 * Exceção: tentativa de votar fora da janela aberta da votação.
 *
 * @package Ibram\ParticipeIbram\Domain\Votacao
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Votacao;

use DomainException;

/**
 * Lançada pelo handler de registro de voto quando a votação não está com status
 * `aberta` ou o timestamp atual está fora da janela [abertura, encerramento].
 */
final class VotacaoNaoAberta extends DomainException
{
    public static function paraVotacao(int $votacaoId, string $statusAtual): self
    {
        return new self(sprintf(
            'Votacao %d nao esta aberta (status atual: %s).',
            $votacaoId,
            $statusAtual
        ));
    }

    public static function foraDaJanela(int $votacaoId): self
    {
        return new self(sprintf(
            'Votacao %d esta fora da janela [abertura, encerramento].',
            $votacaoId
        ));
    }
}
