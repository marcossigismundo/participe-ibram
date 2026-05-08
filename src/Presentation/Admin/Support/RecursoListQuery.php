<?php
/**
 * Read-model query para listagens administrativas de Recursos.
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\Support
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\Support;

use DateTimeImmutable;
use Ibram\ParticipeIbram\Core\Audit\PiiMasker;

/**
 * Encapsula consultas otimizadas (com JOIN) para popular as List Tables de
 * Recursos sem expor SQL ad-hoc para os Controllers.
 *
 * As listagens já mascaram PII (R5 V-01): nomes de agentes aparecem com inicial
 * + redação. CPF/CNPJ não são mostrados nesses telas — apenas tipo, número de
 * registro original e identificadores não-sensíveis.
 */
final class RecursoListQuery
{
    /** @var \wpdb */
    private $wpdb;

    private string $tableRecursos;
    private string $tableAnalises;
    private string $tableAgentes;
    private string $tableAgentesPf;
    private string $tableAgentesOr;
    private string $tableAgentesSm;

    public function __construct($wpdb)
    {
        $this->wpdb           = $wpdb;
        $prefix               = isset($wpdb->prefix) && is_string($wpdb->prefix) ? $wpdb->prefix : 'wp_';
        $this->tableRecursos  = $prefix . 'pi_recursos';
        $this->tableAnalises  = $prefix . 'pi_analises';
        $this->tableAgentes   = $prefix . 'pi_agentes';
        $this->tableAgentesPf = $prefix . 'pi_agentes_pf';
        $this->tableAgentesOr = $prefix . 'pi_agentes_or';
        $this->tableAgentesSm = $prefix . 'pi_agentes_sm';
    }

    /**
     * Lista recursos abertos por fase.
     *
     * @param array{
     *     fase?: string,
     *     decisao?: string|null,
     *     agente_tipo?: string|null,
     *     prazo_status?: string|null,
     *     order_by?: string,
     *     order?: string,
     *     limit?: int,
     *     offset?: int,
     *     incluir_decididos?: bool
     * } $filters
     *
     * @return list<RecursoListItem>
     */
    public function listar(array $filters = []): array
    {
        $where  = [];
        $params = [];

        $incluirDecididos = (bool) ($filters['incluir_decididos'] ?? false);
        if (!$incluirDecididos) {
            $where[] = 'r.decisao IS NULL';
        }

        if (!empty($filters['fase'])) {
            $where[] = 'r.fase = %s';
            $params[] = (string) $filters['fase'];
        }

        if (!empty($filters['agente_tipo'])) {
            $where[] = 'a.tipo = %s';
            $params[] = strtoupper((string) $filters['agente_tipo']);
        }

        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        if (!empty($filters['prazo_status'])) {
            switch ($filters['prazo_status']) {
                case 'vencido':
                    $where[]  = 'r.prazo_fim < %s';
                    $params[] = $now;
                    break;
                case 'vencendo':
                    $where[]  = 'r.prazo_fim >= %s AND r.prazo_fim <= DATE_ADD(%s, INTERVAL 2 DAY)';
                    $params[] = $now;
                    $params[] = $now;
                    break;
                case 'com_prazo':
                    $where[]  = 'r.prazo_fim > DATE_ADD(%s, INTERVAL 2 DAY)';
                    $params[] = $now;
                    break;
            }
        }

        $orderBy = $this->safeOrderColumn((string) ($filters['order_by'] ?? 'prazo_fim'));
        $order   = strtoupper((string) ($filters['order'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

        $limit  = max(1, min(200, (int) ($filters['limit'] ?? 50)));
        $offset = max(0, (int) ($filters['offset'] ?? 0));

        $whereSql = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT r.id AS recurso_id,
                       r.analise_id AS analise_id,
                       r.fase AS fase,
                       r.decisao AS decisao,
                       r.protocolado_em AS protocolado_em,
                       r.prazo_fim AS prazo_fim,
                       a.id AS agente_id,
                       a.tipo AS agente_tipo,
                       a.numero_registro AS numero_registro,
                       an.analista_id AS analista_id,
                       pf.nome AS pf_nome,
                       org.razao_social AS org_nome,
                       sm.nome_oficial AS sm_nome
                FROM {$this->tableRecursos} r
                INNER JOIN {$this->tableAnalises} an ON an.id = r.analise_id
                INNER JOIN {$this->tableAgentes} a ON a.id = an.agente_id
                LEFT JOIN {$this->tableAgentesPf} pf ON pf.agente_id = a.id
                LEFT JOIN {$this->tableAgentesOr} org ON org.agente_id = a.id
                LEFT JOIN {$this->tableAgentesSm} sm ON sm.agente_id = a.id
                {$whereSql}
                ORDER BY {$orderBy} {$order}
                LIMIT %d OFFSET %d";

        $params[] = $limit;
        $params[] = $offset;

        if ($params !== []) {
            $prepared = $this->wpdb->prepare($sql, $params);
        } else {
            $prepared = $sql;
        }
        $rows = $this->wpdb->get_results($prepared, ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        $now = new DateTimeImmutable('now');
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->hydrateItem($row, $now);
        }

        return $out;
    }

    /**
     * Conta total de recursos por categoria de prazo.
     *
     * @return array{vencendo_hoje:int,vencidos:int,total_abertos:int}
     */
    public function dashboard(): array
    {
        $now      = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $tomorrow = (new DateTimeImmutable('now'))->modify('+1 day')->format('Y-m-d H:i:s');

        $totalSql    = "SELECT COUNT(*) FROM {$this->tableRecursos} WHERE decisao IS NULL";
        $venceSql    = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->tableRecursos} WHERE decisao IS NULL AND prazo_fim >= %s AND prazo_fim < %s",
            $now,
            $tomorrow
        );
        $vencidosSql = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->tableRecursos} WHERE decisao IS NULL AND prazo_fim < %s",
            $now
        );

