<?php
/**
 * Handler: calcula elegibilidade de um WP user em uma votação.
 *
 * @package Ibram\ParticipeIbram\Application\Votacao
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Votacao;

use Ibram\ParticipeIbram\Application\Votacao\Ports\AgenteVotanteGateway;
use Ibram\ParticipeIbram\Application\Votacao\Ports\CategoriaConsultaGateway;
use Ibram\ParticipeIbram\Domain\Votacao\EleitorHasher;
use Ibram\ParticipeIbram\Domain\Votacao\VotacaoRepository;
use Ibram\ParticipeIbram\Domain\Votacao\VotoRepository;

/**
 * Caso de uso: dada uma `(wpUserId, votacaoId)`, retorna estrutura segura para
 * o frontend exibir o estado de elegibilidade.
 *
 * Privacidade (CRÍTICO):
 *  - O `agente_id` resolvido **JAMAIS** sai do retorno deste handler. É usado
 *    apenas internamente para calcular `eleitor_hash` e consultar
 *    {@see VotoRepository::existeVoto()}.
 *  - O `eleitor_hash` também NÃO retorna no payload — só é necessário para a
 *    verificação interna `ja_votou`. Vazá-lo permitiria que um caller curioso
 *    correlacione respostas de elegibilidade com o conteúdo dos votos.
 *  - O `nome` ou `tipo` do agente também não retornam — apenas a flag de
 *    elegibilidade e o motivo (machine-readable).
 *
 * Resolver de user→agente é injetado como callable cross-domain (mesmo padrão
 * usado por {@see \Ibram\ParticipeIbram\Presentation\Rest\InscricaoEndpoints}).
 */
final class VerificarElegibilidadeHandler
{
    /**
     * Motivos de inelegibilidade machine-readable. Corresponde a chaves i18n.
     */
    public const MOTIVO_VOTACAO_INEXISTENTE   = 'votacao_inexistente';
    public const MOTIVO_VOTACAO_NAO_ABERTA    = 'votacao_nao_aberta';
    public const MOTIVO_FORA_DA_JANELA        = 'fora_da_janela';
    public const MOTIVO_SEM_AGENTE_ASSOCIADO  = 'sem_agente_associado';
    public const MOTIVO_CADASTRO_NAO_DEFERIDO = 'cadastro_nao_deferido';
    public const MOTIVO_TIPO_AGENTE_INVALIDO  = 'tipo_agente_invalido';

    private VotacaoRepository $votacaoRepo;

    private VotoRepository $votoRepo;

    private AgenteVotanteGateway $agenteGateway;

    private CategoriaConsultaGateway $categoriaGateway;

    private EleitorHasher $eleitorHasher;

    /**
     * Resolver cross-domain: WP user id → agente_id|null.
     *
     * @var callable(int): (int|null)
     */
    private $agenteIdByUserResolver;

    /**
     * Provider opcional cross-domain para nome humano da categoria.
     * Recebe `(int $categoriaId): string|null`.
     *
     * @var callable(int): (string|null)|null
     */
    private $categoriaNomeProvider;

    /**
     * @param callable(int): (int|null) $agenteIdByUserResolver
     * @param callable(int): (string|null)|null $categoriaNomeProvider
     */
    public function __construct(
        VotacaoRepository $votacaoRepo,
        VotoRepository $votoRepo,
        AgenteVotanteGateway $agenteGateway,
        CategoriaConsultaGateway $categoriaGateway,
        EleitorHasher $eleitorHasher,
        callable $agenteIdByUserResolver,
        ?callable $categoriaNomeProvider = null
    ) {
        $this->votacaoRepo            = $votacaoRepo;
        $this->votoRepo               = $votoRepo;
        $this->agenteGateway          = $agenteGateway;
        $this->categoriaGateway       = $categoriaGateway;
        $this->eleitorHasher          = $eleitorHasher;
        $this->agenteIdByUserResolver = $agenteIdByUserResolver;
        $this->categoriaNomeProvider  = $categoriaNomeProvider;
    }

