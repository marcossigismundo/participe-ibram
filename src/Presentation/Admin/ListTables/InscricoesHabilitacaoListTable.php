<?php
/**
 * List Table — Inscrições em fase de habilitação (W5-C).
 *
 * NÃO exibe CPF, e-mail pessoal nem telefone. Nomes mascarados via PiiMasker.
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\ListTables
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\ListTables;

use Ibram\ParticipeIbram\Core\Audit\PiiMasker;
use Ibram\ParticipeIbram\Core\Helpers\RequestHelper;
use Ibram\ParticipeIbram\Domain\Edital\StatusInscricao;
use Ibram\ParticipeIbram\Presentation\Admin\HabilitacaoMenuRegistry;

if (!class_exists('\\WP_List_Table') && defined('ABSPATH')) {
    $piAbspath      = (string) constant('ABSPATH');
    $piListTabPath  = $piAbspath . 'wp-admin/includes/class-wp-list-table.php';
    if (is_file($piListTabPath)) {
        require_once $piListTabPath;
    }
    unset($piAbspath, $piListTabPath);
}

/**
 * Tabela de inscrições com status: INSCRITO, EM_HABILITACAO, HABILITADO, INABILITADO.
 *
 * Filtros: edital_id, categoria, status, data_inicio/fim.
 * Bulk actions: nenhuma (decisão individual por inscrição).
 * PII: agente_nome mascarado via PiiMasker::maskGeneric (1,2).
 */
final class InscricoesHabilitacaoListTable extends \WP_List_Table
{
    /**
     * Status elegíveis para exibição nesta tela.
     */
    private const STATUS_VISIVEIS = [
        StatusInscricao::INSCRITO,
        StatusInscricao::EM_HABILITACAO,
        StatusInscricao::HABILITADO,
        StatusInscricao::INABILITADO,
    ];

    /** @var \wpdb */
    private $wpdb;

    /** @var string */
    private string $tableInscricoes;

    public function __construct($wpdb)
    {
        if (method_exists(\WP_List_Table::class, '__construct')) {
            parent::__construct([
                'singular' => 'pi_inscricao',
                'plural'   => 'pi_inscricoes',
                'ajax'     => false,
                'screen'   => null,
            ]);
        }
        $this->wpdb            = $wpdb;
        $prefix                = isset($wpdb->prefix) && is_string($wpdb->prefix) ? $wpdb->prefix : 'wp_';
        $this->tableInscricoes = $prefix . 'pi_inscricoes';
    }

    /**
     * @return array<string,string>
     */
    public function get_columns(): array
    {
        return [
            'edital_titulo'  => \__('Edital', 'participe-ibram'),
            'categoria_nome' => \__('Categoria', 'participe-ibram'),
            'agente_nome'    => \__('Agente (mascarado)', 'participe-ibram'),
            'tipo_agente'    => \__('Tipo', 'participe-ibram'),
            'inscrito_em'    => \__('Inscrito em', 'participe-ibram'),
            'status'         => \__('Status', 'participe-ibram'),
            'acoes'          => \__('Ações', 'participe-ibram'),
        ];
    }

    /**
     * @return array<string,array{0:string,1:bool}>
     */
    public function get_sortable_columns(): array
    {
        return [
            'inscrito_em' => ['inscrito_em', false],
            'status'      => ['status', false],
        ];
    }

    /**
     * @return array<string,string>
     */
    public function get_bulk_actions(): array
    {
        return []; // Decisão individual — sem bulk.
    }

