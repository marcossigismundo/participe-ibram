<?php
/**
 * EditalAdminAjax — AJAX endpoints para a UI admin de editais.
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\Ajax
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\Ajax;

use DomainException;
use Ibram\ParticipeIbram\Application\Edital\AbrirInscricoesHandler;
use Ibram\ParticipeIbram\Application\Edital\PublicarEditalHandler;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Core\Helpers\RateLimiter;
use Ibram\ParticipeIbram\Core\Helpers\RequestHelper;
use Ibram\ParticipeIbram\Domain\Edital\Categoria;
use Ibram\ParticipeIbram\Domain\Edital\EditalNotFound;
use Ibram\ParticipeIbram\Domain\Edital\StatusEdital;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbCategoriaRepository;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbEditalRepository;
use Throwable;

/**
 * Endpoints registrados (todos `wp_ajax_*` — nunca nopriv):
 *
 *  Action                       | Capability
 *  -----------------------------|-------------------
 *  pi_admin_publicar_edital     | pi_publicar_edital
 *  pi_admin_abrir_inscricoes    | pi_publicar_edital
 *  pi_admin_salvar_categoria    | pi_editar_edital
 *  pi_admin_remover_categoria   | pi_editar_edital
 *
 * Pipeline padrão (R5 V-06, V-08, AGENTS-PLAN pontos 3, 5, 9):
 *  1. nonce (escopado por user)
 *  2. capability check
 *  3. rate limit 5/min por user
 *  4. lê body JSON
 *  5. invoca handler
 *  6. audit (via handler ou explicitamente)
 *  7. dispara do_action (IDs apenas — sem PII)
 *
 * $wpdb->last_error suprimido em produção (AGENTS-PLAN ponto 1).
 */
final class EditalAdminAjax
{
    public const CAP_PUBLICAR = 'pi_publicar_edital';
    public const CAP_EDITAR   = 'pi_editar_edital';

    private const RATE_MAX    = 5;
    private const RATE_WINDOW = 60;

    private PublicarEditalHandler $publicar;
    private AbrirInscricoesHandler $abrirInscricoes;
    private WpdbEditalRepository $editaisRepo;
    private WpdbCategoriaRepository $categoriasRepo;
    private AuditLogger $audit;

    public function __construct(
        PublicarEditalHandler $publicar,
        AbrirInscricoesHandler $abrirInscricoes,
        WpdbEditalRepository $editaisRepo,
        WpdbCategoriaRepository $categoriasRepo,
        AuditLogger $audit
    ) {
        $this->publicar        = $publicar;
        $this->abrirInscricoes = $abrirInscricoes;
        $this->editaisRepo     = $editaisRepo;
        $this->categoriasRepo  = $categoriasRepo;
        $this->audit           = $audit;
    }

    public function registerHooks(): void
    {
        if (!function_exists('add_action')) {
            return;
        }
        \add_action('wp_ajax_pi_admin_publicar_edital',   [$this, 'ajaxPublicar']);
        \add_action('wp_ajax_pi_admin_abrir_inscricoes',  [$this, 'ajaxAbrirInscricoes']);
        \add_action('wp_ajax_pi_admin_salvar_categoria',  [$this, 'ajaxSalvarCategoria']);
        \add_action('wp_ajax_pi_admin_remover_categoria', [$this, 'ajaxRemoverCategoria']);
    }

    /* ===================== Endpoint: publicar edital ===================== */

    public function ajaxPublicar(): void
    {
        try {
            $userId   = $this->guardAuth(self::CAP_PUBLICAR, 'publicar_edital');
            $editalId = $this->readEditalId();
            $this->publicar->handle($editalId, $userId);
            // do_action('pi_edital_publicado') já é disparado pelo handler.
            $this->sendSuccess(['edital_id' => $editalId, 'status_novo' => StatusEdital::PUBLICADO]);
        } catch (Throwable $e) {
            $this->fromThrowable($e);
        }
    }

    /* ===================== Endpoint: abrir inscrições =================== */

    public function ajaxAbrirInscricoes(): void
    {
        try {
            $userId   = $this->guardAuth(self::CAP_PUBLICAR, 'abrir_inscricoes');
            $editalId = $this->readEditalId();
            $this->abrirInscricoes->handle($editalId, $userId);
            $this->sendSuccess(['edital_id' => $editalId, 'status_novo' => StatusEdital::INSCRICOES_ABERTAS]);
        } catch (Throwable $e) {
            $this->fromThrowable($e);
        }
    }

