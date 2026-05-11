<?php
/**
 * AuditLogListTable — WP_List_Table para o log de auditoria.
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\ListTables
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\ListTables;

use Ibram\ParticipeIbram\Core\Audit\PiiMasker;
use Ibram\ParticipeIbram\Presentation\Admin\Support\AuditLogQuery;

\Ibram\ParticipeIbram\Presentation\Admin\ListTables\ListTableShim::ensure();

/**
 * Exibe wp_pi_audit_log com filtros (entidade, acao, data range, ator) e
 * paginação. NUNCA exibe dados_antes/dados_depois na listagem.
 *
 * Whitelist defensiva de colunas renderizadas:
 *   ocorrido_em, entidade, entidade_id, acao, ator, ip_hash, acoes
 *
 * Sem bulk actions (log imutável).
 */
class AuditLogListTable extends \WP_List_Table
{
    public const DEFAULT_PER_PAGE = 50;
    public const MAX_PER_PAGE     = 200;

    /** @var array<int,string> Ações que mapeiam para categoria "visualizar" */
    private const ACAO_VISUALIZAR = [
        'visualizar_dado_sensivel',
        'visualizar_cpf',
        'visualizar_documento',
        'decifrar_cpf',
        'decifrar_cnpj',
        'decifrar_rg',
        'decifrar_passaporte',
    ];

    /** @var array<int,string> Ações que mapeiam para categoria "deferir/indeferir" */
    private const ACAO_DECISAO = [
        'deferir',
        'indeferir',
        'decidir_recurso_retratacao',
        'decidir_recurso_presidencia',
        'habilitacao_decidida',
        'recurso_inabilitacao_decidido',
        'apurar',
        'publicar_resultado',
    ];

    /** @var array<int,string> Ações que mapeiam para categoria "criar/atualizar" */
    private const ACAO_ESCRITA = ['criar', 'atualizar', 'excluir', 'deletar'];

    /** Filtros aplicados na renderização atual. */
    protected array $appliedFilters = [];

    /** Filtros pré-fixados pela sub-classe (PII/Decisões). */
    protected array $fixedFilters = [];

    protected AuditLogQuery $query;

    public function __construct(AuditLogQuery $query)
    {
        parent::__construct([
            'singular' => 'pi_audit_log',
            'plural'   => 'pi_audit_logs',
            'ajax'     => false,
            'screen'   => 'participe-ibram_audit_log',
        ]);
        $this->query = $query;
    }

    /** @return array<string,string> */
    public function get_columns(): array
    {
        return [
            'ocorrido_em' => self::tr('Data/hora'),
            'entidade'    => self::tr('Entidade'),
            'entidade_id' => self::tr('ID'),
            'acao'        => self::tr('Ação'),
            'ator'        => self::tr('Ator'),
            'ip_hash'     => self::tr('IP (hash)'),
            'acoes'       => self::tr('Detalhes'),
        ];
    }

    /** @return array<string,array{0:string,1:bool}> */
    public function get_sortable_columns(): array
    {
        return [
            'ocorrido_em' => ['ocorrido_em', true],
            'entidade'    => ['entidade', false],
            'acao'        => ['acao', false],
        ];
    }

    /** Sem bulk actions — audit log é imutável. */
    public function get_bulk_actions(): array
    {
        return [];
    }

    public function prepare_items(): void
    {
        $columns               = $this->get_columns();
        $sortable              = $this->get_sortable_columns();
        $hidden                = [];
        $this->_column_headers = [$columns, $hidden, $sortable];

        $perPage = min(self::MAX_PER_PAGE, max(1, (int) $this->getQuery('per_page', 'absint') ?: self::DEFAULT_PER_PAGE));
        $page    = $this->get_pagenum();

        $filters = array_merge($this->fixedFilters, $this->collectFilters());

        $this->appliedFilters = $filters;

        $items = $this->query->list($filters, $page, $perPage);
        $total = $this->query->count($filters);

        $this->items = $items;

        $this->set_pagination_args([
            'total_items' => $total,
            'per_page'    => $perPage,
            'total_pages' => (int) ceil(max(1, $total) / $perPage),
        ]);
    }

