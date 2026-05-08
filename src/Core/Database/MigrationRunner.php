<?php
/**
 * Versioned SQL migration runner.
 *
 * @package Ibram\ParticipeIbram\Core\Database
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Core\Database;

use wpdb;

/**
 * Applies SQL files from `migrations/V{NNN}__{descricao}.sql` in order,
 * recording each applied version + file hash in `wp_pi_migrations`.
 *
 * Design goals:
 *  - Replace the legacy ad-hoc `maybe_upgrade` (R5 B-20) with a real, versioned
 *    migration system.
 *  - Idempotent: re-running `run()` is a no-op once everything is current.
 *  - Tamper-evident: applied migrations are pinned to the SHA-256 of the file
 *    contents at apply time. Editing a file after it has been applied is a
 *    fatal error (forces operators to author a new V*** migration).
 *  - Driver-neutral parsing: a tiny SQL splitter that respects single-quoted
 *    strings, double-quoted identifiers, backticked identifiers and `--`
 *    line comments. Good enough for our hand-written DDL/DML files.
 */
final class MigrationRunner
{
    /**
     * WordPress database handle.
     *
     * @var wpdb
     */
    private wpdb $wpdb;

    /**
     * Absolute path to the directory holding `V*.sql` files.
     *
     * @var string
     */
    private string $migrationsDir;

    /**
     * Plugin-scoped table prefix (e.g. `wp_pi_`).
     *
     * @var string
     */
    private string $prefix;

    /**
     * @param wpdb        $wpdb          WordPress database handle.
     * @param string      $migrationsDir Absolute path to migrations directory.
     * @param string|null $prefix        Override prefix for tests; defaults to
     *                                   `$wpdb->prefix . 'pi_'`.
     */
    public function __construct(wpdb $wpdb, string $migrationsDir, ?string $prefix = null)
    {
        // TODO injetar via DI: hoje o caller compoe (Activator -> new MigrationRunner($wpdb, ...));
        // quando o Container registrar 'wpdb', o prefix sai daqui e fica em config.
        $this->wpdb          = $wpdb;
        $this->migrationsDir = rtrim($migrationsDir, "/\\");
        $this->prefix        = $prefix ?? ($wpdb->prefix . 'pi_');
    }

    /**
     * Apply all pending migrations.
     *
     * @return array<int, string> Versions applied during this call (ordered).
     *
     * @throws MigrationException When a file is missing, malformed, the hash
     *                            of an already-applied migration drifted, or
     *                            a SQL statement fails.
     */
    public function run(): array
    {
        $this->ensureControlTable();

        $files = $this->discoverFiles();
        if ($files === []) {
            return [];
        }

        $applied = [];
        foreach ($files as $version => $path) {
            if ($this->wasApplied($version, $path)) {
                continue;
            }

            $this->apply($version, $path);
            $applied[] = $version;
        }

        return $applied;
    }

