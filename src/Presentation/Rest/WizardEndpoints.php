<?php
/**
 * Endpoints REST do wizard de cadastro (rascunho/submeter/upload/vocabulario).
 *
 * @package Ibram\ParticipeIbram\Presentation\Rest
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Rest;

use Ibram\ParticipeIbram\Application\Documento\UploadDocumentoCommand;
use Ibram\ParticipeIbram\Application\Documento\UploadDocumentoHandler;
use Ibram\ParticipeIbram\Application\Vocabulario\ListarVocabularioHandler;
use Ibram\ParticipeIbram\Application\Vocabulario\ListarVocabularioQuery;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Core\Network\IpResolver;
use Ibram\ParticipeIbram\Domain\Agente\AgenteRepository;
use Ibram\ParticipeIbram\Domain\Documento\DocumentoNotFound;
use Ibram\ParticipeIbram\Domain\Documento\DocumentoRepository;
use Ibram\ParticipeIbram\Domain\Vocabulario\TipoVocabulario;
use Ibram\ParticipeIbram\Infrastructure\Storage\PrivateFileStorage;
use Throwable;

/**
 * Endpoints REST do fluxo de cadastro multi-etapa do agente.
 *
 *  - POST   /pi/v1/wizard/rascunho            (autenticado)  rate 30/min
 *  - GET    /pi/v1/wizard/rascunho/{id}       (dono ou admin)
 *  - POST   /pi/v1/wizard/submeter            (autenticado)
 *  - POST   /pi/v1/wizard/upload-documento    (autenticado)  rate 10/min
 *  - DELETE /pi/v1/wizard/documento/{id}      (dono)
 *  - GET    /pi/v1/wizard/vocabulario/{tipo}  (autenticado)  cache 1h
 *
 * O wizard depende dos handlers de Application de Cadastro (W3-A) — referenciados
 * por classe canônica. Em ambientes onde a classe ainda não exista, o endpoint
 * responde 503 (`pi_not_ready`) sem quebrar o registro.
 */
final class WizardEndpoints
{
    use RestSupport;

    /** Capability para administradores que podem ver rascunhos alheios. */
    public const CAP_ADMIN_VIEW_RASCUNHO = 'pi_listar_cadastros';

    /** Capability para o dono do rascunho. */
    public const CAP_USER_BASE = 'read';

    private UploadDocumentoHandler $uploadHandler;
    private DocumentoRepository $documentos;
    private AgenteRepository $agentes;
    private ListarVocabularioHandler $vocabHandler;
    private PrivateFileStorage $storage;
    private IpResolver $ipResolver;
    private AuditLogger $audit;

    /** @var callable(array<string,mixed>, int): int Handler salvar rascunho. */
    private $salvarRascunhoFactory;

    /** @var callable(array<string,mixed>, int, ?string, ?string): array<string,mixed> Handler submeter. */
    private $submeterFactory;

    /**
     * @param callable|null $salvarRascunhoFactory  Recebe (dadosArray, userId) → agenteId.
     *                                              Quando null, endpoint retorna 503.
     * @param callable|null $submeterFactory        Recebe (dados, userId, ipHash, ua) →
     *                                              array{agente_id, status, protocolo}.
     */
    public function __construct(
        UploadDocumentoHandler $uploadHandler,
        DocumentoRepository $documentos,
        AgenteRepository $agentes,
        ListarVocabularioHandler $vocabHandler,
        PrivateFileStorage $storage,
        IpResolver $ipResolver,
        AuditLogger $audit,
        ?callable $salvarRascunhoFactory = null,
        ?callable $submeterFactory = null
    ) {
        $this->uploadHandler          = $uploadHandler;
        $this->documentos             = $documentos;
        $this->agentes                = $agentes;
        $this->vocabHandler           = $vocabHandler;
        $this->storage                = $storage;
        $this->ipResolver             = $ipResolver;
        $this->audit                  = $audit;
        $this->salvarRascunhoFactory  = $salvarRascunhoFactory;
        $this->submeterFactory        = $submeterFactory;
    }

