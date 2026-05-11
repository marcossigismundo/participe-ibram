<?php
/**
 * AuditAdminAjax — AJAX endpoints para a UI do log de auditoria.
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\Ajax
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\Ajax;

use Ibram\ParticipeIbram\Application\Audit\ExportarAuditLogCommand;
use Ibram\ParticipeIbram\Application\Audit\ExportarAuditLogHandler;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Core\Helpers\RateLimiter;
use Ibram\ParticipeIbram\Core\Helpers\RequestHelper;
use Ibram\ParticipeIbram\Presentation\Admin\Controllers\AuditDetalheController;
use Ibram\ParticipeIbram\Presentation\Admin\Support\AuditLogQuery;
use Throwable;

/**
 * Endpoints AJAX (todos wp_ajax_* — nunca nopriv):
 *
 *  - pi_admin_audit_get_detalhe      cap pi_visualizar_audit_log, rate 30/min
 *  - pi_admin_audit_export           cap pi_visualizar_audit_log, rate 5/hora
 *  - pi_admin_audit_test_integridade cap pi_administrador,        rate 5/min
 *
 * Pipeline: nonce (escopado por user) → cap → rate → lógica → audit → resposta.
 */
final class AuditAdminAjax
{
    public const CAP_VISUALIZAR = 'pi_visualizar_audit_log';
    public const CAP_ADMIN      = 'pi_administrador';

    private const RATE_DETALHE_MAX    = 30;
    private const RATE_DETALHE_WINDOW = 60;

    private const RATE_EXPORT_MAX    = 5;
    private const RATE_EXPORT_WINDOW = 3600;

    private const RATE_INTEGRIDADE_MAX    = 5;
    private const RATE_INTEGRIDADE_WINDOW = 60;

    private AuditLogQuery $query;
    private AuditDetalheController $detalheCtrl;
    private ExportarAuditLogHandler $exportHandler;
    private AuditLogger $audit;

    public function __construct(
        AuditLogQuery $query,
        AuditDetalheController $detalheCtrl,
        ExportarAuditLogHandler $exportHandler,
        AuditLogger $audit
    ) {
        $this->query         = $query;
        $this->detalheCtrl   = $detalheCtrl;
        $this->exportHandler = $exportHandler;
        $this->audit         = $audit;
    }

    /**
     * Registra os hooks AJAX. Idempotente.
     */
    public function registerHooks(): void
    {
        if (!function_exists('add_action')) {
            return;
        }
        \add_action('wp_ajax_pi_admin_audit_get_detalhe',      [$this, 'handleGetDetalhe']);
        \add_action('wp_ajax_pi_admin_audit_export',           [$this, 'handleExport']);
        \add_action('wp_ajax_pi_admin_audit_test_integridade', [$this, 'handleTestIntegridade']);
    }

    /* ====================== handlers ======================= */

