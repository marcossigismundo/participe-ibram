<?php
/**
 * Implementação wpdb do {@see VotacaoRepository}.
 *
 * @package Ibram\ParticipeIbram\Infrastructure\Repository
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Infrastructure\Repository;

use DateTimeImmutable;
use DateTimeZone;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Domain\Votacao\ModoVotacao;
use Ibram\ParticipeIbram\Domain\Votacao\StatusVotacao;
use Ibram\ParticipeIbram\Domain\Votacao\Votacao;
use Ibram\ParticipeIbram\Domain\Votacao\VotacaoNotFound;
use Ibram\ParticipeIbram\Domain\Votacao\VotacaoRepository;
use RuntimeException;
use wpdb;

/**
 * Persistência de {@see Votacao} em `{$wpdb->prefix}pi_votacoes`.
 *
 * Auditoria: cada save dispara entrada em `wp_pi_audit_log` (entidade=`votacao`).
 */
final class WpdbVotacaoRepository implements VotacaoRepository
{
    /** @var wpdb */
    private $wpdb;

    private AuditLogger $audit;

    private string $tableName;

    /**
     * @param wpdb $wpdb
     */
    public function __construct($wpdb, AuditLogger $audit, ?string $tableName = null)
    {
        $this->wpdb      = $wpdb;
        $this->audit     = $audit;
        $prefix          = isset($wpdb->prefix) && is_string($wpdb->prefix) ? $wpdb->prefix : 'wp_';
        $this->tableName = $tableName ?? ($prefix . 'pi_votacoes');
    }

    public function findById(int $id): Votacao
    {
        if ($id <= 0) {
            throw VotacaoNotFound::withId($id);
        }
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tableName} WHERE id = %d LIMIT 1",
            $id
        );
        $row = $this->wpdb->get_row($sql, ARRAY_A);
        if (!is_array($row)) {
            throw VotacaoNotFound::withId($id);
        }

        return self::hydrate($row);
    }

    public function findByEdital(int $editalId): ?Votacao
    {
        if ($editalId <= 0) {
            return null;
        }
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tableName} WHERE edital_id = %d LIMIT 1",
            $editalId
        );
        $row = $this->wpdb->get_row($sql, ARRAY_A);
        if (!is_array($row)) {
            return null;
        }

        return self::hydrate($row);
    }

    public function save(Votacao $votacao): int
    {
        $data = [
            'edital_id'         => $votacao->editalId(),
            'abertura'          => $votacao->abertura()->format('Y-m-d H:i:s'),
            'encerramento'      => $votacao->encerramento()->format('Y-m-d H:i:s'),
            'status'            => $votacao->status()->value(),
            'modo'              => $votacao->modo()->value(),
            'hash_pre_apuracao' => $votacao->hashPreApuracao(),
            'apurado_em'        => $votacao->apuradoEm() !== null
                ? $votacao->apuradoEm()->format('Y-m-d H:i:s')
                : null,
        ];
        $formats = ['%d', '%s', '%s', '%s', '%s', '%s', '%s'];

        if ($votacao->id() === null) {
            $ok = $this->wpdb->insert($this->tableName, $data, $formats);
            if ($ok === false) {
                throw new RuntimeException('Falha ao inserir votacao.');
            }
            $newId = (int) $this->wpdb->insert_id;
            $this->audit->log('votacao', $newId, 'criar', null, $data);
            return $newId;
        }

        $existing = $this->findById($votacao->id());
        $before   = self::toAuditRow($existing);

        $ok = $this->wpdb->update(
            $this->tableName,
            $data,
            ['id' => $votacao->id()],
            $formats,
            ['%d']
        );
        if ($ok === false) {
            throw new RuntimeException('Falha ao atualizar votacao.');
        }
        $this->audit->log('votacao', $votacao->id(), 'atualizar', $before, $data);

        return $votacao->id();
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function hydrate(array $row): Votacao
    {
        $tz = new DateTimeZone('UTC');
        return new Votacao(
            isset($row['id']) ? (int) $row['id'] : null,
            (int) $row['edital_id'],
            new DateTimeImmutable((string) $row['abertura'], $tz),
            new DateTimeImmutable((string) $row['encerramento'], $tz),
            StatusVotacao::fromString((string) $row['status']),
            ModoVotacao::fromString((string) $row['modo']),
            isset($row['hash_pre_apuracao']) && $row['hash_pre_apuracao'] !== null && $row['hash_pre_apuracao'] !== ''
                ? (string) $row['hash_pre_apuracao']
                : null,
            isset($row['apurado_em']) && $row['apurado_em'] !== null && $row['apurado_em'] !== ''
                ? new DateTimeImmutable((string) $row['apurado_em'], $tz)
                : null
        );
    }

    /**
     * @return array<string,mixed>
     */
    private static function toAuditRow(Votacao $v): array
    {
        return [
            'id'                => $v->id(),
            'edital_id'         => $v->editalId(),
            'abertura'          => $v->abertura()->format('Y-m-d H:i:s'),
            'encerramento'      => $v->encerramento()->format('Y-m-d H:i:s'),
            'status'            => $v->status()->value(),
            'modo'              => $v->modo()->value(),
            'hash_pre_apuracao' => $v->hashPreApuracao(),
            'apurado_em'        => $v->apuradoEm() !== null
                ? $v->apuradoEm()->format('Y-m-d H:i:s')
                : null,
        ];
    }
}
