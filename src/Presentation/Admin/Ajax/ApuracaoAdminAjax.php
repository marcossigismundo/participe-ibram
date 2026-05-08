<?php
/**
 * ApuracaoAdminAjax — endpoints AJAX da UI admin de apuração.
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\Ajax
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\Ajax;

use DomainException;
use Ibram\ParticipeIbram\Application\Votacao\ApurarCommand;
use Ibram\ParticipeIbram\Application\Votacao\ApurarHandler;
use Ibram\ParticipeIbram\Application\Votacao\ExportarRelatorioApuracaoCommand;
use Ibram\ParticipeIbram\Application\Votacao\ExportarRelatorioApuracaoHandler;
use Ibram\ParticipeIbram\Application\Votacao\PublicarResultadoCommand;
use Ibram\ParticipeIbram\Application\Votacao\PublicarResultadoHandler;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Core\Helpers\RateLimiter;
use Ibram\ParticipeIbram\Core\Helpers\RequestHelper;
use Ibram\ParticipeIbram\Domain\Votacao\IllegalStateTransition;
use Ibram\ParticipeIbram\Domain\Votacao\VotacaoNotFound;
use Throwable;

/**
 * Endpoints registrados (todos `wp_ajax_*` — nunca nopriv):
 *
 *  Action                          | Capability
 *  --------------------------------|------------------------
 *  pi_admin_apurar_votacao         | pi_apurar_votacao
 *  pi_admin_publicar_resultado     | pi_publicar_resultado
 *  pi_admin_exportar_apuracao      | pi_apurar_votacao
 *
 * Pipeline padrão (R5 V-06, V-08):
 *  1. nonce escopado por usuário
 *  2. capability check (defense in depth)
 *  3. rate limit 5/min (operações destrutivas/finais)
 *  4. invocação do handler
 *  5. audit log (handler ou explicit)
 *  6. resposta consistente
 *
 * Em produção `$wpdb->last_error` NUNCA é exposto.
 */
final class ApuracaoAdminAjax
{
    public const CAP_APURAR   = 'pi_apurar_votacao';
    public const CAP_PUBLICAR = 'pi_publicar_resultado';

    private const RATE_MAX    = 5;
    private const RATE_WINDOW = 60;

    private ApurarHandler $apurar;

    private PublicarResultadoHandler $publicar;

    private ExportarRelatorioApuracaoHandler $exportar;

    private AuditLogger $audit;

    public function __construct(
        ApurarHandler $apurar,
        PublicarResultadoHandler $publicar,
        ExportarRelatorioApuracaoHandler $exportar,
        AuditLogger $audit
    ) {
        $this->apurar   = $apurar;
        $this->publicar = $publicar;
        $this->exportar = $exportar;
        $this->audit    = $audit;
    }

    public function registerHooks(): void
    {
        if (!function_exists('add_action')) {
            return;
        }
        \add_action('wp_ajax_pi_admin_apurar_votacao',     [$this, 'ajaxApurar']);
        \add_action('wp_ajax_pi_admin_publicar_resultado', [$this, 'ajaxPublicar']);
        \add_action('wp_ajax_pi_admin_exportar_apuracao',  [$this, 'ajaxExportar']);
    }

    /* ========================= Endpoints ========================= */

    public function ajaxApurar(): void
    {
        try {
            $userId    = $this->guardAuth(self::CAP_APURAR, 'apurar_votacao');
            $votacaoId = $this->readVotacaoId();
            $resultados = $this->apurar->handle(new ApurarCommand($votacaoId, $userId));
            $this->sendSuccess([
                'votacao_id'      => $votacaoId,
                'total_resultados' => count($resultados),
                'status_novo'     => 'apurada',
            ]);
        } catch (Throwable $e) {
            $this->fromThrowable($e);
        }
    }

    public function ajaxPublicar(): void
    {
        try {
            $userId    = $this->guardAuth(self::CAP_PUBLICAR, 'publicar_resultado');
            $votacaoId = $this->readVotacaoId();
            $relatorio = $this->publicar->handle(new PublicarResultadoCommand($votacaoId, $userId));
            // Whitelist do retorno — não devolve nada além de IDs e contagem.
            $this->sendSuccess([
                'votacao_id'    => isset($relatorio['votacao_id']) ? (int) $relatorio['votacao_id'] : $votacaoId,
                'edital_id'     => isset($relatorio['edital_id']) ? (int) $relatorio['edital_id'] : 0,
                'qtd_categorias' => isset($relatorio['categorias']) && is_array($relatorio['categorias'])
                    ? count($relatorio['categorias'])
                    : 0,
            ]);
        } catch (Throwable $e) {
            $this->fromThrowable($e);
        }
    }

    public function ajaxExportar(): void
    {
        try {
            $userId    = $this->guardAuth(self::CAP_APURAR, 'exportar_apuracao');
            $votacaoId = $this->readVotacaoId();
            $info      = $this->exportar->handle(new ExportarRelatorioApuracaoCommand($votacaoId, $userId));
            $this->sendSuccess([
                'votacao_id' => $votacaoId,
                'filename'   => (string) ($info['filename'] ?? ''),
                'url'        => (string) ($info['url'] ?? ''),
                'bytes'      => (int) ($info['bytes'] ?? 0),
                'sha256'     => (string) ($info['sha256'] ?? ''),
            ]);
        } catch (Throwable $e) {
            $this->fromThrowable($e);
        }
    }

    /* ========================= Pipeline ========================= */

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

    private function readVotacaoId(): int
    {
        $id = (int) RequestHelper::request('votacao_id', 'absint', 0);
        if ($id <= 0) {
            $body = $this->readJsonBody();
            if (isset($body['votacao_id'])) {
                $id = (int) $body['votacao_id'];
            }
        }
        if ($id <= 0) {
            $this->sendError(400, 'pi_validation', self::tr('Identificador da votação é obrigatório.'));
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

    /* ========================= Output ========================= */

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
        if ($e instanceof VotacaoNotFound) {
            $this->sendError(404, 'pi_not_found', self::tr('Votação não encontrada.'));
            return;
        }
        if ($e instanceof IllegalStateTransition) {
            $this->sendError(409, 'pi_invalid_state', $e->getMessage());
            return;
        }
        if ($e instanceof \InvalidArgumentException || $e instanceof DomainException) {
            $this->sendError(400, 'pi_validation', $e->getMessage());
            return;
        }
        $debug = \defined('WP_DEBUG') && \WP_DEBUG;
        // Em produção NUNCA expõe $wpdb->last_error / stack.
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
        echo (string) wp_json_encode(
            $payload,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
        );
        exit;
    }

    private static function tr(string $text): string
    {
        return function_exists('__') ? (string) \__($text, 'participe-ibram') : $text;
    }
}
