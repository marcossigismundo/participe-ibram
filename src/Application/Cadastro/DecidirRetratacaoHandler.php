<?php
/**
 * Handler do DecidirRetratacaoCommand (em_retratacao -> deferido_em_retratacao
 * ou em_recurso_presidencia).
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
 * Decide o recurso na fase de retratação.
 *
 *  - Reconsiderar: gera {@see NumeroRegistro} e chama
 *    {@see Agente::reconsiderar()} (em_retratacao -> deferido_em_retratacao).
 *  - Manter: chama {@see Agente::manterIndeferimento()} (em_retratacao ->
 *    em_recurso_presidencia) e cria UM NOVO recurso em fase `presidencia`,
 *    cujo prazo é o mesmo do recurso original (ou recalculado a partir do
 *    momento da publicação da decisão de retratação — ajustável pelo job
 *    de publicação posterior).
 */
final class DecidirRetratacaoHandler
{
    /** Janela de recurso na presidência (mesmo prazo de Art. 7º — 10 dias contínuos). */
    private const PRAZO_DIAS_PRESIDENCIA = 10;

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
     * @return int ID do recurso (mesmo recurso de retratação atualizado).
     *
     * @throws DomainException
     */
    public function handle(DecidirRetratacaoCommand $command): int
    {
        $recurso = $this->recursos->findById($command->recursoId());
        if ($recurso === null) {
            throw new DomainException(sprintf('Recurso id=%d nao encontrado.', $command->recursoId()));
        }
        if (!$recurso->isFaseRetratacao()) {
            throw new DomainException('Recurso nao esta em fase de retratacao.');
        }
        if ($recurso->isDecidido()) {
            throw new DomainException('Recurso ja foi decidido.');
        }

        // Localiza o agente via análise vinculada ao recurso.
        $agenteId = $this->agenteIdByAnalise($recurso->analiseId());
        if ($agenteId === null) {
            throw new DomainException('Nao foi possivel localizar agente para o recurso.');
        }
        $agente = $this->agentes->findById($agenteId);
        if ($agente === null) {
            throw new DomainException(sprintf('Agente id=%d nao encontrado.', $agenteId));
        }
        if ($agente->getStatusCadastro()->value() !== StatusCadastro::EM_RETRATACAO) {
            throw new DomainException(sprintf(
                'Agente nao esta em em_retratacao (status atual: %s).',
                $agente->getStatusCadastro()->value()
            ));
        }

        $now             = new DateTimeImmutable('now');
        $statusAnterior  = $agente->getStatusCadastro()->value();

        if ($command->reconsiderar()) {
            // Gera número e aplica reconsideração na entidade.
            $numero = new NumeroRegistro($this->sequence->alocar($agente->getTipo()->value()));
            $agente->reconsiderar($numero, $now);
            $recurso->decidir(Recurso::DECISAO_RECONSIDERAR, $command->analistaId(), $command->decisaoMd(), $now);
        } else {
            $agente->manterIndeferimento($now);
            $recurso->decidir(Recurso::DECISAO_MANTER, $command->analistaId(), $command->decisaoMd(), $now);
        }

        // Persistir agente.
        $detalhes       = $this->detalhes->loadDetalhes($agenteId, $agente->getTipo()->value());
        $representantes = $this->detalhes->loadRepresentantes($agenteId);
        $this->agentes->save($agente, $detalhes, $representantes);

        // Persistir recurso de retratação decidido.
        $this->recursos->save($recurso);

        // Se mantido, abrir NOVO recurso fase=presidencia (mesmos prazos).
        $recursoPresidenciaId = null;
        if (!$command->reconsiderar()) {
            $prazoInicio = $now;
            $prazoFim    = $now->modify(sprintf('+%d days', self::PRAZO_DIAS_PRESIDENCIA));
            $novo = Recurso::protocolar(
                $recurso->analiseId(),
                Recurso::FASE_PRESIDENCIA,
                $recurso->recorrenteId(),
                $recurso->fundamentacaoMd(),
                $now,
                $prazoInicio,
                $prazoFim
            );
            $recursoPresidenciaId = $this->recursos->save($novo);
        }

        $this->statusHistorico->registrar(
            $agenteId,
            $statusAnterior,
            $agente->getStatusCadastro()->value(),
            $command->analistaId(),
            null
        );

        $this->audit->log(
            'recurso',
            $command->recursoId(),
            'decidir_retratacao',
            ['status_agente' => $statusAnterior],
            [
                'status_agente'           => $agente->getStatusCadastro()->value(),
                'decisao'                 => $command->reconsiderar() ? 'reconsiderar' : 'manter',
                'recurso_presidencia_id'  => $recursoPresidenciaId,
            ],
            $command->analistaId()
        );

        if (function_exists('do_action')) {
            do_action(
                'pi_recurso_retratacao_decidido',
                $agenteId,
                $command->recursoId(),
                $command->reconsiderar(),
                $recursoPresidenciaId
            );
        }

        return (int) $command->recursoId();
    }

    /**
     * Resolve o id do agente a partir do id da análise associada ao recurso.
     */
    private function agenteIdByAnalise(int $analiseId): ?int
    {
        $analise = $this->analises->findById($analiseId);

        return $analise !== null ? $analise->agenteId() : null;
    }
}
