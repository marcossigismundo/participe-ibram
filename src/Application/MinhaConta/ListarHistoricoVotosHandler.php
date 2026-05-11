<?php
/**
 * Handler: lista votos do próprio agente preservando voto secreto.
 *
 * @package Ibram\ParticipeIbram\Application\MinhaConta
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\MinhaConta;

use Ibram\ParticipeIbram\Domain\Votacao\EleitorHasher;

/**
 * Caso de uso: para um agente autenticado, retornar o FATO de cada voto que ele
 * registrou (votação, edital, categoria, data) — **SEM** revelar o candidato
 * escolhido.
 *
 * Voto secreto (CRÍTICO — anti-coerção):
 *  1. Para cada `votacao_id` candidata, recalculamos o `eleitor_hash` via HMAC
 *     ({@see EleitorHasher::hash()}). Quem não tem o `PI_VOTING_SECRET` não
 *     consegue reproduzir o mapeamento `agente→eleitor_hash`.
 *  2. Consultamos a tabela `wp_pi_votos` filtrando por esses `eleitor_hash`,
 *     mas o {@see HistoricoVotosPort::listarFatosVoto()} **NÃO seleciona**
 *     `candidato_inscricao_id` no SQL — defesa em profundidade.
 *  3. O retorno deste handler também **NUNCA** contém `candidato_inscricao_id`,
 *     nem mesmo `eleitor_hash` (poderia ser correlacionado em vazamentos
 *     futuros do audit log).
 *
 * Lista de votações elegíveis (heurística cross-domain):
 *  - Tipicamente seria todas as votações cujo edital existe e cuja categoria
 *    aceita o tipo do agente. Como esse cross-domain é caro, o handler
 *    recebe via construtor um *resolver* `agenteId → list<int> votacaoIds`
 *    para manter o desacoplamento (mesmo padrão dos gateways de W6).
 */
final class ListarHistoricoVotosHandler
{
    private EleitorHasher $eleitorHasher;

    private HistoricoVotosPort $historicoPort;

    /**
     * Resolver cross-domain: agente_id → lista de votação_ids onde o agente era
     * potencial eleitor.
     *
     * @var callable(int): list<int>
     */
    private $votacoesElegiveisResolver;

    /**
     * Resolver opcional: votacao_id → ['edital_titulo' => string, 'categorias' => array<int,string>].
     * Retorna `null` quando a votação não existe mais (idealmente nunca acontece).
     *
     * @var callable(int): (array{edital_titulo:string, categorias:array<int,string>}|null)
     */
    private $contextoVotacaoResolver;

    /**
     * @param callable(int): list<int> $votacoesElegiveisResolver
     * @param callable(int): (array{edital_titulo:string, categorias:array<int,string>}|null) $contextoVotacaoResolver
     */
    public function __construct(
        EleitorHasher $eleitorHasher,
        HistoricoVotosPort $historicoPort,
        callable $votacoesElegiveisResolver,
        callable $contextoVotacaoResolver
    ) {
        $this->eleitorHasher             = $eleitorHasher;
        $this->historicoPort             = $historicoPort;
        $this->votacoesElegiveisResolver = $votacoesElegiveisResolver;
        $this->contextoVotacaoResolver   = $contextoVotacaoResolver;
    }

    /**
     * @return list<array{
     *   votacao_id:int,
     *   edital_titulo:string,
     *   categoria_nome:string,
     *   votado_em:string,
     *   recibo_recuperavel:bool
     * }>
     */
    public function handle(ListarHistoricoVotosCommand $command): array
    {
        $agenteId = $command->agenteId();

        $resolverVotacoes = $this->votacoesElegiveisResolver;
        $votacaoIds       = $resolverVotacoes($agenteId);

        if (!is_array($votacaoIds) || $votacaoIds === []) {
            return [];
        }

        // Cache por votação para evitar N chamadas ao contexto.
        $contextosCache = [];

        $resolverContexto = $this->contextoVotacaoResolver;
        $out              = [];

        foreach ($votacaoIds as $votacaoId) {
            $votacaoId = (int) $votacaoId;
            if ($votacaoId <= 0) {
                continue;
            }

            // Calcula eleitor_hash desta votação para o agente.
            $eleitorHash = $this->eleitorHasher->hash($agenteId, $votacaoId);

            // Busca FATO de voto — port NÃO retorna candidato_inscricao_id.
            $fatos = $this->historicoPort->listarFatosVoto($eleitorHash);
            if ($fatos === []) {
                continue;
            }

            if (!isset($contextosCache[$votacaoId])) {
                $contextosCache[$votacaoId] = $resolverContexto($votacaoId);
            }
            $contexto = $contextosCache[$votacaoId];

            foreach ($fatos as $fato) {
                // Filtra apenas fatos desta votação (defesa: port poderia retornar de outras).
                if ((int) ($fato['votacao_id'] ?? 0) !== $votacaoId) {
                    continue;
                }

                $categoriaId   = (int) ($fato['categoria_id'] ?? 0);
                $categoriaNome = '';
                $editalTitulo  = '';
                if (is_array($contexto)) {
                    $editalTitulo  = (string) ($contexto['edital_titulo'] ?? '');
                    $categorias    = is_array($contexto['categorias'] ?? null) ? $contexto['categorias'] : [];
                    $categoriaNome = isset($categorias[$categoriaId]) ? (string) $categorias[$categoriaId] : '';
                }

                // CRÍTICO: monta whitelist explícita — SEM candidato_inscricao_id, SEM eleitor_hash.
                $out[] = [
                    'votacao_id'         => $votacaoId,
                    'edital_titulo'      => $editalTitulo,
                    'categoria_nome'     => $categoriaNome,
                    'votado_em'          => (string) ($fato['votado_em'] ?? ''),
                    'recibo_recuperavel' => true,
                ];
            }
        }

        return $out;
    }
}
