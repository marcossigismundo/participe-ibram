<?php
/**
 * Lightweight schema introspection helpers.
 *
 * @package Ibram\ParticipeIbram\Core\Database
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Core\Database;

use wpdb;

/**
 * Read-only helpers around `$wpdb` for table/column existence checks and
 * charset resolution. Used by the activator, MigrationRunner and any code
 * that needs to branch on schema state without writing custom DDL queries.
 *
 * All methods are intentionally side-effect free.
 */
final class Schema
{
    /**
     * WordPress database abstraction.
     *
     * @var wpdb
     */
    private wpdb $wpdb;

    /**
     * @param wpdb $wpdb WordPress database handle.
     */
    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
    }

    /**
     * Returns the canonical Participe Ibram table prefix (e.g. `wp_pi_`).
     *
     * @return string
     */
    public function getPrefix(): string
    {
        return $this->wpdb->prefix . 'pi_';
    }

    /**
     * Check whether a fully qualified table exists.
     *
     * @param string $table Fully qualified table name (already prefixed).
     *
     * @return bool
     */
    public function tableExists(string $table): bool
    {
        $sql = $this->wpdb->prepare('SHOW TABLES LIKE %s', $table);
        if (!is_string($sql)) {
            return false;
        }

        $found = $this->wpdb->get_var($sql);

        return is_string($found) && $found === $table;
    }

    /**
     * Check whether a column exists on a given table.
     *
     * @param string $table  Fully qualified table name.
     * @param string $column Column name.
     *
     * @return bool
     */
    public function columnExists(string $table, string $column): bool
    {
        // Identifiers cannot be passed as %s placeholders. We validate them
        // before splicing to keep the query safe.
        if (!$this->isSafeIdentifier($table) || !$this->isSafeIdentifier($column)) {
            return false;
        }

        $sql = $this->wpdb->prepare(
            'SHOW COLUMNS FROM `' . $table . '` LIKE %s',
            $column
        );
        if (!is_string($sql)) {
            return false;
        }

        $row = $this->wpdb->get_row($sql, ARRAY_A);

        return is_array($row) && !empty($row);
    }

    /**
     * Get the `DEFAULT CHARSET=...` clause WordPress uses for new tables.
     * Returns the standard utf8mb4 / unicode_520_ci pair for the plugin.
     *
     * @return string
     */
    public function getCharsetCollate(): string
    {
        if (method_exists($this->wpdb, 'get_charset_collate')) {
            $collate = (string) $this->wpdb->get_charset_collate();
            if ($collate !== '') {
                return $collate;
            }
        }

        return 'DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci';
    }

    /**
     * Returns the storage engine reported for a table (e.g. `InnoDB`,
     * `MyISAM`). Empty string when the table is missing or unreadable.
     *
     * @param string $table Fully qualified table name.
     *
     * @return string
     */
    public function getEngine(string $table): string
    {
        $sql = $this->wpdb->prepare('SHOW TABLE STATUS WHERE Name = %s', $table);
        if (!is_string($sql)) {
            return '';
        }

        $row = $this->wpdb->get_row($sql, ARRAY_A);
        if (!is_array($row) || !isset($row['Engine'])) {
            return '';
        }

        return (string) $row['Engine'];
    }

    /**
     * Whitelist-based identifier safety check (letters, digits, underscore).
     *
     * @param string $identifier Identifier candidate.
     *
     * @return bool
     */
    private function isSafeIdentifier(string $identifier): bool
    {
        return $identifier !== '' && preg_match('/^[A-Za-z0-9_]+$/', $identifier) === 1;
    }
}
