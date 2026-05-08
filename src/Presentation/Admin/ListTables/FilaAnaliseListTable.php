<?php
/**
 * FilaAnaliseListTable — WP_List_Table para a fila de análise técnica.
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\ListTables
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\ListTables;

use Ibram\ParticipeIbram\Domain\Agente\StatusCadastro;
use Ibram\ParticipeIbram\Domain\Agente\TipoAgente;
use Ibram\ParticipeIbram\Presentation\Admin\Helpers\AgenteSummary;
use Ibram\ParticipeIbram\Presentation\Admin\MenuRegistry;
use Ibram\ParticipeIbram\Presentation\Admin\Support\CadastroListItem;
use Ibram\ParticipeIbram\Presentation\Admin\Support\CadastroListQuery;

// Inside the WP admin context, `WP_List_Table` lives in the global namespace
// and may need to be loaded manually (it's not bootstrapped on every admin
// screen). For unit-test contexts where ABSPATH does not exist we fall back
// to a minimal shim — declared via {@see ListTableShim::ensure()} which uses
// `eval()` confined to the global namespace.
\Ibram\ParticipeIbram\Presentation\Admin\ListTables\ListTableShim::ensure();

/**
 * Tabela admin para a fila de análise técnica de cadastros submetidos / em
 * análise. Usa {@see CadastroListQuery} como read-model.
 *
 * Convenções:
 *  - NUNCA inclui CPF/CNPJ na listagem (R5 V-01).
 *  - Acoes inline (row actions) usam URLs com nonce escopado por user.
 *  - Bulk actions invocam handlers via Controller (assumir/liberar análise).
 */
class FilaAnaliseListTable extends \WP_List_Table
{
    /** @var array<int,string> Status default exibidos na fila. */
    public const DEFAULT_STATUSES = [
        StatusCadastro::SUBMETIDO,
        StatusCadastro::EM_ANALISE,
    ];

    private CadastroListQuery $query;

    /** @var array<string,mixed> */
    private array $appliedFilters = [];

    public function __construct(CadastroListQuery $query)
    {
        parent::__construct([
            'singular' => 'pi_cadastro',
            'plural'   => 'pi_cadastros',
            'ajax'     => false,
            'screen'   => 'participe-ibram_cadastros',
        ]);
        $this->query = $query;
    }

    /**
     * Define columns (cb + numero_registro + nome + tipo + email + datas + analista).
     *
     * @return array<string,string>
     */
    public function get_columns(): array
    {
        return [
            'cb'                 => '<input type="checkbox" />',
            'numero_registro'    => self::tr('Nº Registro'),
            'nome_agente'        => self::tr('Nome do agente'),
            'tipo'               => self::tr('Tipo'),
            'email'              => self::tr('E-mail'),
            'estado'             => self::tr('UF'),
            'submetido_em'       => self::tr('Submetido em'),
            'tempo_em_analise'   => self::tr('Tempo em análise'),
            'analista_atribuido' => self::tr('Analista'),
            'status'             => self::tr('Status'),
        ];
    }

    /**
     * @return array<string,array{0:string,1:bool}>
     */
    public function get_sortable_columns(): array
    {
        return [
            'numero_registro'  => ['numero_registro', false],
            'tipo'             => ['tipo', false],
            'estado'           => ['estado', false],
            'submetido_em'     => ['submetido_em', true],
            'tempo_em_analise' => ['tempo_em_analise', false],
        ];
    }

    /**
     * @return array<string,string>
     */
    public function get_bulk_actions(): array
    {
        return [
            'assumir_analise' => self::tr('Assumir análise'),
            'liberar_analise' => self::tr('Liberar análise'),
        ];
    }

    /**
     * @param CadastroListItem $item
     */
    public function column_cb($item): string
    {
        return sprintf(
            '<label class="screen-reader-text" for="cb-select-%1$d">%3$s</label>'
            . '<input type="checkbox" id="cb-select-%1$d" name="agente_ids[]" value="%1$d" />',
            $item->agenteId,
            $item->agenteId,
            self::escAttr(self::tr('Selecionar cadastro'))
        );
    }

    /**
     * @param CadastroListItem $item
     * @param string $column_name
     */
    public function column_default($item, $column_name): string
    {
        switch ($column_name) {
            case 'numero_registro':
                return self::escHtml($item->numeroRegistro ?? '—');
            case 'tipo':
                return $this->renderTipoBadge($item->tipo);
            case 'email':
                return self::escHtml($item->emailPrincipal);
            case 'estado':
                return self::escHtml($item->estado ?? '—');
            case 'submetido_em':
                return $item->submetidoEm !== null
                    ? self::escHtml($item->submetidoEm->format('d/m/Y H:i'))
                    : '—';
            case 'tempo_em_analise':
                if ($item->tempoEmAnaliseDias === null) {
                    return '—';
                }
                return sprintf(
                    /* translators: %d: dias */
                    self::escHtml(self::tr('%d dia(s)')),
                    $item->tempoEmAnaliseDias
                );
            case 'analista_atribuido':
                if ($item->analistaNome !== null) {
                    return self::escHtml($item->analistaNome);
                }
                return '<em>' . self::escHtml(self::tr('Não atribuído')) . '</em>';
            case 'status':
                return $this->renderStatusBadge($item->statusCadastro);
            default:
                return '';
        }
    }

