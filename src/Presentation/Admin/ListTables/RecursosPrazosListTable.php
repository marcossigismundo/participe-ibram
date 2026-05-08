<?php
/**
 * List Table — todos os recursos abertos ordenados por prazo (priorização).
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\ListTables
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\ListTables;

use Ibram\ParticipeIbram\Presentation\Admin\RecursoMenuRegistry;

final class RecursosPrazosListTable extends AbstractRecursosListTable
{
    protected function fase(): ?string
    {
        // Sem filtro — lista ambas as fases.
        return null;
    }

    protected function pageSlug(): string
    {
        return RecursoMenuRegistry::SLUG_PRAZOS;
    }

    public function get_columns(): array
    {
        $cols          = parent::get_columns();
        $cols['fase']  = \__('Fase', 'participe-ibram');
        // Reordenação leve: mover fase para após tipo.
        $reordered = [];
        foreach (['agente', 'tipo', 'fase', 'numero_original', 'protocolado_em', 'prazo_fim', 'dias_restantes', 'decisor_potencial', 'acoes'] as $key) {
            if (isset($cols[$key])) {
                $reordered[$key] = $cols[$key];
            }
        }
        return $reordered;
    }

    /**
     * @param array<string,mixed> $item
     */
    public function column_fase($item): string
    {
        $fase = (string) ($item['fase'] ?? '');
        // O array hidratado em prepare_items não inclui fase — popular a partir
        // do dataset original via mapping por recurso_id.
        foreach ($this->items_data as $row) {
            if ($row->recursoId === (int) $item['recurso_id']) {
                $fase = $row->fase;
                break;
            }
        }
        $label = $fase === 'presidencia'
            ? \__('Presidência', 'participe-ibram')
            : \__('Retratação', 'participe-ibram');
        return \esc_html($label);
    }

    /**
     * Sem botão de "Decidir" — esta tela é apenas observação. Linka para a
     * página correspondente da fase.
     *
     * @param array<string,mixed> $item
     */
    public function column_acoes($item): string
    {
        $fase = '';
        foreach ($this->items_data as $row) {
            if ($row->recursoId === (int) $item['recurso_id']) {
                $fase = $row->fase;
                break;
            }
        }
        $slug = $fase === 'presidencia'
            ? RecursoMenuRegistry::SLUG_PRESIDENCIA
            : RecursoMenuRegistry::SLUG_RETRATACAO;
        $base = function_exists('admin_url') ? \admin_url('admin.php') : '/wp-admin/admin.php';
        $url  = \add_query_arg([
            'page'       => $slug,
            'recurso_id' => (int) $item['recurso_id'],
            'action'     => 'view',
        ], $base);

        return sprintf(
            '<a class="button" href="%s">%s</a>',
            \esc_url($url),
            \esc_html__('Abrir', 'participe-ibram')
        );
    }
}