    /**
     * GET payload de um registro com mascaramento PII.
     * Resposta: { success: true, data: { record: {...} } }
     */
    public function handleGetDetalhe(): void
    {
        $userId = $this->requireUser();

        if (!$this->checkNonce('pi_audit_detalhe_' . $userId)) {
            $this->jsonError(self::tr('Nonce inválido.'), 403);
            return;
        }

        if (!self::userCan(self::CAP_VISUALIZAR)) {
            $this->jsonError(self::tr('Permissão negada.'), 403);
            return;
        }

        if (!RateLimiter::check(
            RateLimiter::keyForUser('pi_audit_detalhe', $userId),
            self::RATE_DETALHE_MAX,
            self::RATE_DETALHE_WINDOW
        )) {
            $this->jsonError(self::tr('Limite de requisições atingido.'), 429);
            return;
        }

        $id = (int) RequestHelper::get('id', 'absint', 0);
        if ($id <= 0) {
            $this->jsonError(self::tr('ID inválido.'), 400);
            return;
        }

        $record = $this->query->findById($id);
        if ($record === null) {
            $this->jsonError(self::tr('Registro não encontrado.'), 404);
            return;
        }

        // Mascara PII nos payloads
        $dadosAntes  = $this->detalheCtrl->maskPayload($record['dados_antes'] ?? null);
        $dadosDepois = $this->detalheCtrl->maskPayload($record['dados_depois'] ?? null);

        // Audita o acesso via AJAX
        $this->audit->log(
            'audit_log',
            $id,
            'view_audit_detail',
            null,
            ['via' => 'ajax', 'audit_record_id' => $id],
            $userId
        );

        // Whitelist defensiva: nunca retorna o raw JSON não-mascarado
        $response = [
            'id'           => (int) $record['id'],
            'entidade'     => (string) ($record['entidade'] ?? ''),
            'entidade_id'  => $record['entidade_id'] !== null ? (int) $record['entidade_id'] : null,
            'acao'         => (string) ($record['acao'] ?? ''),
            'ator_id'      => $record['ator_id'] !== null ? (int) $record['ator_id'] : null,
            'ip_hash'      => $record['ip_hash'] !== null
                ? substr((string) $record['ip_hash'], 0, 8) . '...'
                : null,
            'user_agent'   => $record['user_agent'] !== null
                ? substr((string) $record['user_agent'], 0, 120)
                : null,
            'ocorrido_em'  => (string) ($record['ocorrido_em'] ?? ''),
            'dados_antes'  => $dadosAntes,
            'dados_depois' => $dadosDepois,
        ];

        $this->jsonSuccess(['record' => $response]);
    }

    /**
     * Invoca ExportarAuditLogHandler e retorna URL assinada.
     * Resposta: { success: true, data: { url: '...' } }
     */
    public function handleExport(): void
    {
        $userId = $this->requireUser();

        if (!$this->checkNonce('pi_audit_export_' . $userId)) {
            $this->jsonError(self::tr('Nonce inválido.'), 403);
            return;
        }

        if (!self::userCan(self::CAP_VISUALIZAR)) {
            $this->jsonError(self::tr('Permissão negada.'), 403);
            return;
        }

        if (!RateLimiter::check(
            RateLimiter::keyForUser('pi_audit_export', $userId),
            self::RATE_EXPORT_MAX,
            self::RATE_EXPORT_WINDOW
        )) {
            $this->jsonError(self::tr('Limite de exports atingido. Aguarde antes de tentar novamente.'), 429);
            return;
        }

        $format  = (string) RequestHelper::post('formato', 'sanitize_key', 'csv');
        $format  = in_array($format, ['csv', 'json'], true) ? $format : 'csv';

        $filters = [
            'entidade'  => RequestHelper::post('entidade', 'sanitize_text_field') ?: null,
            'acao'      => RequestHelper::post('acao', 'sanitize_text_field') ?: null,
            'data_de'   => RequestHelper::post('data_de', 'sanitize_text_field') ?: null,
            'data_ate'  => RequestHelper::post('data_ate', 'sanitize_text_field') ?: null,
            'ator_id'   => ((int) RequestHelper::post('ator_id', 'absint', 0)) ?: null,
        ];

        try {
            $signedUrl = $this->exportHandler->handle(
                new ExportarAuditLogCommand($filters, $format, $userId)
            );
            $this->jsonSuccess(['url' => $signedUrl]);
        } catch (Throwable $e) {
            $debug = \defined('WP_DEBUG') && \WP_DEBUG;
            $this->jsonError(
                $debug ? $e->getMessage() : self::tr('Falha ao gerar export. Tente filtros mais restritos.'),
                503
            );
        }
    }

