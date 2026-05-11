<?php
/**
 * AuditDecisoesListTable — log filtrado para decisões administrativas.
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\ListTables
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\ListTables;

\Ibram\ParticipeIbram\Presentation\Admin\ListTables\ListTableShim::ensure();

/**
 * Extends AuditLogListTable com filtro pré-fixado de ações de decisão e
 * coluna extra "Decisão" (lida de dados_depois.decisao via AJAX no detalhe).
 */
final class AuditDecisoesListTable extends AuditLogListTable
{
    /** Ações que caracterizam decisões. */
    private const DECISAO_ACOES = [
        'deferir',
        'indeferir',
        'decidir_recurso_retratacao',
        'decidir_recurso_presidencia',
        'habilitacao_decidida',
        'recurso_inabilitacao_decidido',
        'apurar',
        'publicar_resultado',
    ];

    public function __construct(\Ibram\ParticipeIbram\Presentation\Admin\Support\AuditLogQuery $query)
    {
        parent::__construct($query);

        // Pré-fixa filtro — a listagem sempre restringe a ações de decisão
        $this->fixedFilters = [
            'acao_in' => self::DECISAO_ACOES,
        ];
    }

    /** @return array<string,string> */
    public function get_columns(): array
    {
        $cols = parent::get_columns();
        // Insere "Decisão" antes de "Detalhes"
        $acoes = $cols['acoes'] ?? self::tr('Detalhes');
        unset($cols['acoes']);
        $cols['decisao_label'] = self::tr('Decisão');
        $cols['acoes']         = $acoes;
        return $cols;
    }

    /**
     * @param array<string,mixed> $item
     */
    public function column_default($item, $column_name): string
    {
        if ($column_name === 'decisao_label') {
            return $this->renderDecisaoLabel($item);
        }
        return parent::column_default($item, $column_name);
    }

    /**
     * Renderiza label da decisão a partir da ação (sem expor payload completo).
     *
     * @param array<string,mixed> $item
     */
    private function renderDecisaoLabel(array $item): string
    {
        $acao = (string) ($item['acao'] ?? '');
        $map  = [
            'deferir'                        => ['label' => self::tr('Deferido'), 'variant' => 'deferir'],
            'indeferir'                      => ['label' => self::tr('Indeferido'), 'variant' => 'indeferir'],
            'decidir_recurso_retratacao'     => ['label' => self::tr('Recurso retratação'), 'variant' => 'decisao'],
            'decidir_recurso_presidencia'    => ['label' => self::tr('Recurso presidência'), 'variant' => 'decisao'],
            'habilitacao_decidida'           => ['label' => self::tr('Habilitado'), 'variant' => 'deferir'],
            'recurso_inabilitacao_decidido'  => ['label' => self::tr('Recurso inab.'), 'variant' => 'decisao'],
            'apurar'                         => ['label' => self::tr('Apurado'), 'variant' => 'neutro'],
            'publicar_resultado'             => ['label' => self::tr('Resultado publicado'), 'variant' => 'neutro'],
        ];

        if (!isset($map[$acao])) {
            return self::escHtml($acao);
        }

        return sprintf(
            '<span class="pi-audit-badge pi-audit-badge--%s">%s</span>',
            self::escAttr($map[$acao]['variant']),
            self::escHtml($map[$acao]['label'])
        );
    }
}
