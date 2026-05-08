<?php
/**
 * Contrato para iteração paginada de destinatários broadcast.
 *
 * @package Ibram\ParticipeIbram\Application\Email
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Email;

/**
 * Permite que o {@see EnfileirarEmailHandler} faça broadcast sem depender
 * diretamente de wpdb.
 *
 * A implementação concreta (em Wave 5/6, ou em uma adapter sobre `WpdbAgenteRepository`)
 * deve filtrar:
 *  - Apenas agentes com cadastro deferido (status_cadastro IN
 *    deferido, deferido_em_retratacao, deferido_em_recurso).
 *  - Apenas agentes que NÃO revogaram a finalidade `comunicacao`
 *    (esse filtro é responsabilidade do adapter — o handler apenas envia).
 *  - Soft-delete excluído.
 *
 * O retorno traz os pares mínimos para enfileirar. Detalhes adicionais
 * (categoria, etc.) podem ser inclusos no extra para uso por filtros do hook
 * pré-broadcast.
 */
interface AgenteBroadcastQuery
{
    /**
     * Itera destinatários ativos em batches paginados.
     *
     * @return iterable<int, array{agente_id:int, email:string, nome:string}>
     *
     * @param int $batchSize  Tamanho da página interna (default 100).
     */
    public function iterar(int $batchSize = 100): iterable;
}
