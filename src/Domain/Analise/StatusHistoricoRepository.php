<?php
/**
 * Contrato append-only para histórico de status do agente.
 *
 * @package Ibram\ParticipeIbram\Domain\Analise
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Analise;

/**
 * Repositório de domínio. APPEND-ONLY: implementações DEVEM expor somente
 * INSERTs (sem UPDATE/DELETE pela aplicação).
 */
interface StatusHistoricoRepository
{
    /**
     * Insere uma nova linha de histórico. Retorna o id gravado.
     */
    public function registrar(
        int $agenteId,
        string $statusAnterior,
        string $statusNovo,
        ?int $atorId,
        ?string $observacao
    ): int;

    /**
     * Lista as transições de um agente em ordem cronológica.
     *
     * @return list<StatusHistorico>
     */
    public function findByAgente(int $agenteId): array;
}
