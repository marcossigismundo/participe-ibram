<?php
/**
 * Exceção: agente não elegível como eleitor.
 *
 * @package Ibram\ParticipeIbram\Domain\Votacao
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Votacao;

use DomainException;

/**
 * Lançada quando o agente não está deferido (status_cadastro inválido) ou seu
 * tipo (PF/OR/SM) não é admitido pela categoria em que tenta votar.
 */
final class EleitorInelegivel extends DomainException
{
    public static function naoDeferido(int $agenteId): self
    {
        return new self(sprintf(
            'Agente %d nao esta deferido — inelegivel para votar.',
            $agenteId
        ));
    }

    public static function tipoNaoAdmitidoNaCategoria(int $categoriaId, string $tipoAgente): self
    {
        return new self(sprintf(
            'Categoria %d nao admite o tipo de agente "%s".',
            $categoriaId,
            $tipoAgente
        ));
    }

    public static function categoriaForaDoEdital(int $votacaoId, int $categoriaId): self
    {
        return new self(sprintf(
            'Categoria %d nao pertence ao edital da votacao %d.',
            $categoriaId,
            $votacaoId
        ));
    }
}