    /**
     * @param CadastroListItem $item
     */
    public function column_nome_agente($item): string
    {
        $detailsUrl = MenuRegistry::urlAgenteDetalhes($item->agenteId);
        $title      = sprintf(
            '<strong><a href="%s">%s</a></strong>',
            self::escUrl($detailsUrl),
            self::escHtml($item->nome)
        );

        $actions = [
            'visualizar' => sprintf(
                '<a href="%s">%s</a>',
                self::escUrl($detailsUrl),
                self::escHtml(self::tr('Visualizar'))
            ),
        ];

        $currentUserId = function_exists('get_current_user_id')
            ? (int) \get_current_user_id() : 0;

        if ($item->statusCadastro === StatusCadastro::SUBMETIDO) {
            $assumirUrl = $this->actionUrl('assumir_analise', $item->agenteId, $currentUserId);
            $actions['assumir_analise'] = sprintf(
                '<a class="pi-action-confirm" href="%s" data-pi-confirm="%s">%s</a>',
                self::escUrl($assumirUrl),
                self::escAttr(self::tr('Confirmar atribuição deste cadastro à sua análise?')),
                self::escHtml(self::tr('Assumir análise'))
            );
        }

        if ($item->statusCadastro === StatusCadastro::EM_ANALISE
            && $item->analistaId !== null
            && $item->analistaId === $currentUserId
        ) {
            $actions['continuar'] = sprintf(
                '<a href="%s">%s</a>',
                self::escUrl($detailsUrl),
                self::escHtml(self::tr('Continuar análise'))
            );
        }

        return $title . $this->row_actions($actions);
    }

    /**
     * Discover and apply filters from $_GET; load items + counts.
     */
    public function prepare_items(): void
    {
        $columns               = $this->get_columns();
        $sortable              = $this->get_sortable_columns();
        $hidden                = [];
        $this->_column_headers = [$columns, $hidden, $sortable];

        $perPage = 25;
        $page    = $this->get_pagenum();
        $offset  = ($page - 1) * $perPage;

        $filters = $this->collectFilters();
        $filters['limit']  = $perPage;
        $filters['offset'] = $offset;

        $this->appliedFilters = $filters;

        $items = $this->query->listar($filters);
        $total = $this->query->contar($filters);

        $this->items = $items;

        $this->set_pagination_args([
            'total_items' => $total,
            'per_page'    => $perPage,
            'total_pages' => (int) ceil(max(1, $total) / $perPage),
        ]);
    }

    /**
     * Collect filters from $_GET (with wp_unslash + sanitize).
     *
     * @return array<string,mixed>
     */
    public function collectFilters(): array
    {
        $statuses = $this->getStatuses();

        $tipo = $this->getQuery('tipo', 'sanitize_key');
        $tipo = $tipo !== '' && in_array(strtoupper($tipo), TipoAgente::all(), true)
            ? strtoupper($tipo) : null;

        $estado = $this->getQuery('estado', 'sanitize_key');
        $estado = $estado !== '' && strlen($estado) === 2 ? strtoupper($estado) : null;

        $analistaId = $this->getQuery('analista_id', 'absint');
        $analistaId = $analistaId !== '' ? (int) $analistaId : null;
        if ($analistaId !== null && $analistaId <= 0) {
            $analistaId = null;
        }

        $search = $this->getQuery('s', 'sanitize_text_field');
        $search = $search !== '' ? trim($search) : null;

        $orderBy = $this->getQuery('orderby', 'sanitize_key');
        $orderBy = $orderBy !== '' ? $orderBy : 'submetido_em';

        $order = strtoupper($this->getQuery('order', 'sanitize_key'));
        $order = $order !== '' ? $order : 'ASC';

        return [
            'status'      => $statuses,
            'tipo'        => $tipo,
            'estado'      => $estado,
            'analista_id' => $analistaId,
            'search'      => $search,
            'order_by'    => $orderBy,
            'order'       => $order,
        ];
    }

