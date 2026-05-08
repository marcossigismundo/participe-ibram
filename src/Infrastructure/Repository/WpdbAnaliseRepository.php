<?php
/**
 * Repositório `wpdb` para Análises de cadastro.
 *
 * @package Ibram\ParticipeIbram\Infrastructure\Repository
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Infrastructure\Repository;

use DateTimeImmutable;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Domain\Analise\Analise;
use Ibram\ParticipeIbram\Domain\Analise\AnaliseRepository;
use RuntimeException;

/**
 * Persistência de {@see Analise} em `{$wpdb->prefix}pi_analises`.
 *
 *  - INSERT em `save()`; updates de id pré-existente raramente acontecem
 *    (entidade é em essência imutável). Se vier com id, faz UPDATE, mantendo
 *    contrato com o repositório.
 *  - `marcarPublicada()` é a única forma autorizada de mutar uma linha após
 *    decidida — atualiza apenas as colunas de publicação e audita.
 */
final class WpdbAnaliseRepository implements AnaliseRepository
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
        $this->tableName = $tableName ?? ($prefix . 'pi_analises');
    }

    public function findById(int $id): ?Analise
    {
        if ($id <= 0) {
            return null;
        }
        $sql = $this->wpdb->prepare("SELECT * FROM {$this->tableName} WHERE id = %d", $id);
        $row = $this->wpdb->get_row($sql, ARRAY_A);

        return is_array($row) ? self::hydrate($row) : null;
    }

    public function findByAgente(int $agenteId): array
    {
        if ($agenteId <= 0) {
            return [];
        }
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tableName}
             WHERE agente_id = %d
             ORDER BY decidido_em ASC, id ASC",
            $agenteId
        );
        $rows = $this->wpdb->get_results($sql, ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        return array_values(array_map([self::class, 'hydrate'], $rows));
    }

    public function save(Analise $analise): int
    {
        $payload = self::toRow($analise);
        $formats = self::columnFormats();

        if ($analise->id() === null) {
            $ok = $this->wpdb->insert($this->tableName, $payload, $formats);
            if ($ok === false) {
                throw new RuntimeException('Falha ao inserir analise.');
            }
            $newId = (int) $this->wpdb->insert_id;
            $this->audit->log('analise', $newId, 'criar', null, [
                'agente_id'   => $analise->agenteId(),
                'analista_id' => $analise->analistaId(),
                'decisao'     => $analise->decisao(),
            ]);

            return $newId;
        }

        $existing = $this->findById((int) $analise->id());
        $before   = $existing !== null ? self::toRow($existing) : null;

        $ok = $this->wpdb->update(
            $this->tableName,
            $payload,
            ['id' => (int) $analise->id()],
            $formats,
            ['%d']
        );
        if ($ok === false) {
            throw new RuntimeException('Falha ao atualizar analise.');
        }

        $this->audit->log('analise', (int) $analise->id(), 'atualizar', $before, [
            'agente_id'   => $analise->agenteId(),
            'analista_id' => $analise->analistaId(),
            'decisao'     => $analise->decisao(),
        ]);

        return (int) $analise->id();
    }

    public function marcarPublicada(int $id, string $url, string $hash): void
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('marcarPublicada: id invalido.');
        }
        $url  = trim($url);
        $hash = trim($hash);
        if ($url === '' || $hash === '') {
            throw new \InvalidArgumentException('marcarPublicada: url/hash obrigatorios.');
        }

        $now = gmdate('Y-m-d H:i:s');
        $ok = $this->wpdb->update(
            $this->tableName,
            [
                'publicado_em'    => $now,
                'url_publicacao'  => $url,
                'hash_publicacao' => $hash,
            ],
            ['id' => $id],
            ['%s', '%s', '%s'],
            ['%d']
        );
        if ($ok === false) {
            throw new RuntimeException('Falha ao marcar analise como publicada.');
        }

        $this->audit->log('analise', $id, 'publicar', null, [
            'url_publicacao'  => $url,
            'hash_publicacao' => $hash,
        ]);
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function hydrate(array $row): Analise
    {
        $publicadoEm = self::toDate($row['publicado_em'] ?? null);

        return new Analise(
            isset($row['id']) ? (int) $row['id'] : null,
            (int) ($row['agente_id'] ?? 0),
            (int) ($row['analista_id'] ?? 0),
            (string) ($row['decisao'] ?? ''),
            (string) ($row['parecer_md'] ?? ''),
            isset($row['fundamentacao_md']) && $row['fundamentacao_md'] !== null
                ? (string) $row['fundamentacao_md']
                : null,
            self::toDate($row['decidido_em'] ?? null) ?? new DateTimeImmutable('now'),
            $publicadoEm,
            isset($row['url_publicacao']) && $row['url_publicacao'] !== null
                ? (string) $row['url_publicacao']
                : null,
            isset($row['hash_publicacao']) && $row['hash_publicacao'] !== null
                ? (string) $row['hash_publicacao']
                : null
        );
    }

    /**
     * @return array<string,mixed>
     */
    private static function toRow(Analise $analise): array
    {
        return [
            'agente_id'        => $analise->agenteId(),
            'analista_id'      => $analise->analistaId(),
            'decisao'          => $analise->decisao(),
            'parecer_md'       => $analise->parecerMd(),
            'fundamentacao_md' => $analise->fundamentacaoMd(),
            'decidido_em'      => $analise->decididoEm()->format('Y-m-d H:i:s'),
            'publicado_em'     => self::fromDate($analise->publicadoEm()),
            'url_publicacao'   => $analise->urlPublicacao(),
            'hash_publicacao'  => $analise->hashPublicacao(),
        ];
    }

    /**
     * @return array<int,string>
     */
    private static function columnFormats(): array
    {
        return ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s'];
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
