<?php
/**
 * ExportarAuditLogCommand — DTO para export do log de auditoria.
 *
 * @package Ibram\ParticipeIbram\Application\Audit
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Audit;

use InvalidArgumentException;

/**
 * @psalm-immutable
 */
final class ExportarAuditLogCommand
{
    /** @var array<string,mixed> */
    private array $filters;

    private string $format;

    private int $atorId;

    /**
     * @param array<string,mixed> $filters Filtros (mesmos de AuditLogQuery::list).
     * @param string              $format  'csv' ou 'json'.
     * @param int                 $atorId  ID do usuário que solicita o export.
     */
    public function __construct(array $filters, string $format, int $atorId)
    {
        if (!in_array($format, ['csv', 'json'], true)) {
            throw new InvalidArgumentException(
                sprintf('ExportarAuditLogCommand: formato inválido "%s". Use csv ou json.', $format)
            );
        }
        if ($atorId <= 0) {
            throw new InvalidArgumentException('ExportarAuditLogCommand: atorId deve ser positivo.');
        }

        $this->filters = $filters;
        $this->format  = $format;
        $this->atorId  = $atorId;
    }

    /** @return array<string,mixed> */
    public function filters(): array
    {
        return $this->filters;
    }

    public function format(): string
    {
        return $this->format;
    }

    public function atorId(): int
    {
        return $this->atorId;
    }
}
