<?php
/**
 * Implementação `wpdb` do {@see VocabularioRepository} com cache (TD-17).
 *
 * @package Ibram\ParticipeIbram\Infrastructure\Repository
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Infrastructure\Repository;

use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Domain\Vocabulario\ItemVocabulario;
use Ibram\ParticipeIbram\Domain\Vocabulario\TipoVocabulario;
use Ibram\ParticipeIbram\Domain\Vocabulario\VocabularioRepository;
use InvalidArgumentException;
use RuntimeException;

/**
 * Persistência em `{$prefix}pi_vocabularios` com cache em
 * `wp_object_cache` (TD-17).
 *
 * Estratégia de cache:
 *  - `listByTipo()` consulta `wp_cache_get($key, 'pi_vocabularios')`. Em miss,
 *    lê do BD e grava com TTL de 1h.
 *  - A chave inclui o número de versão (`pi_vocabulario_version`, autoload).
 *    Qualquer `save()` ou `desativar()` incrementa a versão **e** invalida
 *    o cache do tipo afetado — invalidação dupla por segurança em
 *    multi-server (Memcached/Redis) onde delete pode falhar silenciosamente.
 *  - `validar()` reutiliza `listByTipo()` (cache hit comum em formulários).
 *
 * Segurança:
 *  - Nomes de tabela e ORDER BY são whitelisted ou hardcoded.
 *  - Valores via `$wpdb->prepare()`.
 */
final class WpdbVocabularioRepository implements VocabularioRepository
{
    /**
     * Group do object cache.
     */
    private const CACHE_GROUP = 'pi_vocabularios';

    /**
     * TTL do cache em segundos (1 hora).
     */
    private const CACHE_TTL = 3600;

    /**
     * Option name (autoload) usada como cache buster.
     */
    private const VERSION_OPTION = 'pi_vocabulario_version';

    /**
     * Whitelist de colunas para ORDER BY.
     *
     * @var array<int,string>
     */
    private const ORDERBY_WHITELIST = ['ordem', 'rotulo', 'valor', 'id'];

    /** @var \wpdb */
    private $wpdb;

    private AuditLogger $audit;

    private string $tableName;

    /**
     * @param \wpdb       $wpdb      WordPress DB facade.
     * @param AuditLogger $audit     Audit logger (Wave 1).
     * @param string|null $tableName Override (defaults to `{prefix}pi_vocabularios`).
     */
    public function __construct($wpdb, AuditLogger $audit, ?string $tableName = null)
    {
        $this->wpdb      = $wpdb;
        $this->audit     = $audit;
        $prefix          = isset($wpdb->prefix) && is_string($wpdb->prefix) ? $wpdb->prefix : 'wp_';
        $this->tableName = $tableName ?? ($prefix . 'pi_vocabularios');
    }

    public function findById(int $id): ?ItemVocabulario
    {
        if ($id <= 0) {
            return null;
        }
        $sql = $this->wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            "SELECT id, tipo, valor, rotulo, descricao, ordem, ativo, metadata
             FROM `{$this->tableName}` WHERE id = %d LIMIT 1",
            $id
        );
        /** @var array<string,mixed>|null $row */
        $row = $this->wpdb->get_row($sql, ARRAY_A);

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function findByValor(string $tipo, string $valor): ?ItemVocabulario
    {
        $tipo = $this->assertTipo($tipo);
        $valor = trim($valor);
        if ($valor === '') {
            return null;
        }
        $sql = $this->wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            "SELECT id, tipo, valor, rotulo, descricao, ordem, ativo, metadata
             FROM `{$this->tableName}` WHERE tipo = %s AND valor = %s LIMIT 1",
            $tipo,
            $valor
        );
        /** @var array<string,mixed>|null $row */
        $row = $this->wpdb->get_row($sql, ARRAY_A);

