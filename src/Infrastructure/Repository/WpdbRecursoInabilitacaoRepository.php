<?php
/**
 * Repositório `wpdb` para RecursoInabilitacao (SCHEMA §4).
 *
 * @package Ibram\ParticipeIbram\Infrastructure\Repository
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Infrastructure\Repository;

use DateTimeImmutable;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Domain\Edital\RecursoInabilitacao;
use RuntimeException;

/**
 * Persistência de {@see RecursoInabilitacao} contra
 * `{$wpdb->prefix}pi_recursos_inabilitacao`.
 */
final class WpdbRecursoInabilitacaoRepository
{
    /** @var \wpdb */
    private $wpdb;

    private AuditLogger $audit;

    private string $tableName;

    public function __construct($wpdb, AuditLogger $audit, ?string $tableName = null)
    {
        $this->wpdb      = $wpdb;
        $this->audit     = $audit;
        $prefix          = isset($wpdb->prefix) && is_string($wpdb->prefix) ? $wpdb->prefix : 'wp_';
        $this->tableName = $tableName ?? ($prefix . 'pi_recursos_inabilitacao');
    }

    public function findById(int $id): ?RecursoInabilitacao
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

    public function findByInscricao(int $inscricaoId): ?RecursoInabilitacao
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->tableName}
                 WHERE inscricao_id = %d ORDER BY id DESC LIMIT 1",
                $inscricaoId
            ),
            ARRAY_A
        );

        return is_array($row) ? self::hydrate($row) : null;
    }

    /**
     * @return int ID do recurso persistido.
     */
    public function save(RecursoInabilitacao $recurso): int
    {
        $payload = self::toRow($recurso);
        $formats = self::columnFormats();

        if ($recurso->id() === null) {
            $result = $this->wpdb->insert($this->tableName, $payload, $formats);
            if ($result === false) {
                throw new RuntimeException('Falha ao inserir recurso de inabilitacao.');
            }
            $newId = (int) $this->wpdb->insert_id;
            $this->audit->log('recurso_inabilitacao', $newId, 'protocolar', null, $payload);

            return $newId;
        }

        $existing = $this->findById((int) $recurso->id());
        $before   = $existing !== null ? self::toRow($existing) : null;

        $result = $this->wpdb->update(
            $this->tableName,
            $payload,
            ['id' => (int) $recurso->id()],
            $formats,
            ['%d']
        );
        if ($result === false) {
            throw new RuntimeException('Falha ao atualizar recurso de inabilitacao.');
        }

        $acao = ($before !== null && empty($before['decisao']) && !empty($payload['decisao']))
            ? 'decidir'
            : 'atualizar';
        $this->audit->log('recurso_inabilitacao', (int) $recurso->id(), $acao, $before, $payload);

        return (int) $recurso->id();
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function hydrate(array $row): RecursoInabilitacao
    {
        return new RecursoInabilitacao(
            isset($row['id']) ? (int) $row['id'] : null,
            (int) ($row['inscricao_id'] ?? 0),
            (string) ($row['fundamentacao_md'] ?? ''),
            self::toDate($row['protocolado_em'] ?? null) ?? new DateTimeImmutable('now'),
            isset($row['decisao']) && $row['decisao'] !== null && $row['decisao'] !== ''
                ? (string) $row['decisao']
                : null,
            isset($row['decisor_id']) && $row['decisor_id'] !== null
                ? (int) $row['decisor_id']
                : null,
            isset($row['decisao_md']) && $row['decisao_md'] !== null ? (string) $row['decisao_md'] : null,
            self::toDate($row['decidido_em'] ?? null)
        );
    }

    /**
     * @return array<string,mixed>
     */
    private static function toRow(RecursoInabilitacao $recurso): array
    {
        return [
            'inscricao_id'     => $recurso->inscricaoId(),
            'fundamentacao_md' => $recurso->fundamentacaoMd(),
            'protocolado_em'   => $recurso->protocoladoEm()->format('Y-m-d H:i:s'),
            'decisao'          => $recurso->decisao(),
            'decisor_id'       => $recurso->decisorId(),
            'decisao_md'       => $recurso->decisaoMd(),
            'decidido_em'      => self::fromDate($recurso->decididoEm()),
        ];
    }

    /**
     * @return array<int,string>
     */
    private static function columnFormats(): array
    {
        return [
            '%d', // inscricao_id
            '%s', // fundamentacao_md
            '%s', // protocolado_em
            '%s', // decisao
            '%d', // decisor_id
            '%s', // decisao_md
            '%s', // decidido_em
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
