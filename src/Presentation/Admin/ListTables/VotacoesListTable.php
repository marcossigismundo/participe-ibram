<?php
/**
 * VotacoesListTable — WP_List_Table para listagem admin de votações.
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\ListTables
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\ListTables;

use Ibram\ParticipeIbram\Core\Helpers\RequestHelper;
use Ibram\ParticipeIbram\Domain\Votacao\StatusVotacao;
use Ibram\ParticipeIbram\Presentation\Admin\Support\VotacaoListQuery;
use Ibram\ParticipeIbram\Presentation\Admin\VotacaoMenuRegistry;

/**
 * Colunas:
 *   edital_titulo, status (badge), abertura, encerramento, total_eleitores
 *   (conta de inscricoes elegiveis — não calculado nesta wave),
 *   total_votos (após encerramento), modo, hash_pre_apuracao (16 chars + ...
 *   com clique para copiar), acoes (Apurar, Publicar Resultado).
 *
 * Filtros: status. Ordenação: encerramento DESC.
 *
 * Não exibe nem aceita: eleitor_hash, agente_id, ator_id, ip_hash. Todas as
 * informações exibidas são neutras de identidade (anti-rastreio).
 */
final class VotacoesListTable extends \WP_List_Table
{
    private VotacaoListQuery $query;

    private int $currentPage = 1;

