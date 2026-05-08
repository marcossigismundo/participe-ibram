<?php
/**
 * Controller AJAX de download autenticado de documentos.
 *
 * @package Ibram\ParticipeIbram\Presentation\Public\Controllers
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Public\Controllers;

use Ibram\ParticipeIbram\Core\Audit\AccessTracker;
use Ibram\ParticipeIbram\Core\Helpers\RateLimiter;
use Ibram\ParticipeIbram\Domain\Documento\DocumentoNotFound;
use Ibram\ParticipeIbram\Domain\Documento\DocumentoRepository;
use Ibram\ParticipeIbram\Infrastructure\Storage\PrivateFileStorage;
use Ibram\ParticipeIbram\Infrastructure\Storage\StorageException;

/**
 * Download autenticado: `admin-ajax.php?action=pi_download_document`.
 *
 * Pipeline:
 *  1. Bloqueia request anônimo (`wp_ajax_nopriv_*` -> 401).
 *  2. Valida nonce (`pi_download_document_<id>`).
 *  3. Aplica rate limit por usuário (10 req/minuto).
 *  4. Carrega o documento por ID (jamais aceita path do request).
 *  5. Verifica capability:
 *     - dono (agente_id corresponde ao user) OU
 *     - analista com `pi_visualizar_documentos`.
 *  6. Audita acesso e faz stream do arquivo.
 *
 * Não retorna em caso de sucesso — `streamDownload` finaliza a request.
 */
final class DownloadDocumentoController
{
    public const ACTION = 'pi_download_document';

    /**
     * Capability de analista que permite baixar documentos de qualquer agente.
     */
    private const CAP_VISUALIZAR = 'pi_visualizar_documentos';

    /**
     * Janela de rate limit (segundos).
     */
    private const RATE_WINDOW_SECONDS = 60;

    /**
     * Número máximo de downloads na janela.
     */
    private const RATE_MAX_REQUESTS = 10;

    private DocumentoRepository $documentosRepo;

    private PrivateFileStorage $storage;

    private AccessTracker $accessTracker;

    public function __construct(
        DocumentoRepository $documentosRepo,
        PrivateFileStorage $storage,
        AccessTracker $accessTracker
    ) {
        $this->documentosRepo = $documentosRepo;
        $this->storage        = $storage;
        $this->accessTracker  = $accessTracker;
    }

    /**
     * Registra os hooks AJAX. Chamar uma única vez no boot.
     */
    public function register(): void
    {
        if (!function_exists('add_action')) {
            return;
        }
        \add_action('wp_ajax_' . self::ACTION, [$this, 'handle']);
        \add_action('wp_ajax_nopriv_' . self::ACTION, [$this, 'handleAnonymous']);
    }

    /**
     * Handler para usuários autenticados.
     */
    public function handle(): void
    {
        $userId = function_exists('get_current_user_id') ? (int) \get_current_user_id() : 0;
        if ($userId <= 0) {
            $this->die401();
            return;
        }

        // Rate limit por usuário (independente de IP).
        $rlKey = RateLimiter::keyForUser('download_document', $userId);
        if (!RateLimiter::check($rlKey, self::RATE_MAX_REQUESTS, self::RATE_WINDOW_SECONDS)) {
            $this->dieWith(429, 'Too Many Requests');
            return;
        }

        // documento_id NÃO é path; o path real é resolvido a partir do DB.
        $documentoId = $this->readDocumentoId();
        if ($documentoId <= 0) {
            $this->dieWith(400, 'Bad Request');
            return;
        }

        // Nonce escopado por documento_id evita reuso entre downloads.
        $nonce = $this->readNonce();
        if (!$this->verifyNonce($nonce, $documentoId)) {
            $this->dieWith(403, 'Forbidden');
            return;
        }

        try {
            $documento = $this->documentosRepo->findById($documentoId);
        } catch (DocumentoNotFound $e) {
            $this->dieWith(404, 'Not Found');
            return;
        }

        if (!$this->canDownload($documento->agenteId(), $userId)) {
            $this->dieWith(403, 'Forbidden');
            return;
        }

        // Auditoria pré-stream (TD-14): mesmo que o stream falhe, o registro
        // de tentativa de acesso fica gravado.
        $this->accessTracker->trackDecryption(
            'documento',
            (int) $documento->id(),
            'arquivo',
            $userId
        );

        try {
            $this->storage->streamDownload(
                $documento->arquivoPath(),
                $documento->nomeOriginal(),
                $userId
            );
        } catch (StorageException $e) {
            // streamDownload já valida path traversal; chegando aqui é I/O.
            $this->dieWith(500, 'Storage error');
            return;
        }
    }

