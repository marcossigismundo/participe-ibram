<?php
/**
 * Handler: assumir análise de cadastro (submetido -> em_analise).
 *
 * @package Ibram\ParticipeIbram\Application\Cadastro
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Cadastro;

use DateTimeImmutable;
use DomainException;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Domain\Agente\AgenteRepository;
use Ibram\ParticipeIbram\Domain\Analise\StatusHistoricoRepository;

/**
 * Atribui o cadastro à análise. A capability `pi_analisar_cadastro` é
 * verificada na borda (REST/Controller) — aqui a regra é puramente de
 * domínio.
 */
final class AssumirAnaliseHandler
{
    private AgenteRepository $agentes;
    private AgenteDetalhesLoader $detalhes;
    private StatusHistoricoRepository $statusHistorico;
    private AuditLogger $audit;

    public function __construct(
        AgenteRepository $agentes,
        AgenteDetalhesLoader $detalhes,
        StatusHistoricoRepository $statusHistorico,
        AuditLogger $audit
    ) {
        $this->agentes         = $agentes;
        $this->detalhes        = $detalhes;
        $this->statusHistorico = $statusHistorico;
        $this->audit           = $audit;
    }

    /**
     * @throws DomainException Quando o agente não existe ou está fora de submetido.
     */
    public function handle(int $agenteId, int $analistaId): void
    {
        if ($agenteId <= 0 || $analistaId <= 0) {
            throw new \InvalidArgumentException('IDs invalidos para AssumirAnalise.');
        }
        $agente = $this->agentes->findById($agenteId);
        if ($agente === null) {
            throw new DomainException(sprintf('Agente id=%d nao encontrado.', $agenteId));
        }

        $statusAnterior = $agente->getStatusCadastro()->value();
        $agente->iniciarAnalise(new DateTimeImmutable('now'));

        $detalhes       = $this->detalhes->loadDetalhes($agenteId, $agente->getTipo()->value());
        $representantes = $this->detalhes->loadRepresentantes($agenteId);

        $this->agentes->save($agente, $detalhes, $representantes);

        $this->statusHistorico->registrar(
            $agenteId,
            $statusAnterior,
            $agente->getStatusCadastro()->value(),
            $analistaId,
            null
        );

        $this->audit->log(
            'agente',
            $agenteId,
            'assumir_analise',
            ['status' => $statusAnterior],
            ['status' => $agente->getStatusCadastro()->value()],
            $analistaId
        );

        if (function_exists('do_action')) {
            do_action('pi_cadastro_em_analise', $agenteId, $analistaId);
        }
    }
}
