<?php
/**
 * List Table — Recursos de Inabilitação pendentes de decisão (W5-C).
 *
 * NÃO exibe CPF, e-mail pessoal nem telefone. Nomes mascarados via PiiMasker.
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\ListTables
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\ListTables;

use Ibram\ParticipeIbram\Core\Audit\PiiMasker;
use Ibram\ParticipeIbram\Core\Helpers\RequestHelper;
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
 * Lista `wp_pi_recursos_inabilitacao` WHERE decisao IS NULL.
 *
 * Colunas: agente_nome (mascarado), edital, categoria, motivo_inabilitacao_sumario,
 * protocolado_em, decisor.
 */
final class RecursosInabilitacaoListTable extends \WP_List_Table
{
    /** @var \wpdb */
    private $wpdb;

    private string $tableRecursos;
    private string $tableInscricoes;

    public function __construct($wpdb)
    {
        if (method_exists(\WP_List_Table::class, '__construct')) {
            parent::__construct([
                'singular' => 'pi_recurso_inabilitacao',
                'plural'   => 'pi_recursos_inabilitacao',
                'ajax'     => false,
                'screen'   => null,
            ]);
        }
        $this->wpdb            = $wpdb;
        $prefix                = isset($wpdb->prefix) && is_string($wpdb->prefix) ? $wpdb->prefix : 'wp_';
        $this->tableRecursos   = $prefix . 'pi_recursos_inabilitacao';
        $this->tableInscricoes = $prefix . 'pi_inscricoes';
    }

    /**
     * @return array<string,string>
     */
    public function get_columns(): array
    {
        return [
            'agente_nome'              => \__('Agente (mascarado)', 'participe-ibram'),
            'edital'                   => \__('Edital', 'participe-ibram'),
            'categoria'                => \__('Categoria', 'participe-ibram'),
            'motivo_inabilitacao_sum'  => \__('Motivo inabilitação (resumo)', 'participe-ibram'),
            'protocolado_em'           => \__('Protocolado em', 'participe-ibram'),
            'decisor'                  => \__('Decisor', 'participe-ibram'),
            'acoes'                    => \__('Ações', 'participe-ibram'),
        ];
    }

    /**
     * @return array<string,array{0:string,1:bool}>
     */
    public function get_sortable_columns(): array
    {
        return [
            'protocolado_em' => ['r.protocolado_em', false],
        ];
    }

    /**
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

        $perPage = 25;
        $page    = max(1, (int) RequestHelper::get('paged', 'absint', 1));
        $offset  = ($page - 1) * $perPage;

        $orderBy  = (string) RequestHelper::get('orderby', 'sanitize_key', 'r.protocolado_em');
        $orderDir = strtoupper((string) RequestHelper::get('order', 'sanitize_key', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

        $allowedOrderBy = ['r.protocolado_em'];
        if (!in_array($orderBy, $allowedOrderBy, true)) {
            $orderBy = 'r.protocolado_em';
        }

        $sql = "SELECT r.id, r.inscricao_id, r.fundamentacao_md, r.protocolado_em,
                       r.decisor_id,
                       i.agente_id, i.edital_id, i.categoria_id,
                       i.motivo_inabilitacao_md
                FROM {$this->tableRecursos} r
                INNER JOIN {$this->tableInscricoes} i ON i.id = r.inscricao_id
                WHERE r.decisao IS NULL
                ORDER BY {$orderBy} {$orderDir}
                LIMIT %d OFFSET %d";

        $prepared = $this->wpdb->prepare($sql, $perPage, $offset);
        $rows     = is_string($prepared) ? $this->wpdb->get_results($prepared, ARRAY_A) : [];

        $this->items = is_array($rows) ? array_map([$this, 'buildItem'], $rows) : [];

        $countSql     = "SELECT COUNT(*) FROM {$this->tableRecursos} r WHERE r.decisao IS NULL";
        $total        = (int) $this->wpdb->get_var($countSql);

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

        // Mascaramento PII: nome do agente via WP user, aplicando maskGeneric.
        $nomeBruto = "Agente #{$agenteId}";
        if ($agenteId > 0 && function_exists('get_user_by')) {
            $wpUser = \get_user_by('id', $agenteId);
            if ($wpUser !== false && isset($wpUser->display_name)) {
                $nomeBruto = (string) $wpUser->display_name;
            }
        }
        $nomeMascarado = PiiMasker::maskGeneric($nomeBruto, 2, 2);

        // Decisor (nome do decisor quando já atribuído, ou '—').
        $decisorId   = isset($row['decisor_id']) && $row['decisor_id'] !== null ? (int) $row['decisor_id'] : 0;
        $decisorNome = '—';
        if ($decisorId > 0 && function_exists('get_user_by')) {
            $decisorUser = \get_user_by('id', $decisorId);
            if ($decisorUser !== false && isset($decisorUser->display_name)) {
                $decisorNome = (string) $decisorUser->display_name;
            }
        }

        // Sumário do motivo de inabilitação (primeiros 120 chars, sem HTML).
        $motivoMd  = (string) ($row['motivo_inabilitacao_md'] ?? '');
        $motivoSum = mb_substr(strip_tags($motivoMd), 0, 120, 'UTF-8');
        if (mb_strlen(strip_tags($motivoMd), 'UTF-8') > 120) {
            $motivoSum .= '…';
        }

        return [
            'id'                     => (int) ($row['id'] ?? 0),
            'inscricao_id'           => (int) ($row['inscricao_id'] ?? 0),
            'agente_nome'            => $nomeMascarado,
            'edital'                 => \__('Edital', 'participe-ibram') . ' #' . (int) ($row['edital_id'] ?? 0),
            'categoria'              => \__('Categoria', 'participe-ibram') . ' #' . (int) ($row['categoria_id'] ?? 0),
            'motivo_inabilitacao_sum' => $motivoSum !== '' ? $motivoSum : '—',
            'protocolado_em'         => !empty($row['protocolado_em']) ? (string) $row['protocolado_em'] : '—',
            'decisor'                => $decisorNome,
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
    public function column_acoes($item): string
    {
        $url = $this->detalheUrl((int) $item['id']);
        return sprintf(
            '<a class="button button-primary" href="%s">%s</a>',
            \esc_url($url),
            \esc_html__('Decidir', 'participe-ibram')
        );
    }

    private function detalheUrl(int $recursoId): string
    {
        $base = function_exists('admin_url') ? \admin_url('admin.php') : '/wp-admin/admin.php';
        if (function_exists('add_query_arg')) {
            return (string) \add_query_arg(
                [
                    'page'       => HabilitacaoMenuRegistry::SLUG_RECURSOS_INABILITACAO,
                    'action'     => 'view',
                    'recurso_id' => $recursoId,
                ],
                $base
            );
        }
        return $base . '?page=' . rawurlencode(HabilitacaoMenuRegistry::SLUG_RECURSOS_INABILITACAO) . '&action=view&recurso_id=' . $recursoId;
    }
}
