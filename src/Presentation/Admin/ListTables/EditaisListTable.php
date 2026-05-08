<?php
/**
 * EditaisListTable — WP_List_Table para a listagem de editais.
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\ListTables
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\ListTables;

use Ibram\ParticipeIbram\Core\Helpers\RequestHelper;
use Ibram\ParticipeIbram\Domain\Edital\StatusEdital;
use Ibram\ParticipeIbram\Presentation\Admin\EditalMenuRegistry;
use Ibram\ParticipeIbram\Presentation\Admin\Support\EditalListQuery;

if (!class_exists('\\WP_List_Table') && defined('ABSPATH')) {
    $piAbspath      = (string) constant('ABSPATH');
    $piListTablePath = $piAbspath . 'wp-admin/includes/class-wp-list-table.php';
    if (is_file($piListTablePath)) {
        require_once $piListTablePath;
    }
    unset($piAbspath, $piListTablePath);
}

/**
 * Colunas: cb (desabilitado — bulk action é decisão individual), titulo,
 * status (badge), abertura, encerramento_inscricoes, num_categorias,
 * num_inscricoes, criado_por, acoes.
 *
 * Nunca exibe dados de agente (CPF, email) — AGENTS-PLAN ponto 1.
 */
final class EditaisListTable extends \WP_List_Table
{
    private EditalListQuery $query;

    /** @var int Página corrente. */
    private int $currentPage = 1;

    public function __construct(EditalListQuery $query)
    {
        if (method_exists(\WP_List_Table::class, '__construct')) {
            parent::__construct([
                'singular' => 'pi_edital',
                'plural'   => 'pi_editais',
                'ajax'     => false,
                'screen'   => null,
            ]);
        }
        $this->query = $query;
    }

    /**
     * @return array<string,string>
     */
    public function get_columns(): array
    {
        return [
            'titulo'                  => \__('Título', 'participe-ibram'),
            'status'                  => \__('Status', 'participe-ibram'),
            'abertura'                => \__('Abertura', 'participe-ibram'),
            'encerramento_inscricoes' => \__('Encerr. Inscrições', 'participe-ibram'),
            'num_categorias'          => \__('Categorias', 'participe-ibram'),
            'num_inscricoes'          => \__('Inscrições', 'participe-ibram'),
            'criado_por'              => \__('Criado por', 'participe-ibram'),
            'acoes'                   => \__('Ações', 'participe-ibram'),
        ];
    }

    /**
     * @return array<string,array{0:string,1:bool}>
     */
    public function get_sortable_columns(): array
    {
        return [
            'titulo'   => ['titulo', false],
            'status'   => ['status', false],
            'abertura' => ['abertura', false],
        ];
    }

    /**
     * No bulk actions — edital decisions are individual.
     *
     * @return array<string,string>
     */
    public function get_bulk_actions(): array
    {
        return [];
    }

