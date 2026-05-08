<?php
/**
 * Handler do SubmeterCadastroCommand (TD-05 rascunho -> submetido).
 *
 * @package Ibram\ParticipeIbram\Application\Cadastro
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Cadastro;

use DateTimeImmutable;
use DomainException;
use Ibram\ParticipeIbram\Application\Consentimento\RegistrarConsentimentoCommand;
use Ibram\ParticipeIbram\Application\Consentimento\RegistrarConsentimentoHandler;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Core\Network\IpResolver;
use Ibram\ParticipeIbram\Domain\Agente\AgenteRepository;
use Ibram\ParticipeIbram\Domain\Analise\StatusHistoricoRepository;
use Ibram\ParticipeIbram\Domain\Consentimento\Finalidade;
use Ibram\ParticipeIbram\Domain\Consentimento\TermoRepository;
use Ibram\ParticipeIbram\Domain\Documento\DocumentoRepository;
use Ibram\ParticipeIbram\Domain\Documento\TipoDocumentoRepository;

/**
 * Submete o cadastro à análise.
 *
 *  1. Valida que o agente existe (status atual qualquer; transição rascunho->submetido
 *     é guardada pela própria entidade {@see Agente::submeter()}).
 *  2. Valida que TODOS os documentos obrigatórios para o tipo de agente já
 *     estão anexados.
 *  3. Garante que finalidades obrigatórias foram aceitas (defesa em profundidade
 *     — `RegistrarConsentimentoHandler` revalida).
 *  4. Registra consentimentos via {@see RegistrarConsentimentoHandler}.
 *  5. Aplica `Agente::submeter()` (domínio guarda transição válida).
 *  6. Persiste agente (com detalhes/reps já existentes) e registra histórico.
 *  7. Dispara `pi_cadastro_submetido` (TD-13).
 */
final class SubmeterCadastroHandler
{
    private AgenteRepository $agentes;
    private AgenteDetalhesLoader $detalhes;
    private DocumentoRepository $documentos;
    private TipoDocumentoRepository $tiposDocumento;
    private TermoRepository $termos;
    private RegistrarConsentimentoHandler $registrarConsentimento;
    private StatusHistoricoRepository $statusHistorico;
    private AuditLogger $audit;
    private IpResolver $ipResolver;

    public function __construct(
        AgenteRepository $agentes,
        AgenteDetalhesLoader $detalhes,
        DocumentoRepository $documentos,
        TipoDocumentoRepository $tiposDocumento,
        TermoRepository $termos,
        RegistrarConsentimentoHandler $registrarConsentimento,
        StatusHistoricoRepository $statusHistorico,
        AuditLogger $audit,
        IpResolver $ipResolver
    ) {
        $this->agentes                = $agentes;
        $this->detalhes               = $detalhes;
        $this->documentos             = $documentos;
        $this->tiposDocumento         = $tiposDocumento;
        $this->termos                 = $termos;
        $this->registrarConsentimento = $registrarConsentimento;
        $this->statusHistorico        = $statusHistorico;
        $this->audit                  = $audit;
        $this->ipResolver             = $ipResolver;
    }

    /**
     * @throws DomainException
     */
    public function handle(SubmeterCadastroCommand $command): void
    {
        $agente = $this->agentes->findById($command->agenteId());
        if ($agente === null) {
            throw new DomainException(sprintf('Agente id=%d nao encontrado.', $command->agenteId()));
        }
        $statusAnterior = $agente->getStatusCadastro()->value();

        // 1. Documentos obrigatórios.
        $obrigatorios = $this->tiposDocumento->findObrigatoriosPara(
            $agente->getTipo()->value(),
            true
        );
        if ($obrigatorios !== []) {
            $anexados = $this->documentos->findByAgente($command->agenteId());
            $tipoIdsAnexados = [];
            foreach ($anexados as $doc) {
                $tipoIdsAnexados[(int) $doc->tipoDocumentoId()] = true;
            }
            $faltando = [];
            foreach ($obrigatorios as $tipo) {
                if ($tipo->id() !== null && !isset($tipoIdsAnexados[(int) $tipo->id()])) {
                    $faltando[] = $tipo->codigo();
                }
            }
            if ($faltando !== []) {
                throw new DomainException(sprintf(
                    'Faltam documentos obrigatorios: %s',
                    implode(', ', $faltando)
                ));
            }
        }

        // 2. Finalidades obrigatórias aceitas.
        $aceitas = $command->finalidadesAceitas();
        foreach (Finalidade::all() as $f) {
            if ($f->isObrigatoria() && !in_array($f->value(), $aceitas, true)) {
                throw new DomainException(sprintf(
                    'Finalidade obrigatoria "%s" deve ser aceita para submeter o cadastro.',
                    $f->value()
                ));
            }
        }

        // 3. Termo vigente.
        $termo = $this->termos->findAtivoCorrente();
        if ($termo === null || !$termo->isAtivo()) {
            throw new DomainException('Nenhum termo de consentimento ativo no momento.');
        }
        $termoId = $termo->id();
        if ($termoId === null) {
            throw new DomainException('Termo ativo sem id persistido.');
        }

        // 4. Registrar consentimentos.
        $ipHash = $this->ipResolver->hashIp($command->ipAddress());
        $registrarCmd = new RegistrarConsentimentoCommand(
            $command->agenteId(),
            $termoId,
            $aceitas,
            $command->finalidadesNegadas(),
            $ipHash,
            $command->userAgent()
        );
        $this->registrarConsentimento->handle($registrarCmd);

        // 5. Transição.
        $now = new DateTimeImmutable('now');
        $agente->submeter($now);

        // 6. Persistir agente com detalhes/reps existentes.
        $detalhes       = $this->detalhes->loadDetalhes($command->agenteId(), $agente->getTipo()->value());
        $representantes = $this->detalhes->loadRepresentantes($command->agenteId());

        $this->agentes->save($agente, $detalhes, $representantes);

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
            'submeter',
            ['status' => $statusAnterior],
            ['status' => $agente->getStatusCadastro()->value()],
            $command->userId()
        );

        if (function_exists('do_action')) {
            do_action('pi_cadastro_submetido', $command->agenteId());
        }
    }
}
