<?php
/**
 * Repositório `wpdb` para Recursos de cadastro (Portaria 3230, Arts. 7º/8º).
 *
 * @package Ibram\ParticipeIbram\Infrastructure\Repository
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Infrastructure\Repository;

use DateTimeImmutable;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Domain\Analise\Recurso;
use Ibram\ParticipeIbram\Domain\Analise\RecursoRepository;
use RuntimeException;

/**
 * Persistência de {@see Recurso} contra `{$wpdb->prefix}pi_recursos`.
 *
 * `findPorAgenteEFase()` faz JOIN com `wp_pi_analises` para localizar o recurso
 * mais recente de um agente em uma fase específica.
 */
final class WpdbRecursoRepository implements RecursoRepository
{
    /** @var \wpdb */
    private $wpdb;

    private AuditLogger $audit;

    private string $tableName;

    private string $tableAnalises;

    public function __construct($wpdb, AuditLogger $audit, ?string $tableName = null, ?string $tableAnalises = null)
    {
        $this->wpdb          = $wpdb;
        $this->audit         = $audit;
        $prefix              = isset($wpdb->prefix) && is_string($wpdb->prefix) ? $wpdb->prefix : 'wp_';
        $this->tableName     = $tableName ?? ($prefix . 'pi_recursos');
        $this->tableAnalises = $tableAnalises ?? ($prefix . 'pi_analises');
    }

    public function findById(int $id): ?Recurso
    {
        if ($id <= 0) {
            return null;
        }
        $sql = $this->wpdb->prepare("SELECT * FROM {$this->tableName} WHERE id = %d", $id);
        $row = $this->wpdb->get_row($sql, ARRAY_A);

        return is_array($row) ? self::hydrate($row) : null;
    }

    public function findPorAgenteEFase(int $agenteId, string $fase): ?Recurso
    {
        if ($agenteId <= 0) {
            return null;
        }
        $faseNorm = strtolower(trim($fase));
        $sql = $this->wpdb->prepare(
            "SELECT r.*
             FROM {$this->tableName} r
             INNER JOIN {$this->tableAnalises} a ON a.id = r.analise_id
             WHERE a.agente_id = %d AND r.fase = %s
             ORDER BY r.protocolado_em DESC, r.id DESC
             LIMIT 1",
            $agenteId,
            $faseNorm
        );
        $row = $this->wpdb->get_row($sql, ARRAY_A);

        return is_array($row) ? self::hydrate($row) : null;
    }

    public function findVencendoEm(int $dias): array
    {
        if ($dias < 0) {
            return [];
        }
        $now    = new DateTimeImmutable('now');
        $limite = $now->modify(sprintf('+%d days', $dias));

        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tableName}
             WHERE decisao IS NULL
               AND prazo_fim >= %s
               AND prazo_fim <= %s
             ORDER BY prazo_fim ASC, id ASC",
            $now->format('Y-m-d H:i:s'),
            $limite->format('Y-m-d H:i:s')
        );
        $rows = $this->wpdb->get_results($sql, ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        return array_values(array_map([self::class, 'hydrate'], $rows));
    }

    public function save(Recurso $recurso): int
    {
        $payload = self::toRow($recurso);
        $formats = self::columnFormats();

        if ($recurso->id() === null) {
            $ok = $this->wpdb->insert($this->tableName, $payload, $formats);
            if ($ok === false) {
                throw new RuntimeException('Falha ao inserir recurso.');
            }
            $newId = (int) $this->wpdb->insert_id;
            $this->audit->log('recurso', $newId, 'protocolar', null, [
                'analise_id' => $recurso->analiseId(),
                'fase'       => $recurso->fase(),
                'recorrente' => $recurso->recorrenteId(),
            ]);

            return $newId;
        }

        $existing = $this->findById((int) $recurso->id());
        $before   = $existing !== null ? self::toRow($existing) : null;

        $ok = $this->wpdb->update(
            $this->tableName,
            $payload,
            ['id' => (int) $recurso->id()],
            $formats,
            ['%d']
        );
        if ($ok === false) {
            throw new RuntimeException('Falha ao atualizar recurso.');
        }

        $acao = ($before !== null && empty($before['decisao']) && !empty($payload['decisao']))
            ? 'decidir'
            : 'atualizar';
        $this->audit->log('recurso', (int) $recurso->id(), $acao, $before, $payload);

        return (int) $recurso->id();
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function hydrate(array $row): Recurso
    {
        return new Recurso(
            isset($row['id']) ? (int) $row['id'] : null,
            (int) ($row['analise_id'] ?? 0),
            (string) ($row['fase'] ?? ''),
            (int) ($row['recorrente_id'] ?? 0),
            (string) ($row['fundamentacao_md'] ?? ''),
            self::toDate($row['protocolado_em'] ?? null) ?? new DateTimeImmutable('now'),
            self::toDate($row['prazo_inicio'] ?? null) ?? new DateTimeImmutable('now'),
            self::toDate($row['prazo_fim'] ?? null) ?? new DateTimeImmutable('now'),
            isset($row['decisao']) && $row['decisao'] !== null && $row['decisao'] !== ''
                ? (string) $row['decisao']
                : null,
            isset($row['decisor_id']) && $row['decisor_id'] !== null
                ? (int) $row['decisor_id']
                : null,
            isset($row['decisao_md']) && $row['decisao_md'] !== null
                ? (string) $row['decisao_md']
                : null,
            self::toDate($row['decidido_em'] ?? null),
            self::toDate($row['publicado_em'] ?? null)
        );
    }

    /**
     * @return array<string,mixed>
     */
    private static function toRow(Recurso $r): array
    {
        return [
            'analise_id'       => $r->analiseId(),
            'fase'             => $r->fase(),
            'recorrente_id'    => $r->recorrenteId(),
            'fundamentacao_md' => $r->fundamentacaoMd(),
            'protocolado_em'   => $r->protocoladoEm()->format('Y-m-d H:i:s'),
            'prazo_inicio'     => $r->prazoInicio()->format('Y-m-d H:i:s'),
            'prazo_fim'        => $r->prazoFim()->format('Y-m-d H:i:s'),
            'decisao'          => $r->decisao(),
            'decisor_id'       => $r->decisorId(),
            'decisao_md'       => $r->decisaoMd(),
            'decidido_em'      => self::fromDate($r->decididoEm()),
            'publicado_em'     => self::fromDate($r->publicadoEm()),
        ];
    }

    /**
     * @return array<int,string>
     */
    private static function columnFormats(): array
    {
        return [
            '%d', // analise_id
            '%s', // fase
            '%d', // recorrente_id
            '%s', // fundamentacao_md
            '%s', // protocolado_em
            '%s', // prazo_inicio
            '%s', // prazo_fim
            '%s', // decisao
            '%d', // decisor_id
            '%s', // decisao_md
            '%s', // decidido_em
            '%s', // publicado_em
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
