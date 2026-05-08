<?php
/**
 * Implementação wpdb do {@see VotoRepository}.
 *
 * @package Ibram\ParticipeIbram\Infrastructure\Repository
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Infrastructure\Repository;

use Ibram\ParticipeIbram\Domain\Votacao\Voto;
use Ibram\ParticipeIbram\Domain\Votacao\VotoDuplicado;
use Ibram\ParticipeIbram\Domain\Votacao\VotoRepository;
use RuntimeException;
use wpdb;

/**
 * Persistência de {@see Voto} em `{$wpdb->prefix}pi_votos`.
 *
 * Privacidade:
 *  - Esta classe **NUNCA** lê ou escreve `agente_id` — o eleitor é referenciado
 *    apenas pelo seu `eleitor_hash` (HMAC) já calculado externamente.
 *  - Não há método para listar todos os votos com PII associada.
 *  - {@see existeVoto()} apenas confirma presença sem revelar candidato.
 *
 * Auditoria:
 *  - Não loga `eleitor_hash` no audit log (auditoria sem rastreamento).
 *  - Apenas `entidade_id` (id do voto) e `categoria_id` aparecem no log.
 */
final class WpdbVotoRepository implements VotoRepository
{
    /** @var wpdb */
    private $wpdb;

    private string $tableName;

    /**
     * @param wpdb $wpdb
     */
    public function __construct($wpdb, ?string $tableName = null)
    {
        $this->wpdb      = $wpdb;
        $prefix          = isset($wpdb->prefix) && is_string($wpdb->prefix) ? $wpdb->prefix : 'wp_';
        $this->tableName = $tableName ?? ($prefix . 'pi_votos');
    }

    public function existeVoto(int $votacaoId, int $categoriaId, string $eleitorHash): bool
    {
        if ($votacaoId <= 0 || $categoriaId <= 0) {
            return false;
        }
        if (strlen($eleitorHash) !== 64 || !ctype_xdigit($eleitorHash)) {
            return false;
        }

        $sql = $this->wpdb->prepare(
            "SELECT 1 FROM {$this->tableName}
             WHERE votacao_id = %d AND categoria_id = %d AND eleitor_hash = %s
             LIMIT 1",
            $votacaoId,
            $categoriaId,
            $eleitorHash
        );

        return $this->wpdb->get_var($sql) !== null;
    }

    public function salvarVoto(Voto $voto): int
    {
        if ($voto->id() !== null) {
            throw new RuntimeException(
                'Voto e imutavel apos criacao — nao deve receber save de update.'
            );
        }

        $data = [
            'votacao_id'             => $voto->votacaoId(),
            'categoria_id'           => $voto->categoriaId(),
            'eleitor_hash'           => $voto->eleitorHash(),
            'candidato_inscricao_id' => $voto->candidatoInscricaoId(),
            'votado_em'              => $voto->votadoEm()->format('Y-m-d H:i:s'),
            'ip_hash'                => $voto->ipHash(),
        ];
        $formats = ['%d', '%d', '%s', '%d', '%s', '%s'];

        // Limpa erros prévios para que possamos inspecionar a falha desta operação.
        if (isset($this->wpdb->last_error)) {
            $this->wpdb->last_error = '';
        }

        $ok = $this->wpdb->insert($this->tableName, $data, $formats);
        if ($ok === false) {
            $errorMessage = isset($this->wpdb->last_error) ? (string) $this->wpdb->last_error : '';
            // Detecta colisão da UNIQUE(votacao_id, categoria_id, eleitor_hash) — MySQL 1062.
            if (
                stripos($errorMessage, 'Duplicate entry') !== false
                || stripos($errorMessage, '1062') !== false
                || stripos($errorMessage, 'uniq_eleitor_categoria') !== false
            ) {
                throw VotoDuplicado::paraVotacaoCategoria($voto->votacaoId(), $voto->categoriaId());
            }
            throw new RuntimeException('Falha ao registrar voto.');
        }

        return (int) $this->wpdb->insert_id;
    }

    public function contarPorCandidato(int $votacaoId, int $categoriaId): array
    {
        if ($votacaoId <= 0 || $categoriaId <= 0) {
            return [];
        }

        $sql = $this->wpdb->prepare(
            "SELECT candidato_inscricao_id, COUNT(*) AS total
             FROM {$this->tableName}
             WHERE votacao_id = %d AND categoria_id = %d
             GROUP BY candidato_inscricao_id",
            $votacaoId,
            $categoriaId
        );

        $rows = $this->wpdb->get_results($sql, ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $candidatoId = (int) $row['candidato_inscricao_id'];
            $out[$candidatoId] = (int) $row['total'];
        }
        return $out;
    }

    public function gerarHashPreApuracao(int $votacaoId): string
    {
        if ($votacaoId <= 0) {
            return hash('sha256', '');
        }

        // Ordenação canônica determinística — sem incluir id (que é volátil) nem agente_id.
        // Dois bancos com os mesmos votos produzem o mesmo hash.
        $sql = $this->wpdb->prepare(
            "SELECT categoria_id, eleitor_hash, candidato_inscricao_id, votado_em
             FROM {$this->tableName}
             WHERE votacao_id = %d
             ORDER BY categoria_id ASC, eleitor_hash ASC, candidato_inscricao_id ASC, votado_em ASC",
            $votacaoId
        );

        $rows = $this->wpdb->get_results($sql, ARRAY_A);
        if (!is_array($rows)) {
            return hash('sha256', '');
        }

        // Concatena cada voto canonicamente; separa votos com newline.
        $buffer = '';
        foreach ($rows as $row) {
            $buffer .= ((int) $row['categoria_id']) . '|'
                . ((string) $row['eleitor_hash']) . '|'
                . ((int) $row['candidato_inscricao_id']) . '|'
                . ((string) $row['votado_em']) . "\n";
        }

        return hash('sha256', $buffer);
    }

    public function contarTotalDaVotacao(int $votacaoId): int
    {
        if ($votacaoId <= 0) {
            return 0;
        }
        $sql = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->tableName} WHERE votacao_id = %d",
            $votacaoId
        );
        return (int) $this->wpdb->get_var($sql);
    }
}
