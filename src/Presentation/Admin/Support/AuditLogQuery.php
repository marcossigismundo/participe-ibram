<?php
/**
 * AuditLogQuery — read-model for wp_pi_audit_log (admin UI).
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\Support
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\Support;

/**
 * Encapsula SELECT explícito (nunca SELECT *) sobre wp_pi_audit_log.
 *
 * Convenções:
 *  - Whitelist de orderby e order (R5 V-04, B-04).
 *  - $wpdb->prepare() em chamada única (R5 V-03).
 *  - Nunca retorna dados_antes/dados_depois na listagem — apenas no findById.
 *  - Construtor recebe wpdb (DI).
 */
final class AuditLogQuery
{
    /** Colunas retornadas em list(). NUNCA inclui dados_antes/dados_depois. */
    private const LIST_COLUMNS = 'id, entidade, entidade_id, acao, ator_id, ocorrido_em';

    /** Colunas retornadas em findById() — inclui payload completo. */
    private const DETAIL_COLUMNS = 'id, entidade, entidade_id, acao, ator_id, dados_antes, dados_depois, ip_hash, user_agent, ocorrido_em';

    /** @var array<int,string> */
    private const ORDERBY_WHITELIST = ['id', 'ocorrido_em', 'entidade', 'acao'];

    /** @var array<int,string> */
    private const ORDER_WHITELIST = ['ASC', 'DESC'];

    /** Default sort. */
    private const DEFAULT_ORDERBY = 'ocorrido_em';
    private const DEFAULT_ORDER   = 'DESC';

    /** @var \wpdb */
    private $wpdb;

    private string $table;

    public function __construct($wpdb)
    {
        $this->wpdb  = $wpdb;
        $prefix      = isset($wpdb->prefix) && is_string($wpdb->prefix) ? $wpdb->prefix : 'wp_';
        $this->table = $prefix . 'pi_audit_log';
    }

    /**
     * Lista registros com filtros e paginação.
     *
     * @param array{
     *     entidade?: string|null,
     *     entidade_id?: int|null,
     *     acao?: string|null,
     *     acao_in?: list<string>|null,
     *     ator_id?: int|null,
     *     data_de?: string|null,
     *     data_ate?: string|null,
     *     orderby?: string,
     *     order?: string
     * } $filters
     *
     * @return list<array<string,mixed>>
     */
    public function list(array $filters, int $page = 1, int $perPage = 50): array
    {
        $page    = max(1, $page);
        $perPage = min(200, max(1, $perPage));
        $offset  = ($page - 1) * $perPage;

        [$where, $params] = $this->buildWhere($filters);
        $orderby = $this->safeOrderby($filters['orderby'] ?? '');
        $order   = $this->safeOrder($filters['order'] ?? '');

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT " . self::LIST_COLUMNS
            . " FROM {$this->table}"
            . ($where !== '' ? " WHERE {$where}" : '')
            . " ORDER BY {$orderby} {$order}"
            . " LIMIT %d OFFSET %d";

        $params[] = $perPage;
        $params[] = $offset;

        // $wpdb->prepare em chamada única (R5 V-03)
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $prepared = $this->wpdb->prepare($sql, ...$params);
        if ($prepared === null) {
            return [];
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $this->wpdb->get_results($prepared, ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    /**
     * Conta registros conforme filtros (sem paginação).
     *
     * @param array<string,mixed> $filters
     */
    public function count(array $filters): int
    {
        [$where, $params] = $this->buildWhere($filters);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT COUNT(*) FROM {$this->table}"
            . ($where !== '' ? " WHERE {$where}" : '');

        if ($params !== []) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $prepared = $this->wpdb->prepare($sql, ...$params);
        } else {
            $prepared = $sql;
        }

        if ($prepared === null) {
            return 0;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $result = $this->wpdb->get_var($prepared);

        return (int) $result;
    }

    /**
     * Retorna registro completo pelo id (inclui dados_antes/dados_depois).
     */
    public function findById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql  = "SELECT " . self::DETAIL_COLUMNS . " FROM {$this->table} WHERE id = %d LIMIT 1";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $prepared = $this->wpdb->prepare($sql, $id);
        if ($prepared === null) {
            return null;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $row = $this->wpdb->get_row($prepared, ARRAY_A);

        return is_array($row) ? $row : null;
    }

    /**
     * Constrói cláusula WHERE e array de parâmetros a partir de filtros.
     *
     * @param array<string,mixed> $filters
     * @return array{0:string, 1:list<mixed>}
     */
    private function buildWhere(array $filters): array
    {
        $clauses = [];
        $params  = [];

        if (!empty($filters['entidade']) && is_string($filters['entidade'])) {
            $clauses[] = 'entidade = %s';
            $params[]  = $filters['entidade'];
        }

        if (!empty($filters['entidade_id']) && is_numeric($filters['entidade_id'])) {
            $clauses[] = 'entidade_id = %d';
            $params[]  = (int) $filters['entidade_id'];
        }

        if (!empty($filters['acao']) && is_string($filters['acao'])) {
            $clauses[] = 'acao = %s';
            $params[]  = $filters['acao'];
        }

        // Suporte a acao_in para páginas especializadas (PII / Decisões)
        if (!empty($filters['acao_in']) && is_array($filters['acao_in'])) {
            $allowed = array_filter(
                $filters['acao_in'],
                static fn ($v): bool => is_string($v) && $v !== ''
            );
            if ($allowed !== []) {
                $placeholders = implode(', ', array_fill(0, count($allowed), '%s'));
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $clauses[] = "acao IN ({$placeholders})";
                foreach ($allowed as $a) {
                    $params[] = $a;
                }
            }
        }

        if (!empty($filters['ator_id']) && is_numeric($filters['ator_id'])) {
            $clauses[] = 'ator_id = %d';
            $params[]  = (int) $filters['ator_id'];
        }

        if (!empty($filters['data_de']) && is_string($filters['data_de'])) {
            $clauses[] = 'ocorrido_em >= %s';
            $params[]  = $filters['data_de'] . ' 00:00:00';
        }

        if (!empty($filters['data_ate']) && is_string($filters['data_ate'])) {
            $clauses[] = 'ocorrido_em <= %s';
            $params[]  = $filters['data_ate'] . ' 23:59:59';
        }

        $where = $clauses !== [] ? implode(' AND ', $clauses) : '';

        return [$where, $params];
    }

    private function safeOrderby(string $raw): string
    {
        $clean = strtolower(trim($raw));
        return in_array($clean, self::ORDERBY_WHITELIST, true) ? $clean : self::DEFAULT_ORDERBY;
    }

    private function safeOrder(string $raw): string
    {
        $clean = strtoupper(trim($raw));
        return in_array($clean, self::ORDER_WHITELIST, true) ? $clean : self::DEFAULT_ORDER;
    }
}
