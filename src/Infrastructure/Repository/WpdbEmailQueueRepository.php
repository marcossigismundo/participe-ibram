<?php
/**
 * WPDB-backed implementação de {@see EmailQueueRepository}.
 *
 * @package Ibram\ParticipeIbram\Infrastructure\Repository
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Infrastructure\Repository;

use DateTimeImmutable;
use Ibram\ParticipeIbram\Domain\Email\EmailQueueRepository;
use Ibram\ParticipeIbram\Domain\Email\MensagemEnfileirada;
use RuntimeException;

/**
 * Persistência da fila de e-mails em `{$wpdb->prefix}pi_email_queue`.
 *
 * A atomicidade é garantida por UPDATE com guard no WHERE:
 *
 *   UPDATE ... SET status='enviando', tentativas=tentativas+1
 *   WHERE id=? AND status='pendente'
 *
 * Esse padrão usa o lock de linha do InnoDB e o ROW_COUNT() reflete se este
 * chamador foi quem mudou o estado. Dois workers concorrentes só conseguem
 * 1 vitorioso por id (R5 B-09).
 */
final class WpdbEmailQueueRepository implements EmailQueueRepository
{
    /** @var \wpdb */
    private $wpdb;

    private string $tableName;

    public function __construct($wpdb, ?string $tableName = null)
    {
        $this->wpdb      = $wpdb;
        $prefix          = isset($wpdb->prefix) && is_string($wpdb->prefix) ? $wpdb->prefix : 'wp_';
        $this->tableName = $tableName ?? ($prefix . 'pi_email_queue');
    }

    public function enfileirar(MensagemEnfileirada $mensagem): int
    {
        $row = [
            'evento'        => $mensagem->evento(),
            'agente_id'     => $mensagem->agenteId(),
            'destinatario'  => $mensagem->destinatario(),
            'assunto'       => $mensagem->assunto(),
            'corpo_html'    => $mensagem->corpoHtml(),
            'payload_json'  => self::encodePayload($mensagem->payloadJson()),
            'tentativas'    => $mensagem->tentativas(),
            'status'        => $mensagem->status(),
            'ultimo_erro'   => $mensagem->ultimoErro(),
            'agendado_para' => $mensagem->agendadoPara()->format('Y-m-d H:i:s'),
            'enviado_em'    => $mensagem->enviadoEm() !== null
                ? $mensagem->enviadoEm()->format('Y-m-d H:i:s')
                : null,
            'created_at'    => $mensagem->createdAt()->format('Y-m-d H:i:s'),
        ];
        $formats = ['%s','%d','%s','%s','%s','%s','%d','%s','%s','%s','%s','%s'];

        $ok = $this->wpdb->insert($this->tableName, $row, $formats);
        if ($ok === false) {
            throw new RuntimeException('Falha ao enfileirar e-mail.');
        }

        return (int) $this->wpdb->insert_id;
    }

