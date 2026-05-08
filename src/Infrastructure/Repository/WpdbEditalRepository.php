<?php
/**
 * Repositório `wpdb` para o agregado Edital (SCHEMA §4).
 *
 * @package Ibram\ParticipeIbram\Infrastructure\Repository
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Infrastructure\Repository;

use DateTimeImmutable;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Domain\Edital\Edital;
use Ibram\ParticipeIbram\Domain\Edital\StatusEdital;
use RuntimeException;

/**
 * Persistência de {@see Edital} contra `{$wpdb->prefix}pi_editais`.
 *
 * - INSERT/UPDATE auditados via {@see AuditLogger} com entidade `edital`.
 * - Listagens paginadas usam `findByStatus(... $page, $perPage)`.
 * - Não força integridade referencial — esta é responsabilidade da aplicação.
 */
final class WpdbEditalRepository
{
    /** @var \wpdb */
    private $wpdb;

    private AuditLogger $audit;

    private string $tableName;

    /**
     * @param \wpdb       $wpdb
     * @param AuditLogger $audit
     * @param string|null $tableName Override (defaults to `{prefix}pi_editais`).
     */
    public function __construct($wpdb, AuditLogger $audit, ?string $tableName = null)
    {
        $this->wpdb      = $wpdb;
        $this->audit     = $audit;
        $prefix          = isset($wpdb->prefix) && is_string($wpdb->prefix) ? $wpdb->prefix : 'wp_';
        $this->tableName = $tableName ?? ($prefix . 'pi_editais');
    }

    public function findById(int $id): ?Edital
    {
        if ($id <= 0) {
            return null;
        }
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->tableName} WHERE id = %d", $id),
            ARRAY_A
        );
        if (!is_array($row)) {
            return null;
        }

        return self::hydrate($row);
    }

    /**
     * Lista editais filtrados por status, paginados.
     *
     * @return array<int,Edital>
     */
    public function findByStatus(StatusEdital $status, int $page, int $perPage): array
    {
        $page    = max(1, $page);
        $perPage = max(1, min(200, $perPage));
        $offset  = ($page - 1) * $perPage;

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->tableName} WHERE status = %s ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $status->value(),
                $perPage,
                $offset
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
     * Lista editais com inscrições abertas no momento.
     *
     * @return array<int,Edital>
     */
    public function findAbertosParaInscricao(): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->tableName} WHERE status = %s ORDER BY abertura ASC",
                StatusEdital::INSCRICOES_ABERTAS
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
     * Persiste um edital novo (INSERT) ou existente (UPDATE).
     *
     * Audita a operação em `{prefix}pi_audit_log` (entidade `edital`).
     *
     * @return int ID do edital persistido.
     */
    public function save(Edital $edital): int
    {
        $payload = self::toRow($edital);
        $formats = self::columnFormats();

        if ($edital->id() === null) {
            $result = $this->wpdb->insert($this->tableName, $payload, $formats);
            if ($result === false) {
                throw new RuntimeException('Falha ao inserir edital.');
            }
            $newId = (int) $this->wpdb->insert_id;
            $this->audit->log('edital', $newId, 'criar', null, $payload);

            return $newId;
        }

        $existing = $this->findById((int) $edital->id());
        $before   = $existing !== null ? self::toRow($existing) : null;

        $result = $this->wpdb->update(
            $this->tableName,
            $payload,
            ['id' => (int) $edital->id()],
            $formats,
            ['%d']
        );
        if ($result === false) {
            throw new RuntimeException('Falha ao atualizar edital.');
        }

        $acao = ($before !== null && isset($before['status']) && $before['status'] !== $payload['status'])
            ? 'transicao_status'
            : 'atualizar';
        $this->audit->log('edital', (int) $edital->id(), $acao, $before, $payload);

        return (int) $edital->id();
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function hydrate(array $row): Edital
    {
        return new Edital(
            isset($row['id']) ? (int) $row['id'] : null,
            (string) ($row['titulo'] ?? ''),
            isset($row['descricao_md']) && $row['descricao_md'] !== null ? (string) $row['descricao_md'] : null,
            StatusEdital::fromString((string) ($row['status'] ?? StatusEdital::RASCUNHO)),
            self::toDate($row['abertura'] ?? null),
            self::toDate($row['encerramento_inscricoes'] ?? null),
            self::toDate($row['publicacao_habilitacao'] ?? null),
            self::toDate($row['prazo_recurso_inabilitacao'] ?? null),
            self::toDate($row['abertura_votacao'] ?? null),
            self::toDate($row['encerramento_votacao'] ?? null),
            self::toDate($row['publicacao_resultado'] ?? null),
            (int) ($row['criado_por'] ?? 0),
            self::toDate($row['created_at'] ?? null) ?? new DateTimeImmutable('now'),
            self::toDate($row['updated_at'] ?? null) ?? new DateTimeImmutable('now')
        );
    }

    /**
     * @return array<string,mixed>
     */
    private static function toRow(Edital $edital): array
    {
        return [
            'titulo'                     => $edital->titulo(),
            'descricao_md'               => $edital->descricaoMd(),
            'status'                     => $edital->status()->value(),
            'abertura'                   => self::fromDate($edital->abertura()),
            'encerramento_inscricoes'    => self::fromDate($edital->encerramentoInscricoes()),
            'publicacao_habilitacao'     => self::fromDate($edital->publicacaoHabilitacao()),
            'prazo_recurso_inabilitacao' => self::fromDate($edital->prazoRecursoInabilitacao()),
            'abertura_votacao'           => self::fromDate($edital->aberturaVotacao()),
            'encerramento_votacao'       => self::fromDate($edital->encerramentoVotacao()),
            'publicacao_resultado'       => self::fromDate($edital->publicacaoResultado()),
            'criado_por'                 => $edital->criadoPor(),
            'created_at'                 => $edital->createdAt()->format('Y-m-d H:i:s'),
            'updated_at'                 => $edital->updatedAt()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @return array<int,string>
     */
    private static function columnFormats(): array
    {
        return [
            '%s', // titulo
            '%s', // descricao_md
            '%s', // status
            '%s', // abertura
            '%s', // encerramento_inscricoes
            '%s', // publicacao_habilitacao
            '%s', // prazo_recurso_inabilitacao
            '%s', // abertura_votacao
            '%s', // encerramento_votacao
            '%s', // publicacao_resultado
            '%d', // criado_por
            '%s', // created_at
            '%s', // updated_at
        ];
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

    private static function fromDate(?DateTimeImmutable $value): ?string
    {
        return $value !== null ? $value->format('Y-m-d H:i:s') : null;
    }
}
