<?php
/**
 * CadastroAdminAjax — AJAX endpoints for the agente detalhes page.
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\Ajax
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\Ajax;

use Ibram\ParticipeIbram\Application\Cadastro\AgenteDetalhesLoader;
use Ibram\ParticipeIbram\Application\Cadastro\AssumirAnaliseHandler;
use Ibram\ParticipeIbram\Application\Cadastro\DeferirCadastroCommand;
use Ibram\ParticipeIbram\Application\Cadastro\DeferirCadastroHandler;
use Ibram\ParticipeIbram\Application\Cadastro\IndeferirCadastroCommand;
use Ibram\ParticipeIbram\Application\Cadastro\IndeferirCadastroHandler;
use Ibram\ParticipeIbram\Core\Audit\AccessTracker;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Core\Encryption\EncryptionException;
use Ibram\ParticipeIbram\Core\Encryption\SodiumCipher;
use Ibram\ParticipeIbram\Core\Helpers\RateLimiter;
use Ibram\ParticipeIbram\Core\Helpers\RequestHelper;
use Ibram\ParticipeIbram\Domain\Agente\AgenteOR;
use Ibram\ParticipeIbram\Domain\Agente\AgentePF;
use Ibram\ParticipeIbram\Domain\Agente\AgenteRepository;
use Ibram\ParticipeIbram\Domain\Agente\AgenteSM;
use Ibram\ParticipeIbram\Domain\Agente\StatusCadastro;
use Throwable;

/**
 * Hook registry: 5 actions, all `wp_ajax_*` (auth-only — never `nopriv`).
 *
 *  - pi_admin_assumir_analise   (cap pi_analisar_cadastro)
 *  - pi_admin_iniciar_analise   (cap pi_analisar_cadastro)
 *  - pi_admin_deferir_cadastro  (cap pi_deferir)
 *  - pi_admin_indeferir_cadastro (cap pi_indeferir)
 *  - pi_admin_revelar_sensivel  (cap pi_visualizar_dados_sensiveis)
 *
 * Pipeline padrão (every handler):
 *  1. Verifica nonce (action escopada por user).
 *  2. Verifica capability.
 *  3. Aplica rate limit (5/min destrutivas, 10/min reveals).
 *  4. Lê body (preferencialmente JSON via `php://input`).
 *  5. Invoca handler de Application; converte exceções em JSON.
 *  6. Audita resultado.
 *
 * Sensitive errors (`$wpdb->last_error`) são suprimidos em produção.
 */
final class CadastroAdminAjax
{
    public const CAP_ANALISAR = 'pi_analisar_cadastro';
    public const CAP_DEFERIR  = 'pi_deferir';
    public const CAP_INDEFERIR = 'pi_indeferir';
    public const CAP_REVELAR  = 'pi_visualizar_dados_sensiveis';

    private const RATE_DESTRUCTIVE_MAX = 5;
    private const RATE_DESTRUCTIVE_WINDOW = 60;
    private const RATE_REVEAL_MAX = 10;
    private const RATE_REVEAL_WINDOW = 60;

    /**
     * Allowed sensitive fields. Any other field returns 400.
     */
    private const REVEAL_ALLOWED_FIELDS = ['cpf', 'rg', 'passaporte', 'cnpj', 'representante_cpf'];

    private AssumirAnaliseHandler $assumir;
    private DeferirCadastroHandler $deferir;
    private IndeferirCadastroHandler $indeferir;
    private AgenteRepository $agentes;
    private AgenteDetalhesLoader $detalhes;
    private SodiumCipher $cipher;
    private AccessTracker $accessTracker;
    private AuditLogger $audit;

    public function __construct(
        AssumirAnaliseHandler $assumir,
        DeferirCadastroHandler $deferir,
        IndeferirCadastroHandler $indeferir,
        AgenteRepository $agentes,
        AgenteDetalhesLoader $detalhes,
        SodiumCipher $cipher,
        AccessTracker $accessTracker,
        AuditLogger $audit
    ) {
        $this->assumir       = $assumir;
        $this->deferir       = $deferir;
        $this->indeferir     = $indeferir;
        $this->agentes       = $agentes;
        $this->detalhes      = $detalhes;
        $this->cipher        = $cipher;
        $this->accessTracker = $accessTracker;
        $this->audit         = $audit;
    }