    /**
     * Registra todas as rotas do wizard sob um namespace.
     */
    public function register(string $namespace): void
    {
        if (!function_exists('register_rest_route')) {
            return;
        }

        \register_rest_route($namespace, '/wizard/rascunho', [
            'methods'             => 'POST',
            'callback'            => [$this, 'salvarRascunho'],
            'permission_callback' => $this->permissionLoggedIn(),
            'args'                => [
                'tipo' => [
                    'type'     => 'string',
                    'required' => true,
                    'enum'     => ['PF', 'OR', 'SM'],
                ],
                'etapa_atual' => [
                    'type'              => 'integer',
                    'required'          => false,
                    'sanitize_callback' => 'absint',
                ],
                'agente_id' => [
                    'type'              => 'integer',
                    'required'          => false,
                    'sanitize_callback' => 'absint',
                ],
                'dados' => [
                    'type'     => 'object',
                    'required' => true,
                ],
            ],
        ]);

        \register_rest_route($namespace, '/wizard/rascunho/(?P<agente_id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'obterRascunho'],
            'permission_callback' => $this->permissionLoggedIn(),
            'args'                => [
                'agente_id' => [
                    'type'              => 'integer',
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        \register_rest_route($namespace, '/wizard/submeter', [
            'methods'             => 'POST',
            'callback'            => [$this, 'submeter'],
            'permission_callback' => $this->permissionLoggedIn(),
            'args'                => [
                'agente_id' => [
                    'type'              => 'integer',
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                ],
                'tipo' => [
                    'type'     => 'string',
                    'required' => true,
                    'enum'     => ['PF', 'OR', 'SM'],
                ],
                'dados' => [
                    'type'     => 'object',
                    'required' => true,
                ],
                'consentimentos' => [
                    'type'     => 'object',
                    'required' => true,
                ],
            ],
        ]);

        \register_rest_route($namespace, '/wizard/upload-documento', [
            'methods'             => 'POST',
            'callback'            => [$this, 'uploadDocumento'],
            'permission_callback' => $this->permissionLoggedIn(),
            'args'                => [
                'agente_id' => [
                    'type'              => 'integer',
                    'required'          => false,
                    'sanitize_callback' => 'absint',
                ],
                'tipo_documento_id' => [
                    'type'              => 'integer',
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                ],
                'inscricao_id' => [
                    'type'              => 'integer',
                    'required'          => false,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        \register_rest_route($namespace, '/wizard/documento/(?P<id>\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'deletarDocumento'],
            'permission_callback' => $this->permissionLoggedIn(),
            'args'                => [
                'id' => [
                    'type'              => 'integer',
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        \register_rest_route($namespace, '/wizard/vocabulario/(?P<tipo>[a-z0-9_]+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'listarVocabulario'],
            'permission_callback' => $this->permissionLoggedIn(),
            'args'                => [
                'tipo' => [
                    'type'              => 'string',
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_key',
                ],
            ],
        ]);
    }

    /* =====================================================================
     * Handlers
     * ===================================================================== */

    /**
     * POST /wizard/rascunho.
     *
     * @param object $request WP_REST_Request.
     *
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function salvarRascunho(object $request)
    {
        try {
            $this->enforceRateLimit('wizard_rascunho', 30, 60);

            $userId = $this->currentUserId();
            $body   = $this->readJsonBody($request);

            $allowed = ['tipo', 'agente_id', 'etapa_atual', 'dados'];
            $sanitized = Sanitizer::sanitizeNested($body, $allowed, [
                'tipo'        => Sanitizer::KIND_TEXT,
                'agente_id'   => Sanitizer::KIND_INT,
                'etapa_atual' => Sanitizer::KIND_INT,
            ]);

            $tipo = isset($sanitized['tipo']) ? strtoupper((string) $sanitized['tipo']) : '';
            if (!in_array($tipo, ['PF', 'OR', 'SM'], true)) {
                throw RestException::validation(
                    function_exists('__') ? \__('Tipo de agente inválido.', 'participe-ibram') : 'Tipo inválido.',
                    ['campo' => 'tipo']
                );
            }
            $dados = isset($sanitized['dados']) && is_array($sanitized['dados']) ? $sanitized['dados'] : [];
            if ($dados === []) {
                throw RestException::validation(
                    function_exists('__') ? \__('Dados do rascunho ausentes.', 'participe-ibram') : 'Dados ausentes.',
                    ['campo' => 'dados']
                );
            }

            $agenteId   = isset($sanitized['agente_id']) ? (int) $sanitized['agente_id'] : 0;
            $etapaAtual = isset($sanitized['etapa_atual']) ? (int) $sanitized['etapa_atual'] : 1;

            if ($this->salvarRascunhoFactory === null) {
                throw new RestException(
                    function_exists('__') ? \__('Salvar rascunho ainda não está disponível.', 'participe-ibram') : 'Indisponível.',
                    'pi_not_ready',
                    503
                );
            }

            // Garantir que o usuário só atualiza o próprio rascunho.
            if ($agenteId > 0) {
                $existing = $this->agentes->findById($agenteId);
                if ($existing === null) {
                    throw RestException::notFound();
                }
                if ($existing->getUserId() !== $userId && !$this->canAdminViewRascunho()) {
                    throw RestException::forbidden();
                }
            }

            $payload = [
                'tipo'        => $tipo,
                'agente_id'   => $agenteId > 0 ? $agenteId : null,
                'etapa_atual' => $etapaAtual,
                'dados'       => $dados,
                'user_id'     => $userId,
            ];

            $factory = $this->salvarRascunhoFactory;
            $newId   = (int) $factory($payload, $userId);

            return $this->ok([
                'agente_id'              => $newId,
                'status'                 => 'rascunho',
                'rascunho_atualizado_em' => gmdate(DATE_ATOM),
            ]);
        } catch (Throwable $e) {
            return $this->handleThrowable($e);
        }
    }

    /**
     * GET /wizard/rascunho/{id}.
     *
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function obterRascunho(object $request)
    {
        try {
            $this->enforceRateLimit('wizard_rascunho_get', 60, 60);

            $userId    = $this->currentUserId();
            $agenteId  = (int) $this->paramFromRequest($request, 'agente_id', 0);
            if ($agenteId <= 0) {
                throw RestException::validation(
                    function_exists('__') ? \__('Identificador do agente inválido.', 'participe-ibram') : 'agente_id inválido.'
                );
            }

            $agente = $this->agentes->findById($agenteId);
            if ($agente === null) {
                throw RestException::notFound();
            }

            $isOwner = $agente->getUserId() === $userId;
            if (!$isOwner && !$this->canAdminViewRascunho()) {
                throw RestException::forbidden();
            }

            return $this->ok([
                'agente_id' => $agenteId,
                'tipo'      => $agente->getTipo()->value(),
                'status'    => $agente->getStatusCadastro()->value(),
                'is_owner'  => $isOwner,
                // Detalhamento do rascunho propriamente dito virá via Cadastro repo
                // (W3-A integra `WizardRascunhoRepository` se aplicável). Stub:
                'dados'     => null,
            ]);
        } catch (Throwable $e) {
            return $this->handleThrowable($e);
        }
    }

    /**
     * POST /wizard/submeter.
     *
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function submeter(object $request)
    {
        try {
            $this->enforceRateLimit('wizard_submeter', 5, 60);

            if ($this->submeterFactory === null) {
                throw new RestException(
                    function_exists('__') ? \__('Submissão ainda não está disponível.', 'participe-ibram') : 'Indisponível.',
                    'pi_not_ready',
                    503
                );
            }

            $userId = $this->currentUserId();
            $body   = $this->readJsonBody($request);

            $sanitized = Sanitizer::sanitizeNested(
                $body,
                ['agente_id', 'tipo', 'dados', 'consentimentos'],
                [
                    'agente_id' => Sanitizer::KIND_INT,
                    'tipo'      => Sanitizer::KIND_TEXT,
                ]
            );

            $agenteId = isset($sanitized['agente_id']) ? (int) $sanitized['agente_id'] : 0;
            if ($agenteId <= 0) {
                throw RestException::validation(
                    function_exists('__') ? \__('Identificador do agente é obrigatório.', 'participe-ibram') : 'agente_id inválido.',
                    ['campo' => 'agente_id']
                );
            }

            $agente = $this->agentes->findById($agenteId);
            if ($agente === null) {
                throw RestException::notFound();
            }
            if ($agente->getUserId() !== $userId) {
                throw RestException::forbidden();
            }

            $ip       = $this->ipResolver->resolve();
            $ipHash   = $this->ipResolver->hashIp($ip);
            $ua       = $this->captureUserAgent($request);
            $factory  = $this->submeterFactory;

            $result = (array) $factory(
                [
                    'agente_id'      => $agenteId,
                    'tipo'           => isset($sanitized['tipo']) ? strtoupper((string) $sanitized['tipo']) : $agente->getTipo()->value(),
                    'dados'          => isset($sanitized['dados']) && is_array($sanitized['dados']) ? $sanitized['dados'] : [],
                    'consentimentos' => isset($sanitized['consentimentos']) && is_array($sanitized['consentimentos']) ? $sanitized['consentimentos'] : [],
                ],
                $userId,
                $ipHash,
                $ua
            );

            return $this->ok([
                'agente_id'      => isset($result['agente_id']) ? (int) $result['agente_id'] : $agenteId,
                'status_cadastro' => isset($result['status_cadastro']) ? (string) $result['status_cadastro'] : 'em_analise',
                'protocolo'       => isset($result['protocolo']) ? (string) $result['protocolo'] : '',
            ]);
        } catch (Throwable $e) {
            return $this->handleThrowable($e);
        }
    }

    /**
     * POST /wizard/upload-documento (multipart/form-data).
     *
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function uploadDocumento(object $request)
    {
        try {
            $this->enforceRateLimit('wizard_upload', 10, 60);

            $userId = $this->currentUserId();

            $files = method_exists($request, 'get_file_params') ? (array) $request->get_file_params() : [];
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            if ($files === [] && isset($_FILES) && is_array($_FILES)) {
                $files = $_FILES;
            }
            if (!isset($files['arquivo']) || !is_array($files['arquivo'])) {
                throw RestException::validation(
                    function_exists('__') ? \__('Arquivo ausente (campo "arquivo" obrigatório).', 'participe-ibram') : 'Arquivo ausente.',
                    ['campo' => 'arquivo']
                );
            }
            $upload = $files['arquivo'];
            $error  = isset($upload['error']) ? (int) $upload['error'] : UPLOAD_ERR_NO_FILE;
            if ($error !== UPLOAD_ERR_OK) {
                throw RestException::validation($this->describeUploadError($error));
            }

            $tmp  = isset($upload['tmp_name']) ? (string) $upload['tmp_name'] : '';
            $name = isset($upload['name']) ? (string) $upload['name'] : '';
            if ($tmp === '' || $name === '' || !is_uploaded_file($tmp)) {
                throw RestException::validation(
                    function_exists('__') ? \__('Upload inválido.', 'participe-ibram') : 'Upload inválido.'
                );
            }

            $tipoDocumentoId = (int) $this->paramFromRequest($request, 'tipo_documento_id', 0);
            if ($tipoDocumentoId <= 0) {
                throw RestException::validation(
                    function_exists('__') ? \__('Tipo de documento obrigatório.', 'participe-ibram') : 'tipo_documento_id obrigatório.',
                    ['campo' => 'tipo_documento_id']
                );
            }
            $agenteId    = (int) $this->paramFromRequest($request, 'agente_id', 0);
            $inscricaoId = (int) $this->paramFromRequest($request, 'inscricao_id', 0);

            // Auth: se agente_id presente, deve ser do user (ou admin).
            if ($agenteId > 0) {
                $agente = $this->agentes->findById($agenteId);
                if ($agente === null) {
                    throw RestException::notFound();
                }
                if ($agente->getUserId() !== $userId && !$this->canAdminViewRascunho()) {
                    throw RestException::forbidden();
                }
            }

            $cmd = new UploadDocumentoCommand(
                $agenteId,
                $inscricaoId > 0 ? $inscricaoId : null,
                $tipoDocumentoId,
                $tmp,
                $name,
                $userId
            );
            $id = $this->uploadHandler->handle($cmd);

            return $this->ok(['documento_id' => $id], 201);
        } catch (Throwable $e) {
            return $this->handleThrowable($e);
        }
    }

    /**
     * DELETE /wizard/documento/{id}.
     *
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function deletarDocumento(object $request)
    {
        try {
            $this->enforceRateLimit('wizard_doc_delete', 30, 60);

            $userId = $this->currentUserId();
            $id     = (int) $this->paramFromRequest($request, 'id', 0);
            if ($id <= 0) {
                throw RestException::validation('id inválido.');
            }

            try {
                $doc = $this->documentos->findById($id);
            } catch (DocumentoNotFound $e) {
                throw RestException::notFound();
            }

            $owns = $doc->agenteId() !== null
                && $doc->agenteId() > 0
                && $this->isOwnerOfAgente((int) $doc->agenteId(), $userId);
            if (!$owns && !$this->canAdminViewRascunho()) {
                throw RestException::forbidden();
            }

            // Deleta arquivo físico + linha (storage trata path traversal).
            try {
                $this->storage->delete($doc->arquivoPath());
            } catch (Throwable $ignored) {
                // Mesmo que o arquivo não exista, removemos o registro.
            }
            $this->documentos->delete($id);

            $this->audit->log(
                'documento',
                $id,
                'delete',
                ['agente_id' => $doc->agenteId(), 'tipo_documento_id' => $doc->tipoDocumentoId()],
                null,
                $userId
            );

            return $this->ok(['ok' => true]);
        } catch (Throwable $e) {
            return $this->handleThrowable($e);
        }
    }

    /**
     * GET /wizard/vocabulario/{tipo}. Cacheável (1h).
     *
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function listarVocabulario(object $request)
    {
        try {
            $this->enforceRateLimit('wizard_vocab', 120, 60);

            $tipo = (string) $this->paramFromRequest($request, 'tipo', '');
            if ($tipo === '' || !TipoVocabulario::isValid($tipo)) {
                throw RestException::validation(
                    function_exists('__') ? \__('Tipo de vocabulário inválido.', 'participe-ibram') : 'tipo inválido.',
                    ['campo' => 'tipo']
                );
            }

            $items = $this->vocabHandler->handle(new ListarVocabularioQuery($tipo, true));

            return $this->ok(['tipo' => $tipo, 'items' => $items], 200, 3600);
        } catch (Throwable $e) {
            return $this->handleThrowable($e);
        }
    }

    /* =====================================================================
     * Helpers locais
     * ===================================================================== */

    private function currentUserId(): int
    {
        $userId = function_exists('get_current_user_id') ? (int) \get_current_user_id() : 0;
        if ($userId <= 0) {
            throw RestException::unauthorized();
        }

        return $userId;
    }

    private function canAdminViewRascunho(): bool
    {
        return function_exists('current_user_can') && (bool) \current_user_can(self::CAP_ADMIN_VIEW_RASCUNHO);
    }

    private function isOwnerOfAgente(int $agenteId, int $userId): bool
    {
        $agente = $this->agentes->findById($agenteId);
        if ($agente === null) {
            return false;
        }

        return $agente->getUserId() === $userId;
    }

    /**
     * Lê parâmetro de URL/body sem depender de WP_REST_Request específico.
     *
     * @param mixed  $default
     * @return mixed
     */
    private function paramFromRequest(object $request, string $key, $default)
    {
        if (method_exists($request, 'get_param')) {
            $value = $request->get_param($key);
            if ($value !== null) {
                return $value;
            }
        }
        if (method_exists($request, 'get_url_params')) {
            $params = $request->get_url_params();
            if (is_array($params) && isset($params[$key])) {
                return $params[$key];
            }
        }

        return $default;
    }

    private function captureUserAgent(object $request): ?string
    {
        $ua = null;
        if (method_exists($request, 'get_header')) {
            $ua = $request->get_header('user_agent');
        }
        if ($ua === null && isset($_SERVER['HTTP_USER_AGENT'])) {
            $ua = function_exists('wp_unslash') ? (string) \wp_unslash($_SERVER['HTTP_USER_AGENT']) : (string) $_SERVER['HTTP_USER_AGENT'];
        }
        if (!is_string($ua) || $ua === '') {
            return null;
        }
        if (function_exists('sanitize_text_field')) {
            $ua = (string) \sanitize_text_field($ua);
        }

        return mb_substr($ua, 0, 1024);
    }

    private function describeUploadError(int $code): string
    {
        $msg = function_exists('__') ? \__('Erro no upload.', 'participe-ibram') : 'Erro no upload.';
        switch ($code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return function_exists('__') ? \__('Arquivo excede o tamanho permitido.', 'participe-ibram') : 'Arquivo grande.';
            case UPLOAD_ERR_PARTIAL:
                return function_exists('__') ? \__('Upload incompleto.', 'participe-ibram') : 'Upload incompleto.';
            case UPLOAD_ERR_NO_FILE:
                return function_exists('__') ? \__('Nenhum arquivo enviado.', 'participe-ibram') : 'Nenhum arquivo.';
            case UPLOAD_ERR_NO_TMP_DIR:
            case UPLOAD_ERR_CANT_WRITE:
            case UPLOAD_ERR_EXTENSION:
                return function_exists('__') ? \__('Erro temporário no servidor. Tente novamente.', 'participe-ibram') : 'Erro temporário.';
            default:
                return $msg;
        }
    }
}
