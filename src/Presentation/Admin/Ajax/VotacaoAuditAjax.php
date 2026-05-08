<?php
/**
 * AJAX administrativo para auditoria de votação.
 *
 * Crítico (TD-14):
 *  - `pi_admin_votacao_recalcular_hash`: recalcula o hash pré-apuração e
 *    compara com o publicado em `wp_options`. Comparação em tempo constante
 *    via {@see hash_equals}.
 *  - `pi_admin_votacao_revisar_log`: lista eventos `voto_registrado` do
 *    audit_log SEM PII. NÃO inclui `agente_id` ou `ator_id` legível.
 *  - Pipeline padrão: nonce + cap + rate limit (60/min) + audit do acesso.
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\Ajax
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\Ajax;

use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Core\Helpers\RateLimiter;
use Ibram\ParticipeIbram\Core\Helpers\RequestHelper;
use Ibram\ParticipeIbram\Domain\Votacao\VotacaoNotFound;
use Ibram\ParticipeIbram\Domain\Votacao\VotacaoRepository;
use Ibram\ParticipeIbram\Domain\Votacao\VotoRepository;
use Throwable;

/**
 * Capabilities (TD-18 §RBAC):
 *  - `pi_apurar_votacao`         — recalcular hash.
 *  - `pi_visualizar_audit_log`   — revisar log.
 *
 * Convenção de nonce reutiliza {@see AdminAjaxRouter::nonceAction()}.
 */
final class VotacaoAuditAjax
{
    public const CAP_APURAR              = 'pi_apurar_votacao';
    public const CAP_VISUALIZAR_LOG      = 'pi_visualizar_audit_log';

    private VotacaoRepository $votacoesRepo;
    private VotoRepository $votosRepo;
    private AuditLogger $audit;

    /** @var \wpdb */
    private $wpdb;

    private string $auditTable;

    /**
     * @param \wpdb $wpdb
     */
    public function __construct(
        VotacaoRepository $votacoesRepo,
        VotoRepository $votosRepo,
        AuditLogger $audit,
        $wpdb,
        ?string $auditTable = null
    ) {
        $this->votacoesRepo = $votacoesRepo;
        $this->votosRepo    = $votosRepo;
        $this->audit        = $audit;
        $this->wpdb         = $wpdb;
        $prefix             = isset($wpdb->prefix) && is_string($wpdb->prefix) ? $wpdb->prefix : 'wp_';
        $this->auditTable   = $auditTable ?? ($prefix . 'pi_audit_log');
    }

    /**
     * Registra hooks AJAX. Idempotente.
     */
    public function registerHooks(): void
    {
        if (!function_exists('add_action')) {
            return;
        }
        \add_action('wp_ajax_pi_admin_votacao_recalcular_hash', [$this, 'recalcularHash']);
        \add_action('wp_ajax_pi_admin_votacao_revisar_log', [$this, 'revisarLog']);
    }