    public function proximasParaEnvio(int $limit, ?DateTimeImmutable $agora = null): array
    {
        $limit = max(1, $limit);
        $now   = $agora ?? new DateTimeImmutable('now');
        $sql   = $this->wpdb->prepare(
            "SELECT * FROM {$this->tableName}
             WHERE status = %s
               AND agendado_para <= %s
             ORDER BY agendado_para ASC, id ASC
             LIMIT %d",
            MensagemEnfileirada::STATUS_PENDENTE,
            $now->format('Y-m-d H:i:s'),
            $limit
        );

        $rows = $this->wpdb->get_results($sql, ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        return array_map([self::class, 'hydrate'], $rows);
    }

    public function marcarEnviando(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        // Atomic: ROW_COUNT() = 1 só se esta query realmente mudou status.
        $sql = $this->wpdb->prepare(
            "UPDATE {$this->tableName}
             SET status = %s,
                 tentativas = tentativas + 1
             WHERE id = %d
               AND status = %s",
            MensagemEnfileirada::STATUS_ENVIANDO,
            $id,
            MensagemEnfileirada::STATUS_PENDENTE
        );

        $affected = $this->wpdb->query($sql);

        return is_int($affected) && $affected === 1;
    }

    public function marcarEnviado(int $id, DateTimeImmutable $enviadoEm): void
    {
        if ($id <= 0) {
            return;
        }
        $this->wpdb->update(
            $this->tableName,
            [
                'status'      => MensagemEnfileirada::STATUS_ENVIADO,
                'enviado_em'  => $enviadoEm->format('Y-m-d H:i:s'),
                'ultimo_erro' => null,
            ],
            ['id' => $id],
            ['%s', '%s', '%s'],
            ['%d']
        );
    }

    public function marcarFalha(
        int $id,
        string $erro,
        int $tentativasAtuais,
        bool $retry,
        DateTimeImmutable $proxima
    ): void {
        if ($id <= 0) {
            return;
        }

        $erro = self::truncate($erro, 1000);

        if ($retry) {
            // Volta a "pendente" com agendado_para futuro (backoff). NÃO mexe em
            // tentativas (já foi incrementado em marcarEnviando).
            $this->wpdb->update(
                $this->tableName,
                [
                    'status'        => MensagemEnfileirada::STATUS_PENDENTE,
                    'ultimo_erro'   => $erro,
                    'agendado_para' => $proxima->format('Y-m-d H:i:s'),
                ],
                ['id' => $id],
                ['%s', '%s', '%s'],
                ['%d']
            );
            return;
        }

        $this->wpdb->update(
            $this->tableName,
            [
                'status'      => MensagemEnfileirada::STATUS_FALHOU,
                'ultimo_erro' => $erro,
            ],
            ['id' => $id],
            ['%s', '%s'],
            ['%d']
        );
    }

    public function listar(array $filtros, int $page = 1, int $perPage = 25): array
    {
        $page    = max(1, $page);
        $perPage = min(100, max(1, $perPage));

        $where  = ['1=1'];
        $params = [];

        $evento = isset($filtros['evento']) ? trim((string) $filtros['evento']) : '';
        if ($evento !== '') {
            $where[]  = 'evento = %s';
            $params[] = $evento;
        }
        $status = isset($filtros['status']) ? trim((string) $filtros['status']) : '';
        if ($status !== '') {
            $where[]  = 'status = %s';
            $params[] = $status;
        }
        $destinatario = isset($filtros['destinatario']) ? trim((string) $filtros['destinatario']) : '';
        if ($destinatario !== '') {
            $where[]  = 'destinatario LIKE %s';
            $params[] = '%' . $this->wpdb->esc_like($destinatario) . '%';
        }

        $whereSql = implode(' AND ', $where);

        // total
        $countSql = "SELECT COUNT(*) FROM {$this->tableName} WHERE {$whereSql}";
        $total    = (int) $this->wpdb->get_var(
            $params === []
                ? $countSql
                : $this->wpdb->prepare($countSql, ...$params)
        );

        // page
        $offset = ($page - 1) * $perPage;
        $listSql = "SELECT * FROM {$this->tableName} WHERE {$whereSql}
                    ORDER BY id DESC LIMIT %d OFFSET %d";
        $allParams = array_merge($params, [$perPage, $offset]);
        $rows      = $this->wpdb->get_results(
            $this->wpdb->prepare($listSql, ...$allParams),
            ARRAY_A
        );
        if (!is_array($rows)) {
            $rows = [];
        }

        return [
            'items'    => array_map([self::class, 'hydrate'], $rows),
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
        ];
    }

    public function findById(int $id): ?MensagemEnfileirada
    {
        if ($id <= 0) {
            return null;
        }
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tableName} WHERE id = %d LIMIT 1",
            $id
        );
        $row = $this->wpdb->get_row($sql, ARRAY_A);

        return is_array($row) ? self::hydrate($row) : null;
    }

    public function reenviar(int $id, DateTimeImmutable $agendadoPara): bool
    {
        if ($id <= 0) {
            return false;
        }
        $affected = $this->wpdb->update(
            $this->tableName,
            [
                'status'        => MensagemEnfileirada::STATUS_PENDENTE,
                'ultimo_erro'   => null,
                'agendado_para' => $agendadoPara->format('Y-m-d H:i:s'),
            ],
            ['id' => $id],
            ['%s', '%s', '%s'],
            ['%d']
        );

        return is_int($affected) && $affected >= 1;
    }

    /**
     * @param array<string,mixed>|null $payload
     */
    private static function encodePayload(?array $payload): ?string
    {
        if ($payload === null) {
            return null;
        }
        $json = function_exists('wp_json_encode')
            ? wp_json_encode($payload)
            : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($json) ? $json : null;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function hydrate(array $row): MensagemEnfileirada
    {
        $payload = null;
        if (isset($row['payload_json']) && is_string($row['payload_json']) && $row['payload_json'] !== '') {
            $decoded = json_decode((string) $row['payload_json'], true);
            $payload = is_array($decoded) ? $decoded : null;
        }

        $enviadoEm = null;
        if (isset($row['enviado_em']) && $row['enviado_em'] !== null && $row['enviado_em'] !== '') {
            $enviadoEm = new DateTimeImmutable((string) $row['enviado_em']);
        }

        return MensagemEnfileirada::fromState(
            (int) $row['id'],
            (string) $row['evento'],
            isset($row['agente_id']) && $row['agente_id'] !== null
                ? (int) $row['agente_id']
                : null,
            (string) $row['destinatario'],
            (string) $row['assunto'],
            (string) $row['corpo_html'],
            $payload,
            (int) $row['tentativas'],
            (string) $row['status'],
            isset($row['ultimo_erro']) && $row['ultimo_erro'] !== null
                ? (string) $row['ultimo_erro']
                : null,
            new DateTimeImmutable((string) $row['agendado_para']),
            $enviadoEm,
            new DateTimeImmutable((string) $row['created_at'])
        );
    }

    private static function truncate(string $value, int $max): string
    {
        if (strlen($value) <= $max) {
            return $value;
        }

        return substr($value, 0, $max);
    }
}
