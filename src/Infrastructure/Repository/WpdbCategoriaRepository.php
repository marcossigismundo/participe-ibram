<?php
/**
 * Repositório `wpdb` para Categoria de edital (SCHEMA §4).
 *
 * @package Ibram\ParticipeIbram\Infrastructure\Repository
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Infrastructure\Repository;

use Ibram\ParticipeIbram\Domain\Edital\Categoria;
use RuntimeException;

/**
 * Persistência de {@see Categoria} contra `{$wpdb->prefix}pi_edital_categorias`.
 *
 * `documentos_exigidos` é serializado como JSON (LONGTEXT, MySQL 5.6+).
 */
final class WpdbCategoriaRepository
{
    /** @var \wpdb */
    private $wpdb;

    private string $tableName;

    public function __construct($wpdb, ?string $tableName = null)
    {
        $this->wpdb      = $wpdb;
        $prefix          = isset($wpdb->prefix) && is_string($wpdb->prefix) ? $wpdb->prefix : 'wp_';
        $this->tableName = $tableName ?? ($prefix . 'pi_edital_categorias');
    }

    public function findById(int $id): ?Categoria
    {
        if ($id <= 0) {
            return null;
        }
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->tableName} WHERE id = %d", $id),
            ARRAY_A
        );

        return is_array($row) ? self::hydrate($row) : null;
    }

    /**
     * @return array<int,Categoria>
     */
    public function findByEdital(int $editalId): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->tableName} WHERE edital_id = %d ORDER BY ordem ASC, id ASC",
                $editalId
            ),
            ARRAY_A
        );
        if (!is_array($rows)) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $out[] = self::hydrate($row);
        }

        return $out;
    }

    /**
     * Lista categorias de um edital que aceitam o tipo de agente informado.
     *
     * Filtragem feita via FIND_IN_SET porque `tipos_agente_elegivel` é CSV.
     *
     * @return array<int,Categoria>
     */
    public function findElegivelParaTipoAgente(int $editalId, string $tipoAgente): array
    {
        $tipoNorm = strtoupper(trim($tipoAgente));
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->tableName}
                 WHERE edital_id = %d AND FIND_IN_SET(%s, tipos_agente_elegivel) > 0
                 ORDER BY ordem ASC, id ASC",
                $editalId,
                $tipoNorm
            ),
            ARRAY_A
        );
        if (!is_array($rows)) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $out[] = self::hydrate($row);
        }

        return $out;
    }

    /**
     * @return int ID da categoria persistida.
     */
    public function save(Categoria $categoria): int
    {
        $payload = self::toRow($categoria);
        $formats = self::columnFormats();

        if ($categoria->id() === null) {
            $result = $this->wpdb->insert($this->tableName, $payload, $formats);
            if ($result === false) {
                throw new RuntimeException('Falha ao inserir categoria de edital.');
            }

            return (int) $this->wpdb->insert_id;
        }

        $result = $this->wpdb->update(
            $this->tableName,
            $payload,
            ['id' => (int) $categoria->id()],
            $formats,
            ['%d']
        );
        if ($result === false) {
            throw new RuntimeException('Falha ao atualizar categoria de edital.');
        }

        return (int) $categoria->id();
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function hydrate(array $row): Categoria
    {
        $documentos = [];
        if (isset($row['documentos_exigidos']) && is_string($row['documentos_exigidos']) && $row['documentos_exigidos'] !== '') {
            $decoded = json_decode((string) $row['documentos_exigidos'], true);
            if (is_array($decoded)) {
                foreach ($decoded as $cod) {
                    if (is_string($cod)) {
                        $documentos[] = $cod;
                    }
                }
            }
        }

        return new Categoria(
            isset($row['id']) ? (int) $row['id'] : null,
            (int) ($row['edital_id'] ?? 0),
            (string) ($row['nome'] ?? ''),
            isset($row['descricao_md']) && $row['descricao_md'] !== null ? (string) $row['descricao_md'] : null,
            (int) ($row['num_vagas'] ?? 1),
            (int) ($row['num_suplentes'] ?? 0),
            (string) ($row['tipos_agente_elegivel'] ?? 'PF,OR,SM'),
            isset($row['criterios_md']) && $row['criterios_md'] !== null ? (string) $row['criterios_md'] : null,
            $documentos,
            (int) ($row['ordem'] ?? 0)
        );
    }

    /**
     * @return array<string,mixed>
     */
    private static function toRow(Categoria $categoria): array
    {
        $json = function_exists('wp_json_encode')
            ? wp_json_encode(array_values($categoria->documentosExigidos()))
            : json_encode(array_values($categoria->documentosExigidos()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return [
            'edital_id'             => $categoria->editalId(),
            'nome'                  => $categoria->nome(),
            'descricao_md'          => $categoria->descricaoMd(),
            'num_vagas'             => $categoria->numVagas(),
            'num_suplentes'         => $categoria->numSuplentes(),
            'tipos_agente_elegivel' => $categoria->tiposAgenteElegivel(),
            'criterios_md'          => $categoria->criteriosMd(),
            'documentos_exigidos'   => is_string($json) ? $json : '[]',
            'ordem'                 => $categoria->ordem(),
        ];
    }

    /**
     * @return array<int,string>
     */
    private static function columnFormats(): array
    {
        return [
            '%d', // edital_id
            '%s', // nome
            '%s', // descricao_md
            '%d', // num_vagas
            '%d', // num_suplentes
            '%s', // tipos_agente_elegivel
            '%s', // criterios_md
            '%s', // documentos_exigidos
            '%d', // ordem
        ];
    }
}
