<?php
/**
 * Implementação wpdb do {@see HistoricoVotosPort} — voto secreto preservado.
 *
 * @package Ibram\ParticipeIbram\Infrastructure\Repository
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Infrastructure\Repository;

use Ibram\ParticipeIbram\Application\MinhaConta\HistoricoVotosPort;

/**
 * Lê `{$wpdb->prefix}pi_votos` para o histórico pessoal do eleitor.
 *
 * Garantia de voto secreto (defesa em profundidade):
 *  - {@see listarFatosVoto()} faz SELECT **explícito** das colunas
 *    `votacao_id, categoria_id, votado_em` — `candidato_inscricao_id` **NÃO**
 *    aparece no SQL. Mesmo um eventual `var_dump` da linha bruta não vaza o
 *    voto.
 *  - {@see obterDadosParaRecibo()} SELECT inclui `candidato_inscricao_id`
 *    porque é necessário para o cálculo do `hash_voto`, mas o handler que
 *    consome esse dado **NÃO** o devolve ao caller. O método é privado em
 *    intenção: deveria ser chamado apenas pelo
 *    {@see \Ibram\ParticipeIbram\Application\MinhaConta\RegerarReciboVotoHandler}.
 *  - Nenhum método aceita `agente_id`. O cross-domain (eleitor↔agente) é
 *    mantido fora desta camada — só circulam `eleitor_hash`.
 *
 * O construtor é compatível com a infra do plugin (assinatura `(wpdb $wpdb,
 * ?string $tableName = null)`).
 */
final class WpdbHistoricoVotosRepository implements HistoricoVotosPort
{
    /** @var \wpdb */
    private $wpdb;

    private string $tableName;

    /**
     * @param \wpdb $wpdb
     */
    public function __construct($wpdb, ?string $tableName = null)
    {
        $this->wpdb      = $wpdb;
        $prefix          = isset($wpdb->prefix) && is_string($wpdb->prefix) ? $wpdb->prefix : 'wp_';
        $this->tableName = $tableName ?? ($prefix . 'pi_votos');
    }

    /**
     * @return list<array{votacao_id:int, categoria_id:int, votado_em:string}>
     */
    public function listarFatosVoto(string $eleitorHash): array
    {
        if (strlen($eleitorHash) !== 64 || !ctype_xdigit($eleitorHash)) {
            return [];
        }

        // SELECT EXPLÍCITO — sem candidato_inscricao_id, sem eleitor_hash,
        // sem ip_hash, sem id. Voto secreto preservado em camada SQL.
        $sql = $this->wpdb->prepare(
            "SELECT votacao_id, categoria_id, votado_em
             FROM {$this->tableName}
             WHERE eleitor_hash = %s
             ORDER BY votado_em ASC, categoria_id ASC",
            $eleitorHash
        );

        $rows = $this->wpdb->get_results($sql, ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'votacao_id'   => (int) ($row['votacao_id'] ?? 0),
                'categoria_id' => (int) ($row['categoria_id'] ?? 0),
                'votado_em'    => (string) ($row['votado_em'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * Recupera dados internos para regenerar o `hash_voto`.
     *
     * Este é o ÚNICO ponto em que `candidato_inscricao_id` é selecionado nesta
     * classe — e a camada de aplicação que consome **não** o devolve ao caller.
     *
     * @return array{votacao_id:int, categoria_id:int, candidato_inscricao_id:int, votado_em:string}|null
     */
    public function obterDadosParaRecibo(int $votacaoId, string $eleitorHash): ?array
    {
        if ($votacaoId <= 0) {
            return null;
        }
        if (strlen($eleitorHash) !== 64 || !ctype_xdigit($eleitorHash)) {
            return null;
        }

        $sql = $this->wpdb->prepare(
            "SELECT votacao_id, categoria_id, candidato_inscricao_id, votado_em
             FROM {$this->tableName}
             WHERE votacao_id = %d AND eleitor_hash = %s
             LIMIT 1",
            $votacaoId,
            $eleitorHash
        );

        $row = $this->wpdb->get_row($sql, ARRAY_A);
        if (!is_array($row)) {
            return null;
        }

        return [
            'votacao_id'             => (int) ($row['votacao_id'] ?? 0),
            'categoria_id'           => (int) ($row['categoria_id'] ?? 0),
            'candidato_inscricao_id' => (int) ($row['candidato_inscricao_id'] ?? 0),
            'votado_em'              => (string) ($row['votado_em'] ?? ''),
        ];
    }
}