    /* ===================== Endpoint: salvar categoria =================== */

    public function ajaxSalvarCategoria(): void
    {
        try {
            $userId   = $this->guardAuth(self::CAP_EDITAR, 'salvar_categoria');
            $body     = $this->readJsonBody();
            $editalId = isset($body['edital_id']) ? (int) $body['edital_id'] : 0;
            if ($editalId <= 0) {
                $this->sendError(400, 'pi_validation', self::tr('edital_id é obrigatório.'));
                return;
            }

            $edital = $this->editaisRepo->findById($editalId);
            if ($edital === null) {
                $this->sendError(404, 'pi_not_found', self::tr('Edital não encontrado.'));
                return;
            }

            $editaveis = [StatusEdital::RASCUNHO, StatusEdital::PUBLICADO];
            if (!in_array($edital->status()->value(), $editaveis, true)) {
                $this->sendError(409, 'pi_invalid_state', self::tr('Categorias não podem ser alteradas após a abertura das inscrições.'));
                return;
            }

            $categoriaId = isset($body['categoria_id']) ? (int) $body['categoria_id'] : 0;
            $nome        = isset($body['nome']) ? trim((string) $body['nome']) : '';
            $descricao   = isset($body['descricao_md']) ? (string) $body['descricao_md'] : null;
            $numVagas    = isset($body['num_vagas'])    ? (int) $body['num_vagas']    : 0;
            $numSuplent  = isset($body['num_suplentes']) ? (int) $body['num_suplentes'] : 0;
            $tiposArr    = isset($body['tipos_agente_elegivel']) && is_array($body['tipos_agente_elegivel'])
                ? $body['tipos_agente_elegivel'] : [];
            $tiposStr    = implode(',', array_map('strtoupper', array_filter(array_map('trim', $tiposArr))));
            $docsArr     = isset($body['documentos_exigidos']) && is_array($body['documentos_exigidos'])
                ? array_map('strval', $body['documentos_exigidos']) : [];
            $criterios   = isset($body['criterios_md']) ? (string) $body['criterios_md'] : null;
            $ordem       = isset($body['ordem']) ? (int) $body['ordem'] : 0;

            $categoria = new Categoria(
                $categoriaId > 0 ? $categoriaId : null,
                $editalId,
                $nome,
                ($descricao !== null && $descricao !== '') ? $descricao : null,
                $numVagas > 0 ? $numVagas : 1,
                max(0, $numSuplent),
                $tiposStr !== '' ? $tiposStr : 'PF',
                ($criterios !== null && $criterios !== '') ? $criterios : null,
                array_values(array_filter($docsArr, static fn ($v) => $v !== '')),
                max(0, $ordem)
            );

            $savedId = $this->categoriasRepo->save($categoria);
            $acao    = $categoriaId > 0 ? 'atualizar_categoria' : 'criar_categoria';
            $this->audit->log('edital', $editalId, $acao, null, ['categoria_id' => $savedId, 'nome' => $nome], $userId);

            $this->sendSuccess(['categoria_id' => $savedId, 'edital_id' => $editalId]);
        } catch (Throwable $e) {
            $this->fromThrowable($e);
        }
    }

    /* ===================== Endpoint: remover categoria ================== */

