<?php
/**
 * Repositório `wpdb` append-only para histórico de status do agente.
 *
 * @package Ibram\ParticipeIbram\Infrastructure\Repository
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Infrastructure\Repository;

use DateTimeImmutable;
use Ibram\ParticipeIbram\Domain\Analise\StatusHistorico;
use Ibram\ParticipeIbram\Domain\Analise\StatusHistoricoRepository;
use RuntimeException;

/**
 * Persistência de {@see StatusHistorico} contra `{$wpdb->prefix}pi_status_historico`.
 *
 * APPEND-ONLY: a classe oferece somente INSERT (`registrar`) e SELECT
 * (`findByAgente`). Não há UPDATE/DELETE público — qualquer corrupção precisa
 * ser tratada por DBA fora da aplicação.
 */
final class WpdbStatusHistoricoRepository implements StatusHistoricoRepository
{
    /** @var \wpdb */
    private $wpdb;

    private string $tableName;

    public function __construct($wpdb, ?string $tableName = null)
    {
        $this->wpdb      = $wpdb;
        $prefix          = isset($wpdb->prefix) && is_string($wpdb->prefix) ? $wpdb->prefix : 'wp_';
        $this->tableName = $tableName ?? ($prefix . 'pi_status_historico');
    }

    public function registrar(
        int $agenteId,
        string $statusAnterior,
        string $statusNovo,
        ?int $atorId,
        ?string $observacao
    ): int {
        if ($agenteId <= 0) {
            throw new \InvalidArgumentException('registrar: agenteId invalido.');
        }
        $statusAnterior = trim($statusAnterior);
        $statusNovo     = trim($statusNovo);
        if ($statusAnterior === '' || $statusNovo === '') {
            throw new \InvalidArgumentException('registrar: status nao pode ser vazio.');
        }

        $row = [
            'agente_id'       => $agenteId,
            'status_anterior' => $statusAnterior,
            'status_novo'     => $statusNovo,
            'ator_id'         => $atorId,
            'observacao'      => $observacao !== null && trim($observacao) !== '' ? trim($observacao) : null,
            'ocorrido_em'     => gmdate('Y-m-d H:i:s'),
        ];
        $formats = ['%d', '%s', '%s', '%d', '%s', '%s'];
        if ($atorId === null) {
            // Strip the format for ator_id to allow NULL.
            $row['ator_id'] = null;
        }

        $ok = $this->wpdb->insert($this->tableName, $row, $formats);
        if ($ok === false) {
            throw new RuntimeException('Falha ao inserir status_historico.');
        }

        return (int) $this->wpdb->insert_id;
    }

    public function findByAgente(int $agenteId): array
    {
        if ($agenteId <= 0) {
            return [];
        }
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tableName}
             WHERE agente_id = %d
             ORDER BY ocorrido_em ASC, id ASC",
            $agenteId
        );
        $rows = $this->wpdb->get_results($sql, ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        return array_values(array_map([self::class, 'hydrate'], $rows));
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function hydrate(array $row): StatusHistorico
    {
        return new StatusHistorico(
            isset($row['id']) ? (int) $row['id'] : null,
            (int) ($row['agente_id'] ?? 0),
            (string) ($row['status_anterior'] ?? ''),
            (string) ($row['status_novo'] ?? ''),
            isset($row['ator_id']) && $row['ator_id'] !== null ? (int) $row['ator_id'] : null,
            isset($row['observacao']) && $row['observacao'] !== null
                ? (string) $row['observacao']
                : null,
            self::toDate($row['ocorrido_em'] ?? null) ?? new DateTimeImmutable('now')
        );
    }

    /**
     * @param mixed $raw
     */
    private static function toDate($raw): ?DateTimeImmutable
    {
        if ($raw === null || $raw === '' || $raw === '0000-00-00 00:00:00') {
            return null;
        }
        try {
            return new DateTimeImmutable((string) $raw);
        } catch (\Exception $e) {
            return null;
        }
    }
}