    public function registerHooks(): void
    {
        if (!function_exists('add_action')) {
            return;
        }
        \add_action('wp_ajax_pi_admin_assumir_analise', [$this, 'ajaxAssumir']);
        \add_action('wp_ajax_pi_admin_iniciar_analise', [$this, 'ajaxIniciar']);
        \add_action('wp_ajax_pi_admin_deferir_cadastro', [$this, 'ajaxDeferir']);
        \add_action('wp_ajax_pi_admin_indeferir_cadastro', [$this, 'ajaxIndeferir']);
        \add_action('wp_ajax_pi_admin_revelar_sensivel', [$this, 'ajaxRevelar']);
    }

    public function ajaxAssumir(): void
    {
        try {
            $userId  = $this->guardAuth(self::CAP_ANALISAR, 'assumir_analise', true);
            $agenteId = $this->readAgenteId();
            $this->assumir->handle($agenteId, $userId);
            $this->sendSuccess([
                'agente_id'   => $agenteId,
                'status_novo' => StatusCadastro::EM_ANALISE,
            ]);
        } catch (Throwable $e) {
            $this->fromThrowable($e);
        }
    }

    /**
     * Iniciar análise: idempotente para `EM_ANALISE`. Aceita também
     * `SUBMETIDO` quando o ator é o analista atribuído / ainda não há
     * atribuição — neste caso delega ao mesmo `AssumirAnaliseHandler`.
     */
    public function ajaxIniciar(): void
    {
        try {
            $userId   = $this->guardAuth(self::CAP_ANALISAR, 'iniciar_analise', true);
            $agenteId = $this->readAgenteId();

            $agente = $this->agentes->findById($agenteId);
            if ($agente === null) {
                $this->sendError(404, 'pi_not_found', self::tr('Cadastro não encontrado.'));
                return;
            }
            $statusAtual = $agente->getStatusCadastro()->value();
            if ($statusAtual === StatusCadastro::EM_ANALISE) {
                $this->sendSuccess(['agente_id' => $agenteId, 'status_novo' => $statusAtual]);
                return;
            }
            if ($statusAtual !== StatusCadastro::SUBMETIDO) {
                $this->sendError(409, 'pi_invalid_state', self::tr('Cadastro não está apto para análise.'));
                return;
            }
            $this->assumir->handle($agenteId, $userId);
            $this->sendSuccess([
                'agente_id'   => $agenteId,
                'status_novo' => StatusCadastro::EM_ANALISE,
            ]);
        } catch (Throwable $e) {
            $this->fromThrowable($e);
        }
    }

    public function ajaxDeferir(): void
    {
        try {
            $userId   = $this->guardAuth(self::CAP_DEFERIR, 'deferir_cadastro', true);
            $agenteId = $this->readAgenteId();
            $body     = $this->readJsonBody();
            $parecer  = isset($body['parecer_md']) ? (string) $body['parecer_md'] : '';
            $parecer  = trim($parecer);
            if ($parecer === '') {
                $this->sendError(400, 'pi_validation', self::tr('Parecer é obrigatório.'));
                return;
            }
            $command  = new DeferirCadastroCommand($agenteId, $userId, $parecer);
            $analiseId = $this->deferir->handle($command);

            $agente = $this->agentes->findById($agenteId);
            $numero = $agente !== null && $agente->getNumeroRegistro() !== null
                ? $agente->getNumeroRegistro()->value()
                : null;

            $this->sendSuccess([
                'agente_id'        => $agenteId,
                'analise_id'       => $analiseId,
                'numero_registro'  => $numero,
                'status_novo'      => $agente !== null ? $agente->getStatusCadastro()->value() : StatusCadastro::DEFERIDO,
            ]);
        } catch (Throwable $e) {
            $this->fromThrowable($e);
        }
    }

    public function ajaxIndeferir(): void
    {
        try {
            $userId  = $this->guardAuth(self::CAP_INDEFERIR, 'indeferir_cadastro', true);
            $agenteId = $this->readAgenteId();
            $body     = $this->readJsonBody();
            $parecer  = isset($body['parecer_md']) ? trim((string) $body['parecer_md']) : '';
            $fund     = isset($body['fundamentacao_md']) ? trim((string) $body['fundamentacao_md']) : '';
            if ($parecer === '' || $fund === '') {
                $this->sendError(400, 'pi_validation', self::tr('Parecer e fundamentação são obrigatórios.'));
                return;
            }
            $command  = new IndeferirCadastroCommand($agenteId, $userId, $parecer, $fund);
            $analiseId = $this->indeferir->handle($command);

            $agente = $this->agentes->findById($agenteId);
            $this->sendSuccess([
                'agente_id'   => $agenteId,
                'analise_id'  => $analiseId,
                'status_novo' => $agente !== null ? $agente->getStatusCadastro()->value() : StatusCadastro::INDEFERIDO_AGUARDANDO_RECURSO,
            ]);
        } catch (Throwable $e) {
            $this->fromThrowable($e);
        }
    }

