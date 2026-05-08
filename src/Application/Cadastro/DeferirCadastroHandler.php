<?php
/**
 * Handler do DeferirCadastroCommand (em_analise -> deferido).
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
use Ibram\ParticipeIbram\Domain\Analise\Analise;
use Ibram\ParticipeIbram\Domain\Analise\AnaliseRepository;
use Ibram\ParticipeIbram\Domain\Analise\StatusHistoricoRepository;

/**
 * Defere um cadastro:
 *  1. Carrega o agente e exige status `em_analise` ou `em_retratacao`
 *     (transição pode ocorrer também na fase de retratação — caso de uso
 *     {@see DecidirRetratacaoHandler} chama o repo direto). Aqui cobrimos
 *     somente a transição direta de análise (`em_analise` -> `deferido`).
 *  2. Gera o {@see NumeroRegistro} via {@see SequenceGenerator::next()} —
 *     transação com lock pessimista garante unicidade global.
 *  3. Aplica `Agente::deferir($numero)` (entidade valida transição).
 *  4. Persiste agente, registra histórico e cria {@see Analise} de deferimento.
 *  5. Audita e dispara `pi_cadastro_deferido`.
 */
final class DeferirCadastroHandler
{
    private AgenteRepository $agentes;
    private AgenteDetalhesLoader $detalhes;
    private AnaliseRepository $analises;
    private StatusHistoricoRepository $statusHistorico;
    private NumeroRegistroAllocator $sequence;
    private AuditLogger $audit;

    public function __construct(
        AgenteRepository $agentes,
        AgenteDetalhesLoader $detalhes,
        AnaliseRepository $analises,
        StatusHistoricoRepository $statusHistorico,
        NumeroRegistroAllocator $sequence,
        AuditLogger $audit
    ) {
        $this->agentes         = $agentes;
        $this->detalhes        = $detalhes;
        $this->analises        = $analises;
        $this->statusHistorico = $statusHistorico;
        $this->sequence        = $sequence;
        $this->audit           = $audit;
    }

    /**
     * @return int ID da análise persistida.
     *
     * @throws DomainException
     */
    public function handle(DeferirCadastroCommand $command): int
    {
        $agente = $this->agentes->findById($command->agenteId());
        if ($agente === null) {
            throw new DomainException(sprintf('Agente id=%d nao encontrado.', $command->agenteId()));
        }
        $statusAnterior = $agente->getStatusCadastro()->value();

        $now = new DateTimeImmutable('now');

        // 1. Gera número de registro (lock pessimista por tipo+ano).
        $numeroStr = $this->sequence->alocar($agente->getTipo()->value());
        $numero    = new NumeroRegistro($numeroStr);

        // 2. Aplica transição na entidade (entidade valida e exige numero).
        $agente->deferir($numero, $now);

        // 3. Persiste agente.
        $detalhes       = $this->detalhes->loadDetalhes($command->agenteId(), $agente->getTipo()->value());
        $representantes = $this->detalhes->loadRepresentantes($command->agenteId());
        $this->agentes->save($agente, $detalhes, $representantes);

        // 4. Análise de deferimento.
        $analise   = Analise::deferir(
            $command->agenteId(),
            $command->analistaId(),
            $command->parecerMd(),
            $now
        );
        $analiseId = $this->analises->save($analise);

        // 5. Histórico + auditoria.
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
            'deferir',
            ['status' => $statusAnterior],
            [
                'status'           => $agente->getStatusCadastro()->value(),
                'numero_registro'  => $numero->value(),
                'analise_id'       => $analiseId,
            ],
            $command->analistaId()
        );

        if (function_exists('do_action')) {
            do_action('pi_cadastro_deferido', $command->agenteId(), $numero->value(), $analiseId);
        }

        return $analiseId;
    }
}
