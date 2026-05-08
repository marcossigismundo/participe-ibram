<?php
/**
 * Handler para salvar rascunho de inscrição em edital.
 *
 * @package Ibram\ParticipeIbram\Application\Edital
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Edital;

use DomainException;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Domain\Edital\Inscricao;
use Ibram\ParticipeIbram\Domain\Edital\StatusEdital;
use Ibram\ParticipeIbram\Domain\Edital\StatusInscricao;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbCategoriaRepository;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbEditalRepository;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbInscricaoRepository;

/**
 * Caso de uso "salvar rascunho de inscrição".
 *
 * Validações:
 *  1. Edital existe e está em `inscricoes_abertas`.
 *  2. Categoria pertence ao edital.
 *  3. Agente está deferido (cross-domain via {@see AgenteLookupPort}).
 *  4. Agente é elegível para a categoria.
 *  5. Se `inscricaoId` informado, verifica que pertence ao agente e está em `RASCUNHO`.
 *  6. Persiste {@see Inscricao} com status `RASCUNHO`.
 *  7. Audita.
 *
 * @see SalvarRascunhoInscricaoCommand
 */
final class SalvarRascunhoInscricaoHandler
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
     * Salva ou atualiza o rascunho. Retorna o ID da inscrição.
     *
     * @return int ID da inscrição (nova ou existente).
     *
     * @throws DomainException
     * @throws \Ibram\ParticipeIbram\Domain\Edital\EditalNotFound
     * @throws \Ibram\ParticipeIbram\Domain\Edital\CategoriaInvalida
     */
    public function handle(SalvarRascunhoInscricaoCommand $command): int
    {
        // 1. Edital existe e está com inscrições abertas.
        $edital = $this->editaisRepo->findById($command->editalId());
        if ($edital === null) {
            throw new DomainException(sprintf(
                'Edital %d não encontrado.',
                $command->editalId()
            ));
        }
        if ($edital->status()->value() !== StatusEdital::INSCRICOES_ABERTAS) {
            throw new DomainException(
                'O edital não está com inscrições abertas.'
            );
        }

        // 2. Categoria pertence ao edital.
        $categoria = $this->categoriasRepo->findById($command->categoriaId());
        if ($categoria === null || $categoria->editalId() !== $command->editalId()) {
            throw new DomainException(
                'Categoria não encontrada ou não pertence ao edital informado.'
            );
        }

        // 3/4. Agente deferido e tipo elegível.
        $agenteInfo = $this->agenteLookup->lookup($command->agenteId());
        if (empty($agenteInfo['exists'])) {
            throw new DomainException('Agente não encontrado.');
        }
        if (empty($agenteInfo['deferido'])) {
            throw new DomainException(
                'Apenas agentes deferidos podem se inscrever.'
            );
        }
        $tipo = isset($agenteInfo['tipo']) && is_string($agenteInfo['tipo'])
            ? $agenteInfo['tipo']
            : '';
        if (!$categoria->aceitaTipoAgente($tipo)) {
            throw new DomainException(
                'Sua categoria de agente não é elegível para esta categoria do edital.'
            );
        }

        // 5. Se inscricaoId informado, verifica dono e status rascunho.
        if ($command->inscricaoId() !== null) {
            $inscricaoExistente = $this->inscricoesRepo->findById($command->inscricaoId());
            if ($inscricaoExistente === null) {
                throw new DomainException('Inscrição não encontrada.');
            }
            if ($inscricaoExistente->agenteId() !== $command->agenteId()) {
                throw new DomainException('Permissão negada: inscrição não pertence ao agente.');
            }
            if ($inscricaoExistente->status()->value() !== StatusInscricao::RASCUNHO) {
                throw new DomainException('Apenas rascunhos podem ser editados.');
            }

            // Atualiza o rascunho existente: recria a entidade com dados atualizados.
            $inscricaoAtualizada = new Inscricao(
                $command->inscricaoId(),
                $command->editalId(),
                $command->categoriaId(),
                $command->agenteId(),
                $command->portfolioMd(),
                StatusInscricao::rascunho(),
                $inscricaoExistente->inscritoEm(),
                $inscricaoExistente->habilitadoEm(),
                $inscricaoExistente->inabilitadoEm(),
                $inscricaoExistente->motivoInabilitacaoMd(),
                $inscricaoExistente->createdAt(),
                new \DateTimeImmutable('now')
            );

            $id = $this->inscricoesRepo->save($inscricaoAtualizada);

            $this->audit->log(
                'inscricao',
                $id,
                'rascunho_atualizado',
                null,
                [
                    'edital_id'    => $command->editalId(),
                    'categoria_id' => $command->categoriaId(),
                    'etapa_atual'  => $command->etapaAtual(),
                ],
                $command->agenteId()
            );

            return $id;
        }

        // 6. Nova inscrição em rascunho.
        $inscricao = Inscricao::novoRascunho(
            $command->editalId(),
            $command->categoriaId(),
            $command->agenteId(),
            $command->portfolioMd()
        );

        $id = $this->inscricoesRepo->save($inscricao);

        $this->audit->log(
            'inscricao',
            $id,
            'rascunho_criado',
            null,
            [
                'edital_id'    => $command->editalId(),
                'categoria_id' => $command->categoriaId(),
                'etapa_atual'  => $command->etapaAtual(),
            ],
            $command->agenteId()
        );

        return $id;
    }
}
