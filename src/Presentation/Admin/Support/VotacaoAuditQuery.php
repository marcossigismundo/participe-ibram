<?php
/**
 * VotacaoAuditQuery — leitura agregada de votos para a página de auditoria.
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\Support
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\Support;

/**
 * Lê apenas a tabela `pi_votos` (NÃO `pi_audit_log`) para apresentar
 * estatísticas e linha-a-linha **anonimizada** dos votos registrados.
 *
 * **Crítico anti-rastreio**:
 *  - Nunca SELECT id_voto, agente_id ou ator_id (não existem aqui, mas o
 *    SELECT é defensivo: somente as colunas listadas são lidas).
 *  - eleitor_hash retornado é truncado a 8 chars na UI (mascaramento extra).
 *  - ip_hash retornado é truncado a 8 chars na UI.
 *
 * Não toca `pi_audit_log` porque o audit log é geral (todas as entidades) —
 * a página de auditoria de uma votação specifica precisa apenas dos votos
 * efetivos.
 */
final class VotacaoAuditQuery
{
    /** @var \wpdb */
    private $wpdb;

    private string $tableVotos;

    public function __construct($wpdb)
    {
        $this->wpdb       = $wpdb;
        $prefix           = isset($wpdb->prefix) && is_string($wpdb->prefix) ? $wpdb->prefix : 'wp_';
        $this->tableVotos = $prefix . 'pi_votos';
    }

    /**
     * Lista paginada de votos. Retorna SOMENTE os campos listados.
     *
     * @return array<int,array{
     *   ocorrido_em:string,
     *   categoria_id:int,
     *   eleitor_hash:string,
     *   candidato_inscricao_id:int,
     *   ip_hash:?string
     * }>
     */
    public function listarVotos(int $votacaoId, int $limit = 50, int $offset = 0): array
    {
        if ($votacaoId <= 0) {
            return [];
        }
        $limit  = max(1, min(500, $limit));
        $offset = max(0, $offset);

        $sql = $this->wpdb->prepare(
            "SELECT votado_em AS ocorrido_em, categoria_id, eleitor_hash, candidato_inscricao_id, ip_hash
             FROM {$this->tableVotos}
             WHERE votacao_id = %d
             ORDER BY votado_em DESC, id DESC
             LIMIT %d OFFSET %d",
            $votacaoId,
            $limit,
            $offset
        );
        $rows = $this->wpdb->get_results($sql, ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'ocorrido_em'             => (string) $row['ocorrido_em'],
                'categoria_id'            => (int) $row['categoria_id'],
                'eleitor_hash'            => (string) $row['eleitor_hash'],
                'candidato_inscricao_id'  => (int) $row['candidato_inscricao_id'],
                'ip_hash'                 => isset($row['ip_hash']) && $row['ip_hash'] !== ''
                    ? (string) $row['ip_hash']
                    : null,
            ];
        }
        return $out;
    }

    public function totalVotos(int $votacaoId): int
    {
        if ($votacaoId <= 0) {
            return 0;
        }
        return (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->tableVotos} WHERE votacao_id = %d",
                $votacaoId
            )
        );
    }

    /**
     * @return array<int,array{categoria_id:int,total:int}>
     */
    public function porCategoria(int $votacaoId): array
    {
        if ($votacaoId <= 0) {
            return [];
        }
        $sql = $this->wpdb->prepare(
            "SELECT categoria_id, COUNT(*) AS total
             FROM {$this->tableVotos}
             WHERE votacao_id = %d
             GROUP BY categoria_id
             ORDER BY categoria_id ASC",
            $votacaoId
        );
        $rows = $this->wpdb->get_results($sql, ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'categoria_id' => (int) $row['categoria_id'],
                'total'        => (int) $row['total'],
            ];
        }
        return $out;
    }

    /**
     * Distribuição temporal por dia (YYYY-MM-DD) — sem hora para granularidade
     * compatível com privacy.
     *
     * @return array<int,array{dia:string,total:int}>
     */
    public function distribuicaoTemporal(int $votacaoId): array
    {
        if ($votacaoId <= 0) {
            return [];
        }
        $sql = $this->wpdb->prepare(
            "SELECT DATE(votado_em) AS dia, COUNT(*) AS total
             FROM {$this->tableVotos}
             WHERE votacao_id = %d
             GROUP BY DATE(votado_em)
             ORDER BY dia ASC",
            $votacaoId
        );
        $rows = $this->wpdb->get_results($sql, ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'dia'   => (string) $row['dia'],
                'total' => (int) $row['total'],
            ];
        }
        return $out;
    }

    /**
     * Quantidade de IPs hash únicos (proxy de unicidade, não rastreável).
     */
    public function ipsHashUnicos(int $votacaoId): int
    {
        if ($votacaoId <= 0) {
            return 0;
        }
        return (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(DISTINCT ip_hash) FROM {$this->tableVotos}
                 WHERE votacao_id = %d AND ip_hash IS NOT NULL AND ip_hash <> ''",
                $votacaoId
            )
        );
    }
}
