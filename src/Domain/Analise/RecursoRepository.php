<?php
/**
 * Contrato de persistência para a entidade Recurso.
 *
 * @package Ibram\ParticipeIbram\Domain\Analise
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Analise;

interface RecursoRepository
{
    public function findById(int $id): ?Recurso;

    /**
     * Localiza o recurso vigente de um agente em uma fase específica
     * (retratacao ou presidencia). Devolve null se não existir.
     */
    public function findPorAgenteEFase(int $agenteId, string $fase): ?Recurso;

    /**
     * Lista recursos ainda não decididos cujo prazo termina dentro de
     * `$dias` dias contados a partir de agora. Usado pelo cron de notificação
     * (TD-13 — comunicação automática 48h antes do vencimento).
     *
     * @return list<Recurso>
     */
    public function findVencendoEm(int $dias): array;

    /**
     * Persiste (insert ou update). Retorna o id final.
     */
    public function save(Recurso $recurso): int;
}
