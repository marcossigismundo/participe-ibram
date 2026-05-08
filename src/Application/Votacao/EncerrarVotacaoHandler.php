<?php
/**
 * Handler: encerrar a votação e publicar evidência de pré-apuração.
 *
 * @package Ibram\ParticipeIbram\Application\Votacao
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Votacao;

use DateTimeImmutable;
use DateTimeZone;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Domain\Votacao\ApuracaoEvidence;
use Ibram\ParticipeIbram\Domain\Votacao\Votacao;
use Ibram\ParticipeIbram\Domain\Votacao\VotacaoRepository;
use Ibram\ParticipeIbram\Domain\Votacao\VotoRepository;

/**
 * Caso de uso: encerrar a urna.
 *
 * Fluxo:
 *  1. Carrega votação. Status precisa ser `aberta` (validado por `encerrar()`).
 *  2. Calcula `hashPreApuracao` via {@see VotoRepository::gerarHashPreApuracao()}.
 *  3. Persiste votação com novo status = `encerrada` + hash.
 *  4. Publica evidência ({@see ApuracaoEvidence}) em `wp_options` chave
 *     `pi_votacao_{id}_hash` para auditoria pública via REST.
 *  5. Audit log + hook `pi_votacao_encerrada`.
 */
final class EncerrarVotacaoHandler
{
    private VotacaoRepository $votacaoRepo;

    private VotoRepository $votoRepo;

    private AuditLogger $audit;

    /**
     * @var callable():DateTimeImmutable
     */
    private $clock;

    public function __construct(
        VotacaoRepository $votacaoRepo,
        VotoRepository $votoRepo,
        AuditLogger $audit,
        ?callable $clock = null
    ) {
        $this->votacaoRepo = $votacaoRepo;
        $this->votoRepo    = $votoRepo;
        $this->audit       = $audit;
        $this->clock       = $clock ?? static fn (): DateTimeImmutable
            => new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    public function handle(EncerrarVotacaoCommand $command): Votacao
    {
        $votacao = $this->votacaoRepo->findById($command->votacaoId());

        // 1+2. Hash pré-apuração + transição.
        $hash       = $this->votoRepo->gerarHashPreApuracao($command->votacaoId());
        $totalVotos = $this->votoRepo->contarTotalDaVotacao($command->votacaoId());
        $votacao->encerrar($hash);

        // 3. Persiste.
        $this->votacaoRepo->save($votacao);

        // 4. Evidência pública.
        $evidence = new ApuracaoEvidence(
            $hash,
            $totalVotos,
            ($this->clock)()
        );
        if (function_exists('update_option')) {
            update_option(
                'pi_votacao_' . (int) $votacao->id() . '_hash',
                $evidence->toArray(),
                false  // não autoload — é consultado via REST sob demanda.
            );
        }

        // 5. Audit + hook.
        $this->audit->log(
            'votacao',
            $votacao->id(),
            'encerrar',
            null,
            [
                'votacao_id'        => $votacao->id(),
                'hash_pre_apuracao' => $hash,
                'total_votos'       => $totalVotos,
            ],
            $command->atorId()
        );

        if (function_exists('do_action')) {
            do_action('pi_votacao_encerrada', $votacao->id(), $hash, $totalVotos);
        }

        return $votacao;
    }
}