    public function prepare_items(): void
    {
        $columns  = $this->get_columns();
        $hidden   = [];
        $sortable = $this->get_sortable_columns();

        if (property_exists($this, '_column_headers')) {
            $this->_column_headers = [$columns, $hidden, $sortable];
        }

        $perPage = 25;
        $page    = max(1, (int) RequestHelper::get('paged', 'absint', 1));
        $offset  = ($page - 1) * $perPage;

        $orderBy  = (string) RequestHelper::get('orderby', 'sanitize_key', 'inscrito_em');
        $orderDir = strtoupper((string) RequestHelper::get('order', 'sanitize_key', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $editalId  = (int) RequestHelper::get('edital_id', 'absint', 0);
        $categoria = (string) RequestHelper::get('categoria', 'sanitize_text_field', '');
        $status    = (string) RequestHelper::get('pi_status', 'sanitize_key', '');
        $dataInicio = (string) RequestHelper::get('data_inicio', 'sanitize_text_field', '');
        $dataFim    = (string) RequestHelper::get('data_fim', 'sanitize_text_field', '');

        // Monta os placeholders dos status permitidos.
        $placeholders = implode(', ', array_fill(0, count(self::STATUS_VISIVEIS), '%s'));

        $whereParts = ["i.status IN ({$placeholders})"];
        /** @var array<int,mixed> $whereArgs */
        $whereArgs = array_values(self::STATUS_VISIVEIS);

        if ($editalId > 0) {
            $whereParts[] = 'i.edital_id = %d';
            $whereArgs[]  = $editalId;
        }
        if ($categoria !== '') {
            $whereParts[] = 'i.categoria_id = %d';
            $whereArgs[]  = (int) $categoria;
        }
        if ($status !== '' && in_array($status, self::STATUS_VISIVEIS, true)) {
            // Substitui o IN genérico por filtro exato.
            $whereParts = array_filter($whereParts, static fn (string $w): bool => strpos($w, 'IN (') === false);
            $whereParts[] = 'i.status = %s';
            $whereArgs    = array_filter($whereArgs, static fn ($v): bool => !in_array($v, self::STATUS_VISIVEIS, true));
            $whereArgs[]  = $status;
        }
        if ($dataInicio !== '') {
            $whereParts[] = 'i.inscrito_em >= %s';
            $whereArgs[]  = $dataInicio . ' 00:00:00';
        }
        if ($dataFim !== '') {
            $whereParts[] = 'i.inscrito_em <= %s';
            $whereArgs[]  = $dataFim . ' 23:59:59';
        }

        $allowedOrderBy = ['inscrito_em', 'status', 'agente_id', 'edital_id'];
        if (!in_array($orderBy, $allowedOrderBy, true)) {
            $orderBy = 'inscrito_em';
        }

        $where = implode(' AND ', $whereParts);
        $sql = "SELECT i.id, i.edital_id, i.categoria_id, i.agente_id, i.status, i.inscrito_em
                FROM {$this->tableInscricoes} i
                WHERE {$where}
                ORDER BY i.{$orderBy} {$orderDir}
                LIMIT %d OFFSET %d";

        $queryArgs   = array_merge(array_values($whereArgs), [$perPage, $offset]);
        $preparedSql = $this->wpdb->prepare($sql, $queryArgs);
        $rows = is_string($preparedSql) ? $this->wpdb->get_results($preparedSql, ARRAY_A) : [];

        $this->items = is_array($rows) ? array_map([$this, 'buildItem'], $rows) : [];

        $countSql = "SELECT COUNT(*) FROM {$this->tableInscricoes} i WHERE {$where}";
        $countArgs = array_values($whereArgs);
        $preparedCount = $this->wpdb->prepare($countSql, $countArgs);
        $total = is_string($preparedCount) ? (int) $this->wpdb->get_var($preparedCount) : 0;

        if (method_exists($this, 'set_pagination_args')) {
            $this->set_pagination_args([
                'total_items' => $total,
                'per_page'    => $perPage,
            ]);
        }
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function buildItem(array $row): array
    {
        $agenteId = (int) ($row['agente_id'] ?? 0);
        // Mascaramento de nome: busca display_name do WP, aplica maskGeneric.
        $nomeBruto = '';
        if ($agenteId > 0 && function_exists('get_user_by')) {
            $wpUser = \get_user_by('id', $agenteId);
            $nomeBruto = ($wpUser !== false && isset($wpUser->display_name)) ? (string) $wpUser->display_name : "Agente #{$agenteId}";
        } else {
            $nomeBruto = "Agente #{$agenteId}";
        }
        $nomeMascarado = PiiMasker::maskGeneric($nomeBruto, 2, 2);

        return [
            'id'             => (int) ($row['id'] ?? 0),
            'edital_id'      => (int) ($row['edital_id'] ?? 0),
            'categoria_id'   => (int) ($row['categoria_id'] ?? 0),
            'agente_id'      => $agenteId,
            'edital_titulo'  => \__('Edital', 'participe-ibram') . ' #' . (int) ($row['edital_id'] ?? 0),
            'categoria_nome' => \__('Categoria', 'participe-ibram') . ' #' . (int) ($row['categoria_id'] ?? 0),
            'agente_nome'    => $nomeMascarado,
            'tipo_agente'    => '—', // Não carregado nesta tela por performance; visível no detalhe.
            'inscrito_em'    => !empty($row['inscrito_em']) ? (string) $row['inscrito_em'] : '—',
            'status'         => (string) ($row['status'] ?? ''),
        ];
    }

    /**
     * @param array<string,mixed> $item
     */
    public function column_default($item, $column_name): string
    {
        return \esc_html((string) ($item[$column_name] ?? '—'));
    }

    /**
     * @param array<string,mixed> $item
     */
    public function column_agente_nome($item): string
    {
        $url = $this->detalheUrl((int) $item['id']);
        return sprintf(
            '<a href="%s">%s</a>',
            \esc_url($url),
            \esc_html((string) $item['agente_nome'])
        );
    }

    /**
     * @param array<string,mixed> $item
     */
    public function column_status($item): string
    {
        $status  = (string) ($item['status'] ?? '');
        $variant = $this->statusVariant($status);
        return sprintf(
            '<span class="pi-badge pi-badge--status-%s">%s</span>',
            \esc_attr($variant),
            \esc_html($this->statusLabel($status))
        );
    }

    /**
     * @param array<string,mixed> $item
     */
    public function column_acoes($item): string
    {
        $url = $this->detalheUrl((int) $item['id']);
        return sprintf(
            '<a class="button button-primary" href="%s">%s</a>',
            \esc_url($url),
            \esc_html__('Analisar', 'participe-ibram')
        );
    }

    protected function extra_tablenav($which): void
    {
        if ($which !== 'top') {
            return;
        }
        $editalId   = (int) RequestHelper::get('edital_id', 'absint', 0);
        $categoria  = (string) RequestHelper::get('categoria', 'sanitize_text_field', '');
        $status     = (string) RequestHelper::get('pi_status', 'sanitize_key', '');
        $dataInicio = (string) RequestHelper::get('data_inicio', 'sanitize_text_field', '');
        $dataFim    = (string) RequestHelper::get('data_fim', 'sanitize_text_field', '');
        ?>
        <div class="alignleft actions pi-list-filters">
            <label class="screen-reader-text" for="filter-edital-id">
                <?php \esc_html_e('Filtrar por edital', 'participe-ibram'); ?>
            </label>
            <input type="number" id="filter-edital-id" name="edital_id" min="0"
                   value="<?php echo \esc_attr($editalId > 0 ? (string) $editalId : ''); ?>"
                   placeholder="<?php \esc_attr_e('ID do edital', 'participe-ibram'); ?>">

            <label class="screen-reader-text" for="filter-categoria">
                <?php \esc_html_e('Filtrar por categoria', 'participe-ibram'); ?>
            </label>
            <input type="number" id="filter-categoria" name="categoria" min="0"
                   value="<?php echo \esc_attr($categoria); ?>"
                   placeholder="<?php \esc_attr_e('ID da categoria', 'participe-ibram'); ?>">

            <label class="screen-reader-text" for="filter-pi-status">
                <?php \esc_html_e('Filtrar por status', 'participe-ibram'); ?>
            </label>
            <select id="filter-pi-status" name="pi_status">
                <option value=""><?php \esc_html_e('Todos os status', 'participe-ibram'); ?></option>
                <?php foreach (self::STATUS_VISIVEIS as $s) : ?>
                    <option value="<?php echo \esc_attr($s); ?>" <?php \selected($status, $s); ?>>
                        <?php echo \esc_html($this->statusLabel($s)); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label class="screen-reader-text" for="filter-data-inicio">
                <?php \esc_html_e('Data inicial de inscrição', 'participe-ibram'); ?>
            </label>
            <input type="date" id="filter-data-inicio" name="data_inicio"
                   value="<?php echo \esc_attr($dataInicio); ?>">

            <label class="screen-reader-text" for="filter-data-fim">
                <?php \esc_html_e('Data final de inscrição', 'participe-ibram'); ?>
            </label>
            <input type="date" id="filter-data-fim" name="data_fim"
                   value="<?php echo \esc_attr($dataFim); ?>">

            <?php \submit_button(\__('Filtrar', 'participe-ibram'), 'secondary', 'filter_action', false); ?>
        </div>
        <?php
    }

    private function detalheUrl(int $inscricaoId): string
    {
        $base = function_exists('admin_url') ? \admin_url('admin.php') : '/wp-admin/admin.php';
        if (function_exists('add_query_arg')) {
            return (string) \add_query_arg(
                [
                    'page'         => HabilitacaoMenuRegistry::SLUG_HABILITACOES,
                    'action'       => 'view',
                    'inscricao_id' => $inscricaoId,
                ],
                $base
            );
        }
        return $base . '?page=' . rawurlencode(HabilitacaoMenuRegistry::SLUG_HABILITACOES) . '&action=view&inscricao_id=' . $inscricaoId;
    }

    private function statusLabel(string $status): string
    {
        $labels = [
            StatusInscricao::INSCRITO       => \__('Inscrito', 'participe-ibram'),
            StatusInscricao::EM_HABILITACAO => \__('Em habilitação', 'participe-ibram'),
            StatusInscricao::HABILITADO     => \__('Habilitado', 'participe-ibram'),
            StatusInscricao::INABILITADO    => \__('Inabilitado', 'participe-ibram'),
        ];
        return $labels[$status] ?? $status;
    }

    private function statusVariant(string $status): string
    {
        $variants = [
            StatusInscricao::INSCRITO       => 'info',
            StatusInscricao::EM_HABILITACAO => 'warning',
            StatusInscricao::HABILITADO     => 'success',
            StatusInscricao::INABILITADO    => 'danger',
        ];
        return $variants[$status] ?? 'default';
    }
}