    /**
     * Action `pi_admin_votacao_recalcular_hash`.
     *
     * Recalcula `gerarHashPreApuracao()` e compara em tempo constante com o
     * `hash_pre_apuracao` armazenado no agregado e/ou em `wp_options`. Retorna
     * `{recalculado, publicado, bate, total_votos}`.
     */
    public function recalcularHash(): void
    {
        try {
            $userId = $this->guardAuth(self::CAP_APURAR, 'votacao_recalcular_hash');

            $votacaoId = (int) RequestHelper::request('votacao_id', 'absint', 0);
            if ($votacaoId <= 0) {
                $this->sendError(400, 'pi_validation', __('votacao_id obrigatório.', 'participe-ibram'));
                return;
            }

            try {
                $votacao = $this->votacoesRepo->findById($votacaoId);
            } catch (VotacaoNotFound $e) {
                $this->sendError(404, 'pi_not_found', __('Votação não encontrada.', 'participe-ibram'));
                return;
            }

            $recalculado = $this->votosRepo->gerarHashPreApuracao($votacaoId);
            $totalVotos  = $this->votosRepo->contarTotalDaVotacao($votacaoId);

            $publicadoOption = function_exists('get_option')
                ? \get_option('pi_votacao_' . $votacaoId . '_hash', null)
                : null;
            $publicadoHash = null;
            if (is_array($publicadoOption) && isset($publicadoOption['hash_pre_apuracao'])) {
                $publicadoHash = (string) $publicadoOption['hash_pre_apuracao'];
            }
            $agregadoHash = $votacao->hashPreApuracao();

            $referenceHash = $publicadoHash ?? $agregadoHash;

            // Comparação constant-time.
            $bate = false;
            if (
                is_string($referenceHash)
                && strlen($referenceHash) === 64
                && strlen($recalculado) === 64
            ) {
                $bate = hash_equals($referenceHash, $recalculado);
            }

            // Audit do acesso (sem PII; ator_id é o admin verificando, OK).
            $this->audit->log(
                'votacao',
                $votacaoId,
                'auditoria_recalculo_hash',
                null,
                [
                    'votacao_id'       => $votacaoId,
                    'recalculado_hash' => $recalculado,
                    'reference_hash'   => $referenceHash,
                    'bate'             => $bate,
                    'total_votos'      => $totalVotos,
                ],
                $userId
            );

            $this->sendSuccess([
                'votacao_id'      => $votacaoId,
                'recalculado'     => $recalculado,
                'publicado'       => $publicadoHash,
                'agregado'        => $agregadoHash,
                'bate'            => $bate,
                'total_votos'     => $totalVotos,
            ]);
        } catch (Throwable $e) {
            $this->fromThrowable($e);
        }
    }

    /**
     * Action `pi_admin_votacao_revisar_log`.
     *
     * Lista eventos `voto_registrado` com filtros (votacao_id, categoria_id,
     * ranges de data). NUNCA expõe `ator_id` na resposta — auditor vê apenas
     * dados anonimizados (votacao_id, categoria_id, eleitor_hash, candidato).
     */
    public function revisarLog(): void
    {
        try {
            $userId = $this->guardAuth(self::CAP_VISUALIZAR_LOG, 'votacao_revisar_log');

            $votacaoId   = (int) RequestHelper::request('votacao_id', 'absint', 0);
            $categoriaId = (int) RequestHelper::request('categoria_id', 'absint', 0);
            $page        = max(1, (int) RequestHelper::request('page', 'absint', 1));
            $perPage     = (int) RequestHelper::request('per_page', 'absint', 50);
            if ($perPage < 1 || $perPage > 200) {
                $perPage = 50;
            }
            $offset = ($page - 1) * $perPage;

            // Query parametrizada — filtra apenas voto_registrado.
            $sql = $this->wpdb->prepare(
                "SELECT id, entidade, entidade_id, acao, dados_depois, ocorrido_em
                 FROM {$this->auditTable}
                 WHERE entidade = %s AND acao = %s
                 ORDER BY id DESC
                 LIMIT %d OFFSET %d",
                'voto',
                'voto_registrado',
                $perPage,
                $offset
            );

            $rows = $this->wpdb->get_results($sql, ARRAY_A);
            if (!is_array($rows)) {
                $rows = [];
            }

            $items = [];
            foreach ($rows as $row) {
                $dadosDepois = isset($row['dados_depois']) && is_string($row['dados_depois'])
                    ? json_decode($row['dados_depois'], true)
                    : [];
                if (!is_array($dadosDepois)) {
                    $dadosDepois = [];
                }

                $vId  = isset($dadosDepois['votacao_id']) ? (int) $dadosDepois['votacao_id'] : 0;
                $cId  = isset($dadosDepois['categoria_id']) ? (int) $dadosDepois['categoria_id'] : 0;

                if ($votacaoId > 0 && $vId !== $votacaoId) {
                    continue;
                }
                if ($categoriaId > 0 && $cId !== $categoriaId) {
                    continue;
                }

                // Whitelist de campos do log — NUNCA inclui ator_id ou agente_id.
                $items[] = [
                    'log_id'                  => isset($row['id']) ? (int) $row['id'] : 0,
                    'votacao_id'              => $vId,
                    'categoria_id'            => $cId,
                    'eleitor_hash'            => isset($dadosDepois['eleitor_hash'])
                        ? (string) $dadosDepois['eleitor_hash']
                        : '',
                    'candidato_inscricao_id'  => isset($dadosDepois['candidato_inscricao_id'])
                        ? (int) $dadosDepois['candidato_inscricao_id']
                        : 0,
                    'votado_em'               => isset($dadosDepois['votado_em'])
                        ? (string) $dadosDepois['votado_em']
                        : '',
                    'ocorrido_em'             => isset($row['ocorrido_em']) ? (string) $row['ocorrido_em'] : '',
                ];
            }

            // Audit do acesso ao log de votos (auditor verificando auditoria).
            $this->audit->log(
                'auditoria',
                null,
                'votacao_revisar_log_acessado',
                null,
                [
                    'votacao_id'   => $votacaoId,
                    'categoria_id' => $categoriaId,
                    'qtd_itens'    => count($items),
                ],
                $userId
            );

            $this->sendSuccess([
                'items'    => $items,
                'page'     => $page,
                'per_page' => $perPage,
            ]);
        } catch (Throwable $e) {
            $this->fromThrowable($e);
        }
    }