    public function ajaxRemoverCategoria(): void
    {
        try {
            $userId   = $this->guardAuth(self::CAP_EDITAR, 'remover_categoria');
            $body     = $this->readJsonBody();
            $editalId    = isset($body['edital_id'])    ? (int) $body['edital_id']    : 0;
            $categoriaId = isset($body['categoria_id']) ? (int) $body['categoria_id'] : 0;

            if ($editalId <= 0 || $categoriaId <= 0) {
                $this->sendError(400, 'pi_validation', self::tr('edital_id e categoria_id são obrigatórios.'));
                return;
            }

            $edital = $this->editaisRepo->findById($editalId);
            if ($edital === null) {
                $this->sendError(404, 'pi_not_found', self::tr('Edital não encontrado.'));
                return;
            }

            $editaveis = [StatusEdital::RASCUNHO, StatusEdital::PUBLICADO];
            if (!in_array($edital->status()->value(), $editaveis, true)) {
                $this->sendError(409, 'pi_invalid_state', self::tr('Categorias não podem ser removidas após a abertura das inscrições.'));
                return;
            }

            $categoria = $this->categoriasRepo->findById($categoriaId);
            if ($categoria === null || $categoria->editalId() !== $editalId) {
                $this->sendError(404, 'pi_not_found', self::tr('Categoria não encontrada.'));
                return;
            }

            // Soft-delete via repositório (campo deleted_at, se existir na tabela).
            // Por enquanto o WpdbCategoriaRepository não tem deleteById, usamos o audit.
            // A remoção física é feita via SQL direto auditada abaixo.
            /** @var \wpdb $wpdb */
            global $wpdb;
            if (isset($wpdb)) {
                $tbl = $wpdb->prefix . 'pi_edital_categorias';
                $wpdb->delete($tbl, ['id' => $categoriaId], ['%d']); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            }

            $this->audit->log(
                'edital',
                $editalId,
                'remover_categoria',
                ['categoria_id' => $categoriaId, 'nome' => $categoria->nome()],
                null,
                $userId
            );

            $this->sendSuccess(['categoria_id' => $categoriaId, 'edital_id' => $editalId]);
        } catch (Throwable $e) {
            $this->fromThrowable($e);
        }
    }

    /* ===================== Pipeline ===================== */

    /**
     * Nonce + cap + rate limit. Retorna userId ou encerra com erro.
     */
    private function guardAuth(string $capability, string $action): int
    {
        if (!function_exists('get_current_user_id')) {
            $this->sendError(401, 'pi_unauthorized', self::tr('Autenticação requerida.'));
            exit;
        }
        $userId = (int) \get_current_user_id();
        if ($userId <= 0) {
            $this->sendError(401, 'pi_unauthorized', self::tr('Autenticação requerida.'));
            exit;
        }

        $nonceAction = 'pi_admin_' . $action . '_' . $userId;
        if (!$this->verifyNonce($nonceAction)) {
            $this->sendError(403, 'pi_invalid_nonce', self::tr('Nonce inválido ou expirado.'));
            exit;
        }

        if (!function_exists('current_user_can') || !\current_user_can($capability)) {
            $this->sendError(403, 'pi_forbidden', self::tr('Permissão negada.'));
            exit;
        }

        $key = RateLimiter::keyForUser('admin_' . $action, $userId);
        if (!RateLimiter::check($key, self::RATE_MAX, self::RATE_WINDOW)) {
            $this->sendError(429, 'pi_rate_limited', self::tr('Muitas requisições. Tente novamente em alguns instantes.'));
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

    private function readEditalId(): int
    {
        $id = (int) RequestHelper::request('edital_id', 'absint', 0);
        if ($id <= 0) {
            $body = $this->readJsonBody();
            if (isset($body['edital_id'])) {
                $id = (int) $body['edital_id'];
            }
        }
        if ($id <= 0) {
            $this->sendError(400, 'pi_validation', self::tr('Identificador do edital é obrigatório.'));
            exit;
        }
        return $id;
    }

    /**
     * @return array<string,mixed>
     */
    private function readJsonBody(): array
    {
        $json = RequestHelper::postJson();
        return is_array($json) ? $json : [];
    }

    /* ===================== Output helpers ===================== */

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
        if ($e instanceof EditalNotFound) {
            $this->sendError(404, 'pi_not_found', self::tr('Edital não encontrado.'));
            return;
        }
        if ($e instanceof \InvalidArgumentException || $e instanceof DomainException) {
            $this->sendError(400, 'pi_validation', $e->getMessage());
            return;
        }
        $debug = \defined('WP_DEBUG') && \WP_DEBUG;
        // Nunca expõe $wpdb->last_error em produção (AGENTS-PLAN ponto 1).
        $this->sendError(500, 'pi_internal', $debug ? $e->getMessage() : self::tr('Erro interno.'));
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
        // JSON_HEX_* flags — AGENTS-PLAN convenção + TD-18.
        echo (string) wp_json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    private static function tr(string $text): string
    {
        return function_exists('__') ? (string) \__($text, 'participe-ibram') : $text;
    }
}
