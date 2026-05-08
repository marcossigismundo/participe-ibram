<?php
/**
 * Tracks access to sensitive (decrypted) fields.
 *
 * @package Ibram\ParticipeIbram\Core\Audit
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Core\Audit;

/**
 * Thin observer over {@see AuditLogger} dedicated to "viewed sensitive data" events.
 *
 * Wired either:
 *  - explicitly by repositories after successful decryption, or
 *  - implicitly via the `onDecrypt` callback accepted by SodiumCipher
 *    (in which case the repository is responsible for calling
 *    {@see trackDecryption()} with the right `entidade`/`campo` context).
 */
final class AccessTracker
{
    private AuditLogger $auditLogger;

    public function __construct(AuditLogger $auditLogger)
    {
        $this->auditLogger = $auditLogger;
    }

    /**
     * Record a single sensitive-field decryption.
     *
     * @param string $entidade   Logical entity (`agente`, `agente_pf`, `agente_or`, ...).
     * @param int    $entidadeId Primary key of the record being viewed.
     * @param string $campo      Field name (`cpf`, `rg`, `passaporte`, `cnpj`, ...).
     * @param int    $atorId     WordPress user id performing the view (0 = system).
     */
    public function trackDecryption(
        string $entidade,
        int $entidadeId,
        string $campo,
        int $atorId
    ): void {
        $this->auditLogger->log(
            $entidade,
            $entidadeId,
            'visualizar_dado_sensivel',
            null,
            ['campo' => $campo],
            $atorId > 0 ? $atorId : null
        );
    }

    /**
     * Convenience: log multiple field accesses in one call (e.g. an export view).
     *
     * @param list<string> $campos
     */
    public function trackBulkDecryption(
        string $entidade,
        int $entidadeId,
        array $campos,
        int $atorId
    ): void {
        if ($campos === []) {
            return;
        }
        $this->auditLogger->log(
            $entidade,
            $entidadeId,
            'visualizar_dado_sensivel',
            null,
            ['campos' => array_values($campos)],
            $atorId > 0 ? $atorId : null
        );
    }
}