    /**
     * Render the toolbar of filters above the table.
     */
    public function extra_tablenav($which): void
    {
        if ($which !== 'top') {
            return;
        }
        $tipo   = (string) ($this->appliedFilters['tipo'] ?? '');
        $estado = (string) ($this->appliedFilters['estado'] ?? '');

        echo '<div class="alignleft actions">';
        echo '<label for="pi-filter-tipo" class="screen-reader-text">' . self::escHtml(self::tr('Filtrar por tipo')) . '</label>';
        echo '<select name="tipo" id="pi-filter-tipo">';
        echo '<option value="">' . self::escHtml(self::tr('Todos os tipos')) . '</option>';
        foreach (TipoAgente::all() as $t) {
            $selected = $t === $tipo ? ' selected' : '';
            echo '<option value="' . self::escAttr($t) . '"' . $selected . '>'
                . self::escHtml(AgenteSummary::tipoLabel($t)) . '</option>';
        }
        echo '</select>';

        echo '<label for="pi-filter-estado" class="screen-reader-text">' . self::escHtml(self::tr('Filtrar por UF')) . '</label>';
        echo '<input type="text" name="estado" id="pi-filter-estado" maxlength="2" '
            . 'value="' . self::escAttr($estado) . '" '
            . 'placeholder="' . self::escAttr(self::tr('UF')) . '" '
            . 'style="width:60px" />';

        echo '<input type="submit" class="button" value="' . self::escAttr(self::tr('Filtrar')) . '" />';
        echo '</div>';
    }

    /**
     * Default statuses for the queue (override via ?status=...).
     *
     * @return list<string>
     */
    protected function getStatuses(): array
    {
        $raw = $this->getQuery('status', 'sanitize_key');
        if ($raw === '') {
            return self::DEFAULT_STATUSES;
        }
        $list = array_filter(array_map('trim', explode(',', $raw)), static function ($s): bool {
            try {
                StatusCadastro::fromString((string) $s);
                return true;
            } catch (\Throwable $e) {
                return false;
            }
        });
        return $list === [] ? self::DEFAULT_STATUSES : array_values($list);
    }

    /**
     * Helper that reads $_GET safely (wp_unslash + sanitize).
     */
    protected function getQuery(string $key, string $sanitizer): string
    {
        if (!isset($_GET[$key])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return '';
        }
        $raw = function_exists('wp_unslash')
            ? \wp_unslash($_GET[$key]) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            : $_GET[$key]; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        if (is_array($raw)) {
            return '';
        }
        $raw = (string) $raw;

        switch ($sanitizer) {
            case 'sanitize_key':
                return function_exists('sanitize_key')
                    ? (string) \sanitize_key($raw) : strtolower(preg_replace('/[^a-z0-9_\-]/i', '', $raw) ?? '');
            case 'absint':
                return (string) (int) $raw;
            case 'sanitize_text_field':
            default:
                return function_exists('sanitize_text_field')
                    ? (string) \sanitize_text_field($raw)
                    : trim(preg_replace('/[\r\n\t\0\x0B]+/', ' ', strip_tags($raw)) ?? '');
        }
    }

    private function actionUrl(string $action, int $agenteId, int $userId): string
    {
        $baseUrl = function_exists('admin_url') ? \admin_url('admin.php') : 'admin.php';
        $args    = [
            'page'      => 'participe-ibram_cadastros',
            'pi_action' => $action,
            'agente_id' => $agenteId,
        ];
        if (function_exists('wp_create_nonce')) {
            $args['_wpnonce'] = \wp_create_nonce('pi_admin_' . $action . '_' . $userId);
        }
        return $baseUrl . '?' . http_build_query($args);
    }

    private function renderTipoBadge(string $tipo): string
    {
        $label = AgenteSummary::tipoLabel($tipo);
        return sprintf(
            '<span class="pi-badge pi-badge--tipo pi-badge--tipo-%s">%s</span>',
            self::escAttr(strtolower($tipo)),
            self::escHtml($label)
        );
    }

    private function renderStatusBadge(string $statusCode): string
    {
        try {
            $status = StatusCadastro::fromString($statusCode);
        } catch (\Throwable $e) {
            return self::escHtml($statusCode);
        }
        $badge = AgenteSummary::statusBadge($status);
        return sprintf(
            '<span class="pi-badge pi-badge--status pi-badge--status-%s">%s</span>',
            self::escAttr($badge['variant']),
            self::escHtml($badge['label'])
        );
    }

    /* ------------------------ helpers ------------------------ */

    private static function tr(string $text): string
    {
        return function_exists('__') ? (string) \__($text, 'participe-ibram') : $text;
    }

    private static function escHtml(string $text): string
    {
        return function_exists('esc_html') ? (string) \esc_html($text) : htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    private static function escAttr(string $text): string
    {
        return function_exists('esc_attr') ? (string) \esc_attr($text) : htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    private static function escUrl(string $url): string
    {
        return function_exists('esc_url') ? (string) \esc_url($url) : htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    }
}
