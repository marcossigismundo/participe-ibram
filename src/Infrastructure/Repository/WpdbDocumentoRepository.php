<?php
/**
 * Implementação WPDB do DocumentoRepository.
 *
 * @package Ibram\ParticipeIbram\Infrastructure\Repository
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Infrastructure\Repository;

use DateTimeImmutable;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Domain\Documento\Documento;
use Ibram\ParticipeIbram\Domain\Documento\DocumentoNotFound;
use Ibram\ParticipeIbram\Domain\Documento\DocumentoRepository;

/**
 * Persiste {@see Documento} em `{prefix}pi_documentos`.
 *
 * Convenções:
 *  - Toda query usa `$wpdb->prepare()` em chamada única (sem concatenação)
 *    para satisfazer phpcs WordPress.DB.PreparedSQL.
 *  - Audita criar/deletar via {@see AuditLogger} (TD-14). Atualizações de
 *    status de validação serão auditadas pelos handlers de aplicação.
 *  - Não loga PII (apenas IDs e nome do tipo de documento).
 */
final class WpdbDocumentoRepository implements DocumentoRepository
{
    /** @var \wpdb */
    private $wpdb;

    private AuditLogger $auditLogger;

    private string $tableName;

    /**
     * @param \wpdb       $wpdb
     * @param AuditLogger $auditLogger
     * @param string|null $tableName Override (default `{prefix}pi_documentos`).
     */
    public function __construct($wpdb, AuditLogger $auditLogger, ?string $tableName = null)
    {
        $this->wpdb        = $wpdb;
        $this->auditLogger = $auditLogger;
        $prefix            = isset($wpdb->prefix) && is_string($wpdb->prefix) ? $wpdb->prefix : 'wp_';
        $this->tableName   = $tableName ?? ($prefix . 'pi_documentos');
    }

    public function findById(int $id): Documento
    {
        if ($id <= 0) {
            throw DocumentoNotFound::comId($id);
        }
        $sql = $this->wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            'SELECT * FROM `' . $this->tableName . '` WHERE id = %d LIMIT 1',
            $id
        );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $row = $this->wpdb->get_row($sql, ARRAY_A);
        if (!is_array($row)) {
            throw DocumentoNotFound::comId($id);
        }

