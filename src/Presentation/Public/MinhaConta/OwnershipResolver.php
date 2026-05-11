<?php
/**
 * Ownership resolver/middleware: nunca confia em `agente_id` enviado pelo cliente.
 *
 * @package Ibram\ParticipeIbram\Presentation\Public\MinhaConta
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Public\MinhaConta;

use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Domain\Agente\AgenteRepository;

/**
 * Resolve o `agente_id` do usuário logado a partir do `user_id` do WordPress.
 *
 * Regras críticas (W8-A, TD-12):
 *  - Nunca, sob qualquer hipótese, confia em `agente_id` enviado pelo cliente.
 *  - Sempre busca via {@see AgenteRepository::findByUserId()}.
 *  - Em violação, audita via {@see AuditLogger} com `acao='ownership_denied'`
 *    ANTES de lançar {@see OwnershipDeniedException}.
 *  - Sempre filtra soft-deleted (delegado ao repositório — ele já ignora
 *    `deleted_at IS NOT NULL`).
 */
final class OwnershipResolver
{
    private AgenteRepository $agentes;
    private AuditLogger $audit;

    public function __construct(AgenteRepository $agentes, AuditLogger $audit)
    {
        $this->agentes = $agentes;
        $this->audit   = $audit;
    }

    /**
     * Resolve o agente_id do usuário informado. Null quando não há cadastro
     * ou o usuário não tem permissão (user_id inválido).
     */
    public function resolveAgenteIdByUserId(int $userId): ?int
    {
        if ($userId <= 0) {
            return null;
        }
        $agente = $this->agentes->findByUserId($userId);
        if ($agente === null) {
            return null;
        }
        $id = $agente->getId();

        return $id !== null && $id > 0 ? $id : null;
    }

    /**
     * Resolve o agente_id do usuário atualmente logado no WordPress.
     */
    public function currentUserAgenteId(): ?int
    {
        $userId = function_exists('get_current_user_id') ? (int) \get_current_user_id() : 0;
        if ($userId <= 0) {
            return null;
        }

        return $this->resolveAgenteIdByUserId($userId);
    }

    /**
     * Assegura que o `$agenteId` solicitado pertence ao `$userId`.
     *
     * Em caso de mismatch, registra evento `ownership_denied` no audit log
     * com `entidade='agente'`, `entidade_id=$agenteId` (alvo da tentativa),
     * `ator_id=$userId` (tentante) e payload mascarado.
     *
     * @throws OwnershipDeniedException Sempre que `$userId` não é dono de `$agenteId`.
     */
    public function assertOwnership(int $userId, int $agenteId): void
    {
        $resolved = $this->resolveAgenteIdByUserId($userId);
        if ($resolved !== null && $resolved === $agenteId) {
            return;
        }

        // Audita ANTES do throw.
        $this->audit->log(
            'agente',
            $agenteId > 0 ? $agenteId : null,
            'ownership_denied',
            null,
            [
                'tentou_user_id'   => $userId,
                'tentou_agente_id' => $agenteId,
                'resolved_para'    => $resolved, // null se não tem cadastro
            ],
            $userId > 0 ? $userId : null
        );

        throw new OwnershipDeniedException('Acesso negado.');
    }
}
