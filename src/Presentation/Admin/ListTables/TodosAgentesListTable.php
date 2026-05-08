<?php
/**
 * TodosAgentesListTable — variante ampla mostrando todos os status.
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\ListTables
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\ListTables;

use Ibram\ParticipeIbram\Domain\Agente\StatusCadastro;
use Ibram\ParticipeIbram\Presentation\Admin\Helpers\AgenteSummary;
use Ibram\ParticipeIbram\Presentation\Admin\Support\CadastroListItem;

/**
 * Mesma estrutura da fila de análise, porém: (a) sem default de status (mostra
 * todos exceto rascunhos próprios de outros usuários — filtrável); (b) inclui
 * coluna `data_deferimento` quando aplicável; (c) bulk actions limitadas a
 * exportação simples (futuras waves).
 */
final class TodosAgentesListTable extends FilaAnaliseListTable
{
    /**
     * @return array<string,string>
     */
    public function get_columns(): array
    {
        return [
            'cb'                 => '<input type="checkbox" />',
            'numero_registro'    => self::trLabel('Nº Registro'),
            'nome_agente'        => self::trLabel('Nome do agente'),
            'tipo'               => self::trLabel('Tipo'),
            'email'              => self::trLabel('E-mail'),
            'estado'             => self::trLabel('UF'),
            'submetido_em'       => self::trLabel('Submetido em'),
            'deferido_em'        => self::trLabel('Deferido em'),
            'analista_atribuido' => self::trLabel('Analista'),
            'status'             => self::trLabel('Status'),
        ];
    }

    /**
     * @return array<string,array{0:string,1:bool}>
     */
    public function get_sortable_columns(): array
    {
        return [
            'numero_registro' => ['numero_registro', false],
            'tipo'            => ['tipo', false],
            'estado'          => ['estado', false],
            'submetido_em'    => ['submetido_em', false],
            'deferido_em'     => ['deferido_em', true],
        ];
    }

    /**
     * @return array<string,string>
     */
    public function get_bulk_actions(): array
    {
        return [];
    }

    /**
     * Override default status filter: when ?status= is empty, show every
     * non-rascunho status.
     *
     * @return list<string>
     */
    protected function getStatuses(): array
    {
        $raw = $this->getQuery('status', 'sanitize_key');
        if ($raw !== '') {
            return parent::getStatuses();
        }
        return array_values(array_filter(
            StatusCadastro::all(),
            static fn (string $s): bool => $s !== StatusCadastro::RASCUNHO
        ));
    }

    /**
     * @param CadastroListItem $item
     * @param string $column_name
     */
    public function column_default($item, $column_name): string
    {
        if ($column_name === 'deferido_em') {
            return $item->deferidoEm !== null
                ? self::esc($item->deferidoEm->format('d/m/Y'))
                : '—';
        }
        return parent::column_default($item, $column_name);
    }

    private static function trLabel(string $text): string
    {
        return function_exists('__') ? (string) \__($text, 'participe-ibram') : $text;
    }

    private static function esc(string $text): string
    {
        return function_exists('esc_html') ? (string) \esc_html($text) : htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