    public function prepare_items(): void
    {
        $columns  = $this->get_columns();
        $hidden   = [];
        $sortable = $this->get_sortable_columns();

        if (property_exists($this, '_column_headers')) {
            $this->_column_headers = [$columns, $hidden, $sortable];
        }

        $perPage  = 25;
        $this->currentPage = max(1, (int) RequestHelper::get('paged', 'absint', 1));

        $orderBy = (string) RequestHelper::get('orderby', 'sanitize_key', 'created_at');
        $order   = strtoupper((string) RequestHelper::get('order', 'sanitize_key', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        $status  = (string) RequestHelper::get('status_filter', 'sanitize_key', '');

        $filters = [
            'status'  => $status !== '' ? $status : null,
            'orderby' => $orderBy,
            'order'   => $order,
            'limit'   => $perPage,
            'offset'  => ($this->currentPage - 1) * $perPage,
        ];

        $rows        = $this->query->listar($filters);
        $this->items = $rows;
        $total       = $this->query->total($status !== '' ? $status : null);

        if (method_exists($this, 'set_pagination_args')) {
            $this->set_pagination_args([
                'total_items' => $total,
                'per_page'    => $perPage,
                'total_pages' => (int) ceil($total / $perPage),
            ]);
        }
    }

    /**
     * @param array<string,mixed> $item
     */
    public function column_default($item, $column_name): string
    {
        $value = $item[$column_name] ?? '—';
        return \esc_html((string) $value);
    }

    /**
     * @param array<string,mixed> $item
     */
    public function column_titulo($item): string
    {
        $id      = (int) $item['id'];
        $titulo  = \esc_html((string) $item['titulo']);
        $url     = \esc_url(EditalMenuRegistry::urlEditalDetalhes($id));
        $urlEdit = \esc_url(EditalMenuRegistry::urlEditalEdit($id));

        $actions = [];
        if (function_exists('current_user_can') && \current_user_can(EditalMenuRegistry::CAP_LISTAR)) {
            $actions['view'] = sprintf(
                '<a href="%s">%s</a>',
                $url,
                \esc_html__('Ver', 'participe-ibram')
            );
        }
        if (function_exists('current_user_can') && \current_user_can(EditalMenuRegistry::CAP_EDITAR)) {
            $actions['edit'] = sprintf(
                '<a href="%s">%s</a>',
                $urlEdit,
                \esc_html__('Editar', 'participe-ibram')
            );
        }

        $rowActions = method_exists($this, 'row_actions') ? $this->row_actions($actions) : '';

        return sprintf('<a href="%s"><strong>%s</strong></a>', $url, $titulo) . $rowActions;
    }

    /**
     * @param array<string,mixed> $item
     */
    public function column_status($item): string
    {
        $status = (string) ($item['status'] ?? '');
        $label  = self::statusLabel($status);
        $mod    = \esc_attr(str_replace('_', '-', $status));
        return sprintf(
            '<span class="pi-status-badge pi-status-badge--%s">%s</span>',
            $mod,
            \esc_html($label)
        );
    }

    /**
     * @param array<string,mixed> $item
     */
    public function column_abertura($item): string
    {
        $raw = $item['abertura'] ?? null;
        if ($raw === null || $raw === '') {
            return '<span class="pi-muted">—</span>';
        }
        try {
            $dt = new \DateTimeImmutable((string) $raw);
            return \esc_html($dt->format('d/m/Y'));
        } catch (\Exception $e) {
            return \esc_html((string) $raw);
        }
    }

    /**
     * @param array<string,mixed> $item
     */
    public function column_encerramento_inscricoes($item): string
    {
        $raw = $item['encerramento_inscricoes'] ?? null;
        if ($raw === null || $raw === '') {
            return '<span class="pi-muted">—</span>';
        }
        try {
            $dt = new \DateTimeImmutable((string) $raw);
            return \esc_html($dt->format('d/m/Y'));
        } catch (\Exception $e) {
            return \esc_html((string) $raw);
        }
    }

    /**
     * @param array<string,mixed> $item
     */
    public function column_criado_por($item): string
    {
        $userId = (int) ($item['criado_por'] ?? 0);
        if ($userId <= 0) {
            return '—';
        }
        // Display display_name only (never email/login — PII guard).
        $user = function_exists('get_userdata') ? \get_userdata($userId) : false;
        if ($user === false || !$user) {
            return \esc_html(sprintf(\__('Usuário #%d', 'participe-ibram'), $userId));
        }
        return \esc_html((string) $user->display_name);
    }

    /**
     * @param array<string,mixed> $item
     */
    public function column_acoes($item): string
    {
        $id  = (int) $item['id'];
        $url = \esc_url(EditalMenuRegistry::urlEditalDetalhes($id));
        return sprintf(
            '<a class="button button-secondary" href="%s">%s</a>',
            $url,
            \esc_html__('Detalhes', 'participe-ibram')
        );
    }

    /**
     * Filtros por status acima da tabela.
     *
     * @param string $which `top` | `bottom`
     */
    protected function extra_tablenav($which): void
    {
        if ($which !== 'top') {
            return;
        }
        $current = (string) RequestHelper::get('status_filter', 'sanitize_key', '');
        $statuses = StatusEdital::all();
        ?>
        <div class="alignleft actions pi-list-filters">
            <label class="screen-reader-text" for="pi-filter-status">
                <?php \esc_html_e('Filtrar por status', 'participe-ibram'); ?>
            </label>
            <select id="pi-filter-status" name="status_filter">
                <option value=""><?php \esc_html_e('Todos os status', 'participe-ibram'); ?></option>
                <?php foreach ($statuses as $s) : ?>
                    <option value="<?php echo \esc_attr($s); ?>"<?php \selected($current, $s); ?>>
                        <?php echo \esc_html(self::statusLabel($s)); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php \submit_button(\__('Filtrar', 'participe-ibram'), 'secondary', 'filter_action', false); ?>
        </div>
        <?php
    }

    private static function statusLabel(string $status): string
    {
        $map = [
            StatusEdital::RASCUNHO            => \__('Rascunho', 'participe-ibram'),
            StatusEdital::PUBLICADO           => \__('Publicado', 'participe-ibram'),
            StatusEdital::INSCRICOES_ABERTAS  => \__('Inscrições Abertas', 'participe-ibram'),
            StatusEdital::EM_HABILITACAO      => \__('Em Habilitação', 'participe-ibram'),
            StatusEdital::EM_RECURSO          => \__('Em Recurso', 'participe-ibram'),
            StatusEdital::VOTACAO_ABERTA      => \__('Votação Aberta', 'participe-ibram'),
            StatusEdital::VOTACAO_ENCERRADA   => \__('Votação Encerrada', 'participe-ibram'),
            StatusEdital::ENCERRADO           => \__('Encerrado', 'participe-ibram'),
        ];
        return $map[$status] ?? \esc_html(ucwords(str_replace('_', ' ', $status)));
    }
}