    /**
     * @param array<string,mixed> $item
     */
    public function column_default($item, $column_name): string
    {
        // Whitelist defensiva: apenas colunas declaradas em get_columns() são renderizadas.
        switch ($column_name) {
            case 'ocorrido_em':
                return self::escHtml((string) ($item['ocorrido_em'] ?? '—'));

            case 'entidade':
                return self::escHtml((string) ($item['entidade'] ?? '—'));

            case 'entidade_id':
                $val = $item['entidade_id'] ?? null;
                return $val !== null ? self::escHtml((string) $val) : '—';

            case 'acao':
                return $this->renderAcaoBadge((string) ($item['acao'] ?? ''));

            case 'ator':
                return $this->renderAtor($item);

            case 'ip_hash':
                $hash = (string) ($item['ip_hash'] ?? '');
                if ($hash === '') {
                    return '—';
                }
                return '<code>' . self::escHtml(substr($hash, 0, 8)) . '&hellip;</code>';

            case 'acoes':
                return $this->renderVerDetalhes($item);

            default:
                // NUNCA renderiza colunas fora da whitelist — LGPD
                return '';
        }
    }

    /**
     * Toolbar de filtros (acima da tabela).
     */
    public function extra_tablenav($which): void
    {
        if ($which !== 'top') {
            return;
        }

        $entidade = (string) ($this->appliedFilters['entidade'] ?? '');
        $acao     = (string) ($this->appliedFilters['acao'] ?? '');
        $dataDe   = (string) ($this->appliedFilters['data_de'] ?? '');
        $dataAte  = (string) ($this->appliedFilters['data_ate'] ?? '');
        $atorId   = (int) ($this->appliedFilters['ator_id'] ?? 0);

        echo '<div class="alignleft actions pi-audit-filters">';

        // Filtro entidade
        echo '<label for="pi-audit-entidade" class="screen-reader-text">' . self::escHtml(self::tr('Filtrar por entidade')) . '</label>';
        echo '<select name="entidade" id="pi-audit-entidade">';
        echo '<option value="">' . self::escHtml(self::tr('Todas as entidades')) . '</option>';
        foreach ($this->getEntidadeOptions() as $opt) {
            $selected = $opt === $entidade ? ' selected' : '';
            echo '<option value="' . self::escAttr($opt) . '"' . $selected . '>' . self::escHtml($opt) . '</option>';
        }
        echo '</select> ';

        // Filtro acao (apenas se não fixada pela sub-classe)
        if (empty($this->fixedFilters['acao']) && empty($this->fixedFilters['acao_in'])) {
            echo '<label for="pi-audit-acao" class="screen-reader-text">' . self::escHtml(self::tr('Filtrar por ação')) . '</label>';
            echo '<select name="acao" id="pi-audit-acao">';
            echo '<option value="">' . self::escHtml(self::tr('Todas as ações')) . '</option>';
            foreach ($this->getAcaoOptions() as $opt) {
                $selected = $opt === $acao ? ' selected' : '';
                echo '<option value="' . self::escAttr($opt) . '"' . $selected . '>' . self::escHtml($opt) . '</option>';
            }
            echo '</select> ';
        }

        // Filtro data_de
        echo '<label for="pi-audit-data-de" class="screen-reader-text">' . self::escHtml(self::tr('Data de')) . '</label>';
        echo '<input type="date" name="data_de" id="pi-audit-data-de" value="' . self::escAttr($dataDe) . '" /> ';

        // Filtro data_ate
        echo '<label for="pi-audit-data-ate" class="screen-reader-text">' . self::escHtml(self::tr('Data até')) . '</label>';
        echo '<input type="date" name="data_ate" id="pi-audit-data-ate" value="' . self::escAttr($dataAte) . '" /> ';

        // Filtro ator_id
        echo '<label for="pi-audit-ator" class="screen-reader-text">' . self::escHtml(self::tr('Ator (ID)')) . '</label>';
        echo '<input type="number" name="ator_id" id="pi-audit-ator" min="1" style="width:80px" '
            . 'value="' . self::escAttr($atorId > 0 ? (string) $atorId : '') . '" '
            . 'placeholder="' . self::escAttr(self::tr('ID do usuário')) . '" /> ';

        echo '<input type="submit" class="button" value="' . self::escAttr(self::tr('Filtrar')) . '" />';
        echo '</div>';
    }

