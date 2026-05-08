<?php
/**
 * EditalListQuery — paginação e filtros para a listagem de editais.
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\Support
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\Support;

use Ibram\ParticipeIbram\Domain\Edital\StatusEdital;

/**
 * Queries `wp_pi_editais` com filtros de status, paginação e contagem por
 * status. NÃO retorna nenhum dado de agente/inscrição — apenas títulos, datas,
 * contagens (AGENTS-PLAN ponto 1).
 */
final class EditalListQuery
{
    /** Colunas ORDER BY permitidas (whitelist — R5 V-08). */
    private const ALLOWED_ORDERBY = ['created_at', 'titulo', 'abertura', 'status'];

    /** @var \wpdb */
    private $wpdb;

    private string $tableEditais;
    private string $tableCategorias;
    private string $tableInscricoes;

    public function __construct($wpdb)
    {
        $this->wpdb            = $wpdb;
        $prefix                = isset($wpdb->prefix) && is_string($wpdb->prefix) ? $wpdb->prefix : 'wp_';
        $this->tableEditais    = $prefix . 'pi_editais';
        $this->tableCategorias = $prefix . 'pi_edital_categorias';
        $this->tableInscricoes = $prefix . 'pi_inscricoes';
    }

    /**
     * @param array{status?:string,orderby?:string,order?:string,limit?:int,offset?:int} $filters
     *
     * @return array<int, array{id:int,titulo:string,status:string,abertura:string|null,encerramento_inscricoes:string|null,num_categorias:int,num_inscricoes:int,criado_por:int,created_at:string}>
     */
    public function listar(array $filters): array
    {
        $orderBy = in_array($filters['orderby'] ?? 'created_at', self::ALLOWED_ORDERBY, true)
            ? ($filters['orderby'] ?? 'created_at')
            : 'created_at';
        $order  = strtoupper($filters['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        $limit  = max(1, min(100, (int) ($filters['limit'] ?? 25)));
        $offset = max(0, (int) ($filters['offset'] ?? 0));

        $where  = '1=1';
        $params = [];

        if (!empty($filters['status'])) {
            $where    .= ' AND e.status = %s';
            $params[] = (string) $filters['status'];
        }

        // Explicit column names — no user data in SELECT (AGENTS-PLAN ponto 1).
        $sql = "
            SELECT
                e.id,
                e.titulo,
                e.status,
                e.abertura,
                e.encerramento_inscricoes,
                e.criado_por,
                e.created_at,
                (SELECT COUNT(*) FROM {$this->tableCategorias} c WHERE c.edital_id = e.id) AS num_categorias,
                (SELECT COUNT(*) FROM {$this->tableInscricoes} i WHERE i.edital_id = e.id) AS num_inscricoes
            FROM {$this->tableEditais} e
            WHERE {$where}
            ORDER BY e.{$orderBy} {$order}
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
                'id'                      => (int) $row['id'],
                'titulo'                  => (string) $row['titulo'],
                'status'                  => (string) $row['status'],
                'abertura'                => isset($row['abertura']) ? (string) $row['abertura'] : null,
                'encerramento_inscricoes' => isset($row['encerramento_inscricoes']) ? (string) $row['encerramento_inscricoes'] : null,
                'num_categorias'          => (int) $row['num_categorias'],
                'num_inscricoes'          => (int) $row['num_inscricoes'],
                'criado_por'              => (int) $row['criado_por'],
                'created_at'              => (string) $row['created_at'],
            ];
        }

        return $out;
    }

    /**
     * @return array<string,int> status_value => contagem
     */
    public function contagensPorStatus(): array
    {
        $rows = $this->wpdb->get_results(
            "SELECT status, COUNT(*) as total FROM {$this->tableEditais} GROUP BY status",
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

    public function total(?string $status = null): int
    {
        if ($status !== null) {
            return (int) $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->tableEditais} WHERE status = %s",
                    $status
                )
            );
        }
        return (int) $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->tableEditais}");
    }
}
