<?php
/**
 * Handler do DecidirRecursoPresidenciaCommand
 * (em_recurso_presidencia -> deferido_em_recurso ou indeferido_final).
 *
 * @package Ibram\ParticipeIbram\Application\Cadastro
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Cadastro;

use DateTimeImmutable;
use DomainException;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Domain\Agente\AgenteRepository;
use Ibram\ParticipeIbram\Domain\Agente\NumeroRegistro;
use Ibram\ParticipeIbram\Domain\Agente\StatusCadastro;
use Ibram\ParticipeIbram\Domain\Analise\AnaliseRepository;
use Ibram\ParticipeIbram\Domain\Analise\Recurso;
use Ibram\ParticipeIbram\Domain\Analise\RecursoRepository;
use Ibram\ParticipeIbram\Domain\Analise\StatusHistoricoRepository;

/**
 * Decide o recurso na fase de presidência (instância final).
 *
 *  - Deferir: gera {@see NumeroRegistro} e chama
 *    `Agente::decidirRecursoPresidencia(true, $numero)`.
 *  - Indeferir: chama `Agente::decidirRecursoPresidencia(false)` (estado terminal
 *    `indeferido_final`).
 *
 * Audita e dispara `pi_recurso_presidencia_decidido`.
 */
final class DecidirRecursoPresidenciaHandler
{
    private AgenteRepository $agentes;
    private AgenteDetalhesLoader $detalhes;
    private AnaliseRepository $analises;
    private RecursoRepository $recursos;
    private StatusHistoricoRepository $statusHistorico;
    private NumeroRegistroAllocator $sequence;
    private AuditLogger $audit;

    public function __construct(
        AgenteRepository $agentes,
        AgenteDetalhesLoader $detalhes,
        AnaliseRepository $analises,
        RecursoRepository $recursos,
        StatusHistoricoRepository $statusHistorico,
        NumeroRegistroAllocator $sequence,
        AuditLogger $audit
    ) {
        $this->agentes         = $agentes;
        $this->detalhes        = $detalhes;
        $this->analises        = $analises;
        $this->recursos        = $recursos;
        $this->statusHistorico = $statusHistorico;
        $this->sequence        = $sequence;
        $this->audit           = $audit;
    }

    /**
     * @return int ID do recurso decidido.
     *
     * @throws DomainException
     */
    public function handle(DecidirRecursoPresidenciaCommand $command): int
    {
        $recurso = $this->recursos->findById($command->recursoId());
        if ($recurso === null) {
            throw new DomainException(sprintf('Recurso id=%d nao encontrado.', $command->recursoId()));
        }
        if (!$recurso->isFasePresidencia()) {
            throw new DomainException('Recurso nao esta em fase de presidencia.');
        }
        if ($recurso->isDecidido()) {
            throw new DomainException('Recurso ja foi decidido.');
        }

        $analise = $this->analises->findById($recurso->analiseId());
        if ($analise === null) {
            throw new DomainException('Analise vinculada ao recurso nao encontrada.');
        }
        $agente = $this->agentes->findById($analise->agenteId());
        if ($agente === null) {
            throw new DomainException(sprintf('Agente id=%d nao encontrado.', $analise->agenteId()));
        }
        if ($agente->getStatusCadastro()->value() !== StatusCadastro::EM_RECURSO_PRESIDENCIA) {
            throw new DomainException(sprintf(
                'Agente nao esta em em_recurso_presidencia (status atual: %s).',
                $agente->getStatusCadastro()->value()
            ));
        }

        $now            = new DateTimeImmutable('now');
        $statusAnterior = $agente->getStatusCadastro()->value();

        if ($command->deferir()) {
            $numero = new NumeroRegistro($this->sequence->alocar($agente->getTipo()->value()));
            $agente->decidirRecursoPresidencia(true, $numero, $now);
            $recurso->decidir(Recurso::DECISAO_DEFERIR, $command->presidenteId(), $command->decisaoMd(), $now);
        } else {
            $agente->decidirRecursoPresidencia(false, null, $now);
            $recurso->decidir(Recurso::DECISAO_INDEFERIR, $command->presidenteId(), $command->decisaoMd(), $now);
        }

        $detalhes       = $this->detalhes->loadDetalhes($analise->agenteId(), $agente->getTipo()->value());
        $representantes = $this->detalhes->loadRepresentantes($analise->agenteId());
        $this->agentes->save($agente, $detalhes, $representantes);
        $this->recursos->save($recurso);

        $this->statusHistorico->registrar(
            $analise->agenteId(),
            $statusAnterior,
            $agente->getStatusCadastro()->value(),
            $command->presidenteId(),
            null
        );

        $this->audit->log(
            'recurso',
            $command->recursoId(),
            'decidir_presidencia',
            ['status_agente' => $statusAnterior],
            [
                'status_agente'   => $agente->getStatusCadastro()->value(),
                'decisao'         => $command->deferir() ? 'deferir' : 'indeferir',
                'numero_registro' => $command->deferir() && $agente->getNumeroRegistro() !== null
                    ? $agente->getNumeroRegistro()->value()
                    : null,
            ],
            $command->presidenteId()
        );

        if (function_exists('do_action')) {
            do_action(
                'pi_recurso_presidencia_decidido',
                $analise->agenteId(),
                $command->recursoId(),
                $command->deferir()
            );
        }

        return $command->recursoId();
    }
}
