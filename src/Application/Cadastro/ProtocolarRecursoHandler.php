<?php
/**
 * Handler do ProtocolarRecursoCommand (Art. 7º Portaria 3230/2024).
 *
 * @package Ibram\ParticipeIbram\Application\Cadastro
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Cadastro;

use DateTimeImmutable;
use DomainException;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Domain\Agente\AgenteRepository;
use Ibram\ParticipeIbram\Domain\Agente\StatusCadastro;
use Ibram\ParticipeIbram\Domain\Analise\AnaliseRepository;
use Ibram\ParticipeIbram\Domain\Analise\Recurso;
use Ibram\ParticipeIbram\Domain\Analise\RecursoRepository;
use Ibram\ParticipeIbram\Domain\Analise\StatusHistoricoRepository;

/**
 * Protocola um recurso contra indeferimento de cadastro.
 *
 *  1. Valida que o agente está em `indeferido_aguardando_recurso`.
 *  2. Localiza a última análise indeferitória do agente; só é admitido
 *     recurso a partir de sua publicação (Art. 8º — Ibram publica no site
 *     com snapshot/hash; o repositório usa `publicado_em` da análise).
 *  3. Calcula o prazo de 10 dias CONTÍNUOS conforme Art. 7º + 8º:
 *       - prazoInicio = publicacao da decisão (ou decididoEm se não houve
 *         publicação ainda — fallback robusto, com warning de auditoria).
 *       - prazoFim = prazoInicio + P10D (10 dias corridos, sem exclusão de
 *         feriados/finais de semana). Trabalhamos em UTC para consistência.
 *  4. Verifica `now <= prazoFim`; caso contrário rejeita.
 *  5. Aplica `Agente::protocolarRecurso()` e persiste.
 *  6. Cria {@see Recurso} fase=retratacao e dispara `pi_recurso_protocolado`.
 */
final class ProtocolarRecursoHandler
{
    /** Janela de recurso (Art. 7º Portaria 3230 — dias contínuos). */
    private const PRAZO_DIAS = 10;

    private AgenteRepository $agentes;
    private AgenteDetalhesLoader $detalhes;
    private AnaliseRepository $analises;
    private RecursoRepository $recursos;
    private StatusHistoricoRepository $statusHistorico;
    private AuditLogger $audit;

    public function __construct(
        AgenteRepository $agentes,
        AgenteDetalhesLoader $detalhes,
        AnaliseRepository $analises,
        RecursoRepository $recursos,
        StatusHistoricoRepository $statusHistorico,
        AuditLogger $audit
    ) {
        $this->agentes         = $agentes;
        $this->detalhes        = $detalhes;
        $this->analises        = $analises;
        $this->recursos        = $recursos;
        $this->statusHistorico = $statusHistorico;
        $this->audit           = $audit;
    }

    /**
     * @return int ID do recurso persistido.
     *
     * @throws DomainException Quando estado, prazo ou análise são inválidos.
     */
    public function handle(ProtocolarRecursoCommand $command): int
    {
        $agente = $this->agentes->findById($command->agenteId());
        if ($agente === null) {
            throw new DomainException(sprintf('Agente id=%d nao encontrado.', $command->agenteId()));
        }
        if ($agente->getStatusCadastro()->value() !== StatusCadastro::INDEFERIDO_AGUARDANDO_RECURSO) {
            throw new DomainException(sprintf(
                'Recurso so pode ser protocolado em %s. Status atual: %s.',
                StatusCadastro::INDEFERIDO_AGUARDANDO_RECURSO,
                $agente->getStatusCadastro()->value()
            ));
        }

        $analiseIndef = $this->ultimaAnaliseIndeferimento($command->agenteId());
        if ($analiseIndef === null) {
            throw new DomainException('Nao ha analise de indeferimento associada ao agente.');
        }
        $analiseId = $analiseIndef->id();
        if ($analiseId === null) {
            throw new DomainException('Analise de indeferimento sem id persistido.');
        }

        // Cálculo de prazos (UTC, dias contínuos).
        $prazoInicio = $analiseIndef->publicadoEm() ?? $analiseIndef->decididoEm();
        $prazoFim    = $prazoInicio->modify(sprintf('+%d days', self::PRAZO_DIAS));
        $now         = new DateTimeImmutable('now');

        if ($now > $prazoFim) {
            throw new DomainException(sprintf(
                'Prazo de %d dias para recurso ja expirou em %s (UTC).',
                self::PRAZO_DIAS,
                $prazoFim->format('Y-m-d H:i:s')
            ));
        }

        // Não permitir duplicidade de recurso na fase retratação.
        $existing = $this->recursos->findPorAgenteEFase($command->agenteId(), Recurso::FASE_RETRATACAO);
        if ($existing !== null) {
            throw new DomainException('Ja existe recurso protocolado em fase de retratacao para este agente.');
        }

        // Transição do agente.
        $statusAnterior = $agente->getStatusCadastro()->value();
        $agente->protocolarRecurso($now);

        $detalhes       = $this->detalhes->loadDetalhes($command->agenteId(), $agente->getTipo()->value());
        $representantes = $this->detalhes->loadRepresentantes($command->agenteId());
        $this->agentes->save($agente, $detalhes, $representantes);

        // Cria recurso.
        $recurso = Recurso::protocolar(
            $analiseId,
            Recurso::FASE_RETRATACAO,
            $command->userId(),
            $command->fundamentacaoMd(),
            $now,
            $prazoInicio,
            $prazoFim
        );
        $recursoId = $this->recursos->save($recurso);

        $this->statusHistorico->registrar(
            $command->agenteId(),
            $statusAnterior,
            $agente->getStatusCadastro()->value(),
            $command->userId(),
            null
        );

        $this->audit->log(
            'agente',
            $command->agenteId(),
            'protocolar_recurso',
            ['status' => $statusAnterior],
            [
                'status'        => $agente->getStatusCadastro()->value(),
                'recurso_id'    => $recursoId,
                'fase'          => Recurso::FASE_RETRATACAO,
                'prazo_inicio'  => $prazoInicio->format('Y-m-d H:i:s'),
                'prazo_fim'     => $prazoFim->format('Y-m-d H:i:s'),
            ],
            $command->userId()
        );

        if (function_exists('do_action')) {
            do_action('pi_recurso_protocolado', $command->agenteId(), $recursoId);
        }

        return $recursoId;
    }

    /**
     * Localiza a análise de indeferimento mais recente do agente.
     */
    private function ultimaAnaliseIndeferimento(int $agenteId): ?\Ibram\ParticipeIbram\Domain\Analise\Analise
    {
        $analises = $this->analises->findByAgente($agenteId);
        $ultima   = null;
        foreach ($analises as $a) {
            if ($a->isIndeferimento()) {
                $ultima = $a; // Lista ordenada cronológica ASC; último é o mais recente.
            }
        }

        return $ultima;
    }
}