    /**
     * Coleta filtros do $_GET (wp_unslash + sanitize).
     *
     * @return array<string,mixed>
     */
    public function collectFilters(): array
    {
        $entidade = $this->getQuery('entidade', 'sanitize_text_field');
        $acao     = $this->getQuery('acao', 'sanitize_text_field');
        $dataDe   = $this->getQuery('data_de', 'sanitize_text_field');
        $dataAte  = $this->getQuery('data_ate', 'sanitize_text_field');
        $atorId   = (int) $this->getQuery('ator_id', 'absint');
        $orderby  = $this->getQuery('orderby', 'sanitize_key');
        $order    = strtoupper($this->getQuery('order', 'sanitize_key'));

        return [
            'entidade'  => $entidade !== '' ? $entidade : null,
            'acao'      => $acao !== '' ? $acao : null,
            'data_de'   => $this->validateDate($dataDe) ? $dataDe : null,
            'data_ate'  => $this->validateDate($dataAte) ? $dataAte : null,
            'ator_id'   => $atorId > 0 ? $atorId : null,
            'orderby'   => $orderby,
            'order'     => $order,
        ];
    }

    /** @return list<string> */
    protected function getEntidadeOptions(): array
    {
        return ['agente', 'edital', 'inscricao', 'votacao', 'recurso', 'consentimento', 'audit_log'];
    }

    /** @return list<string> */
    protected function getAcaoOptions(): array
    {
        return [
            'criar', 'atualizar', 'excluir',
            'deferir', 'indeferir',
            'visualizar_dado_sensivel', 'visualizar_cpf', 'visualizar_documento',
            'decidir_recurso_retratacao', 'decidir_recurso_presidencia',
            'habilitacao_decidida', 'recurso_inabilitacao_decidido',
            'apurar', 'publicar_resultado',
            'export_audit_log', 'view_audit_detail',
        ];
    }

    /* ==================== render helpers ==================== */

    protected function renderAcaoBadge(string $acao): string
    {
        $category = $this->categorizeAcao($acao);
        return sprintf(
            '<span class="pi-audit-badge pi-audit-badge--%s" title="%s">%s</span>',
            self::escAttr($category),
            self::escAttr($acao),
            self::escHtml($acao)
        );
    }

    protected function categorizeAcao(string $acao): string
    {
        if (in_array($acao, self::ACAO_VISUALIZAR, true) || strpos($acao, 'decifrar_') === 0) {
            return 'visualizar';
        }
        if (in_array($acao, self::ACAO_DECISAO, true)) {
            if (in_array($acao, ['deferir', 'habilitacao_decidida'], true)) {
                return 'deferir';
            }
            if (in_array($acao, ['indeferir', 'recurso_inabilitacao_decidido'], true)) {
                return 'indeferir';
            }
            return 'decisao';
        }
        if (in_array($acao, self::ACAO_ESCRITA, true)) {
            return 'escrita';
        }
        return 'outro';
    }