        return [
            'total_abertos' => (int) $this->wpdb->get_var($totalSql),
            'vencendo_hoje' => (int) $this->wpdb->get_var($venceSql),
            'vencidos'      => (int) $this->wpdb->get_var($vencidosSql),
        ];
    }

    /**
     * @param array<string,mixed> $row
     */
    private function hydrateItem(array $row, DateTimeImmutable $now): RecursoListItem
    {
        $tipo = strtoupper((string) ($row['agente_tipo'] ?? ''));
        switch ($tipo) {
            case 'PF':
                $nomeBruto = (string) ($row['pf_nome'] ?? '');
                break;
            case 'OR':
                $nomeBruto = (string) ($row['org_nome'] ?? '');
                break;
            case 'SM':
                $nomeBruto = (string) ($row['sm_nome'] ?? '');
                break;
            default:
                $nomeBruto = '';
        }
        $nomeMascarado = $nomeBruto !== '' ? PiiMasker::maskGeneric($nomeBruto, 1, 1) : '[REDACTED]';

        try {
            $protocolado = new DateTimeImmutable((string) ($row['protocolado_em'] ?? 'now'));
        } catch (\Exception $e) {
            $protocolado = $now;
        }
        try {
            $prazoFim = new DateTimeImmutable((string) ($row['prazo_fim'] ?? 'now'));
        } catch (\Exception $e) {
            $prazoFim = $now;
        }

        $diff = $now->diff($prazoFim);
        $dias = (int) $diff->format('%r%a');

        $decisor = null;
        $analistaId = (int) ($row['analista_id'] ?? 0);
        if ($analistaId > 0 && function_exists('get_user_by')) {
            $u = \get_user_by('id', $analistaId);
            if ($u && isset($u->display_name) && is_string($u->display_name) && $u->display_name !== '') {
                $decisor = (string) $u->display_name;
            }
        }

        return new RecursoListItem(
            (int) $row['recurso_id'],
            (int) $row['analise_id'],
            (int) $row['agente_id'],
            (string) $row['fase'],
            isset($row['decisao']) && $row['decisao'] !== '' ? (string) $row['decisao'] : null,
            $tipo,
            $nomeMascarado,
            isset($row['numero_registro']) && $row['numero_registro'] !== '' ? (string) $row['numero_registro'] : null,
            $protocolado,
            $prazoFim,
            $decisor,
            $dias
        );
    }

    private function safeOrderColumn(string $column): string
    {
        $allowed = [
            'prazo_fim'      => 'r.prazo_fim',
            'protocolado_em' => 'r.protocolado_em',
            'fase'           => 'r.fase',
            'agente_tipo'    => 'a.tipo',
        ];

        return $allowed[$column] ?? 'r.prazo_fim';
    }
}
