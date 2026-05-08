<?php
/**
 * Handler para inscrever um agente em uma categoria de edital.
 *
 * @package Ibram\ParticipeIbram\Application\Edital
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Edital;

use DomainException;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Domain\Edital\CategoriaInvalida;
use Ibram\ParticipeIbram\Domain\Edital\EditalNotFound;
use Ibram\ParticipeIbram\Domain\Edital\Inscricao;
use Ibram\ParticipeIbram\Domain\Edital\InscricaoDuplicada;
use Ibram\ParticipeIbram\Domain\Edital\StatusEdital;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbCategoriaRepository;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbEditalRepository;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbInscricaoRepository;

/**
 * Caso de uso "inscrever agente em categoria de edital".
 *
 * Validações:
 *  1. Edital existe e está em `inscricoes_abertas`.
 *  2. Categoria pertence ao edital e aceita o `TipoAgente` do agente.
 *  3. Agente existe e tem `status_cadastro` deferido (consulta cross-domain
 *     via {@see AgenteLookupPort} — forward reference para evitar
 *     instanciar classes do domínio Agente diretamente).
 *  4. Inscrição não duplicada (UNIQUE edital+categoria+agente).
 *  5. Persiste {@see Inscricao} com status `inscrito` + auditoria.
 *  6. Dispara `do_action('pi_inscricao_submetida', int $inscricaoId, int $agenteId)`.
 */
final class InscreverAgenteHandler
{
    private WpdbEditalRepository $editaisRepo;

    private WpdbCategoriaRepository $categoriasRepo;

    private WpdbInscricaoRepository $inscricoesRepo;

    private AgenteLookupPort $agenteLookup;

    private AuditLogger $audit;

    public function __construct(
        WpdbEditalRepository $editaisRepo,
        WpdbCategoriaRepository $categoriasRepo,
        WpdbInscricaoRepository $inscricoesRepo,
        AgenteLookupPort $agenteLookup,
        AuditLogger $audit
    ) {
        $this->editaisRepo    = $editaisRepo;
        $this->categoriasRepo = $categoriasRepo;
        $this->inscricoesRepo = $inscricoesRepo;
        $this->agenteLookup   = $agenteLookup;
        $this->audit          = $audit;
    }

    /**
     * @return int ID da inscrição criada.
     *
     * @throws EditalNotFound
     * @throws CategoriaInvalida
     * @throws InscricaoDuplicada
     * @throws DomainException Quando o edital não está em inscrições abertas
     *                         ou o agente não está deferido.
     */
    public function handle(InscreverAgenteCommand $command): int
    {
        // 1. Edital existe e em inscricoes_abertas.
        $edital = $this->editaisRepo->findById($command->editalId());
        if ($edital === null) {
            throw EditalNotFound::withId($command->editalId());
        }
        if ($edital->status()->value() !== StatusEdital::INSCRICOES_ABERTAS) {
            throw new DomainException(sprintf(
                __('Edital %d nao esta com inscricoes abertas (status atual: %s).', 'participe-ibram'),
                $command->editalId(),
                $edital->status()->value()
            ));
        }

        // 2. Categoria pertence ao edital e aceita tipo do agente.
        $categoria = $this->categoriasRepo->findById($command->categoriaId());
        if ($categoria === null) {
            throw CategoriaInvalida::withId($command->categoriaId());
        }
        if ($categoria->editalId() !== $command->editalId()) {
            throw CategoriaInvalida::notInEdital($command->categoriaId(), $command->editalId());
        }

        // 3. Cross-domain: agente deferido + tipo elegível pela categoria.
        $agenteInfo = $this->agenteLookup->lookup($command->agenteId());
        if (empty($agenteInfo['exists'])) {
            throw new DomainException(sprintf(
                __('Agente %d nao encontrado.', 'participe-ibram'),
                $command->agenteId()
            ));
        }
        if (empty($agenteInfo['deferido'])) {
            throw new DomainException(
                __('Agente nao esta com cadastro deferido.', 'participe-ibram')
            );
        }
        $tipo = isset($agenteInfo['tipo']) && is_string($agenteInfo['tipo'])
            ? $agenteInfo['tipo']
            : '';
        if (!$categoria->aceitaTipoAgente($tipo)) {
            throw CategoriaInvalida::tipoAgenteNaoAceito($command->categoriaId(), $tipo);
        }

        // 4. UNIQUE (edital, categoria, agente).
        $existing = $this->inscricoesRepo->findByEditalCategoriaEAgente(
            $command->editalId(),
            $command->categoriaId(),
            $command->agenteId()
        );
        if ($existing !== null) {
            throw InscricaoDuplicada::for(
                $command->editalId(),
                $command->categoriaId(),
                $command->agenteId()
            );
        }

        // 5. Persiste em status `inscrito`.
        $inscricao = Inscricao::novoRascunho(
            $command->editalId(),
            $command->categoriaId(),
            $command->agenteId(),
            $command->portfolioMd()
        );
        $inscricao->submeter();

        $id = $this->inscricoesRepo->save($inscricao);

        $this->audit->log(
            'inscricao',
            $id,
            'submeter',
            null,
            [
                'edital_id'    => $command->editalId(),
                'categoria_id' => $command->categoriaId(),
                'agente_id'    => $command->agenteId(),
            ],
            $command->agenteId()
        );

        if (function_exists('do_action')) {
            do_action('pi_inscricao_submetida', $id, $command->agenteId());
        }

        return $id;
    }
}