    /**
     * Create `wp_pi_migrations` if missing. Independent of any V*.sql file so
     * that a fresh activation can always record V001.
     *
     * @return void
     *
     * @throws MigrationException
     */
    private function ensureControlTable(): void
    {
        $table = $this->prefix . 'migrations';
        $sql   = sprintf(
            'CREATE TABLE IF NOT EXISTS `%s` (
              `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `versao`        VARCHAR(20)     NOT NULL,
              `descricao`     VARCHAR(255)    NOT NULL,
              `aplicada_em`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `hash_arquivo`  CHAR(64)        NOT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uniq_versao` (`versao`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci',
            $table
        );

        $result = $this->wpdb->query($sql);
        if ($result === false) {
            $this->failQuery('failed to create migrations control table', $sql);
        }
    }

    /**
     * Enumerate `V*.sql` files ordered by their numeric version suffix.
     *
     * @return array<string, string> Map version => absolute file path.
     *
     * @throws MigrationException
     */
    private function discoverFiles(): array
    {
        if (!is_dir($this->migrationsDir)) {
            throw new MigrationException(
                sprintf('migrations directory not found: %s', $this->migrationsDir)
            );
        }

        $entries = glob($this->migrationsDir . DIRECTORY_SEPARATOR . 'V*.sql');
        if (!is_array($entries) || $entries === []) {
            return [];
        }

        $indexed = [];
        foreach ($entries as $path) {
            $name = basename($path);
            if (preg_match('/^(V\d{3,})__[A-Za-z0-9_]+\.sql$/', $name, $m) !== 1) {
                continue;
            }
            $indexed[$m[1]] = $path;
        }

        ksort($indexed, SORT_NATURAL);

        return $indexed;
    }

    /**
     * Detect whether `$version` is already recorded. Throws if the persisted
     * hash differs from the file on disk (forbidden mutation of an applied
     * migration).
     *
     * @param string $version e.g. `V001`.
     * @param string $path    Absolute path to the SQL file.
     *
     * @return bool
     *
     * @throws MigrationException
     */
    private function wasApplied(string $version, string $path): bool
    {
        $table = $this->prefix . 'migrations';
        $sql   = $this->wpdb->prepare(
            'SELECT hash_arquivo FROM `' . $table . '` WHERE versao = %s LIMIT 1',
            $version
        );

        if (!is_string($sql)) {
            throw new MigrationException('failed to prepare migration lookup');
        }

        $row = $this->wpdb->get_row($sql, ARRAY_A);
        if (!is_array($row) || !isset($row['hash_arquivo'])) {
            return false;
        }

        $current = $this->hashFile($path);
        if (!hash_equals((string) $row['hash_arquivo'], $current)) {
            error_log(sprintf(
                '[participe-ibram] migration %s hash drift detected (file changed after apply)',
                $version
            ));
            throw new MigrationException(sprintf(
                'migration %s was already applied with a different file hash. '
                . 'Applied migrations are immutable - create a new V*** file instead of editing %s.',
                $version,
                basename($path)
            ));
        }

        return true;
    }

    /**
     * Read, splice, split and execute one migration file inside a transaction.
     *
     * @param string $version Version label.
     * @param string $path    Absolute path to file.
     *
     * @return void
     *
     * @throws MigrationException
     */
    private function apply(string $version, string $path): void
    {
        $sqlRaw = @file_get_contents($path);
        if (!is_string($sqlRaw)) {
            throw new MigrationException(sprintf('cannot read migration file: %s', $path));
        }

        $hash       = hash('sha256', $sqlRaw);
        $sqlPrefixed = str_replace('{prefix}', $this->prefix, $sqlRaw);
        $statements  = $this->splitStatements($sqlPrefixed);
        if ($statements === []) {
            throw new MigrationException(sprintf('migration %s contains no executable statements', $version));
        }

        // DDL statements (CREATE TABLE, ALTER TABLE, ...) implicitly commit on
        // MySQL/MariaDB so wrapping in a transaction is mostly cosmetic, but
        // it protects DML-only seed migrations.
        $this->wpdb->query('START TRANSACTION');

        foreach ($statements as $idx => $statement) {
            $result = $this->wpdb->query($statement);
            if ($result === false) {
                $this->wpdb->query('ROLLBACK');
                $this->failQuery(
                    sprintf('migration %s failed at statement #%d', $version, $idx + 1),
                    $statement
                );
            }
        }

        $description = $this->describe($path);
        $table       = $this->prefix . 'migrations';
        $insertSql   = $this->wpdb->prepare(
            'INSERT INTO `' . $table . '` (`versao`, `descricao`, `hash_arquivo`) VALUES (%s, %s, %s)',
            $version,
            $description,
            $hash
        );
        if (!is_string($insertSql)) {
            $this->wpdb->query('ROLLBACK');
            throw new MigrationException(sprintf('failed to prepare migration record for %s', $version));
        }

        $recorded = $this->wpdb->query($insertSql);
        if ($recorded === false) {
            $this->wpdb->query('ROLLBACK');
            $this->failQuery(
                sprintf('failed to record applied migration %s', $version),
                $insertSql
            );
        }

        $this->wpdb->query('COMMIT');
    }

    /**
     * Compute the file hash. Centralised so both apply/check use identical
     * normalisation.
     *
     * @param string $path Absolute path.
     *
     * @return string Hex SHA-256.
     *
     * @throws MigrationException
     */
    private function hashFile(string $path): string
    {
        $contents = @file_get_contents($path);
        if (!is_string($contents)) {
            throw new MigrationException(sprintf('cannot read migration file: %s', $path));
        }

        return hash('sha256', $contents);
    }

    /**
     * Derive a human-readable description from the file name suffix.
     *
     * @param string $path Absolute path.
     *
     * @return string
     */
    private function describe(string $path): string
    {
        $name = basename($path, '.sql');
        $pos  = strpos($name, '__');
        if ($pos === false) {
            return $name;
        }
        $desc = substr($name, $pos + 2);
        return str_replace('_', ' ', $desc);
    }

    /**
     * Split SQL into individual statements honouring quotes/comments. Robust
     * enough for hand-authored migrations; intentionally NOT a full SQL parser.
     *
     * @param string $sql SQL bundle.
     *
     * @return array<int, string> Trimmed statements (empty ones removed).
     */
    private function splitStatements(string $sql): array
    {
        $length      = strlen($sql);
        $buffer      = '';
        $statements  = [];
        $inSingle    = false; // '...'
        $inDouble    = false; // "..."
        $inBacktick  = false; // `...`
        $inLineComment  = false;
        $inBlockComment = false;

        for ($i = 0; $i < $length; $i++) {
            $ch   = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            if ($inLineComment) {
                if ($ch === "\n") {
                    $inLineComment = false;
                    $buffer .= $ch;
                }
                continue;
            }

            if ($inBlockComment) {
                if ($ch === '*' && $next === '/') {
                    $inBlockComment = false;
                    $i++;
                }
                continue;
            }

            if (!$inSingle && !$inDouble && !$inBacktick) {
                // Begin comment recognition only outside any quoted span.
                if ($ch === '-' && $next === '-') {
                    $inLineComment = true;
                    $i++;
                    continue;
                }
                if ($ch === '/' && $next === '*') {
                    $inBlockComment = true;
                    $i++;
                    continue;
                }
                if ($ch === '#') {
                    $inLineComment = true;
                    continue;
                }
            }

            if ($ch === "'" && !$inDouble && !$inBacktick) {
                // Allow doubled-up '' escape inside a single-quoted string.
                if ($inSingle && $next === "'") {
                    $buffer .= "''";
                    $i++;
                    continue;
                }
                $inSingle = !$inSingle;
                $buffer .= $ch;
                continue;
            }

            if ($ch === '"' && !$inSingle && !$inBacktick) {
                $inDouble = !$inDouble;
                $buffer .= $ch;
                continue;
            }

            if ($ch === '`' && !$inSingle && !$inDouble) {
                $inBacktick = !$inBacktick;
                $buffer .= $ch;
                continue;
            }

            if ($ch === ';' && !$inSingle && !$inDouble && !$inBacktick) {
                $stmt = trim($buffer);
                if ($stmt !== '') {
                    $statements[] = $stmt;
                }
                $buffer = '';
                continue;
            }

            $buffer .= $ch;
        }

        $tail = trim($buffer);
        if ($tail !== '') {
            $statements[] = $tail;
        }

        return $statements;
    }

    /**
     * Build a MigrationException from a `$wpdb` error and bail.
     *
     * @param string $message Caller-provided context.
     * @param string $sql     SQL that triggered the failure (for the log).
     *
     * @return void
     *
     * @throws MigrationException Always.
     */
    private function failQuery(string $message, string $sql): void
    {
        $dbError = isset($this->wpdb->last_error) ? (string) $this->wpdb->last_error : '';
        // No PII in logs: SQL DDL/seed text is safe; never log row data.
        error_log(sprintf(
            '[participe-ibram] %s :: db_error=%s :: sql_excerpt=%s',
            $message,
            $dbError,
            $this->excerpt($sql)
        ));

        throw new MigrationException(
            $dbError !== ''
                ? sprintf('%s: %s', $message, $dbError)
                : $message
        );
    }

    /**
     * Trim a SQL excerpt for logging.
     *
     * @param string $sql SQL.
     *
     * @return string
     */
    private function excerpt(string $sql): string
    {
        $clean = preg_replace('/\s+/', ' ', trim($sql)) ?? $sql;
        return strlen($clean) > 240 ? substr($clean, 0, 240) . '...' : $clean;
    }
}