    public function __construct(VotacaoListQuery $query)
    {
        ListTableShim::ensure();
        if (method_exists(\WP_List_Table::class, '__construct')) {
            parent::__construct([
                'singular' => 'pi_votacao',
                'plural'   => 'pi_votacoes',
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
            'edital_titulo'     => \__('Edital', 'participe-ibram'),
            'status'            => \__('Status', 'participe-ibram'),
            'abertura'          => \__('Abertura', 'participe-ibram'),
            'encerramento'      => \__('Encerramento', 'participe-ibram'),
            'modo'              => \__('Modo', 'participe-ibram'),
            'total_votos'       => \__('Total de votos', 'participe-ibram'),
            'hash_pre_apuracao' => \__('Hash pré-apuração', 'participe-ibram'),
            'acoes'             => \__('Ações', 'participe-ibram'),
        ];
    }

    /**
     * @return array<string,array{0:string,1:bool}>
     */
    public function get_sortable_columns(): array
    {
        return [
            'encerramento' => ['encerramento', false],
            'abertura'     => ['abertura', false],
            'status'       => ['status', false],
        ];
    }

    /**
     * @return array<string,string>
     */
    public function get_bulk_actions(): array
    {
        // Decisões de votação são sempre individuais — sem bulk.
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

        $perPage           = 25;
        $this->currentPage = max(1, (int) RequestHelper::get('paged', 'absint', 1));

        $orderBy = (string) RequestHelper::get('orderby', 'sanitize_key', 'encerramento');
        $order   = strtoupper((string) RequestHelper::get('order', 'sanitize_key', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        $status  = (string) RequestHelper::get('status_filter', 'sanitize_key', '');

        $filters = [
            'status'  => $status !== '' ? $status : null,
            'orderby' => $orderBy,
            'order'   => $order,
            'limit'   => $perPage,
            'offset'  => ($this->currentPage - 1) * $perPage,
        ];

        $this->items = $this->query->listar($filters);
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
    public function column_edital_titulo($item): string
    {
        $id     = (int) $item['id'];
        $titulo = (string) ($item['edital_titulo'] ?? \__('(sem título)', 'participe-ibram'));
        $url    = \esc_url(VotacaoMenuRegistry::urlApurar($id));

        return sprintf(
            '<a href="%s"><strong>%s</strong></a>',
            $url,
            \esc_html($titulo)
        );
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
        return self::renderDate($item['abertura'] ?? null);
    }

    /**
     * @param array<string,mixed> $item
     */
    public function column_encerramento($item): string
    {
        return self::renderDate($item['encerramento'] ?? null);
    }

    /**
     * @param array<string,mixed> $item
     */
    public function column_modo($item): string
    {
        $modo = (string) ($item['modo'] ?? '');
        $map  = [
            'por_categoria' => \__('Por categoria', 'participe-ibram'),
            'geral'         => \__('Geral', 'participe-ibram'),
        ];
        return \esc_html($map[$modo] ?? $modo);
    }

    /**
     * @param array<string,mixed> $item
     */
    public function column_total_votos($item): string
    {
        $status = (string) ($item['status'] ?? '');
        // Antes de encerrar a votação, total de votos não é exibido (sigilo
        // até apuração — Despacho 98/2025).
        $disponivel = in_array($status, [
            StatusVotacao::ENCERRADA,
            StatusVotacao::APURADA,
        ], true);
        if (!$disponivel) {
            return '<span class="pi-muted" aria-label="'
                . esc_attr__('Disponível somente após o encerramento', 'participe-ibram')
                . '">—</span>';
        }
        return \esc_html((string) (int) ($item['total_votos'] ?? 0));
    }

    /**
     * @param array<string,mixed> $item
     */
    public function column_hash_pre_apuracao($item): string
    {
        $hash = (string) ($item['hash_pre_apuracao'] ?? '');
        if ($hash === '') {
            return '<span class="pi-muted">—</span>';
        }
        $short = substr($hash, 0, 16) . '…';
        return sprintf(
            '<button type="button" class="pi-hash-chip" data-pi-copy="%s" '
            . 'aria-label="%s">'
            . '<code>%s</code></button>',
            \esc_attr($hash),
            \esc_attr__('Copiar hash completo de pré-apuração', 'participe-ibram'),
            \esc_html($short)
        );
    }

    /**
     * @param array<string,mixed> $item
     */
    public function column_acoes($item): string
    {
        $id      = (int) $item['id'];
        $status  = (string) ($item['status'] ?? '');
        $urlApurar = \esc_url(VotacaoMenuRegistry::urlApurar($id));

        $labelGerir = \__('Gerir', 'participe-ibram');
        $btn        = sprintf(
            '<a class="button button-secondary" href="%s">%s</a>',
            $urlApurar,
            \esc_html($labelGerir)
        );

        if ($status === StatusVotacao::ENCERRADA && self::userCan('pi_apurar_votacao')) {
            $btn .= ' <a class="button button-primary" href="' . $urlApurar
                . '#apurar">' . \esc_html__('Apurar', 'participe-ibram') . '</a>';
        }
        if ($status === StatusVotacao::APURADA && self::userCan('pi_publicar_resultado')) {
            $btn .= ' <a class="button button-primary" href="' . $urlApurar
                . '#publicar">' . \esc_html__('Publicar Resultado', 'participe-ibram') . '</a>';
        }
        return $btn;
    }

    /**
     * @param string $which
     */
    protected function extra_tablenav($which): void
    {
        if ($which !== 'top') {
            return;
        }
        $current = (string) RequestHelper::get('status_filter', 'sanitize_key', '');
        ?>
        <div class="alignleft actions pi-list-filters">
            <label class="screen-reader-text" for="pi-filter-votacao-status">
                <?php \esc_html_e('Filtrar por status', 'participe-ibram'); ?>
            </label>
            <select id="pi-filter-votacao-status" name="status_filter">
                <option value=""><?php \esc_html_e('Todos os status', 'participe-ibram'); ?></option>
                <?php foreach (StatusVotacao::all() as $s) : ?>
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
            StatusVotacao::AGENDADA  => \__('Agendada', 'participe-ibram'),
            StatusVotacao::ABERTA    => \__('Aberta', 'participe-ibram'),
            StatusVotacao::ENCERRADA => \__('Encerrada', 'participe-ibram'),
            StatusVotacao::APURADA   => \__('Apurada', 'participe-ibram'),
            StatusVotacao::CANCELADA => \__('Cancelada', 'participe-ibram'),
        ];
        return $map[$status] ?? ucwords(str_replace('_', ' ', $status));
    }

    /**
     * @param mixed $raw
     */
    private static function renderDate($raw): string
    {
        if ($raw === null || $raw === '') {
            return '<span class="pi-muted">—</span>';
        }
        try {
            $dt = new \DateTimeImmutable((string) $raw);
            return \esc_html($dt->format('d/m/Y H:i'));
        } catch (\Exception $e) {
            return \esc_html((string) $raw);
        }
    }

    private static function userCan(string $cap): bool
    {
        return function_exists('current_user_can') && \current_user_can($cap);
    }
}
