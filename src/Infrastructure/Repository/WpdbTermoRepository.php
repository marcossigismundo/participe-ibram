<?php
/**
 * WPDB-backed implementação de {@see TermoRepository}.
 *
 * @package Ibram\ParticipeIbram\Infrastructure\Repository
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Infrastructure\Repository;

use DateTimeImmutable;
use Ibram\ParticipeIbram\Domain\Consentimento\Termo;
use Ibram\ParticipeIbram\Domain\Consentimento\TermoRepository;
use RuntimeException;

/**
 * Persistência de Termo em `{$wpdb->prefix}pi_termos`.
 */
final class WpdbTermoRepository implements TermoRepository
{
    /** @var \wpdb */
    private $wpdb;

    private string $tableName;

    /**
     * @param \wpdb       $wpdb
     * @param string|null $tableName Override (defaults to `{prefix}pi_termos`).
     */
    public function __construct($wpdb, ?string $tableName = null)
    {
        $this->wpdb      = $wpdb;
        $prefix          = isset($wpdb->prefix) && is_string($wpdb->prefix) ? $wpdb->prefix : 'wp_';
        $this->tableName = $tableName ?? ($prefix . 'pi_termos');
    }

    public function findById(int $id): ?Termo
    {
        if ($id < 1) {
            return null;
        }
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tableName} WHERE id = %d LIMIT 1",
            $id
        );
        $row = $this->wpdb->get_row($sql, ARRAY_A);

        return is_array($row) ? self::hydrate($row) : null;
    }

    public function findByVersao(string $versao): ?Termo
    {
        $versao = trim($versao);
        if ($versao === '') {
            return null;
        }
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tableName} WHERE versao = %s LIMIT 1",
            $versao
        );
        $row = $this->wpdb->get_row($sql, ARRAY_A);

        return is_array($row) ? self::hydrate($row) : null;
    }

    public function findAtivoCorrente(): ?Termo
    {
        $now = gmdate('Y-m-d H:i:s');
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tableName}
             WHERE ativo_em <= %s
               AND (inativo_em IS NULL OR inativo_em > %s)
             ORDER BY ativo_em DESC
             LIMIT 1",
            $now,
            $now
        );
        $row = $this->wpdb->get_row($sql, ARRAY_A);

        return is_array($row) ? self::hydrate($row) : null;
    }

    public function save(Termo $termo): int
    {
        $row = [
            'versao'        => $termo->versao(),
            'conteudo_md'   => $termo->conteudoMd(),
            'hash_conteudo' => $termo->hashConteudo(),
            'ativo_em'      => $termo->ativoEm()->format('Y-m-d H:i:s'),
            'inativo_em'    => $termo->inativoEm() !== null ? $termo->inativoEm()->format('Y-m-d H:i:s') : null,
            'publicado_por' => $termo->publicadoPor(),
        ];
        $formats = ['%s', '%s', '%s', '%s', '%s', '%d'];

        if ($termo->id() === null) {
            $ok = $this->wpdb->insert($this->tableName, $row, $formats);
            if ($ok === false) {
                throw new RuntimeException('Falha ao inserir termo.');
            }

            return (int) $this->wpdb->insert_id;
        }

        $ok = $this->wpdb->update(
            $this->tableName,
            $row,
            ['id' => $termo->id()],
            $formats,
            ['%d']
        );
        if ($ok === false) {
            throw new RuntimeException('Falha ao atualizar termo.');
        }

        return $termo->id();
    }

    public function inativarAnterior(int $exceptoId): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $sql = $this->wpdb->prepare(
            "UPDATE {$this->tableName}
             SET inativo_em = %s
             WHERE id <> %d AND inativo_em IS NULL",
            $now,
            $exceptoId
        );
        $this->wpdb->query($sql);
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function hydrate(array $row): Termo
    {
        return Termo::fromState(
            (int) $row['id'],
            (string) $row['versao'],
            (string) $row['conteudo_md'],
            (string) $row['hash_conteudo'],
            new DateTimeImmutable((string) $row['ativo_em']),
            isset($row['inativo_em']) && $row['inativo_em'] !== null
                ? new DateTimeImmutable((string) $row['inativo_em'])
                : null,
            (int) $row['publicado_por']
        );
    }
}
