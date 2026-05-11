<?php
/**
 * DashboardMetricsQuery — aggregate KPIs for the admin dashboard.
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\Support
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\Support;

/**
 * Read-model: all queries use SELECT with explicit field lists (never SELECT *),
 * whitelist-driven WHERE clauses and $wpdb->prepare() in a single call.
 *
 * Results are cached in wp_cache group 'pi_dashboard' with TTL 5 minutes.
 * Invalidate manually by executing:
 *   do_action('pi_dashboard_cache_bust');
 *
 * NEVER exposes names, e-mails, CPFs or any other PII — only numeric aggregates.
 */
final class DashboardMetricsQuery
{
    private const CACHE_GROUP = 'pi_dashboard';
    private const CACHE_TTL   = 300; // 5 minutes

    /** @var \wpdb */
    private $wpdb;

    private string $tableAgentes;
    private string $tableEditais;
    private string $tableSolicitacoes;
    private string $tableAnalises;
    private string $tableRecursos;
    private string $tableEmails;
    private string $tableMigracoes;

    public function __construct($wpdb)
    {
        $this->wpdb              = $wpdb;
        $prefix                  = isset($wpdb->prefix) && is_string($wpdb->prefix) ? $wpdb->prefix : 'wp_';
        $this->tableAgentes      = $prefix . 'pi_agentes';
        $this->tableEditais      = $prefix . 'pi_editais';
        $this->tableSolicitacoes = $prefix . 'pi_solicitacoes_titular';
        $this->tableAnalises     = $prefix . 'pi_analises';
        $this->tableRecursos     = $prefix . 'pi_recursos';
        $this->tableEmails       = $prefix . 'pi_email_queue';
        $this->tableMigracoes    = $prefix . 'pi_migrations';

        if (function_exists('add_action')) {
            \add_action('pi_dashboard_cache_bust', [$this, 'bustCache']);
        }
    }

    /**
     * Invalidate all dashboard cache entries.
     */
    public function bustCache(): void
    {
        if (function_exists('wp_cache_flush_group')) {
            \wp_cache_flush_group(self::CACHE_GROUP);
        }
        // Fallback: delete individual known keys.
        $keys = [
            'cadastros_por_status', 'cadastros_por_tipo', 'cadastros_por_estado',
            'cadastros_por_mes', 'editais_ativos', 'editais_por_status',
            'solicitacoes_lgpd_pendentes', 'recursos_vencendo', 'tempo_medio_analise',
        ];
        foreach ($keys as $key) {
            if (function_exists('wp_cache_delete')) {
                \wp_cache_delete($key, self::CACHE_GROUP);
            }
        }
    }

    /**
     * Count of agentes grouped by status_cadastro.
     *
     * @return array<string,int>  ['rascunho' => N, 'submetido' => N, ...]
     */
    public function cadastrosPorStatus(): array
    {
        $cacheKey = 'cadastros_por_status';
        $cached   = $this->cacheGet($cacheKey);
        if ($cached !== false) {
            return (array) $cached;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql  = "SELECT status_cadastro AS status, COUNT(*) AS total
                 FROM {$this->tableAgentes}
                 GROUP BY status_cadastro";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $this->wpdb->get_results($sql, ARRAY_A);

        $result = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $result[(string) $row['status']] = (int) $row['total'];
            }
        }

