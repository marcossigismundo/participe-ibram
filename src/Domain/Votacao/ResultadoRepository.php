<?php
/**
 * Repositório (interface) para Resultado.
 *
 * @package Ibram\ParticipeIbram\Domain\Votacao
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Votacao;

/**
 * Contrato de persistência de {@see Resultado}.
 */
interface ResultadoRepository
{
    /**
     * Lista todos os resultados de uma votação.
     *
     * @return list<Resultado>
     */
    public function findByVotacao(int $votacaoId): array;

    /**
     * Lista apenas eleitos (eleito = 1) de uma votação.
     *
     * @return list<Resultado>
     */
    public function findEleitos(int $votacaoId): array;

    /**
     * Persiste o conjunto de resultados de uma votação.
     *
     * Implementações DEVEM operar em transação: ou todos os resultados
     * são persistidos, ou nenhum (apuração é atômica).
     *
     * @param int             $votacaoId
     * @param list<Resultado> $resultados
     */
    public function salvarResultados(int $votacaoId, array $resultados): void;
}
