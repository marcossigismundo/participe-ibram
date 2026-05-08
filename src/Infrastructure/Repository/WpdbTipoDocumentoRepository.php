<?php
/**
 * Implementação WPDB do TipoDocumentoRepository.
 *
 * @package Ibram\ParticipeIbram\Infrastructure\Repository
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Infrastructure\Repository;

use Ibram\ParticipeIbram\Domain\Documento\DocumentoNotFound;
use Ibram\ParticipeIbram\Domain\Documento\TipoDocumento;
use Ibram\ParticipeIbram\Domain\Documento\TipoDocumentoRepository;

/**
 * Lê tipos de documento configurados em `{prefix}pi_tipos_documento`.
 *
 * Sem mutações; tipos são seedados via migration (V001) e raramente alterados
 * em runtime.
 */
final class WpdbTipoDocumentoRepository implements TipoDocumentoRepository
{
    /**
     * Códigos de documento exigidos exclusivamente para coletivos sem CNPJ
     * (VOCABULARIES.md §13: `OR sem CNPJ` exige `carta_indicacao_coletivo`).
     */
    private const CODES_OR_SEM_CNPJ = ['carta_indicacao_coletivo'];

    /**
     * Códigos exigidos APENAS quando OR tem CNPJ.
     */
    private const CODES_OR_COM_CNPJ = ['cnpj', 'estatuto', 'ata_posse'];

    /** @var \wpdb */
    private $wpdb;

    private string $tableName;

    /**
     * @param \wpdb       $wpdb
     * @param string|null $tableName Override.
     */
    public function __construct($wpdb, ?string $tableName = null)
    {
        $this->wpdb      = $wpdb;
        $prefix          = isset($wpdb->prefix) && is_string($wpdb->prefix) ? $wpdb->prefix : 'wp_';
        $this->tableName = $tableName ?? ($prefix . 'pi_tipos_documento');
    }

    public function findById(int $id): TipoDocumento
    {
        if ($id <= 0) {
            throw DocumentoNotFound::tipoComId($id);
        }
        $sql = $this->wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            'SELECT * FROM `' . $this->tableName . '` WHERE id = %d LIMIT 1',
            $id
        );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $row = $this->wpdb->get_row($sql, ARRAY_A);
        if (!is_array($row)) {
            throw DocumentoNotFound::tipoComId($id);
        }

        return self::hydrate($row);
    }

    public function findByCodigo(string $codigo): TipoDocumento
    {
        $codigo = strtolower(trim($codigo));
        if ($codigo === '') {
            throw DocumentoNotFound::tipoComCodigo($codigo);
        }
        $sql = $this->wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            'SELECT * FROM `' . $this->tableName . '` WHERE codigo = %s LIMIT 1',
            $codigo
        );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $row = $this->wpdb->get_row($sql, ARRAY_A);
        if (!is_array($row)) {
            throw DocumentoNotFound::tipoComCodigo($codigo);
        }

        return self::hydrate($row);
    }

    /**
     * @return list<TipoDocumento>
     */
    public function listAtivos(): array
    {
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = 'SELECT * FROM `' . $this->tableName . '` WHERE ativo = 1 ORDER BY ordem ASC, nome ASC';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $this->wpdb->get_results($sql, ARRAY_A);

        return self::hydrateMany(is_array($rows) ? $rows : []);
    }

    /**
     * Lista tipos obrigatórios para um agente, considerando se OR tem CNPJ.
     *
     * Estratégia:
     *  1. SELECT cujos `obrigatorio_para` (CSV) contenha o tipo de agente.
     *  2. Em PHP, filtra por `temCnpj`:
     *     - Se OR sem CNPJ: exclui {cnpj, estatuto, ata_posse}; mantém
     *       `carta_indicacao_coletivo`.
     *     - Se OR com CNPJ: exclui `carta_indicacao_coletivo`; mantém
     *       {cnpj, estatuto, ata_posse}.
     *
     * @return list<TipoDocumento>
     */
    public function findObrigatoriosPara(string $tipoAgente, bool $temCnpj = true): array
    {
        $tipo = strtoupper(trim($tipoAgente));
        if ($tipo === '') {
            return [];
        }

        // CSV pode ser: 'PF', 'OR', 'PF,OR', 'PF,OR,SM', etc.
        // Match por substring com âncoras de vírgula/borda.
        $like1 = '%' . $this->wpdb->esc_like($tipo) . '%';
        $sql   = $this->wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            'SELECT * FROM `' . $this->tableName . '` '
                . "WHERE ativo = 1 AND obrigatorio_para IS NOT NULL AND obrigatorio_para <> '' "
                . 'AND obrigatorio_para LIKE %s '
                . 'ORDER BY ordem ASC, nome ASC',
            $like1
        );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows  = $this->wpdb->get_results($sql, ARRAY_A);
        $tipos = self::hydrateMany(is_array($rows) ? $rows : []);

        // Filtra por match exato no CSV (LIKE pode trazer falsos positivos
        // hipotéticos como 'PRE' ao buscar 'PR').
        $exact = array_values(array_filter(
            $tipos,
            static fn (TipoDocumento $t): bool => $t->isObrigatorioParaTipoAgente($tipo)
        ));

        if ($tipo !== 'OR') {
            return $exact;
        }

        // Regra OR + CNPJ.
        if ($temCnpj) {
            return array_values(array_filter(
                $exact,
                static fn (TipoDocumento $t): bool => !in_array($t->codigo(), self::CODES_OR_SEM_CNPJ, true)
            ));
        }

        return array_values(array_filter(
            $exact,
            static fn (TipoDocumento $t): bool => !in_array($t->codigo(), self::CODES_OR_COM_CNPJ, true)
        ));
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function hydrate(array $row): TipoDocumento
    {
        return new TipoDocumento(
            isset($row['id']) ? (int) $row['id'] : null,
            (string) ($row['codigo'] ?? ''),
            (string) ($row['nome'] ?? ''),
            isset($row['descricao']) && $row['descricao'] !== null ? (string) $row['descricao'] : null,
            isset($row['obrigatorio_para']) && $row['obrigatorio_para'] !== null
                ? (string) $row['obrigatorio_para']
                : null,
            (string) ($row['mime_permitidos'] ?? ''),
            (int) ($row['tamanho_max_kb'] ?? 0),
            !empty($row['ativo']),
            (int) ($row['ordem'] ?? 0)
        );
    }

    /**
     * @param list<array<string,mixed>> $rows
     *
     * @return list<TipoDocumento>
     */
    private static function hydrateMany(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = self::hydrate($row);
        }

        return $out;
    }
}