        $this->cacheSet($cacheKey, $result);
        return $result;
    }

    /**
     * Count of agentes grouped by tipo (PF, OR, SM).
     *
     * @return array<string,int>  ['PF' => N, 'OR' => N, 'SM' => N]
     */
    public function cadastrosPorTipoTotal(): array
    {
        $cacheKey = 'cadastros_por_tipo';
        $cached   = $this->cacheGet($cacheKey);
        if ($cached !== false) {
            return (array) $cached;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql  = "SELECT tipo, COUNT(*) AS total
                 FROM {$this->tableAgentes}
                 GROUP BY tipo";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $this->wpdb->get_results($sql, ARRAY_A);

        $result = ['PF' => 0, 'OR' => 0, 'SM' => 0];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $tipo = strtoupper((string) $row['tipo']);
                if (array_key_exists($tipo, $result)) {
                    $result[$tipo] = (int) $row['total'];
                }
            }
        }

        $this->cacheSet($cacheKey, $result);
        return $result;
    }

    /**
     * Count of agentes with status LIKE 'deferido%' grouped by UF.
     *
     * @return array<string,int>  ['SP' => N, 'RJ' => N, ...]
     */
    public function cadastrosPorEstado(): array
    {
        $cacheKey = 'cadastros_por_estado';
        $cached   = $this->cacheGet($cacheKey);
        if ($cached !== false) {
            return (array) $cached;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql  = "SELECT uf, COUNT(*) AS total
                 FROM {$this->tableAgentes}
                 WHERE status_cadastro LIKE %s
                 GROUP BY uf
                 ORDER BY total DESC";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $prepared = $this->wpdb->prepare($sql, 'deferido%');
        if ($prepared === null) {
            return [];
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $this->wpdb->get_results($prepared, ARRAY_A);

        $result = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $uf = strtoupper((string) $row['uf']);
                if ($uf !== '') {
                    $result[$uf] = (int) $row['total'];
                }
            }
        }

        $this->cacheSet($cacheKey, $result);
        return $result;
    }

    /**
     * Monthly series of submitted agentes over the past N months.
     *
     * @param int $monthsBack Number of months to look back (max 24).
     * @return list<array{mes: string, total: int}>  Ordered by month ASC.
     */
    public function cadastrosPorMes(int $monthsBack = 12): array
    {
        $monthsBack = min(24, max(1, $monthsBack));
        $cacheKey   = 'cadastros_por_mes_' . $monthsBack;
        $cached     = $this->cacheGet($cacheKey);
        if ($cached !== false) {
            return (array) $cached;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT DATE_FORMAT(submetido_em, '%%Y-%%m') AS mes, COUNT(*) AS total
                FROM {$this->tableAgentes}
                WHERE submetido_em >= DATE_SUB(NOW(), INTERVAL %d MONTH)
                  AND status_cadastro != %s
                GROUP BY mes
                ORDER BY mes ASC";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $prepared = $this->wpdb->prepare($sql, $monthsBack, 'rascunho');
        if ($prepared === null) {
            return [];
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $this->wpdb->get_results($prepared, ARRAY_A);

        $result = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $result[] = [
                    'mes'   => (string) $row['mes'],
                    'total' => (int) $row['total'],
                ];
            }
        }

        $this->cacheSet($cacheKey, $result);
        return $result;
    }

    /**
     * Count of currently active editais.
     */
    public function editaisAtivos(): int
    {
        $cacheKey = 'editais_ativos';
        $cached   = $this->cacheGet($cacheKey);
        if ($cached !== false) {
            return (int) $cached;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql  = "SELECT COUNT(*) FROM {$this->tableEditais} WHERE status = %s";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $prepared = $this->wpdb->prepare($sql, 'ativo');
        if ($prepared === null) {
            return 0;
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $count = (int) $this->wpdb->get_var($prepared);

        $this->cacheSet($cacheKey, $count);
        return $count;
    }

    /**
     * Count of editais grouped by status.
     *
     * @return array<string,int>
     */
    public function editaisPorStatus(): array
    {
        $cacheKey = 'editais_por_status';
        $cached   = $this->cacheGet($cacheKey);
        if ($cached !== false) {
            return (array) $cached;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql  = "SELECT status, COUNT(*) AS total FROM {$this->tableEditais} GROUP BY status";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $this->wpdb->get_results($sql, ARRAY_A);

        $result = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $result[(string) $row['status']] = (int) $row['total'];
            }
        }

        $this->cacheSet($cacheKey, $result);
        return $result;
    }

    /**
     * Count of pending LGPD requests (status = 'pendente' or 'em_andamento').
     */
    public function solicitacoesLgpdPendentes(): int
    {
        $cacheKey = 'solicitacoes_lgpd_pendentes';
        $cached   = $this->cacheGet($cacheKey);
        if ($cached !== false) {
            return (int) $cached;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT COUNT(*) FROM {$this->tableSolicitacoes}
                WHERE status IN (%s, %s)";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $prepared = $this->wpdb->prepare($sql, 'pendente', 'em_andamento');
        if ($prepared === null) {
            return 0;
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $count = (int) $this->wpdb->get_var($prepared);

        $this->cacheSet($cacheKey, $count);
        return $count;
    }

    /**
     * Count of recursos with prazo_fim <= NOW() + 2 days (about to expire).
     */
    public function recursosVencendo(): int
    {
        $cacheKey = 'recursos_vencendo';
        $cached   = $this->cacheGet($cacheKey);
        if ($cached !== false) {
            return (int) $cached;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT COUNT(*) FROM {$this->tableRecursos}
                WHERE status IN (%s, %s)
                  AND prazo_fim IS NOT NULL
                  AND prazo_fim <= DATE_ADD(NOW(), INTERVAL %d DAY)
                  AND prazo_fim >= NOW()";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $prepared = $this->wpdb->prepare($sql, 'pendente', 'em_analise', 2);
        if ($prepared === null) {
            return 0;
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $count = (int) $this->wpdb->get_var($prepared);

        $this->cacheSet($cacheKey, $count);
        return $count;
    }

    /**
     * Average analysis time in days (last 30 days, concluded analyses only).
     */
    public function tempoMedioAnalise(): ?float
    {
        $cacheKey = 'tempo_medio_analise';
        $cached   = $this->cacheGet($cacheKey);
        if ($cached !== false) {
            return $cached === null ? null : (float) $cached;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT AVG(TIMESTAMPDIFF(SECOND, iniciado_em, concluido_em)) / 86400.0 AS media_dias
                FROM {$this->tableAnalises}
                WHERE concluido_em IS NOT NULL
                  AND concluido_em >= DATE_SUB(NOW(), INTERVAL %d DAY)";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $prepared = $this->wpdb->prepare($sql, 30);
        if ($prepared === null) {
            return null;
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $raw = $this->wpdb->get_var($prepared);

        $value = $raw !== null ? round((float) $raw, 1) : null;
        $this->cacheSet($cacheKey, $value);
        return $value;
    }

    /**
     * All KPIs bundled for the AJAX endpoint.
     *
     * @return array<string,mixed>
     */
    public function allMetrics(int $monthsBack = 12): array
    {
        return [
            'cadastros_por_status'      => $this->cadastrosPorStatus(),
            'cadastros_por_tipo'        => $this->cadastrosPorTipoTotal(),
            'cadastros_por_estado'      => $this->cadastrosPorEstado(),
            'cadastros_por_mes'         => $this->cadastrosPorMes($monthsBack),
            'editais_ativos'            => $this->editaisAtivos(),
            'editais_por_status'        => $this->editaisPorStatus(),
            'solicitacoes_lgpd'         => $this->solicitacoesLgpdPendentes(),
            'recursos_vencendo'         => $this->recursosVencendo(),
            'tempo_medio_analise_dias'  => $this->tempoMedioAnalise(),
        ];
    }

    /**
     * Derived: cadastros pending review (submetido + em_analise).
     */
    public function cadastrosPendentes(): int
    {
        $porStatus = $this->cadastrosPorStatus();
        return ($porStatus['submetido'] ?? 0) + ($porStatus['em_analise'] ?? 0);
    }

    /**
     * Derived: cadastros in analysis.
     */
    public function cadastrosEmAnalise(): int
    {
        $porStatus = $this->cadastrosPorStatus();
        return $porStatus['em_analise'] ?? 0;
    }

    /* -------------------- Cache helpers -------------------- */

    /** @return mixed */
    private function cacheGet(string $key)
    {
        if (!function_exists('wp_cache_get')) {
            return false;
        }
        return \wp_cache_get($key, self::CACHE_GROUP);
    }

    /** @param mixed $value */
    private function cacheSet(string $key, $value): void
    {
        if (!function_exists('wp_cache_set')) {
            return;
        }
        \wp_cache_set($key, $value, self::CACHE_GROUP, self::CACHE_TTL);
    }
}
