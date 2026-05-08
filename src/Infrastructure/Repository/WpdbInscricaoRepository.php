<?php
/**
 * Repositório `wpdb` para Inscricao em edital (SCHEMA §4).
 *
 * @package Ibram\ParticipeIbram\Infrastructure\Repository
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Infrastructure\Repository;

use DateTimeImmutable;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Domain\Edital\Inscricao;
use Ibram\ParticipeIbram\Domain\Edital\StatusInscricao;
use RuntimeException;

/**
 * Persistência de {@see Inscricao} contra `{$wpdb->prefix}pi_inscricoes`.
 *
 * UNIQUE (edital_id, categoria_id, agente_id) é garantido pelo schema; aqui
 * expomos {@see findByAgenteEEdital()} para a aplicação evitar a violação.
 */
final class WpdbInscricaoRepository
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
        $this->tableName = $tableName ?? ($prefix . 'pi_inscricoes');
    }

    public function findById(int $id): ?Inscricao
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

    /**
     * Localiza qualquer inscrição existente do agente no edital (qualquer categoria).
     */
    public function findByAgenteEEdital(int $agenteId, int $editalId): ?Inscricao
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->tableName}
                 WHERE agente_id = %d AND edital_id = %d
                 ORDER BY id ASC LIMIT 1",
                $agenteId,
                $editalId
            ),
            ARRAY_A
        );

        return is_array($row) ? self::hydrate($row) : null;
    }

    /**
     * Localiza inscrição específica do trio (edital, categoria, agente). Útil
     * para detectar duplicidade de UNIQUE.
     */
    public function findByEditalCategoriaEAgente(int $editalId, int $categoriaId, int $agenteId): ?Inscricao
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->tableName}
                 WHERE edital_id = %d AND categoria_id = %d AND agente_id = %d
                 LIMIT 1",
                $editalId,
                $categoriaId,
                $agenteId
            ),
            ARRAY_A
        );

        return is_array($row) ? self::hydrate($row) : null;
    }

    /**
     * Lista inscrições de uma categoria, paginadas.
     *
     * @return array<int,Inscricao>
     */
    public function findByEditalECategoria(int $editalId, int $categoriaId, int $page, int $perPage): array
    {
        $page    = max(1, $page);
        $perPage = max(1, min(200, $perPage));
        $offset  = ($page - 1) * $perPage;

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->tableName}
                 WHERE edital_id = %d AND categoria_id = %d
                 ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $editalId,
                $categoriaId,
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
     * Inscrições com status final habilitado de uma categoria — alvo da votação.
     *
     * @return array<int,Inscricao>
     */
    public function findHabilitadasParaVotacao(int $editalId, int $categoriaId): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->tableName}
                 WHERE edital_id = %d AND categoria_id = %d AND status = %s
                 ORDER BY id ASC",
                $editalId,
                $categoriaId,
                StatusInscricao::FINAL_HABILITADO
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
     * @return int ID da inscrição persistida.
     */
    public function save(Inscricao $inscricao): int
    {
        $payload = self::toRow($inscricao);
        $formats = self::columnFormats();

        if ($inscricao->id() === null) {
            $result = $this->wpdb->insert($this->tableName, $payload, $formats);
            if ($result === false) {
                throw new RuntimeException('Falha ao inserir inscricao.');
            }
            $newId = (int) $this->wpdb->insert_id;
            $this->audit->log('inscricao', $newId, 'criar', null, $payload);

            return $newId;
        }

        $existing = $this->findById((int) $inscricao->id());
        $before   = $existing !== null ? self::toRow($existing) : null;

        $result = $this->wpdb->update(
            $this->tableName,
            $payload,
            ['id' => (int) $inscricao->id()],
            $formats,
            ['%d']
        );
        if ($result === false) {
            throw new RuntimeException('Falha ao atualizar inscricao.');
        }

        $acao = ($before !== null && isset($before['status']) && $before['status'] !== $payload['status'])
            ? 'transicao_status'
            : 'atualizar';
        $this->audit->log('inscricao', (int) $inscricao->id(), $acao, $before, $payload);

        return (int) $inscricao->id();
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function hydrate(array $row): Inscricao
    {
        return new Inscricao(
            isset($row['id']) ? (int) $row['id'] : null,
            (int) ($row['edital_id'] ?? 0),
            (int) ($row['categoria_id'] ?? 0),
            (int) ($row['agente_id'] ?? 0),
            isset($row['portfolio_md']) && $row['portfolio_md'] !== null ? (string) $row['portfolio_md'] : null,
            StatusInscricao::fromString((string) ($row['status'] ?? StatusInscricao::RASCUNHO)),
            self::toDate($row['inscrito_em'] ?? null),
            self::toDate($row['habilitado_em'] ?? null),
            self::toDate($row['inabilitado_em'] ?? null),
            isset($row['motivo_inabilitacao_md']) && $row['motivo_inabilitacao_md'] !== null
                ? (string) $row['motivo_inabilitacao_md']
                : null,
            self::toDate($row['created_at'] ?? null) ?? new DateTimeImmutable('now'),
            self::toDate($row['updated_at'] ?? null) ?? new DateTimeImmutable('now')
        );
    }

    /**
     * @return array<string,mixed>
     */
    private static function toRow(Inscricao $inscricao): array
    {
        return [
            'edital_id'              => $inscricao->editalId(),
            'categoria_id'           => $inscricao->categoriaId(),
            'agente_id'              => $inscricao->agenteId(),
            'portfolio_md'           => $inscricao->portfolioMd(),
            'status'                 => $inscricao->status()->value(),
            'inscrito_em'            => self::fromDate($inscricao->inscritoEm()),
            'habilitado_em'          => self::fromDate($inscricao->habilitadoEm()),
            'inabilitado_em'         => self::fromDate($inscricao->inabilitadoEm()),
            'motivo_inabilitacao_md' => $inscricao->motivoInabilitacaoMd(),
            'created_at'             => $inscricao->createdAt()->format('Y-m-d H:i:s'),
            'updated_at'             => $inscricao->updatedAt()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @return array<int,string>
     */
    private static function columnFormats(): array
    {
        return [
            '%d', // edital_id
            '%d', // categoria_id
            '%d', // agente_id
            '%s', // portfolio_md
            '%s', // status
            '%s', // inscrito_em
            '%s', // habilitado_em
            '%s', // inabilitado_em
            '%s', // motivo_inabilitacao_md
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
