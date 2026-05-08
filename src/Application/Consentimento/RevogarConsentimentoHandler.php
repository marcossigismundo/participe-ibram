<?php
/**
 * Handler para revogação de consentimento granular.
 *
 * @package Ibram\ParticipeIbram\Application\Consentimento
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Consentimento;

use DomainException;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Domain\Consentimento\Consentimento;
use Ibram\ParticipeIbram\Domain\Consentimento\ConsentimentoRepository;
use Ibram\ParticipeIbram\Domain\Consentimento\Finalidade;
use Ibram\ParticipeIbram\Domain\Consentimento\StatusConsentimento;

/**
 * Revoga uma finalidade opcional para um agente.
 *
 *  - Lança se a finalidade for obrigatória.
 *  - Lança se não houver consentimento prévio (nada a revogar).
 *  - Insere um novo registro com status REVOGADO referenciando o termo do
 *    consentimento vigente.
 *  - Audita ("consentimento_revogado") e dispara hook
 *    `pi_consentimento_revogado`.
 */
final class RevogarConsentimentoHandler
{
    private ConsentimentoRepository $consentimentos;
    private AuditLogger $audit;

    public function __construct(
        ConsentimentoRepository $consentimentos,
        AuditLogger $audit
    ) {
        $this->consentimentos = $consentimentos;
        $this->audit          = $audit;
    }

    /**
     * @throws DomainException Quando finalidade obrigatória ou sem registro prévio.
     */
    public function handle(
        int $agenteId,
        Finalidade $finalidade,
        ?string $ipHash,
        ?string $userAgent
    ): int {
        if ($finalidade->isObrigatoria()) {
            throw new DomainException(sprintf(
                'Finalidade obrigatória "%s" não pode ser revogada.',
                $finalidade->value()
            ));
        }

        $vigente = $this->consentimentos->findVigentePorAgenteEFinalidade($agenteId, $finalidade);
        if ($vigente === null) {
            throw new DomainException('Não há consentimento prévio para revogar.');
        }
        if ($vigente->status()->isRevogado()) {
            throw new DomainException('Consentimento já está revogado.');
        }
        if ($vigente->status()->isNegado()) {
            throw new DomainException('Não há consentimento ativo para revogar (estava negado).');
        }

        $now = new \DateTimeImmutable('now');
        $rev = new Consentimento(
            null,
            $agenteId,
            $vigente->termoId(),
            $finalidade,
            StatusConsentimento::revogado(),
            $ipHash,
            $userAgent,
            $now,
            $now
        );

        $id = $this->consentimentos->save($rev);

        $this->audit->log(
            'consentimento',
            $id,
            'consentimento_revogado',
            ['finalidade' => $finalidade->value(), 'agente_id' => $agenteId],
            null
        );

        ConsentimentoEventos::dispararRevogado($id, $finalidade);

        return $id;
    }
}
