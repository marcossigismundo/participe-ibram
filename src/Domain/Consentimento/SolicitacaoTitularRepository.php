<?php
/**
 * Repositório de Solicitações do Titular (interface de domínio).
 *
 * @package Ibram\ParticipeIbram\Domain\Consentimento
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Consentimento;

/**
 * Contrato para persistência de {@see SolicitacaoTitular}.
 */
interface SolicitacaoTitularRepository
{
    public function findById(int $id): ?SolicitacaoTitular;

    /**
     * Solicitações abertas/em atendimento de um agente (ordem por protocoladaEm DESC).
     *
     * @return array<int,SolicitacaoTitular>
     */
    public function findAbertasPorAgente(int $agenteId): array;

    /**
     * Lista paginada para o painel do DPO (ordem: prazo mais próximo primeiro).
     *
     * @return array<int,SolicitacaoTitular>
     */
    public function findPendentesParaDPO(int $page = 1, int $perPage = 25): array;

    /**
     * Persiste (INSERT ou UPDATE conforme presença de id) e retorna o id.
     */
    public function save(SolicitacaoTitular $solicitacao): int;

    /**
     * Solicitações cuja `prazoFinal()` ocorre nos próximos N dias e ainda não
     * foram encerradas (alvo do alerta D+10 ao DPO — R2-lgpd.md §6.3).
     *
     * @return array<int,SolicitacaoTitular>
     */
    public function findVencendoEmDias(int $dias): array;
}
