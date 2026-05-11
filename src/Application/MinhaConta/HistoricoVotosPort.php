<?php
/**
 * Port: consultas read-only sobre votos do próprio eleitor.
 *
 * @package Ibram\ParticipeIbram\Application\MinhaConta
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\MinhaConta;

/**
 * Contrato cross-domain para acesso ao histórico de votos do próprio agente.
 *
 * CRÍTICO (voto secreto, anti-coerção):
 *  - Implementações **NUNCA** podem retornar `candidato_inscricao_id` nos
 *    métodos cujo nome contém `Historico` — esse campo é restrito ao motor
 *    de apuração/recibo, jamais à UI de "minha conta".
 *  - Os métodos só recebem `eleitor_hash` (HMAC) — nunca `agente_id` — para
 *    garantir que o port não vaze a correlação interna eleitor↔agente.
 */
interface HistoricoVotosPort
{
    /**
     * Lista, para o `eleitorHash` informado, apenas FATO de voto + timestamp.
     *
     * Retorno DEVE OMITIR `candidato_inscricao_id` (SELECT explícito sem o campo).
     *
     * @param string $eleitorHash Hash hex (64 chars).
     *
     * @return list<array{votacao_id:int, categoria_id:int, votado_em:string}>
     */
    public function listarFatosVoto(string $eleitorHash): array;

    /**
     * Recupera dados do voto necessários SOMENTE para regenerar o recibo.
     *
     * O `candidato_inscricao_id` é necessário aqui porque entra no hash, mas o
     * caller **não deve** retorná-lo para o cliente — usa apenas para
     * cálculo interno.
     *
     * @return array{votacao_id:int, categoria_id:int, candidato_inscricao_id:int, votado_em:string}|null
     */
    public function obterDadosParaRecibo(int $votacaoId, string $eleitorHash): ?array;
}
