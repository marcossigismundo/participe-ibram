<?php
/**
 * Read-model query para as List Tables de Cadastros (admin).
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\Support
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\Support;

use DateTimeImmutable;

/**
 * Encapsula consultas otimizadas para popular FilaAnaliseListTable e
 * TodosAgentesListTable com JOIN único (agente + sub-tabela do tipo + última
 * análise para extrair analista atribuído).
 *
 * Convenções:
 *  - SQL parametrizado via `$wpdb->prepare`.
 *  - ORDER BY + DIRECTION via WHITELIST (R5 V-04, B-04).
 *  - Status default = ['submetido', 'em_analise'] (Wave 4-A: fila de análise).
 *  - Filtros por tipo, estado, analista e busca textual.
 *  - Nunca retorna CPF/CNPJ — somente nome, email, número de registro,
 *    estado, datas e analista atribuído.
 */
final class CadastroListQuery
{
    /** @var array<int,string> */
    private const ORDERBY_WHITELIST = [
        'submetido_em',
        'deferido_em',
        'tipo',
        'estado',
        'tempo_em_analise',
        'created_at',
        'numero_registro',
    ];

    /** @var array<int,string> */
    private const ORDER_WHITELIST = ['ASC', 'DESC'];

    /** @var \wpdb */
    private $wpdb;

    private string $tableAgentes;
    private string $tableAgentesPf;
    private string $tableAgentesOr;
    private string $tableAgentesSm;
    private string $tableAnalises;
    private string $tableUsers;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
        $prefix     = isset($wpdb->prefix) && is_string($wpdb->prefix) ? $wpdb->prefix : 'wp_';