    /* =====================================================================
     * Pipeline auxiliar (replica AdminAjaxRouter para auto-contenção)
     * ===================================================================== */

    /**
     * Pipeline auth: nonce + capability + rate limit. Devolve userId.
     */
    private function guardAuth(string $capability, string $action): int
    {
        if (!function_exists('get_current_user_id')) {
            $this->sendError(401, 'pi_unauthorized', 'Auth indisponível.');
            exit;
        }
        $userId = (int) \get_current_user_id();
        if ($userId <= 0) {
            $this->sendError(401, 'pi_unauthorized', __('Autenticação requerida.', 'participe-ibram'));
            exit;
        }

        $nonceAction = AdminAjaxRouter::nonceAction($action, $userId);
        if (!$this->verifyNonce($nonceAction)) {
            $this->sendError(403, 'pi_invalid_nonce', __('Nonce inválido ou expirado.', 'participe-ibram'));
            exit;
        }
        if (!function_exists('current_user_can') || !\current_user_can($capability)) {
            $this->sendError(403, 'pi_forbidden', __('Permissão negada.', 'participe-ibram'));
            exit;
        }

        $key = RateLimiter::keyForUser('admin_' . $action, $userId);
        if (!RateLimiter::check($key, 60, 60)) {
            $this->sendError(429, 'pi_rate_limited', __('Muitas requisições.', 'participe-ibram'));
            exit;
        }

        return $userId;
    }

    private function verifyNonce(string $action): bool
    {
        if (function_exists('check_ajax_referer')) {
            $ok = \check_ajax_referer($action, '_wpnonce', false);
            return $ok !== false && (int) $ok > 0;
        }
        $nonce = (string) RequestHelper::request('_wpnonce', 'sanitize_text_field', '');
        return $nonce !== '' && function_exists('wp_verify_nonce') && (bool) \wp_verify_nonce($nonce, $action);
    }

    /**
     * @param array<string,mixed> $data
     */
    private function sendSuccess(array $data, int $status = 200): void
    {
        if (function_exists('wp_send_json_success')) {
            \wp_send_json_success($data, $status);
            return;
        }
        $this->emitJson(['success' => true, 'data' => $data], $status);
    }

    /**
     * @param array<string,mixed> $details
     */
    private function sendError(int $status, string $code, string $message, array $details = []): void
    {
        $payload = [
            'code'    => $code,
            'message' => $message,
            'data'    => ['status' => $status, 'details' => $details],
        ];
        if (function_exists('wp_send_json_error')) {
            \wp_send_json_error($payload, $status);
            return;
        }
        $this->emitJson(['success' => false, 'data' => $payload], $status);
    }

    private function fromThrowable(Throwable $e): void
    {
        if ($e instanceof \InvalidArgumentException || $e instanceof \DomainException) {
            $this->sendError(400, 'pi_validation', $e->getMessage());
            return;
        }
        $debug = \defined('WP_DEBUG') && \WP_DEBUG;
        $this->sendError(
            500,
            'pi_internal',
            $debug ? $e->getMessage() : __('Erro interno.', 'participe-ibram')
        );
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function emitJson(array $payload, int $status): void
    {
        if (function_exists('status_header')) {
            \status_header($status);
        } elseif (!headers_sent()) {
            header('HTTP/1.1 ' . $status);
        }
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
