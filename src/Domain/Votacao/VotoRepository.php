<?php
/**
 * Repositório (interface) para a entidade Voto.
 *
 * @package Ibram\ParticipeIbram\Domain\Votacao
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Domain\Votacao;

/**
 * Contrato de persistência de {@see Voto}.
 *
 * Restrições de privacidade explícitas:
 *  - Implementações **NÃO PODEM** expor consultas que liguem `eleitor_hash` a
 *    `agente_id`. O hash é computado externamente por {@see EleitorHasher} e o
 *    repositório só lida com o hash.
 *  - O método {@see existeVoto()} apenas confirma a presença, sem revelar
 *    contra qual candidato o eleitor votou.
 */
interface VotoRepository
{
    /**
     * Verifica a existência de voto para o trio (votacao, categoria, eleitor_hash).
     *
     * Sinaliza, sem revelar candidato, que o eleitor já participou desta
     * combinação. A constraint UNIQUE no banco garante consistência.
     */
    public function existeVoto(int $votacaoId, int $categoriaId, string $eleitorHash): bool;

    /**
     * Persiste um voto novo. Retorna o id gerado.
     *
     * @throws VotoDuplicado Em violação da UNIQUE(votacao_id, categoria_id, eleitor_hash)
     *                       — código MySQL 1062.
     */
    public function salvarVoto(Voto $voto): int;

    /**
     * Conta votos por candidato em uma categoria de uma votação.
     *
     * @return array<int,int> Mapa `candidato_inscricao_id => total_votos`.
     */
    public function contarPorCandidato(int $votacaoId, int $categoriaId): array;

    /**
     * Calcula o hash determinístico do conjunto de votos de uma votação para
     * fins de auditoria pública (pré-apuração).
     *
     * Convenção de canonicalização (consultar implementação para detalhes):
     *  - Ordenação estável por (categoria_id, eleitor_hash, candidato_inscricao_id, votado_em).
     *  - Para cada voto: `categoria_id|eleitor_hash|candidato_inscricao_id|votado_em`.
     *  - Concatenação separada por `\n`, depois `sha256` do total.
     *
     * Não inclui PII (nem agente_id) — apenas dados já anonimizados.
     */
    public function gerarHashPreApuracao(int $votacaoId): string;

    /**
     * Total de votos em uma votação (para evidência pública).
     */
    public function contarTotalDaVotacao(int $votacaoId): int;
}
