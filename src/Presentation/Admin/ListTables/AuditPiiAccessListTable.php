<?php
/**
 * AuditPiiAccessListTable — log filtrado para acessos a dados PII.
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\ListTables
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\ListTables;

\Ibram\ParticipeIbram\Presentation\Admin\ListTables\ListTableShim::ensure();

/**
 * Extends AuditLogListTable com filtro pré-fixado de ações PII e coluna extra
 * "Campo acessado" (lido de dados_depois.campo sem expor conteúdo sensível).
 */
final class AuditPiiAccessListTable extends AuditLogListTable
{
    /** Ações que caracterizam acesso a PII. */
    private const PII_ACOES = [
        'visualizar_dado_sensivel',
        'visualizar_cpf',
        'visualizar_documento',
        'decifrar_cpf',
        'decifrar_cnpj',
        'decifrar_rg',
        'decifrar_passaporte',
    ];

    public function __construct(\Ibram\ParticipeIbram\Presentation\Admin\Support\AuditLogQuery $query)
    {
        parent::__construct($query);

        // Pré-fixa filtro — a listagem sempre restringe a ações PII
        $this->fixedFilters = [
            'acao_in' => self::PII_ACOES,
        ];
    }

    /** @return array<string,string> */
    public function get_columns(): array
    {
        $cols = parent::get_columns();
        // Insere "Campo acessado" antes de "Detalhes"
        $acoes = $cols['acoes'] ?? self::tr('Detalhes');
        unset($cols['acoes']);
        $cols['campo_acessado'] = self::tr('Campo acessado');
        $cols['acoes']          = $acoes;
        return $cols;
    }

    /**
     * @param array<string,mixed> $item
     */
    public function column_default($item, $column_name): string
    {
        if ($column_name === 'campo_acessado') {
            return $this->renderCampoAcessado($item);
        }
        return parent::column_default($item, $column_name);
    }

    /**
     * Lê "campo" de dados_depois sem expor o valor sensível.
     *
     * @param array<string,mixed> $item
     */
    private function renderCampoAcessado(array $item): string
    {
        // NOTA: dados_depois não está presente nos registros da listagem
        // (AuditLogQuery::list usa LIST_COLUMNS). O campo é lido via AJAX
        // no detalhe. Na listagem exibimos apenas o nome do campo (já auditado).
        $acao = (string) ($item['acao'] ?? '');

        // Infere o campo a partir do nome da ação
        $map = [
            'visualizar_cpf'          => 'cpf',
            'decifrar_cpf'            => 'cpf',
            'decifrar_cnpj'           => 'cnpj',
            'decifrar_rg'             => 'rg',
            'decifrar_passaporte'     => 'passaporte',
            'visualizar_documento'    => self::tr('documento'),
            'visualizar_dado_sensivel' => self::tr('(ver detalhe)'),
        ];

        $campo = $map[$acao] ?? self::tr('—');
        return '<code>' . self::escHtml($campo) . '</code>';
    }
}