    /**
     * @param array<string,mixed> $item
     */
    protected function renderAtor(array $item): string
    {
        $atorId = isset($item['ator_id']) && $item['ator_id'] !== null
            ? (int) $item['ator_id']
            : null;

        if ($atorId === null) {
            return '<em>' . self::escHtml(self::tr('sistema')) . '</em>';
        }

        // Resolve login mascarado — apenas exibe ID para não vazar login em tela
        $login = $this->resolveUserLogin($atorId);
        if ($login === null) {
            return self::escHtml('#' . $atorId);
        }

        // Mascara login: exibe primeiros 2 chars + *** (não é PII mas é boa prática)
        $masked = PiiMasker::maskGeneric($login, 2, 0);
        return self::escHtml($masked . ' (#' . $atorId . ')');
    }

    /**
     * @param array<string,mixed> $item
     */
    protected function renderVerDetalhes(array $item): string
    {
        $id = (int) ($item['id'] ?? 0);
        if ($id <= 0) {
            return '';
        }

        $url = $this->buildDetalheUrl($id);

        return sprintf(
            '<button type="button" class="button button-small pi-audit-ver-detalhe" '
            . 'data-audit-id="%d" data-nonce="%s" '
            . 'aria-haspopup="dialog" aria-label="%s">%s</button>',
            $id,
            self::escAttr(
                function_exists('wp_create_nonce')
                    ? (string) \wp_create_nonce('pi_audit_detalhe_' . $id)
                    : ''
            ),
            self::escAttr(sprintf(self::tr('Ver detalhes do registro #%d'), $id)),
            self::escHtml(self::tr('Ver detalhes'))
        );

        // Also provide direct link for no-JS fallback
        $link = ' <a href="' . self::escUrl($url) . '" class="pi-audit-detalhe-link">'
            . self::escHtml(self::tr('Link direto')) . '</a>';

        return $link;
    }

    protected function buildDetalheUrl(int $id): string
    {
        $base = function_exists('admin_url') ? \admin_url('admin.php') : 'admin.php';
        return $base . '?' . http_build_query([
            'page'   => 'participe-ibram_audit_log',
            'action' => 'view',
            'id'     => $id,
        ]);
    }

    protected function resolveUserLogin(int $userId): ?string
    {
        if (!function_exists('get_userdata')) {
            return null;
        }
        $user = \get_userdata($userId);
        if ($user === false || !isset($user->user_login)) {
            return null;
        }
        return (string) $user->user_login;
    }

    private function validateDate(string $date): bool
    {
        if ($date === '') {
            return false;
        }
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d !== false && $d->format('Y-m-d') === $date;
    }

    /* ====================== base helpers ==================== */

    protected function getQuery(string $key, string $sanitizer): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (!isset($_GET[$key])) {
            return '';
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $raw = function_exists('wp_unslash') ? \wp_unslash($_GET[$key]) : $_GET[$key]; // phpcs:ignore
        if (is_array($raw)) {
            return '';
        }
        $raw = (string) $raw;

        switch ($sanitizer) {
            case 'sanitize_key':
                return function_exists('sanitize_key')
                    ? (string) \sanitize_key($raw)
                    : strtolower(preg_replace('/[^a-z0-9_\-]/i', '', $raw) ?? '');
            case 'absint':
                return (string) abs((int) $raw);
            case 'sanitize_text_field':
            default:
                return function_exists('sanitize_text_field')
                    ? (string) \sanitize_text_field($raw)
                    : trim(strip_tags($raw));
        }
    }

    protected static function tr(string $text): string
    {
        return function_exists('__') ? (string) \__($text, 'participe-ibram') : $text;
    }

    protected static function escHtml(string $text): string
    {
        return function_exists('esc_html') ? (string) \esc_html($text) : htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    protected static function escAttr(string $text): string
    {
        return function_exists('esc_attr') ? (string) \esc_attr($text) : htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    protected static function escUrl(string $url): string
    {
        return function_exists('esc_url') ? (string) \esc_url($url) : htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    }
}