        return self::hydrate($row);
    }

    /**
     * @return list<Documento>
     */
    public function findByAgente(int $agenteId): array
    {
        if ($agenteId <= 0) {
            return [];
        }
        $sql = $this->wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            'SELECT * FROM `' . $this->tableName . '` WHERE agente_id = %d ORDER BY uploaded_at DESC',
            $agenteId
        );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $this->wpdb->get_results($sql, ARRAY_A);

        return self::hydrateMany(is_array($rows) ? $rows : []);
    }

    /**
     * @return list<Documento>
     */
    public function findByInscricao(int $inscricaoId): array
    {
        if ($inscricaoId <= 0) {
            return [];
        }
        $sql = $this->wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            'SELECT * FROM `' . $this->tableName . '` WHERE inscricao_id = %d ORDER BY uploaded_at DESC',
            $inscricaoId
        );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $this->wpdb->get_results($sql, ARRAY_A);

        return self::hydrateMany(is_array($rows) ? $rows : []);
    }

    public function save(Documento $documento): int
    {
        $row = [
            'agente_id'             => $documento->agenteId(),
            'inscricao_id'          => $documento->inscricaoId(),
            'tipo_documento_id'     => $documento->tipoDocumentoId(),
            'arquivo_path'          => $documento->arquivoPath(),
            'nome_original'         => $documento->nomeOriginal(),
            'mime_real'             => $documento->mimeReal(),
            'tamanho_bytes'         => $documento->tamanhoBytes(),
            'hash_sha256'           => $documento->hashSha256(),
            'uploaded_by'           => $documento->uploadedBy(),
            'uploaded_at'           => $documento->uploadedAt()->format('Y-m-d H:i:s'),
            'validado'              => $documento->isValidado() ? 1 : 0,
            'validado_em'           => $documento->validadoEm() !== null
                ? $documento->validadoEm()->format('Y-m-d H:i:s')
                : null,
            'validado_por'          => $documento->validadoPor(),
            'observacoes_validacao' => $documento->observacoesValidacao(),
        ];

        $formats = [
            $documento->agenteId() === null ? null : '%d',
            $documento->inscricaoId() === null ? null : '%d',
            '%d',
            '%s',
            '%s',
            '%s',
            '%d',
            '%s',
            '%d',
            '%s',
            '%d',
            $documento->validadoEm() === null ? null : '%s',
            $documento->validadoPor() === null ? null : '%d',
            $documento->observacoesValidacao() === null ? null : '%s',
        ];
        $cleanFormats = array_values(array_filter($formats, static fn ($f) => $f !== null));

        $existingId = $documento->id();
        if ($existingId === null) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $ok = $this->wpdb->insert($this->tableName, $row, $cleanFormats);
            if ($ok === false) {
                throw new \RuntimeException('Falha ao inserir documento.');
            }
            $id = (int) $this->wpdb->insert_id;
            $this->auditLogger->log(
                'documento',
                $id,
                'criar',
                null,
                [
                    'tipo_documento_id' => $documento->tipoDocumentoId(),
                    'agente_id'         => $documento->agenteId(),
                    'inscricao_id'      => $documento->inscricaoId(),
                    'tamanho_bytes'     => $documento->tamanhoBytes(),
                    'mime_real'         => $documento->mimeReal(),
                ],
                $documento->uploadedBy()
            );

            return $id;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $ok = $this->wpdb->update(
            $this->tableName,
            $row,
            ['id' => $existingId],
            $cleanFormats,
            ['%d']
        );
        if ($ok === false) {
            throw new \RuntimeException('Falha ao atualizar documento.');
        }

        return $existingId;
    }

    public function delete(int $id): void
    {
        if ($id <= 0) {
            return;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $this->wpdb->delete($this->tableName, ['id' => $id], ['%d']);
        $this->auditLogger->log(
            'documento',
            $id,
            'deletar',
            null,
            null,
            null
        );
    }

    /**
     * Constrói entidade a partir de uma row crua de wpdb.
     *
     * @param array<string,mixed> $row
     */
    private static function hydrate(array $row): Documento
    {
        return new Documento(
            isset($row['id']) ? (int) $row['id'] : null,
            isset($row['agente_id']) && $row['agente_id'] !== null ? (int) $row['agente_id'] : null,
            isset($row['inscricao_id']) && $row['inscricao_id'] !== null ? (int) $row['inscricao_id'] : null,
            (int) ($row['tipo_documento_id'] ?? 0),
            (string) ($row['arquivo_path'] ?? ''),
            (string) ($row['nome_original'] ?? ''),
            (string) ($row['mime_real'] ?? ''),
            (int) ($row['tamanho_bytes'] ?? 0),
            (string) ($row['hash_sha256'] ?? ''),
            (int) ($row['uploaded_by'] ?? 0),
            self::parseDateTime((string) ($row['uploaded_at'] ?? 'now')),
            !empty($row['validado']),
            isset($row['validado_em']) && $row['validado_em'] !== null
                ? self::parseDateTime((string) $row['validado_em'])
                : null,
            isset($row['validado_por']) && $row['validado_por'] !== null
                ? (int) $row['validado_por']
                : null,
            isset($row['observacoes_validacao']) && $row['observacoes_validacao'] !== null
                ? (string) $row['observacoes_validacao']
                : null
        );
    }

    /**
     * @param list<array<string,mixed>> $rows
     *
     * @return list<Documento>
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

    private static function parseDateTime(string $raw): DateTimeImmutable
    {
        $clean = trim($raw);
        if ($clean === '' || $clean === '0000-00-00 00:00:00') {
            return new DateTimeImmutable('now');
        }
        try {
            return new DateTimeImmutable($clean);
        } catch (\Exception $e) {
            return new DateTimeImmutable('now');
        }
    }
}
