<?php
/**
 * Handler: abrir uma votação (transição agendada → aberta).
 *
 * @package Ibram\ParticipeIbram\Application\Votacao
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Votacao;

use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Domain\Votacao\Votacao;
use Ibram\ParticipeIbram\Domain\Votacao\VotacaoRepository;

/**
 * Caso de uso: abrir a votação para receber votos.
 *
 * Pré-condições (Votacao::abrir() valida os invariantes):
 *  - Status atual = `agendada`.
 *  - "Agora" dentro de [abertura, encerramento].
 *
 * Efeitos colaterais:
 *  - Persiste o novo status.
 *  - Audit log.
 *  - Hook WordPress `pi_votacao_aberta` com o id da votação.
 */
final class AbrirVotacaoHandler
{
    private VotacaoRepository $votacaoRepo;

    private AuditLogger $audit;

    public function __construct(VotacaoRepository $votacaoRepo, AuditLogger $audit)
    {
        $this->votacaoRepo = $votacaoRepo;
        $this->audit       = $audit;
    }

    /**
     * @return Votacao A votação atualizada.
     */
    public function handle(AbrirVotacaoCommand $command): Votacao
    {
        $votacao = $this->votacaoRepo->findById($command->votacaoId());

        $statusAntes = $votacao->status()->value();
        $votacao->abrir();
        $this->votacaoRepo->save($votacao);

        $this->audit->log(
            'votacao',
            $votacao->id(),
            'abrir',
            ['status' => $statusAntes],
            ['status' => $votacao->status()->value()],
            $command->atorId()
        );

        if (function_exists('do_action')) {
            do_action('pi_votacao_aberta', $votacao->id());
        }

        return $votacao;
    }
}