    /**
     * Handler para anônimos: 401.
     */
    public function handleAnonymous(): void
    {
        $this->die401();
    }

    /* ---------------------------------------------------------------------
     * Internals
     * ------------------------------------------------------------------- */

    /**
     * Lê o documento_id do request com `absint` — não confia em path.
     */
    private function readDocumentoId(): int
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_REQUEST['documento_id'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $raw = function_exists('wp_unslash') ? \wp_unslash($_REQUEST['documento_id']) : $_REQUEST['documento_id'];
            if (is_scalar($raw)) {
                $val = function_exists('absint') ? (int) \absint((string) $raw) : (int) $raw;
                return $val > 0 ? $val : 0;
            }
        }
        return 0;
    }

    private function readNonce(): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_REQUEST['_wpnonce'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $raw = function_exists('wp_unslash') ? \wp_unslash($_REQUEST['_wpnonce']) : $_REQUEST['_wpnonce'];
            if (is_scalar($raw)) {
                $val = (string) $raw;
                if (function_exists('sanitize_text_field')) {
                    $val = (string) \sanitize_text_field($val);
                }
                return $val;
            }
        }
        return '';
    }

    private function verifyNonce(string $nonce, int $documentoId): bool
    {
        if ($nonce === '' || $documentoId <= 0) {
            return false;
        }
        if (!function_exists('wp_verify_nonce')) {
            return false;
        }
        $action = self::ACTION . '_' . $documentoId;

        return (bool) \wp_verify_nonce($nonce, $action);
    }

    /**
     * Decide se o usuário pode baixar este documento.
     */
    private function canDownload(?int $agenteId, int $userId): bool
    {
        // Analista com capability tem acesso a todos os documentos sob análise.
        if (function_exists('current_user_can') && \current_user_can(self::CAP_VISUALIZAR)) {
            return true;
        }

        // Dono: o agente_id deve corresponder a um agente do usuário atual.
        // A correspondência exata "user_id -> agente_id" depende do
        // AgenteRepository (D1). Aqui usamos a convenção `pi_user_agente_id`
        // armazenada como user meta: é o ID do agente associado ao user.
        if ($agenteId === null || $agenteId <= 0) {
            return false;
        }
        if (!function_exists('get_user_meta')) {
            return false;
        }
        $owned = (int) \get_user_meta($userId, 'pi_user_agente_id', true);

        return $owned > 0 && $owned === $agenteId;
    }

    private function die401(): void
    {
        $this->dieWith(401, 'Unauthorized');
    }

    private function dieWith(int $status, string $reason): void
    {
        if (function_exists('status_header')) {
            \status_header($status);
        } else {
            // @codeCoverageIgnoreStart
            if (!headers_sent()) {
                header('HTTP/1.1 ' . $status . ' ' . $reason);
            }
            // @codeCoverageIgnoreEnd
        }
        if (function_exists('nocache_headers')) {
            \nocache_headers();
        }
        if (function_exists('wp_die')) {
            \wp_die(esc_html($reason), esc_html($reason), ['response' => $status]);
            return;
        }
        // @codeCoverageIgnoreStart
        echo $reason; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
        // @codeCoverageIgnoreEnd
    }
}
