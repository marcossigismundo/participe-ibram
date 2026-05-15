<?php
/**
 * Handler: criar uma votação (status inicial: agendada).
 *
 * @package Ibram\ParticipeIbram\Application\Votacao
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Votacao;

use DomainException;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Domain\Votacao\ModoVotacao;
use Ibram\ParticipeIbram\Domain\Votacao\StatusVotacao;
use Ibram\ParticipeIbram\Domain\Votacao\Votacao;
use Ibram\ParticipeIbram\Domain\Votacao\VotacaoRepository;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbEditalRepository;
use InvalidArgumentException;

/**
 * Caso de uso: criar uma nova votação para um edital.
 *
 * Pré-condições validadas:
 *  - O edital existe (via WpdbEditalRepository::findById).
 *  - Não há votação ativa (não cancelada/apurada) para o mesmo edital.
 *  - encerramento > abertura (validado no Command e na entidade Votacao).
 *
 * Efeitos colaterais:
 *  - Persiste a votação em status `agendada`.
 *  - Audit log.
 *  - Hook WordPress `pi_votacao_criada` com o id da votação.
 */
final class CriarVotacaoHandler
{
    private VotacaoRepository $votacaoRepo;

    private WpdbEditalRepository $editalRepo;

    private AuditLogger $audit;

    public function __construct(
        VotacaoRepository $votacaoRepo,
        WpdbEditalRepository $editalRepo,
        AuditLogger $audit
    ) {
        $this->votacaoRepo = $votacaoRepo;
        $this->editalRepo  = $editalRepo;
        $this->audit       = $audit;
    }

    /**
     * @throws DomainException       Quando o edital não existe ou já possui votação ativa.
     * @throws InvalidArgumentException Quando modo é inválido.
     *
     * @return Votacao A votação criada e persistida.
     */
    public function handle(CriarVotacaoCommand $command): Votacao
    {
        // 1. Edital deve existir.
        $edital = $this->editalRepo->findById($command->editalId());
        if ($edital === null) {
            throw new DomainException(
                sprintf(
                    /* translators: %d = id do edital */
                    (string) (function_exists('__')
                        ? __('Edital #%d não encontrado.', 'participe-ibram')
                        : 'Edital #%d não encontrado.'),
                    $command->editalId()
                )
            );
        }

        // 2. Não pode haver outra votação ativa (agendada ou aberta) para o edital.
        $existente = $this->votacaoRepo->findByEdital($command->editalId());
        if ($existente !== null) {
            $statusExistente = $existente->status()->value();
            $terminais       = [StatusVotacao::APURADA, StatusVotacao::CANCELADA];
            if (!in_array($statusExistente, $terminais, true)) {
                throw new DomainException(
                    function_exists('__')
                        ? (string) __('Já existe uma votação ativa para este edital.', 'participe-ibram')
                        : 'Já existe uma votação ativa para este edital.'
                );
            }
        }

        // 3. Validar modo.
        $modo = ModoVotacao::fromString($command->modo()); // throws InvalidArgumentException if invalid

        // 4. Construir entidade no estado inicial `agendada`.
        $votacao = new Votacao(
            null,
            $command->editalId(),
            $command->abertura(),
            $command->encerramento(),
            StatusVotacao::agendada(),
            $modo
        );

        // 5. Persistir.
        $novoId  = $this->votacaoRepo->save($votacao);
        $votacao = $votacao->withId($novoId);

        // 6. Audit log.
        $this->audit->log(
            'votacao',
            $novoId,
            'criar_votacao',
            null,
            [
                'edital_id'    => $command->editalId(),
                'abertura'     => $command->abertura()->format('Y-m-d H:i:s'),
                'encerramento' => $command->encerramento()->format('Y-m-d H:i:s'),
                'modo'         => $modo->value(),
                'status'       => StatusVotacao::AGENDADA,
            ],
            $command->atorId()
        );

        // 7. Hook WP.
        if (function_exists('do_action')) {
            do_action('pi_votacao_criada', $novoId);
        }

        return $votacao;
    }
}
