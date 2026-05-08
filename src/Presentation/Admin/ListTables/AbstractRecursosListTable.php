<?php
/**
 * Base abstrata para as List Tables de recursos.
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\ListTables
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\ListTables;

use Ibram\ParticipeIbram\Core\Helpers\RequestHelper;
use Ibram\ParticipeIbram\Presentation\Admin\Support\RecursoListItem;
use Ibram\ParticipeIbram\Presentation\Admin\Support\RecursoListQuery;

if (!class_exists('\\WP_List_Table') && defined('ABSPATH')) {
    $piAbspath = (string) constant('ABSPATH');
    $piListTablePath = $piAbspath . 'wp-admin/includes/class-wp-list-table.php';
    if (is_file($piListTablePath)) {
        require_once $piListTablePath;
    }
    unset($piAbspath, $piListTablePath);
}

/**
 * Centraliza render de colunas comuns: agente, tipo, número, data, prazo,
 * dias restantes, decisor potencial. Subclasses só precisam configurar fase
 * e título.
 */
abstract class AbstractRecursosListTable extends \WP_List_Table
{
    protected RecursoListQuery $query;

    /** @var list<RecursoListItem> */
    protected array $items_data = [];

    public function __construct(RecursoListQuery $query)
    {
        if (function_exists('parent::__construct') || method_exists(\WP_List_Table::class, '__construct')) {
            parent::__construct([
                'singular' => 'pi_recurso',
                'plural'   => 'pi_recursos',
                'ajax'     => false,
                'screen'   => null,
            ]);
        }
        $this->query = $query;
    }

    /**
     * Subclasses informam a fase do recurso (`retratacao` | `presidencia` | null).
     */
    abstract protected function fase(): ?string;

    /**
     * Slug da página admin para construir links de ação.
     */
    abstract protected function pageSlug(): string;

    /**
     * @return array<string,string>
     */
    public function get_columns(): array
    {
        return [
            'agente'            => \__('Agente', 'participe-ibram'),
            'tipo'              => \__('Tipo', 'participe-ibram'),
            'numero_original'   => \__('Nº Registro Original', 'participe-ibram'),
            'protocolado_em'    => \__('Protocolado em', 'participe-ibram'),
            'prazo_fim'         => \__('Prazo Final', 'participe-ibram'),
            'dias_restantes'    => \__('Dias restantes', 'participe-ibram'),
            'decisor_potencial' => \__('Decisor potencial', 'participe-ibram'),
            'acoes'             => \__('Ações', 'participe-ibram'),
        ];
    }

