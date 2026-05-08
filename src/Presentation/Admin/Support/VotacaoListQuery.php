<?php
/**
 * VotacaoListQuery — paginação/filtros para a listagem admin de votações.
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\Support
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\Support;

use Ibram\ParticipeIbram\Domain\Votacao\StatusVotacao;

/**
 * Query para listar votações no admin (Onda 6).
 *
 * NÃO retorna nenhum eleitor_hash, ip_hash, agente_id, ator_id ou outro
 * campo capaz de quebrar pseudonimização. Apenas:
 *  - id da votação, edital_id e edital_titulo (lookup)
 *  - status, abertura, encerramento, modo
 *  - hash_pre_apuracao (apenas para auditoria visual)
 *  - apurado_em
 *  - total_votos (computado de pi_votos), total_eleitores (categorias)
 *
 * Whitelist de orderby para evitar injection (R5 V-08).
 */
final class VotacaoListQuery
{
    /** Colunas ORDER BY permitidas (whitelist). */
    private const ALLOWED_ORDERBY = ['encerramento', 'abertura', 'status', 'id'];

    /** @var \wpdb */
    private $wpdb;

    private string $tableVotacoes;
    private string $tableVotos;
    private string $tableEditais;

    public function __construct($wpdb)
    {
        $this->wpdb         = $wpdb;
        $prefix             = isset($wpdb->prefix) && is_string($wpdb->prefix) ? $wpdb->prefix : 'wp_';
        $this->tableVotacoes = $prefix . 'pi_votacoes';
        $this->tableVotos    = $prefix . 'pi_votos';
        $this->tableEditais  = $prefix . 'pi_editais';
    }

    /**
     * @param array{status?:?string,orderby?:string,order?:string,limit?:int,offset?:int} $filters
     *
     * @return array<int,array{
     *   id:int,
     *   edital_id:int,
     *   edital_titulo:string,
     *   status:string,
     *   abertura:string|null,
     *   encerramento:string|null,
     *   modo:string,
     *   hash_pre_apuracao:?string,
     *   apurado_em:string|null,
     *   total_votos:int
     * }>
     */
    public function listar(array $filters): array
    {
        $orderBy = in_array($filters['orderby'] ?? 'encerramento', self::ALLOWED_ORDERBY, true)
            ? ($filters['orderby'] ?? 'encerramento')
            : 'encerramento';
        $order  = strtoupper($filters['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        $limit  = max(1, min(100, (int) ($filters['limit'] ?? 25)));
        $offset = max(0, (int) ($filters['offset'] ?? 0));

        $where  = '1=1';
        $params = [];

        if (!empty($filters['status'])) {
            $statusValue = (string) $filters['status'];
            try {
                StatusVotacao::fromString($statusValue);
                $where    .= ' AND v.status = %s';
                $params[] = $statusValue;
            } catch (\InvalidArgumentException $e) {
                // Status inválido — ignora silenciosamente.
            }
        }

        $sql = "
            SELECT
                v.id,
                v.edital_id,
                e.titulo AS edital_titulo,
                v.status,
                v.abertura,
                v.encerramento,
                v.modo,
                v.hash_pre_apuracao,
                v.apurado_em,
                (SELECT COUNT(*) FROM {$this->tableVotos} vt WHERE vt.votacao_id = v.id) AS total_votos
            FROM {$this->tableVotacoes} v
            LEFT JOIN {$this->tableEditais} e ON e.id = v.edital_id
            WHERE {$where}
            ORDER BY v.{$orderBy} {$order}
            LIMIT %d OFFSET %d
        ";

        $params[] = $limit;
        $params[] = $offset;

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare($sql, ...$params), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            ARRAY_A
        );
        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id'                => (int) $row['id'],
                'edital_id'         => (int) $row['edital_id'],
                'edital_titulo'     => isset($row['edital_titulo']) ? (string) $row['edital_titulo'] : '',
                'status'            => (string) $row['status'],
                'abertura'          => isset($row['abertura']) ? (string) $row['abertura'] : null,
                'encerramento'      => isset($row['encerramento']) ? (string) $row['encerramento'] : null,
                'modo'              => (string) $row['modo'],
                'hash_pre_apuracao' => isset($row['hash_pre_apuracao']) && $row['hash_pre_apuracao'] !== ''
                    ? (string) $row['hash_pre_apuracao']
                    : null,
                'apurado_em'        => isset($row['apurado_em']) && $row['apurado_em'] !== ''
                    ? (string) $row['apurado_em']
                    : null,
                'total_votos'       => (int) $row['total_votos'],
            ];
        }
        return $out;
    }

    public function total(?string $status = null): int
    {
        if ($status !== null && $status !== '') {
            return (int) $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->tableVotacoes} WHERE status = %s",
                    $status
                )
            );
        }
        return (int) $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->tableVotacoes}");
    }

    /**
     * @return array<string,int>
     */
    public function contagensPorStatus(): array
    {
        $rows = $this->wpdb->get_results(
            "SELECT status, COUNT(*) AS total FROM {$this->tableVotacoes} GROUP BY status",
            ARRAY_A
        );
        if (!is_array($rows)) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row['status']] = (int) $row['total'];
        }
        return $out;
    }
}
