<?php
/**
 * Handler: editar datas/modo de uma votação agendada.
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
use InvalidArgumentException;

/**
 * Caso de uso: editar datas e modo de uma votação ainda agendada.
 *
 * Só é permitido enquanto status == `agendada`. Uma vez aberta ou em estado
 * terminal, a votação não pode ter seus parâmetros alterados.
 *
 * Efeitos colaterais:
 *  - Reconstrói a entidade Votacao com as novas datas/modo (imutabilidade preservada).
 *  - Persiste via VotacaoRepository::save().
 *  - Audit log.
 */
final class EditarVotacaoHandler
{
    private VotacaoRepository $votacaoRepo;

    private AuditLogger $audit;

    public function __construct(VotacaoRepository $votacaoRepo, AuditLogger $audit)
    {
        $this->votacaoRepo = $votacaoRepo;
        $this->audit       = $audit;
    }

    /**
     * @throws DomainException          Quando a votação não está em status `agendada`.
     * @throws InvalidArgumentException Quando modo é inválido.
     *
     * @return Votacao A votação atualizada e persistida.
     */
    public function handle(EditarVotacaoCommand $command): Votacao
    {
        $votacao = $this->votacaoRepo->findById($command->votacaoId());

        // Só edita no estado agendada.
        if (!$votacao->status()->isAgendada()) {
            throw new DomainException(
                function_exists('__')
                    ? (string) __('Apenas votações agendadas podem ser editadas.', 'participe-ibram')
                    : 'Apenas votações agendadas podem ser editadas.'
            );
        }

        $modo = ModoVotacao::fromString($command->modo()); // throws InvalidArgumentException if invalid

        // Snapshot para auditoria.
        $before = [
            'abertura'     => $votacao->abertura()->format('Y-m-d H:i:s'),
            'encerramento' => $votacao->encerramento()->format('Y-m-d H:i:s'),
            'modo'         => $votacao->modo()->value(),
        ];

        // Reconstrói a entidade com as novas datas/modo, preservando tudo mais.
        // Votacao não tem setters — reconstruímos pela via do construtor.
        $atualizada = new Votacao(
            $votacao->id(),
            $votacao->editalId(),
            $command->abertura(),
            $command->encerramento(),
            $votacao->status(),
            $modo,
            $votacao->hashPreApuracao(),
            $votacao->apuradoEm()
        );

        $this->votacaoRepo->save($atualizada);

        $after = [
            'abertura'     => $command->abertura()->format('Y-m-d H:i:s'),
            'encerramento' => $command->encerramento()->format('Y-m-d H:i:s'),
            'modo'         => $modo->value(),
        ];

        $this->audit->log(
            'votacao',
            (int) $votacao->id(),
            'editar_votacao',
            $before,
            $after,
            $command->atorId()
        );

        return $atualizada;
    }
}