        $this->tableAgentes   = $prefix . 'pi_agentes';
        $this->tableAgentesPf = $prefix . 'pi_agentes_pf';
        $this->tableAgentesOr = $prefix . 'pi_agentes_or';
        $this->tableAgentesSm = $prefix . 'pi_agentes_sm';
        $this->tableAnalises  = $prefix . 'pi_analises';
        $this->tableUsers     = $prefix . 'users';
    }

    /**
     * Lista cadastros conforme filtros.
     *
     * @param array{
     *     status?: list<string>,
     *     tipo?: string|null,
     *     estado?: string|null,
     *     analista_id?: int|null,
     *     search?: string|null,
     *     order_by?: string,
     *     order?: string,
     *     limit?: int,
     *     offset?: int
     * } $filters
     *
     * @return list<CadastroListItem>
     */
    public function listar(array $filters = []): array
    {
        [$sql, $params] = $this->buildSelect($filters, false);
        $prepared       = $params === [] ? $sql : $this->wpdb->prepare($sql, $params);
        if (!is_string($prepared)) {
            return [];
        }

        $rows = $this->wpdb->get_results($prepared, ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        $items = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $items[] = $this->hydrate($row);
            }
        }

        return $items;
    }

    /**
     * Conta total de cadastros conforme filtros (para paginação).
     *
     * @param array<string,mixed> $filters
     */
    public function contar(array $filters = []): int
    {
        [$sql, $params] = $this->buildSelect($filters, true);
        $prepared       = $params === [] ? $sql : $this->wpdb->prepare($sql, $params);
        if (!is_string($prepared)) {
            return 0;
        }

        return (int) $this->wpdb->get_var($prepared);
    }

    /**
     * Resumo: contagem por status (para sidebar do template).
     *
     * @return array<string,int>
     */
    public function contagensPorStatus(array $statuses): array
    {
        $statuses = array_values(array_filter($statuses, 'is_string'));
        if ($statuses === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($statuses), '%s'));
        $sql = sprintf(
            'SELECT status_cadastro AS status, COUNT(*) AS total
             FROM `%s`
             WHERE deleted_at IS NULL AND status_cadastro IN (%s)
             GROUP BY status_cadastro',
            $this->tableAgentes,
            $placeholders
        );
        $prepared = $this->wpdb->prepare($sql, $statuses);
        if (!is_string($prepared)) {
            return [];
        }
        $rows = $this->wpdb->get_results($prepared, ARRAY_A);
        $out  = [];
        foreach ((array) $rows as $row) {
            if (is_array($row) && isset($row['status'])) {
                $out[(string) $row['status']] = (int) ($row['total'] ?? 0);
            }
        }

        return $out;
    }

    /**
     * Tempo médio (em dias) de cadastros atualmente em análise.
     */
    public function tempoMedioEmAnaliseDias(): float
    {
        $sql = sprintf(
            "SELECT AVG(TIMESTAMPDIFF(SECOND, submetido_em, UTC_TIMESTAMP())) / 86400
             FROM `%s`
             WHERE deleted_at IS NULL
               AND submetido_em IS NOT NULL
               AND status_cadastro IN ('submetido','em_analise')",
            $this->tableAgentes
        );
        $val = $this->wpdb->get_var($sql);
        if ($val === null || $val === '') {
            return 0.0;
        }
        return round((float) $val, 1);
    }

    /**
     * Builds the SELECT for both listar() and contar().
     *
     * @return array{0:string,1:array<int,scalar>}
     */
    private function buildSelect(array $filters, bool $countOnly): array
    {
        $where  = ['a.deleted_at IS NULL'];
        $params = [];

        $statuses = isset($filters['status']) && is_array($filters['status']) && $filters['status'] !== []
            ? array_values(array_filter($filters['status'], 'is_string'))
            : [];
        if ($statuses !== []) {
            $placeholders = implode(',', array_fill(0, count($statuses), '%s'));
            $where[]      = 'a.status_cadastro IN (' . $placeholders . ')';
            foreach ($statuses as $s) {
                $params[] = $s;
            }
        }

        if (!empty($filters['tipo'])) {
            $where[]  = 'a.tipo = %s';
            $params[] = (string) $filters['tipo'];
        }

        if (!empty($filters['estado'])) {
            $where[]  = '('
                . 'COALESCE(pf.estado_residencia, org.estado_sede, sm.uf) = %s'
                . ')';
            $params[] = strtoupper((string) $filters['estado']);
        }

        if (!empty($filters['search'])) {
            $term     = '%' . $this->wpdb->esc_like((string) $filters['search']) . '%';
            $where[]  = '('
                . 'a.email_principal LIKE %s'
                . ' OR a.numero_registro LIKE %s'
                . ' OR pf.nome_completo LIKE %s'
                . ' OR pf.nome_social LIKE %s'
                . ' OR org.nome_organizacao LIKE %s'
                . ' OR sm.nome_orgao LIKE %s'
                . ')';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        if (!empty($filters['analista_id'])) {
            $where[]  = 'last_an.analista_id = %d';
            $params[] = (int) $filters['analista_id'];
        }

        $whereSql = implode(' AND ', $where);

        if ($countOnly) {
            $sql = sprintf(
                'SELECT COUNT(*) FROM `%s` a
                 LEFT JOIN `%s` pf ON pf.agente_id = a.id
                 LEFT JOIN `%s` org ON org.agente_id = a.id
                 LEFT JOIN `%s` sm ON sm.agente_id = a.id
                 LEFT JOIN (
                     SELECT an1.agente_id, an1.analista_id
                     FROM `%s` an1
                     INNER JOIN (
                         SELECT agente_id, MAX(decidido_em) AS max_dt
                         FROM `%s`
                         GROUP BY agente_id
                     ) an2 ON an2.agente_id = an1.agente_id AND an2.max_dt = an1.decidido_em
                 ) last_an ON last_an.agente_id = a.id
                 WHERE %s',
                $this->tableAgentes,
                $this->tableAgentesPf,
                $this->tableAgentesOr,
                $this->tableAgentesSm,
                $this->tableAnalises,
                $this->tableAnalises,
                $whereSql
            );
            return [$sql, $params];
        }

        // ORDER BY whitelist
        $orderBy = isset($filters['order_by']) && is_string($filters['order_by'])
            ? $filters['order_by']
            : 'submetido_em';
        $order   = isset($filters['order']) && is_string($filters['order'])
            ? strtoupper($filters['order'])
            : 'ASC';
        if (!in_array($orderBy, self::ORDERBY_WHITELIST, true)) {
            $orderBy = 'submetido_em';
        }
        if (!in_array($order, self::ORDER_WHITELIST, true)) {
            $order = 'ASC';
        }
        $orderColumn = $this->mapOrderByColumn($orderBy);

        $limit  = isset($filters['limit']) ? max(1, min(100, (int) $filters['limit'])) : 25;
        $offset = isset($filters['offset']) ? max(0, (int) $filters['offset']) : 0;

        $sql = sprintf(
            'SELECT a.id AS agente_id,
                    a.tipo AS tipo,
                    a.status_cadastro AS status_cadastro,
                    a.numero_registro AS numero_registro,
                    a.email_principal AS email_principal,
                    a.submetido_em AS submetido_em,
                    a.deferido_em AS deferido_em,
                    a.created_at AS created_at,
                    pf.nome_completo AS pf_nome_completo,
                    pf.nome_social AS pf_nome_social,
                    pf.estado_residencia AS pf_estado,
                    org.nome_organizacao AS or_nome,
                    org.estado_sede AS or_estado,
                    sm.nome_orgao AS sm_nome,
                    sm.uf AS sm_uf,
                    last_an.analista_id AS analista_id,
                    u.display_name AS analista_nome,
                    TIMESTAMPDIFF(SECOND, a.submetido_em, UTC_TIMESTAMP()) / 86400 AS tempo_em_analise
             FROM `%s` a
             LEFT JOIN `%s` pf ON pf.agente_id = a.id
             LEFT JOIN `%s` org ON org.agente_id = a.id
             LEFT JOIN `%s` sm ON sm.agente_id = a.id
             LEFT JOIN (
                 SELECT an1.agente_id, an1.analista_id, an1.decidido_em
                 FROM `%s` an1
                 INNER JOIN (
                     SELECT agente_id, MAX(decidido_em) AS max_dt
                     FROM `%s`
                     GROUP BY agente_id
                 ) an2 ON an2.agente_id = an1.agente_id AND an2.max_dt = an1.decidido_em
             ) last_an ON last_an.agente_id = a.id
             LEFT JOIN `%s` u ON u.ID = last_an.analista_id
             WHERE %s
             ORDER BY %s %s
             LIMIT %%d OFFSET %%d',
            $this->tableAgentes,
            $this->tableAgentesPf,
            $this->tableAgentesOr,
            $this->tableAgentesSm,
            $this->tableAnalises,
            $this->tableAnalises,
            $this->tableUsers,
            $whereSql,
            $orderColumn,
            $order
        );
        $params[] = $limit;
        $params[] = $offset;

        return [$sql, $params];
    }

    private function mapOrderByColumn(string $key): string
    {
        switch ($key) {
            case 'tipo':
                return 'a.tipo';
            case 'estado':
                return 'COALESCE(pf.estado_residencia, org.estado_sede, sm.uf)';
            case 'tempo_em_analise':
                return 'a.submetido_em';
            case 'deferido_em':
                return 'a.deferido_em';
            case 'created_at':
                return 'a.created_at';
            case 'numero_registro':
                return 'a.numero_registro';
            case 'submetido_em':
            default:
                return 'a.submetido_em';
        }
    }

    /**
     * @param array<string,mixed> $row
     */
    private function hydrate(array $row): CadastroListItem
    {
        $tipo = (string) ($row['tipo'] ?? '');
        $nome = '';
        switch ($tipo) {
            case 'PF':
                $social = (string) ($row['pf_nome_social'] ?? '');
                $full   = (string) ($row['pf_nome_completo'] ?? '');
                $nome   = $social !== '' ? $social : $full;
                $estado = isset($row['pf_estado']) && $row['pf_estado'] !== ''
                    ? (string) $row['pf_estado'] : null;
                break;
            case 'OR':
                $nome   = (string) ($row['or_nome'] ?? '');
                $estado = isset($row['or_estado']) && $row['or_estado'] !== ''
                    ? (string) $row['or_estado'] : null;
                break;
            case 'SM':
                $nome   = (string) ($row['sm_nome'] ?? '');
                $estado = isset($row['sm_uf']) && $row['sm_uf'] !== ''
                    ? (string) $row['sm_uf'] : null;
                break;
            default:
                $estado = null;
        }

        $tempoDias = null;
        if (isset($row['tempo_em_analise']) && $row['tempo_em_analise'] !== null) {
            $tempoDias = (int) round((float) $row['tempo_em_analise']);
        }

        return new CadastroListItem(
            (int) ($row['agente_id'] ?? 0),
            $tipo,
            (string) ($row['status_cadastro'] ?? ''),
            !empty($row['numero_registro']) ? (string) $row['numero_registro'] : null,
            (string) ($row['email_principal'] ?? ''),
            $nome !== '' ? $nome : '—',
            $estado,
            self::toDate($row['submetido_em'] ?? null),
            self::toDate($row['deferido_em'] ?? null),
            isset($row['analista_id']) && $row['analista_id'] !== null
                ? (int) $row['analista_id'] : null,
            isset($row['analista_nome']) && $row['analista_nome'] !== null
                ? (string) $row['analista_nome'] : null,
            $tempoDias
        );
    }

    /**
     * @param mixed $raw
     */
    private static function toDate($raw): ?DateTimeImmutable
    {
        if ($raw === null || $raw === '' || $raw === '0000-00-00 00:00:00') {
            return null;
        }
        try {
            return new DateTimeImmutable((string) $raw);
        } catch (\Exception $e) {
            return null;
        }
    }
}