        return is_array($row) ? $this->hydrate($row) : null;
    }

    /**
     * @return array<int,ItemVocabulario>
     */
    public function listByTipo(string $tipo, bool $apenasAtivos = true): array
    {
        $tipo = $this->assertTipo($tipo);

        $cacheKey = $this->cacheKey($tipo, $apenasAtivos);
        $cached   = function_exists('wp_cache_get')
            ? wp_cache_get($cacheKey, self::CACHE_GROUP)
            : false;
        if (is_array($cached)) {
            return array_map([$this, 'hydrate'], $cached);
        }

        $orderby = $this->resolveOrderby('ordem');
        $where   = 'tipo = %s';
        $params  = [$tipo];
        if ($apenasAtivos) {
            $where .= ' AND ativo = %d';
            $params[] = 1;
        }

        $sql = $this->wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            "SELECT id, tipo, valor, rotulo, descricao, ordem, ativo, metadata
             FROM `{$this->tableName}`
             WHERE {$where}
             ORDER BY {$orderby} ASC, rotulo ASC",
            $params
        );

        /** @var array<int,array<string,mixed>>|null $rows */
        $rows = $this->wpdb->get_results($sql, ARRAY_A);
        if (!is_array($rows)) {
            $rows = [];
        }

        if (function_exists('wp_cache_set')) {
            wp_cache_set($cacheKey, $rows, self::CACHE_GROUP, self::CACHE_TTL);
        }

        return array_map([$this, 'hydrate'], $rows);
    }

    public function save(ItemVocabulario $item): int
    {
        $tipo = $this->assertTipo($item->tipo());
        $row  = [
            'tipo'      => $tipo,
            'valor'     => $item->valor(),
            'rotulo'    => $item->rotulo(),
            'descricao' => $item->descricao(),
            'ordem'     => $item->ordem(),
            'ativo'     => $item->ativo() ? 1 : 0,
            'metadata'  => $this->encodeMetadata($item->metadata()),
        ];
        $formats = ['%s', '%s', '%s', '%s', '%d', '%d', '%s'];

        $existingId = $item->id();
        if ($existingId === null) {
            $existing = $this->findByValor($tipo, $item->valor());
            if ($existing !== null) {
                $existingId = $existing->id();
            }
        }

        if ($existingId !== null && $existingId > 0) {
            $result = $this->wpdb->update(
                $this->tableName,
                $row,
                ['id' => $existingId],
                $formats,
                ['%d']
            );
            if ($result === false) {
                throw new RuntimeException('Falha ao atualizar item de vocabulario.');
            }
            $persistedId = $existingId;
            $acao        = 'atualizar';
        } else {
            $result = $this->wpdb->insert($this->tableName, $row, $formats);
            if ($result === false) {
                throw new RuntimeException('Falha ao inserir item de vocabulario.');
            }
            $persistedId = (int) $this->wpdb->insert_id;
            $acao        = 'criar';
        }

        $this->bustCacheFor($tipo);

        $this->audit->log(
            'vocabulario',
            $persistedId,
            $acao,
            null,
            [
                'tipo'   => $tipo,
                'valor'  => $item->valor(),
                'rotulo' => $item->rotulo(),
                'ativo'  => $item->ativo(),
            ]
        );

        return $persistedId;
    }

    public function desativar(int $id): void
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Id invalido para desativar.');
        }
        $current = $this->findById($id);
        if ($current === null) {
            return;
        }
        if (!$current->ativo()) {
            return;
        }
        $result = $this->wpdb->update(
            $this->tableName,
            ['ativo' => 0],
            ['id' => $id],
            ['%d'],
            ['%d']
        );
        if ($result === false) {
            throw new RuntimeException('Falha ao desativar item de vocabulario.');
        }
        $this->bustCacheFor($current->tipo());
        $this->audit->log(
            'vocabulario',
            $id,
            'desativar',
            ['ativo' => true],
            ['ativo' => false]
        );
    }

    public function validar(string $tipo, string $valor): bool
    {
        if (!TipoVocabulario::isValid($tipo)) {
            return false;
        }
        $valor = trim($valor);
        if ($valor === '') {
            return false;
        }
        foreach ($this->listByTipo($tipo, true) as $item) {
            if ($item->valor() === $valor) {
                return true;
            }
        }

        return false;
    }

    /**
     * Hidrata uma linha do BD em {@see ItemVocabulario}.
     *
     * @param array<string,mixed> $row
     */
    private function hydrate(array $row): ItemVocabulario
    {
        $metadataRaw = isset($row['metadata']) ? $row['metadata'] : null;
        $metadata    = null;
        if (is_string($metadataRaw) && $metadataRaw !== '') {
            $decoded = json_decode($metadataRaw, true);
            if (is_array($decoded)) {
                $metadata = $decoded;
            }
        }

        return new ItemVocabulario(
            isset($row['id']) ? (int) $row['id'] : null,
            (string) ($row['tipo'] ?? ''),
            (string) ($row['valor'] ?? ''),
            (string) ($row['rotulo'] ?? ''),
            isset($row['descricao']) && $row['descricao'] !== null && $row['descricao'] !== ''
                ? (string) $row['descricao']
                : null,
            isset($row['ordem']) ? (int) $row['ordem'] : 0,
            isset($row['ativo']) ? ((int) $row['ativo']) === 1 : false,
            $metadata
        );
    }

    /**
     * @param array<string,mixed>|null $metadata
     */
    private function encodeMetadata(?array $metadata): ?string
    {
        if ($metadata === null) {
            return null;
        }
        $json = function_exists('wp_json_encode')
            ? wp_json_encode($metadata)
            : json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($json) ? $json : null;
    }

    /**
     * @throws InvalidArgumentException Quando $tipo nao esta no whitelist.
     */
    private function assertTipo(string $tipo): string
    {
        $normalized = strtolower(trim($tipo));
        if (!in_array($normalized, TipoVocabulario::all(), true)) {
            throw new InvalidArgumentException(sprintf(
                'TipoVocabulario invalido: "%s".',
                $tipo
            ));
        }

        return $normalized;
    }

    /**
     * @throws InvalidArgumentException Quando $orderby nao esta no whitelist.
     */
    private function resolveOrderby(string $orderby): string
    {
        if (!in_array($orderby, self::ORDERBY_WHITELIST, true)) {
            throw new InvalidArgumentException(sprintf(
                'orderby "%s" nao permitido.',
                $orderby
            ));
        }

        return $orderby;
    }

    /**
     * Constrói a chave de cache (inclui versão para invalidação global).
     */
    private function cacheKey(string $tipo, bool $apenasAtivos): string
    {
        return sprintf(
            'list:%s:%d:v%d',
            $tipo,
            $apenasAtivos ? 1 : 0,
            $this->getCacheVersion()
        );
    }

    private function getCacheVersion(): int
    {
        if (!function_exists('get_option')) {
            return 1;
        }
        $value = get_option(self::VERSION_OPTION, 1);

        return is_numeric($value) ? (int) $value : 1;
    }

    /**
     * Invalida cache do tipo afetado: deleta as duas variantes (ativos / todos)
     * e incrementa a versão para qualquer chave em voo em outros workers.
     */
    private function bustCacheFor(string $tipo): void
    {
        if (function_exists('wp_cache_delete')) {
            wp_cache_delete($this->cacheKey($tipo, true), self::CACHE_GROUP);
            wp_cache_delete($this->cacheKey($tipo, false), self::CACHE_GROUP);
        }
        $this->incrementCacheVersion();
    }

    private function incrementCacheVersion(): void
    {
        if (!function_exists('update_option') || !function_exists('get_option')) {
            return;
        }
        $current = (int) get_option(self::VERSION_OPTION, 1);
        $next    = $current + 1;
        // autoload=true: leitura constante, escrita rara.
        update_option(self::VERSION_OPTION, $next, true);
    }
}