    /**
     * Calcula a elegibilidade.
     *
     * @return array{
     *   elegivel: bool,
     *   motivo: string|null,
     *   votacao_status: string|null,
     *   categorias_elegiveis: list<array{id:int, nome:string, ja_votou:bool}>
     * }
     */
    public function handle(VerificarElegibilidadeQuery $query): array
    {
        // 1. Carrega votação. NotFound → resposta uniforme.
        try {
            $votacao = $this->votacaoRepo->findById($query->votacaoId());
        } catch (\Throwable $e) {
            return $this->buildResponse(false, self::MOTIVO_VOTACAO_INEXISTENTE, null, []);
        }

        if (!$votacao->status()->isAberta()) {
            return $this->buildResponse(
                false,
                self::MOTIVO_VOTACAO_NAO_ABERTA,
                $votacao->status()->value(),
                []
            );
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        if (!$votacao->dentroDaJanela($now)) {
            return $this->buildResponse(
                false,
                self::MOTIVO_FORA_DA_JANELA,
                $votacao->status()->value(),
                []
            );
        }

        // 2. Resolve agente_id (NUNCA exposto no retorno).
        $resolver = $this->agenteIdByUserResolver;
        $agenteId = $resolver($query->wpUserId());

        if (!is_int($agenteId) || $agenteId <= 0) {
            return $this->buildResponse(
                false,
                self::MOTIVO_SEM_AGENTE_ASSOCIADO,
                $votacao->status()->value(),
                []
            );
        }

        if (!$this->agenteGateway->estaDeferido($agenteId)) {
            return $this->buildResponse(
                false,
                self::MOTIVO_CADASTRO_NAO_DEFERIDO,
                $votacao->status()->value(),
                []
            );
        }

        $tipoAgente = $this->agenteGateway->tipoAgente($agenteId);
        if ($tipoAgente === null) {
            return $this->buildResponse(
                false,
                self::MOTIVO_TIPO_AGENTE_INVALIDO,
                $votacao->status()->value(),
                []
            );
        }

        // 3. Lista categorias do edital aceitando o tipo do agente.
        $categoriasIds = $this->categoriaGateway->listarCategoriasDoEdital(
            $votacao->editalId()
        );

        $categoriasElegiveis = [];
        foreach ($categoriasIds as $categoriaId) {
            if (!$this->categoriaGateway->aceitaTipoAgente($categoriaId, $tipoAgente)) {
                continue;
            }
            $eleitorHash = $this->eleitorHasher->hash($agenteId, $query->votacaoId());
            $jaVotou     = $this->votoRepo->existeVoto(
                $query->votacaoId(),
                $categoriaId,
                $eleitorHash
            );

            $nome = '';
            if ($this->categoriaNomeProvider !== null) {
                $providerNome = $this->categoriaNomeProvider;
                $nomeRaw      = $providerNome($categoriaId);
                if (is_string($nomeRaw)) {
                    $nome = $nomeRaw;
                }
            }

            $categoriasElegiveis[] = [
                'id'       => (int) $categoriaId,
                'nome'     => $nome,
                'ja_votou' => $jaVotou,
            ];
        }

        // Agente_id, eleitor_hash e tipoAgente NÃO são incluídos no retorno.
        return $this->buildResponse(
            true,
            null,
            $votacao->status()->value(),
            $categoriasElegiveis
        );
    }

    /**
     * @param list<array{id:int, nome:string, ja_votou:bool}> $categorias
     *
     * @return array{
     *   elegivel: bool,
     *   motivo: string|null,
     *   votacao_status: string|null,
     *   categorias_elegiveis: list<array{id:int, nome:string, ja_votou:bool}>
     * }
     */
    private function buildResponse(
        bool $elegivel,
        ?string $motivo,
        ?string $statusVotacao,
        array $categorias
    ): array {
        return [
            'elegivel'             => $elegivel,
            'motivo'               => $motivo,
            'votacao_status'       => $statusVotacao,
            'categorias_elegiveis' => $categorias,
        ];
    }
}