    /**
     * Revela campos sensíveis para o usuário com cap.
     *
     * Body: { agente_id: int, campos: ["cpf","rg","passaporte","cnpj"] }
     */
    public function ajaxRevelar(): void
    {
        try {
            $userId   = $this->guardAuth(self::CAP_REVELAR, 'revelar_sensivel', false);
            $agenteId = $this->readAgenteId();

            $body   = $this->readJsonBody();
            $campos = isset($body['campos']) && is_array($body['campos'])
                ? array_values(array_filter($body['campos'], 'is_string'))
                : [];
            $campos = array_values(array_intersect($campos, self::REVEAL_ALLOWED_FIELDS));
            if ($campos === []) {
                $this->sendError(400, 'pi_validation', self::tr('Nenhum campo válido solicitado.'));
                return;
            }

            $agente = $this->agentes->findById($agenteId);
            if ($agente === null) {
                $this->sendError(404, 'pi_not_found', self::tr('Cadastro não encontrado.'));
                return;
            }
            $tipo     = $agente->getTipo()->value();
            $detalhes = $this->detalhes->loadDetalhes($agenteId, $tipo);

            $reveals = [];
            foreach ($campos as $campo) {
                try {
                    $plain = $this->extractPlain($detalhes, $campo);
                } catch (\Throwable $e) {
                    $plain = null;
                }
                if ($plain === null || $plain === '') {
                    $reveals[$campo] = null;
                    continue;
                }
                $reveals[$campo] = $plain;
                $this->accessTracker->trackDecryption(
                    'agente',
                    $agenteId,
                    $campo,
                    $userId
                );
            }

            $this->sendSuccess(['agente_id' => $agenteId, 'campos' => $reveals]);
        } catch (EncryptionException $e) {
            $this->sendError(500, 'pi_decrypt_error', self::tr('Falha ao decifrar dados sensíveis.'));
        } catch (Throwable $e) {
            $this->fromThrowable($e);
        }
    }

    /**
     * Extrai o valor em claro do detalhes object para o campo solicitado.
     *
     * @param AgentePF|AgenteOR|AgenteSM $detalhes
     */
    private function extractPlain(object $detalhes, string $campo): ?string
    {
        if ($detalhes instanceof AgentePF) {
            switch ($campo) {
                case 'cpf':        return $detalhes->getCpfPlain();
                case 'rg':         return $detalhes->getRgPlain();
                case 'passaporte': return $detalhes->getPassaportePlain();
            }
        }
        if ($detalhes instanceof AgenteOR) {
            switch ($campo) {
                case 'cnpj': return $detalhes->getCnpjPlain();
            }
        }
        if ($detalhes instanceof AgenteSM) {
            switch ($campo) {
                case 'representante_cpf': return $detalhes->getRepresentanteCpfPlain();
            }
        }
        return null;
    }

    /* -------------------- Pipeline -------------------- */

    /**
     * Auth pipeline shared by every action. Returns the actor user id.
     *
     * @param bool $destructive Whether to use the destructive rate-limit bucket.
     */
    private function guardAuth(string $capability, string $action, bool $destructive): int
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

        $max    = $destructive ? self::RATE_DESTRUCTIVE_MAX : self::RATE_REVEAL_MAX;
        $window = $destructive ? self::RATE_DESTRUCTIVE_WINDOW : self::RATE_REVEAL_WINDOW;
        $key    = RateLimiter::keyForUser('admin_' . $action, $userId);
        if (!RateLimiter::check($key, $max, $window)) {
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

    private function readAgenteId(): int
    {
        $id = (int) RequestHelper::request('agente_id', 'absint', 0);
        if ($id <= 0) {
            $body = $this->readJsonBody();
            if (isset($body['agente_id'])) {
                $id = (int) $body['agente_id'];
            }
        }
        if ($id <= 0) {
            $this->sendError(400, 'pi_validation', self::tr('Identificador do cadastro é obrigatório.'));
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

    /* -------------------- Output helpers -------------------- */

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
            $debug ? $e->getMessage() : self::tr('Erro interno.')
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

    private static function tr(string $text): string
    {
        return function_exists('__') ? (string) \__($text, 'participe-ibram') : $text;
    }
}
