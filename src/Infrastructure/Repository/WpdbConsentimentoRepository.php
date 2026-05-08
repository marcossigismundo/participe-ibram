<?php
/**
 * WPDB-backed implementação de {@see ConsentimentoRepository}.
 *
 * @package Ibram\ParticipeIbram\Infrastructure\Repository
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Infrastructure\Repository;

use DateTimeImmutable;
use DomainException;
use Ibram\ParticipeIbram\Domain\Consentimento\Consentimento;
use Ibram\ParticipeIbram\Domain\Consentimento\ConsentimentoRepository;
use Ibram\ParticipeIbram\Domain\Consentimento\Finalidade;
use Ibram\ParticipeIbram\Domain\Consentimento\StatusConsentimento;
use RuntimeException;

/**
 * Persistência de Consentimento em `{$wpdb->prefix}pi_consentimentos`.
 *
 * Apenas INSERT é usado — cada decisão produz um novo registro (ver pattern
 * append-only do R2-lgpd.md §3.2).
 */
final class WpdbConsentimentoRepository implements ConsentimentoRepository
{
    /** @var \wpdb */
    private $wpdb;

    private string $tableName;

    /**
     * @param \wpdb       $wpdb
     * @param string|null $tableName Override (defaults to `{prefix}pi_consentimentos`).
     */
    public function __construct($wpdb, ?string $tableName = null)
    {
        $this->wpdb      = $wpdb;
        $prefix          = isset($wpdb->prefix) && is_string($wpdb->prefix) ? $wpdb->prefix : 'wp_';
        $this->tableName = $tableName ?? ($prefix . 'pi_consentimentos');
    }

    public function findVigentePorAgenteEFinalidade(int $agenteId, Finalidade $finalidade): ?Consentimento
    {
        if ($agenteId < 1) {
            return null;
        }
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tableName}
             WHERE agente_id = %d AND finalidade = %s
             ORDER BY registrado_em DESC, id DESC
             LIMIT 1",
            $agenteId,
            $finalidade->value()
        );
        $row = $this->wpdb->get_row($sql, ARRAY_A);

        return is_array($row) ? self::hydrate($row) : null;
    }

    public function findTodosPorAgente(int $agenteId): array
    {
        if ($agenteId < 1) {
            return [];
        }
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tableName}
             WHERE agente_id = %d
             ORDER BY registrado_em ASC, id ASC",
            $agenteId
        );
        $rows = $this->wpdb->get_results($sql, ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        return array_map([self::class, 'hydrate'], $rows);
    }

    public function save(Consentimento $consentimento): int
    {
        $row = [
            'agente_id'     => $consentimento->agenteId(),
            'termo_id'      => $consentimento->termoId(),
            'finalidade'    => $consentimento->finalidade()->value(),
            'status'        => $consentimento->status()->value(),
            'ip_hash'       => $consentimento->ipHash(),
            'user_agent'    => $consentimento->userAgent(),
            'registrado_em' => $consentimento->registradoEm()->format('Y-m-d H:i:s'),
            'revogado_em'   => $consentimento->revogadoEm() !== null
                ? $consentimento->revogadoEm()->format('Y-m-d H:i:s')
                : null,
        ];
        $formats = ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s'];

        $ok = $this->wpdb->insert($this->tableName, $row, $formats);
        if ($ok === false) {
            throw new RuntimeException('Falha ao inserir consentimento.');
        }

        return (int) $this->wpdb->insert_id;
    }

    public function revogarPorAgenteEFinalidade(
        int $agenteId,
        Finalidade $finalidade,
        ?string $ipHash,
        ?string $userAgent
    ): void {
        $vigente = $this->findVigentePorAgenteEFinalidade($agenteId, $finalidade);
        if ($vigente === null) {
            throw new DomainException('Não há consentimento prévio para revogar.');
        }
        if ($vigente->status()->isRevogado()) {
            throw new DomainException('Consentimento já está revogado.');
        }
        if ($finalidade->isObrigatoria()) {
            throw new DomainException(sprintf(
                'Finalidade obrigatória "%s" não pode ser revogada.',
                $finalidade->value()
            ));
        }

        $now = new DateTimeImmutable('now');
        $rev = new Consentimento(
            null,
            $agenteId,
            $vigente->termoId(),
            $finalidade,
            StatusConsentimento::revogado(),
            $ipHash,
            $userAgent,
            $now,
            $now
        );

        $this->save($rev);
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function hydrate(array $row): Consentimento
    {
        return Consentimento::fromState(
            (int) $row['id'],
            (int) $row['agente_id'],
            (int) $row['termo_id'],
            Finalidade::fromString((string) $row['finalidade']),
            StatusConsentimento::fromString((string) $row['status']),
            isset($row['ip_hash']) && $row['ip_hash'] !== null ? (string) $row['ip_hash'] : null,
            isset($row['user_agent']) && $row['user_agent'] !== null ? (string) $row['user_agent'] : null,
            new DateTimeImmutable((string) $row['registrado_em']),
            isset($row['revogado_em']) && $row['revogado_em'] !== null
                ? new DateTimeImmutable((string) $row['revogado_em'])
                : null
        );
    }
}