    /**
     * Verifica integridade heurística: count vs max_id.
     * Resposta: { success: true, data: { ok: true, alerta: false, detalhes: {...} } }
     */
    public function handleTestIntegridade(): void
    {
        $userId = $this->requireUser();

        if (!$this->checkNonce('pi_audit_integridade_' . $userId)) {
            $this->jsonError(self::tr('Nonce inválido.'), 403);
            return;
        }

        if (!self::userCan(self::CAP_ADMIN)) {
            $this->jsonError(self::tr('Permissão negada.'), 403);
            return;
        }

        if (!RateLimiter::check(
            RateLimiter::keyForUser('pi_audit_integridade', $userId),
            self::RATE_INTEGRIDADE_MAX,
            self::RATE_INTEGRIDADE_WINDOW
        )) {
            $this->jsonError(self::tr('Limite de requisições atingido.'), 429);
            return;
        }

        // Heurística: total count vs max(id)
        // Diferença grande sugere possível DELETE manual (log deve ser append-only)
        $total = $this->query->count([]);
        $maxId = $this->getMaxId();

        $diferenca = $maxId > 0 ? ($maxId - $total) : 0;
        $alerta    = $diferenca > 100; // threshold configurable futuramente

        // Audita a própria verificação
        $this->audit->log(
            'audit_log',
            null,
            'test_integridade',
            null,
            ['total' => $total, 'max_id' => $maxId, 'diferenca' => $diferenca],
            $userId
        );

        $this->jsonSuccess([
            'ok'       => !$alerta,
            'alerta'   => $alerta,
            'detalhes' => [
                'total_registros' => $total,
                'max_id'          => $maxId,
                'diferenca'       => $diferenca,
                'mensagem'        => $alerta
                    ? self::tr('Possível deleção manual detectada no audit log. Investigar.')
                    : self::tr('Audit log íntegro.'),
            ],
        ]);
    }

    /* ====================== helpers ======================== */

    private function getMaxId(): int
    {
        // Acessa wpdb diretamente via DI de query — usa reflexão ou injeta wpdb
        // Nota: AuditLogQuery não expõe wpdb; usando global $wpdb como fallback seguro
        global $wpdb;
        if (!isset($wpdb)) {
            return 0;
        }
        $prefix = is_object($wpdb) && isset($wpdb->prefix) ? $wpdb->prefix : 'wp_';
        $table  = $prefix . 'pi_audit_log';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $result = $wpdb->get_var("SELECT MAX(id) FROM {$table}");
        return (int) $result;
    }

    private function requireUser(): int
    {
        $userId = function_exists('get_current_user_id') ? (int) \get_current_user_id() : 0;
        if ($userId <= 0) {
            $this->jsonError(self::tr('Não autenticado.'), 401);
            exit;
        }
        return $userId;
    }

    private function checkNonce(string $action): bool
    {
        $nonce = (string) RequestHelper::post('_wpnonce', 'sanitize_text_field', '');
        if ($nonce === '') {
            $nonce = (string) RequestHelper::get('_wpnonce', 'sanitize_text_field', '');
        }
        return $nonce !== ''
            && function_exists('wp_verify_nonce')
            && (bool) \wp_verify_nonce($nonce, $action);
    }

    private function jsonSuccess(array $data): void
    {
        if (function_exists('wp_send_json_success')) {
            \wp_send_json_success($data);
            return;
        }
        header('Content-Type: application/json; charset=utf-8');
        $json = json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
        echo $json;
        exit;
    }

    private function jsonError(string $message, int $statusCode = 400): void
    {
        if (function_exists('wp_send_json_error')) {
            if (function_exists('status_header')) {
                \status_header($statusCode);
            }
            \wp_send_json_error(['message' => $message], $statusCode);
            return;
        }
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        $json = json_encode(['success' => false, 'data' => ['message' => $message]], JSON_UNESCAPED_UNICODE);
        echo $json;
        exit;
    }

    private static function userCan(string $cap): bool
    {
        return function_exists('current_user_can') && (bool) \current_user_can($cap);
    }

    private static function tr(string $text): string
    {
        return function_exists('__') ? (string) \__($text, 'participe-ibram') : $text;
    }
}
