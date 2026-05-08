<?php
/**
 * WPDB-backed implementação de {@see SolicitacaoTitularRepository}.
 *
 * @package Ibram\ParticipeIbram\Infrastructure\Repository
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Infrastructure\Repository;

use DateTimeImmutable;
use Ibram\ParticipeIbram\Domain\Consentimento\SolicitacaoTitular;
use Ibram\ParticipeIbram\Domain\Consentimento\SolicitacaoTitularRepository;
use RuntimeException;

/**
 * Persistência em `{$wpdb->prefix}pi_solicitacoes_titular`.
 */
final class WpdbSolicitacaoTitularRepository implements SolicitacaoTitularRepository
{
    /** @var \wpdb */
    private $wpdb;

    private string $tableName;

    /**
     * @param \wpdb       $wpdb
     * @param string|null $tableName Override (defaults to `{prefix}pi_solicitacoes_titular`).
     */
    public function __construct($wpdb, ?string $tableName = null)
    {
        $this->wpdb      = $wpdb;
        $prefix          = isset($wpdb->prefix) && is_string($wpdb->prefix) ? $wpdb->prefix : 'wp_';
        $this->tableName = $tableName ?? ($prefix . 'pi_solicitacoes_titular');
    }

    public function findById(int $id): ?SolicitacaoTitular
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

    public function findAbertasPorAgente(int $agenteId): array
    {
        if ($agenteId < 1) {
            return [];
        }
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tableName}
             WHERE agente_id = %d AND status IN ('aberta','em_atendimento')
             ORDER BY protocolada_em DESC",
            $agenteId
        );
        $rows = $this->wpdb->get_results($sql, ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        return array_map([self::class, 'hydrate'], $rows);
    }

    public function findPendentesParaDPO(int $page = 1, int $perPage = 25): array
    {
        $page    = max(1, $page);
        $perPage = max(1, min(200, $perPage));
        $offset  = ($page - 1) * $perPage;

        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tableName}
             WHERE status IN ('aberta','em_atendimento')
             ORDER BY protocolada_em ASC
             LIMIT %d OFFSET %d",
            $perPage,
            $offset
        );
        $rows = $this->wpdb->get_results($sql, ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        return array_map([self::class, 'hydrate'], $rows);
    }

    public function save(SolicitacaoTitular $solicitacao): int
    {
        $row = [
            'agente_id'      => $solicitacao->agenteId(),
            'tipo'           => $solicitacao->tipo(),
            'detalhes_md'    => $solicitacao->detalhesMd(),
            'status'         => $solicitacao->status(),
            'resposta_md'    => $solicitacao->respostaMd(),
            'protocolada_em' => $solicitacao->protocoladaEm()->format('Y-m-d H:i:s'),
            'atendida_em'    => $solicitacao->atendidaEm() !== null
                ? $solicitacao->atendidaEm()->format('Y-m-d H:i:s')
                : null,
            'atendida_por'   => $solicitacao->atendidaPor(),
        ];
        $formats = ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d'];

        if ($solicitacao->id() === null) {
            $ok = $this->wpdb->insert($this->tableName, $row, $formats);
            if ($ok === false) {
                throw new RuntimeException('Falha ao inserir solicitação do titular.');
            }

            return (int) $this->wpdb->insert_id;
        }

        $ok = $this->wpdb->update(
            $this->tableName,
            $row,
            ['id' => $solicitacao->id()],
            $formats,
            ['%d']
        );
        if ($ok === false) {
            throw new RuntimeException('Falha ao atualizar solicitação do titular.');
        }

        return $solicitacao->id();
    }

    public function findVencendoEmDias(int $dias): array
    {
        $dias  = max(0, $dias);
        $today = new DateTimeImmutable('now');

        // janela: status pendente E (prazoFinal entre now e now+$dias).
        // prazoFinal = protocolada_em + 15 dias  →  filtrar protocolada_em entre (limite-15) e (limite-15+$dias)
        // Mais direto: filtrar protocolada_em <= now - (15 - $dias).
        $limiteSql = $today->modify(sprintf('-%d days', SolicitacaoTitular::PRAZO_DIAS - $dias))->format('Y-m-d H:i:s');

        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tableName}
             WHERE status IN ('aberta','em_atendimento')
               AND protocolada_em <= %s
             ORDER BY protocolada_em ASC",
            $limiteSql
        );
        $rows = $this->wpdb->get_results($sql, ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        return array_map([self::class, 'hydrate'], $rows);
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function hydrate(array $row): SolicitacaoTitular
    {
        return SolicitacaoTitular::fromState(
            (int) $row['id'],
            (int) $row['agente_id'],
            (string) $row['tipo'],
            isset($row['detalhes_md']) && $row['detalhes_md'] !== null ? (string) $row['detalhes_md'] : null,
            (string) $row['status'],
            isset($row['resposta_md']) && $row['resposta_md'] !== null ? (string) $row['resposta_md'] : null,
            new DateTimeImmutable((string) $row['protocolada_em']),
            isset($row['atendida_em']) && $row['atendida_em'] !== null
                ? new DateTimeImmutable((string) $row['atendida_em'])
                : null,
            isset($row['atendida_por']) && $row['atendida_por'] !== null ? (int) $row['atendida_por'] : null
        );
    }
}
