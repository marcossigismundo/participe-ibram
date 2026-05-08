<?php
/**
 * Implementação wpdb do {@see ResultadoRepository}.
 *
 * @package Ibram\ParticipeIbram\Infrastructure\Repository
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Infrastructure\Repository;

use DateTimeImmutable;
use DateTimeZone;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Domain\Votacao\Resultado;
use Ibram\ParticipeIbram\Domain\Votacao\ResultadoRepository;
use RuntimeException;
use wpdb;

/**
 * Persistência de {@see Resultado} em `{$wpdb->prefix}pi_resultados`.
 *
 * `salvarResultados()` opera em transação para garantir atomicidade da apuração.
 */
final class WpdbResultadoRepository implements ResultadoRepository
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
        $this->tableName = $tableName ?? ($prefix . 'pi_resultados');
    }

    public function findByVotacao(int $votacaoId): array
    {
        if ($votacaoId <= 0) {
            return [];
        }
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tableName}
             WHERE votacao_id = %d
             ORDER BY categoria_id ASC, posicao ASC",
            $votacaoId
        );
        $rows = $this->wpdb->get_results($sql, ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }
        return array_map([self::class, 'hydrate'], $rows);
    }

    public function findEleitos(int $votacaoId): array
    {
        if ($votacaoId <= 0) {
            return [];
        }
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tableName}
             WHERE votacao_id = %d AND eleito = 1
             ORDER BY categoria_id ASC, posicao ASC",
            $votacaoId
        );
        $rows = $this->wpdb->get_results($sql, ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }
        return array_map([self::class, 'hydrate'], $rows);
    }

    public function salvarResultados(int $votacaoId, array $resultados): void
    {
        if ($votacaoId <= 0) {
            throw new RuntimeException('votacaoId invalido para salvarResultados.');
        }

        // Apuração é atômica — usa transação se possível.
        $this->wpdb->query('START TRANSACTION');
        try {
            // Limpa resultados anteriores (uma re-apuração sobrescreve por completo).
            $this->wpdb->query(
                $this->wpdb->prepare(
                    "DELETE FROM {$this->tableName} WHERE votacao_id = %d",
                    $votacaoId
                )
            );

            foreach ($resultados as $resultado) {
                if (!$resultado instanceof Resultado) {
                    throw new RuntimeException('Item nao-Resultado em salvarResultados.');
                }
                if ($resultado->votacaoId() !== $votacaoId) {
                    throw new RuntimeException(
                        'Resultado.votacaoId nao corresponde ao parametro votacaoId.'
                    );
                }

                $data = [
                    'votacao_id'             => $resultado->votacaoId(),
                    'categoria_id'           => $resultado->categoriaId(),
                    'candidato_inscricao_id' => $resultado->candidatoInscricaoId(),
                    'total_votos'            => $resultado->totalVotos(),
                    'posicao'                => $resultado->posicao(),
                    'eleito'                 => $resultado->eleito() ? 1 : 0,
                    'suplente'               => $resultado->suplente() ? 1 : 0,
                    'apurado_em'             => $resultado->apuradoEm()->format('Y-m-d H:i:s'),
                ];
                $formats = ['%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s'];

                $ok = $this->wpdb->insert($this->tableName, $data, $formats);
                if ($ok === false) {
                    throw new RuntimeException('Falha ao inserir resultado da apuracao.');
                }
            }

            $this->wpdb->query('COMMIT');
        } catch (\Throwable $e) {
            $this->wpdb->query('ROLLBACK');
            throw $e;
        }

        $this->audit->log(
            'resultado',
            $votacaoId,
            'apurar_resultados',
            null,
            ['votacao_id' => $votacaoId, 'qtd_resultados' => count($resultados)]
        );
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function hydrate(array $row): Resultado
    {
        $tz = new DateTimeZone('UTC');
        return new Resultado(
            isset($row['id']) ? (int) $row['id'] : null,
            (int) $row['votacao_id'],
            (int) $row['categoria_id'],
            (int) $row['candidato_inscricao_id'],
            (int) $row['total_votos'],
            (int) $row['posicao'],
            (int) $row['eleito'] === 1,
            (int) $row['suplente'] === 1,
            new DateTimeImmutable((string) $row['apurado_em'], $tz)
        );
    }
}
