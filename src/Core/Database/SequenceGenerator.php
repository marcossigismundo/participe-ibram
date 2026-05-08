<?php
/**
 * Atomic generator for the Participe Ibram registration number.
 *
 * @package Ibram\ParticipeIbram\Core\Database
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Core\Database;

use InvalidArgumentException;
use wpdb;

/**
 * Generates the canonical registration code `PI-{TIPO}-{ANO}-{SEQ06}` defined
 * in ARCHITECTURE.md TD-02.
 *
 *   PI-PF-2026-000123
 *   PI-OR-2026-000045
 *   PI-SM-2026-000007
 *
 * Sequences are tracked per (tipo, ano) in `wp_pi_sequencias` and incremented
 * inside a pessimistic-locked transaction (`SELECT ... FOR UPDATE`). InnoDB is
 * mandatory: MyISAM doesn't honour row-level locking and would silently allow
 * duplicate numbers when two concurrent activations call `next()` on the same
 * `(tipo, ano)`.
 */
final class SequenceGenerator
{
    /**
     * Allowed agent type discriminators (TD-01).
     *
     * @var array<int, string>
     */
    private const ALLOWED_TYPES = ['PF', 'OR', 'SM'];

    /**
     * @var wpdb
     */
    private wpdb $wpdb;

    /**
     * @var Schema
     */
    private Schema $schema;

    /**
     * @param wpdb        $wpdb   WordPress database handle.
     * @param Schema|null $schema Optional pre-built helper; built lazily otherwise.
     */
    public function __construct(wpdb $wpdb, ?Schema $schema = null)
    {
        // TODO injetar via DI quando Container expor 'wpdb'/'schema'.
        $this->wpdb   = $wpdb;
        $this->schema = $schema ?? new Schema($wpdb);
    }

    /**
     * Allocate the next number for the given agent type.
     *
     * @param string   $tipo Agent type. One of PF, OR, SM.
     * @param int|null $ano  Optional override for the year (defaults to current).
     *
     * @return string Registration number, e.g. `PI-PF-2026-000123`.
     *
     * @throws InvalidArgumentException When `$tipo` is invalid.
     * @throws MigrationException       When the underlying table is missing
     *                                  or not InnoDB, or the transaction fails.
     */
    public function next(string $tipo, ?int $ano = null): string
    {
        $tipo = strtoupper($tipo);
        if (!in_array($tipo, self::ALLOWED_TYPES, true)) {
            throw new InvalidArgumentException(sprintf(
                'tipo invalido para SequenceGenerator: %s. Esperado: %s',
                $tipo,
                implode(', ', self::ALLOWED_TYPES)
            ));
        }

        $year = $ano ?? (int) gmdate('Y');
        $this->guardEngine();

        $table = $this->schema->getPrefix() . 'sequencias';

        $started = $this->wpdb->query('START TRANSACTION');
        if ($started === false) {
            throw new MigrationException(
                'SequenceGenerator: failed to START TRANSACTION'
                . ($this->wpdb->last_error !== '' ? ': ' . (string) $this->wpdb->last_error : '')
            );
        }

        try {
            $selectSql = $this->wpdb->prepare(
                'SELECT `ultimo_numero` FROM `' . $table . '` WHERE `tipo` = %s AND `ano` = %d FOR UPDATE',
                $tipo,
                $year
            );
            if (!is_string($selectSql)) {
                throw new MigrationException('SequenceGenerator: prepare(SELECT ... FOR UPDATE) failed');
            }

            $current = $this->wpdb->get_var($selectSql);

            if ($current === null) {
                $insertSql = $this->wpdb->prepare(
                    'INSERT INTO `' . $table . '` (`tipo`, `ano`, `ultimo_numero`) VALUES (%s, %d, 0)',
                    $tipo,
                    $year
                );
                if (!is_string($insertSql) || $this->wpdb->query($insertSql) === false) {
                    throw new MigrationException(
                        'SequenceGenerator: failed to insert initial row for ' . $tipo . '/' . $year
                        . ($this->wpdb->last_error !== '' ? ': ' . (string) $this->wpdb->last_error : '')
                    );
                }
                $current = '0';
            }

            $next = ((int) $current) + 1;

            $updateSql = $this->wpdb->prepare(
                'UPDATE `' . $table . '` SET `ultimo_numero` = %d WHERE `tipo` = %s AND `ano` = %d',
                $next,
                $tipo,
                $year
            );
            if (!is_string($updateSql) || $this->wpdb->query($updateSql) === false) {
                throw new MigrationException(
                    'SequenceGenerator: failed to UPDATE sequence for ' . $tipo . '/' . $year
                    . ($this->wpdb->last_error !== '' ? ': ' . (string) $this->wpdb->last_error : '')
                );
            }

            $committed = $this->wpdb->query('COMMIT');
            if ($committed === false) {
                throw new MigrationException(
                    'SequenceGenerator: COMMIT failed'
                    . ($this->wpdb->last_error !== '' ? ': ' . (string) $this->wpdb->last_error : '')
                );
            }

            return sprintf('PI-%s-%d-%06d', $tipo, $year, $next);
        } catch (\Throwable $e) {
            $this->wpdb->query('ROLLBACK');
            // Never log the row data; preserve only context (no PII here anyway).
            error_log(sprintf(
                '[participe-ibram] SequenceGenerator failure tipo=%s ano=%d: %s',
                $tipo,
                $year,
                $e->getMessage()
            ));
            if ($e instanceof MigrationException || $e instanceof InvalidArgumentException) {
                throw $e;
            }
            throw new MigrationException('SequenceGenerator failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Refuse to operate when the sequencias table does not exist or runs on a
     * non-InnoDB engine (locking would be a no-op on MyISAM).
     *
     * @return void
     *
     * @throws MigrationException
     */
    private function guardEngine(): void
    {
        $table = $this->schema->getPrefix() . 'sequencias';

        if (!$this->schema->tableExists($table)) {
            throw new MigrationException(sprintf(
                'SequenceGenerator: tabela %s nao existe. Rode as migrations antes.',
                $table
            ));
        }

        $engine = $this->schema->getEngine($table);
        if ($engine !== '' && strcasecmp($engine, 'InnoDB') !== 0) {
            throw new MigrationException(sprintf(
                'SequenceGenerator: tabela %s usa engine %s; e necessario InnoDB para SELECT ... FOR UPDATE.',
                $table,
                $engine
            ));
        }
    }
}
