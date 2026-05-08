<?php
/**
 * ResultadosListTable — usado dentro do detalhe de uma votação apurada.
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\ListTables
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\ListTables;

use Ibram\ParticipeIbram\Domain\Votacao\Resultado;

/**
 * Lista resultados (já apurados) de uma votação. Resolve
 * `candidato_inscricao_id` → `numero_registro` + `nome_publico` via lookup
 * injetado.
 *
 * **Anti-rastreio**: nenhuma coluna exibe CPF, e-mail, raça, gênero,
 * orientação ou outros dados protegidos. A whitelist de campos exibidos é
 * trivialmente verificável: apenas `categoria_id`, `numero_registro`,
 * `nome_publico`, `total_votos`, `posicao`, `eleito`, `suplente`.
 */
final class ResultadosListTable extends \WP_List_Table
{
    /**
     * @var callable(int): array<string,mixed>
     */
    private $inscricaoLookup;

    /**
     * @var array<int,string>
     */
    private array $categoriaLabels;

    /**
     * @param callable(int): array<string,mixed> $inscricaoLookup Lookup para
     *        resolver candidato_inscricao_id em numero_registro/nome_publico.
     * @param array<int,string>                  $categoriaLabels  categoria_id
     *        => nome legível.
     */
    public function __construct(callable $inscricaoLookup, array $categoriaLabels = [])
    {
        ListTableShim::ensure();
        if (method_exists(\WP_List_Table::class, '__construct')) {
            parent::__construct([
                'singular' => 'pi_resultado',
                'plural'   => 'pi_resultados',
                'ajax'     => false,
                'screen'   => null,
            ]);
        }
        $this->inscricaoLookup = $inscricaoLookup;
        $this->categoriaLabels = $categoriaLabels;
    }

    /**
     * @return array<string,string>
     */
    public function get_columns(): array
    {
        return [
            'categoria_nome'  => \__('Categoria', 'participe-ibram'),
            'numero_registro' => \__('Número de registro', 'participe-ibram'),
            'nome_publico'    => \__('Nome público', 'participe-ibram'),
            'total_votos'     => \__('Total de votos', 'participe-ibram'),
            'posicao'         => \__('Posição', 'participe-ibram'),
            'eleito'          => \__('Eleito', 'participe-ibram'),
            'suplente'        => \__('Suplente', 'participe-ibram'),
        ];
    }

    /**
     * @return array<string,array{0:string,1:bool}>
     */
    public function get_sortable_columns(): array
    {
        return [];
    }

    /**
     * @return array<string,string>
     */
    public function get_bulk_actions(): array
    {
        return [];
    }

    /**
     * @param list<Resultado> $resultados
     */
    public function setResultados(array $resultados): void
    {
        $items = [];
        foreach ($resultados as $r) {
            $lookup = ($this->inscricaoLookup)($r->candidatoInscricaoId());
            $lookup = is_array($lookup) ? $lookup : [];
            $items[] = [
                'categoria_id'           => $r->categoriaId(),
                'categoria_nome'         => $this->categoriaLabels[$r->categoriaId()]
                    ?? sprintf(\__('Categoria #%d', 'participe-ibram'), $r->categoriaId()),
                'numero_registro'        => isset($lookup['numero_registro']) ? (string) $lookup['numero_registro'] : '',
                'nome_publico'           => isset($lookup['nome_publico']) ? (string) $lookup['nome_publico'] : '',
                'total_votos'            => $r->totalVotos(),
                'posicao'                => $r->posicao(),
                'eleito'                 => $r->eleito(),
                'suplente'               => $r->suplente(),
                'candidato_inscricao_id' => $r->candidatoInscricaoId(),
            ];
        }
        // Ordena: categoria asc, posicao asc.
        usort($items, static function (array $a, array $b): int {
            if ($a['categoria_id'] !== $b['categoria_id']) {
                return $a['categoria_id'] <=> $b['categoria_id'];
            }
            return $a['posicao'] <=> $b['posicao'];
        });
        $this->items = $items;
    }

    public function prepare_items(): void
    {
        $columns  = $this->get_columns();
        $hidden   = [];
        $sortable = $this->get_sortable_columns();
        if (property_exists($this, '_column_headers')) {
            $this->_column_headers = [$columns, $hidden, $sortable];
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
    public function column_eleito($item): string
    {
        return !empty($item['eleito'])
            ? '<span class="pi-status-badge pi-status-badge--eleito">'
                . \esc_html__('Sim', 'participe-ibram') . '</span>'
            : '<span class="pi-muted">—</span>';
    }

    /**
     * @param array<string,mixed> $item
     */
    public function column_suplente($item): string
    {
        return !empty($item['suplente'])
            ? '<span class="pi-status-badge pi-status-badge--suplente">'
                . \esc_html__('Sim', 'participe-ibram') . '</span>'
            : '<span class="pi-muted">—</span>';
    }
}
