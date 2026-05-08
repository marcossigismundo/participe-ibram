<?php
/**
 * Handler para decidir recurso de inabilitação (deferir / manter).
 *
 * @package Ibram\ParticipeIbram\Application\Edital
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Edital;

use DomainException;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Domain\Edital\RecursoInabilitacao;
use Ibram\ParticipeIbram\Domain\Edital\StatusInscricao;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbInscricaoRepository;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbRecursoInabilitacaoRepository;
use InvalidArgumentException;
use RuntimeException;

/**
 * Decide um recurso de inabilitação, atualiza o recurso e a inscrição:
 *  - `deferir` → inscrição vai para `final_habilitado`.
 *  - `manter`  → inscrição vai para `final_inabilitado`.
 *
 * Requer capability `pi_decidir_habilitacao` no decisor.
 * Dispara `do_action('pi_recurso_inabilitacao_decidido', $recursoId, $deferido)`.
 */
final class DecidirRecursoInabilitacaoHandler
{
    public const CAPABILITY = 'pi_decidir_habilitacao';

    private WpdbRecursoInabilitacaoRepository $recursosRepo;

    private WpdbInscricaoRepository $inscricoesRepo;

    private AuditLogger $audit;

    public function __construct(
        WpdbRecursoInabilitacaoRepository $recursosRepo,
        WpdbInscricaoRepository $inscricoesRepo,
        AuditLogger $audit
    ) {
        $this->recursosRepo   = $recursosRepo;
        $this->inscricoesRepo = $inscricoesRepo;
        $this->audit          = $audit;
    }

    /**
     * @param string $decisao  `deferir` ou `manter`.
     * @param string $decisaoMd Fundamentação Markdown da decisão.
     *
     * @throws DomainException
     * @throws RuntimeException Sem capability.
     */
    public function handle(int $recursoId, string $decisao, string $decisaoMd, int $decisorId): void
    {
        if ($decisorId <= 0) {
            throw new InvalidArgumentException('DecidirRecursoInabilitacaoHandler: decisorId invalido.');
        }
        $this->guardCapability($decisorId);

        $recurso = $this->recursosRepo->findById($recursoId);
        if ($recurso === null) {
            throw new DomainException(sprintf(
                __('Recurso %d nao encontrado.', 'participe-ibram'),
                $recursoId
            ));
        }
        if ($recurso->isDecidido()) {
            throw new DomainException(
                __('Recurso ja foi decidido.', 'participe-ibram')
            );
        }

        $inscricao = $this->inscricoesRepo->findById($recurso->inscricaoId());
        if ($inscricao === null) {
            throw new DomainException(
                __('Inscricao do recurso nao encontrada.', 'participe-ibram')
            );
        }
        if ($inscricao->status()->value() !== StatusInscricao::EM_RECURSO) {
            throw new DomainException(sprintf(
                __('Inscricao nao esta em recurso (status: %s).', 'participe-ibram'),
                $inscricao->status()->value()
            ));
        }

        $recurso->decidir($decisao, $decisorId, $decisaoMd);
        $deferido = $recurso->isDeferido();
        $inscricao->decidirRecurso($deferido);

        $this->recursosRepo->save($recurso);
        $this->inscricoesRepo->save($inscricao);

        $this->audit->log(
            'recurso_inabilitacao',
            $recursoId,
            'decidir',
            null,
            [
                'decisao' => $deferido ? RecursoInabilitacao::DECISAO_DEFERIR : RecursoInabilitacao::DECISAO_MANTER,
                'inscricao_id' => $inscricao->id(),
                'inscricao_status_novo' => $inscricao->status()->value(),
            ],
            $decisorId
        );

        if (function_exists('do_action')) {
            do_action('pi_recurso_inabilitacao_decidido', $recursoId, $deferido);
        }
    }

    /**
     * @throws RuntimeException
     */
    private function guardCapability(int $decisorId): void
    {
        if (!function_exists('user_can')) {
            return;
        }
        if (!user_can($decisorId, self::CAPABILITY)) {
            throw new RuntimeException(sprintf(
                'Usuario %d nao possui a capability %s.',
                $decisorId,
                self::CAPABILITY
            ));
        }
    }
}
