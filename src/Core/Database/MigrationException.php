<?php
/**
 * Migration runtime exception.
 *
 * @package Ibram\ParticipeIbram\Core\Database
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Core\Database;

use RuntimeException;

/**
 * Thrown when a SQL migration cannot be applied or is in an inconsistent state
 * (missing file, hash drift on an already-applied version, $wpdb->query() error).
 */
final class MigrationException extends RuntimeException
{
}
