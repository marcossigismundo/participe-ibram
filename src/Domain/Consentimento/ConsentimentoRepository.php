<?php
/**
 * Repositório de Consentimentos (interface de domínio).
 *
 * @package Ibram\ParticipeIbram\Domain\Consentimento
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Consentimento;

/**
 * Contrato para persistência de {@see Consentimento}.
 *
 * A tabela é tratada como append-friendly: cada decisão gera um novo registro
 * para manter trilha probatória (R2-lgpd.md §3.2).
 */
interface ConsentimentoRepository
{
    /**
     * Retorna o registro mais recente para um par (agente, finalidade), ou
     * null se nunca houve manifestação.
     */
    public function findVigentePorAgenteEFinalidade(int $agenteId, Finalidade $finalidade): ?Consentimento;

    /**
     * Histórico completo de consentimentos do agente (ordem cronológica).
     *
     * @return array<int,Consentimento>
     */
    public function findTodosPorAgente(int $agenteId): array;

    /**
     * Persiste um novo consentimento (INSERT). Retorna id atribuído.
     */
    public function save(Consentimento $consentimento): int;

    /**
     * Atalho semântico: insere um novo registro com status = REVOGADO para o
     * (agente, finalidade) — referencia o termo do registro vigente.
     *
     * Se não houver registro prévio, lança {@see \DomainException}.
     */
    public function revogarPorAgenteEFinalidade(
        int $agenteId,
        Finalidade $finalidade,
        ?string $ipHash,
        ?string $userAgent
    ): void;
}
