<?php
/**
 * Handler do IndeferirCadastroCommand.
 *
 * @package Ibram\ParticipeIbram\Application\Cadastro
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Cadastro;

use DateTimeImmutable;
use DomainException;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Domain\Agente\AgenteRepository;
use Ibram\ParticipeIbram\Domain\Analise\Analise;
use Ibram\ParticipeIbram\Domain\Analise\AnaliseRepository;
use Ibram\ParticipeIbram\Domain\Analise\StatusHistoricoRepository;

/**
 * Indefere um cadastro em análise (em_analise -> indeferido_aguardando_recurso).
 *
 *  1. Aplica `Agente::indeferir()` (entidade valida transição).
 *  2. Persiste agente; registra histórico.
 *  3. Cria {@see Analise} de indeferimento (parecer + fundamentacao).
 *  4. Audita e dispara `pi_cadastro_indeferido`.
 *
 * O cálculo de prazo de recurso (10 dias contínuos a partir da publicação) é
 * feito no momento do protocolo do recurso (Art. 7º Portaria 3230) — ver
 * {@see ProtocolarRecursoHandler}.
 */
final class IndeferirCadastroHandler
{
    private AgenteRepository $agentes;
    private AgenteDetalhesLoader $detalhes;
    private AnaliseRepository $analises;
    private StatusHistoricoRepository $statusHistorico;
    private AuditLogger $audit;

    public function __construct(
        AgenteRepository $agentes,
        AgenteDetalhesLoader $detalhes,
        AnaliseRepository $analises,
        StatusHistoricoRepository $statusHistorico,
        AuditLogger $audit
    ) {
        $this->agentes         = $agentes;
        $this->detalhes        = $detalhes;
        $this->analises        = $analises;
        $this->statusHistorico = $statusHistorico;
        $this->audit           = $audit;
    }

    /**
     * @return int ID da análise persistida.
     *
     * @throws DomainException
     */
    public function handle(IndeferirCadastroCommand $command): int
    {
        $agente = $this->agentes->findById($command->agenteId());
        if ($agente === null) {
            throw new DomainException(sprintf('Agente id=%d nao encontrado.', $command->agenteId()));
        }
        $statusAnterior = $agente->getStatusCadastro()->value();

        $now = new DateTimeImmutable('now');
        $agente->indeferir($now);

        $detalhes       = $this->detalhes->loadDetalhes($command->agenteId(), $agente->getTipo()->value());
        $representantes = $this->detalhes->loadRepresentantes($command->agenteId());
        $this->agentes->save($agente, $detalhes, $representantes);

        $analise   = Analise::indeferir(
            $command->agenteId(),
            $command->analistaId(),
            $command->parecerMd(),
            $command->fundamentacaoMd(),
            $now
        );
        $analiseId = $this->analises->save($analise);

        $this->statusHistorico->registrar(
            $command->agenteId(),
            $statusAnterior,
            $agente->getStatusCadastro()->value(),
            $command->analistaId(),
            null
        );

        $this->audit->log(
            'agente',
            $command->agenteId(),
            'indeferir',
            ['status' => $statusAnterior],
            [
                'status'     => $agente->getStatusCadastro()->value(),
                'analise_id' => $analiseId,
            ],
            $command->analistaId()
        );

        if (function_exists('do_action')) {
            do_action('pi_cadastro_indeferido', $command->agenteId(), $analiseId);
        }

        return $analiseId;
    }
}