    /**
     * @return array<string,array{0:string,1:bool}>
     */
    public function get_sortable_columns(): array
    {
        return [
            'protocolado_em' => ['protocolado_em', false],
            'prazo_fim'      => ['prazo_fim', true],
            'tipo'           => ['agente_tipo', false],
        ];
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
        $page     = max(1, (int) RequestHelper::get('paged', 'absint', 1));

        $orderBy = (string) RequestHelper::get('orderby', 'sanitize_key', 'prazo_fim');
        $order   = strtoupper((string) RequestHelper::get('order', 'sanitize_key', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

        $filters = [
            'fase'         => $this->fase(),
            'agente_tipo'  => RequestHelper::get('agente_tipo', 'sanitize_key', null),
            'prazo_status' => RequestHelper::get('prazo_status', 'sanitize_key', null),
            'order_by'     => $orderBy,
            'order'        => $order,
            'limit'        => $perPage,
            'offset'       => ($page - 1) * $perPage,
        ];

        $this->items_data = $this->query->listar($filters);
        $this->items      = array_map(static fn (RecursoListItem $i): array => [
            'recurso_id'     => $i->recursoId,
            'agente_id'      => $i->agenteId,
            'agente'         => $i->agenteNomeMascarado,
            'tipo'           => $i->agenteTipo,
            'numero_original'=> $i->numeroRegistroOriginal,
            'protocolado_em' => $i->dataProtocolo->format('Y-m-d H:i'),
            'prazo_fim'      => $i->prazoFim->format('Y-m-d H:i'),
            'dias_restantes' => $i->diasRestantes,
            'decisor_potencial' => $i->decisorPotencialNome,
            'severidade'     => $i->severidadePrazo(),
        ], $this->items_data);

        if (method_exists($this, 'set_pagination_args')) {
            $this->set_pagination_args([
                'total_items' => count($this->items),
                'per_page'    => $perPage,
            ]);
        }
    }

    /**
     * @param array<string,mixed> $item
     */
    public function column_default($item, $column_name): string
    {
        $value = $item[$column_name] ?? '';
        if ($column_name === 'tipo') {
            return \esc_html((string) $value);
        }
        return \esc_html((string) ($value ?? '—'));
    }

    /**
     * @param array<string,mixed> $item
     */
    public function column_agente($item): string
    {
        $detalheUrl = $this->detalheUrl((int) $item['recurso_id']);
        return sprintf(
            '<a href="%s">%s</a>',
            \esc_url($detalheUrl),
            \esc_html((string) $item['agente'])
        );
    }

    /**
     * @param array<string,mixed> $item
     */
    public function column_dias_restantes($item): string
    {
        $dias = (int) $item['dias_restantes'];
        $sev  = (string) ($item['severidade'] ?? 'ok');
        $label = $dias < 0
            ? sprintf(\__('Vencido há %d dia(s)', 'participe-ibram'), abs($dias))
            : sprintf(\__('%d dia(s)', 'participe-ibram'), $dias);

        return sprintf(
            '<span class="pi-prazo pi-prazo--%s">%s</span>',
            \esc_attr($sev),
            \esc_html($label)
        );
    }

    /**
     * @param array<string,mixed> $item
     */
    public function column_acoes($item): string
    {
        $url = $this->detalheUrl((int) $item['recurso_id']);
        return sprintf(
            '<a class="button button-primary" href="%s">%s</a>',
            \esc_url($url),
            \esc_html__('Decidir', 'participe-ibram')
        );
    }

    /**
     * @return array<string,string>
     */
    public function get_bulk_actions(): array
    {
        return [];
    }

    /**
     * Filtros adicionais acima da tabela (agente_tipo, prazo_status).
     *
     * @param string $which `top` ou `bottom`.
     */
    protected function extra_tablenav($which): void
    {
        if ($which !== 'top') {
            return;
        }
        $tipo  = (string) RequestHelper::get('agente_tipo', 'sanitize_key', '');
        $prazo = (string) RequestHelper::get('prazo_status', 'sanitize_key', '');
        ?>
        <div class="alignleft actions pi-list-filters">
            <label class="screen-reader-text" for="filter-agente-tipo">
                <?php \esc_html_e('Filtrar por tipo de agente', 'participe-ibram'); ?>
            </label>
            <select id="filter-agente-tipo" name="agente_tipo">
                <option value=""><?php \esc_html_e('Todos os tipos', 'participe-ibram'); ?></option>
                <option value="PF" <?php \selected($tipo, 'PF'); ?>><?php \esc_html_e('Pessoa Física', 'participe-ibram'); ?></option>
                <option value="OR" <?php \selected($tipo, 'OR'); ?>><?php \esc_html_e('Organização', 'participe-ibram'); ?></option>
                <option value="SM" <?php \selected($tipo, 'SM'); ?>><?php \esc_html_e('Sistema de Museu', 'participe-ibram'); ?></option>
            </select>

            <label class="screen-reader-text" for="filter-prazo-status">
                <?php \esc_html_e('Filtrar por status de prazo', 'participe-ibram'); ?>
            </label>
            <select id="filter-prazo-status" name="prazo_status">
                <option value=""><?php \esc_html_e('Todos os prazos', 'participe-ibram'); ?></option>
                <option value="vencendo" <?php \selected($prazo, 'vencendo'); ?>><?php \esc_html_e('Vencendo (≤ 2 dias)', 'participe-ibram'); ?></option>
                <option value="vencido" <?php \selected($prazo, 'vencido'); ?>><?php \esc_html_e('Vencidos sem decisão', 'participe-ibram'); ?></option>
                <option value="com_prazo" <?php \selected($prazo, 'com_prazo'); ?>><?php \esc_html_e('Acima de 2 dias', 'participe-ibram'); ?></option>
            </select>

            <?php \submit_button(\__('Filtrar', 'participe-ibram'), 'secondary', 'filter_action', false); ?>
        </div>
        <?php
    }

    protected function detalheUrl(int $recursoId): string
    {
        $base = function_exists('admin_url') ? \admin_url('admin.php') : '/wp-admin/admin.php';
        if (function_exists('add_query_arg')) {
            return (string) \add_query_arg(
                [
                    'page'       => $this->pageSlug(),
                    'recurso_id' => $recursoId,
                    'action'     => 'view',
                ],
                $base
            );
        }
        return $base . '?page=' . rawurlencode($this->pageSlug()) . '&action=view&recurso_id=' . $recursoId;
    }
}
