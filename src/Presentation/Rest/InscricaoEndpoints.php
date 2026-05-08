<?php
/**
 * Endpoints REST autenticados de Inscrição em Edital.
 *
 * Segurança:
 *  - Todos exigem usuário logado (permissionLoggedIn).
 *  - Dono da inscrição é verificado cruzando agente_id com o user atual.
 *  - Sem exposição de PII de outros agentes em nenhuma resposta.
 *  - UNIQUE(edital_id, categoria_id, agente_id) resulta em 409.
 *  - Upload limitado a 10 req/min; demais a 60 req/min.
 *
 * @package Ibram\ParticipeIbram\Presentation\Rest
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Rest;

use DomainException;
use Ibram\ParticipeIbram\Application\Documento\UploadDocumentoCommand;
use Ibram\ParticipeIbram\Application\Documento\UploadDocumentoHandler;
use Ibram\ParticipeIbram\Application\Edital\AgenteLookupPort;
use Ibram\ParticipeIbram\Application\Edital\InscreverAgenteCommand;
use Ibram\ParticipeIbram\Application\Edital\InscreverAgenteHandler;
use Ibram\ParticipeIbram\Application\Edital\SalvarRascunhoInscricaoCommand;
use Ibram\ParticipeIbram\Application\Edital\SalvarRascunhoInscricaoHandler;
use Ibram\ParticipeIbram\Domain\Edital\InscricaoDuplicada;
use Ibram\ParticipeIbram\Domain\Edital\StatusInscricao;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbDocumentoRepository;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbInscricaoRepository;
use Throwable;

/**
 * Endpoints autenticados em pi/v1/inscricao:
 *  - POST   /pi/v1/inscricao/rascunho             — cria/atualiza rascunho.
 *  - GET    /pi/v1/inscricao/{id}                  — lê inscrição do dono.
 *  - POST   /pi/v1/inscricao/submeter              — submete inscrição final.
 *  - POST   /pi/v1/inscricao/{id}/upload-documento — multipart upload.
 *  - DELETE /pi/v1/inscricao/{id}/documento/{doc_id} — exclui documento.
 */
final class InscricaoEndpoints
{
    use RestSupport;

    private SalvarRascunhoInscricaoHandler $rascunhoHandler;
    private InscreverAgenteHandler $inscreverHandler;
    private UploadDocumentoHandler $uploadHandler;
    private WpdbInscricaoRepository $inscricoesRepo;
    private WpdbDocumentoRepository $documentosRepo;
    private AgenteLookupPort $agenteLookup;

    /**
     * Map de agente_id por WP user id — provider cross-domain.
     * Recebe (int $wpUserId): int|null
     *
     * @var callable(int): (int|null)
     */
    private $agenteIdByUserProvider;

    /**
     * @param callable $agenteIdByUserProvider  (int $wpUserId) → int|null
     */
    public function __construct(
        SalvarRascunhoInscricaoHandler $rascunhoHandler,
        InscreverAgenteHandler $inscreverHandler,
        UploadDocumentoHandler $uploadHandler,
        WpdbInscricaoRepository $inscricoesRepo,
        WpdbDocumentoRepository $documentosRepo,
        AgenteLookupPort $agenteLookup,
        callable $agenteIdByUserProvider
    ) {
        $this->rascunhoHandler        = $rascunhoHandler;
        $this->inscreverHandler       = $inscreverHandler;
        $this->uploadHandler          = $uploadHandler;
        $this->inscricoesRepo         = $inscricoesRepo;
        $this->documentosRepo         = $documentosRepo;
        $this->agenteLookup           = $agenteLookup;
        $this->agenteIdByUserProvider = $agenteIdByUserProvider;
    }

    public function register(string $namespace): void
    {
        if (!function_exists('register_rest_route')) {
            return;
        }

        // POST /inscricao/rascunho
        \register_rest_route($namespace, '/inscricao/rascunho', [
            'methods'             => 'POST',
            'callback'            => [$this, 'salvarRascunho'],
            'permission_callback' => $this->permissionLoggedIn(),
            'args'                => [
                'edital_id' => [
                    'type'              => 'integer',
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                ],
                'categoria_id' => [
                    'type'              => 'integer',
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                ],
                'agente_id' => [
                    'type'              => 'integer',
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                ],
                'portfolio_md' => [
                    'type'     => 'string',
                    'required' => false,
                    'default'  => '',
                ],
                'inscricao_id' => [
                    'type'              => 'integer',
                    'required'          => false,
                    'sanitize_callback' => 'absint',
                ],
                'etapa_atual' => [
                    'type'              => 'string',
                    'required'          => false,
                    'default'           => 'categoria',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // GET /inscricao/{id}
        \register_rest_route($namespace, '/inscricao/(?P<id>\\d+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'lerInscricao'],
            'permission_callback' => $this->permissionLoggedIn(),
            'args'                => [
                'id' => [
                    'type'              => 'integer',
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // POST /inscricao/submeter
        \register_rest_route($namespace, '/inscricao/submeter', [
            'methods'             => 'POST',
            'callback'            => [$this, 'submeterInscricao'],
            'permission_callback' => $this->permissionLoggedIn(),
            'args'                => [
                'inscricao_id' => [
                    'type'              => 'integer',
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // POST /inscricao/{id}/upload-documento
        \register_rest_route($namespace, '/inscricao/(?P<id>\\d+)/upload-documento', [
            'methods'             => 'POST',
            'callback'            => [$this, 'uploadDocumento'],
            'permission_callback' => $this->permissionLoggedIn(),
            'args'                => [
                'id' => [
                    'type'              => 'integer',
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                ],
                'tipo_documento_id' => [
                    'type'              => 'integer',
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // DELETE /inscricao/{id}/documento/{doc_id}
        \register_rest_route($namespace, '/inscricao/(?P<id>\\d+)/documento/(?P<doc_id>\\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'deletarDocumento'],
            'permission_callback' => $this->permissionLoggedIn(),
            'args'                => [
                'id' => [
                    'type'              => 'integer',
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                ],
                'doc_id' => [
                    'type'              => 'integer',
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
    }

    /**
     * POST /inscricao/rascunho
     *
     * @param object $request WP_REST_Request
     *
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function salvarRascunho(object $request)
    {
        try {
            $this->enforceRateLimit('inscricao_rascunho', 60, 60);

            $body        = $this->readJsonBody($request);
            $editalId    = (int) ($body['edital_id'] ?? $this->param($request, 'edital_id', 0));
            $categoriaId = (int) ($body['categoria_id'] ?? $this->param($request, 'categoria_id', 0));
            $agenteId    = (int) ($body['agente_id'] ?? $this->param($request, 'agente_id', 0));
            $portfolioMd = isset($body['portfolio_md']) ? (string) $body['portfolio_md'] : null;
            $inscricaoId = isset($body['inscricao_id']) ? (int) $body['inscricao_id'] : null;
            $etapaAtual  = isset($body['etapa_atual']) ? (string) $body['etapa_atual'] : 'categoria';

            if ($portfolioMd !== null && mb_strlen($portfolioMd) > 5000) {
                throw RestException::validation(
                    function_exists('__')
                        ? \__('Portfólio não pode exceder 5000 caracteres.', 'participe-ibram')
                        : 'Portfólio não pode exceder 5000 caracteres.'
                );
            }

            // Garante que o agente_id pertence ao usuário logado.
            $this->assertAgenteOwnership($agenteId);

            $command = new SalvarRascunhoInscricaoCommand(
                $editalId,
                $categoriaId,
                $agenteId,
                $portfolioMd,
                $inscricaoId,
                $etapaAtual
            );

            $id = $this->rascunhoHandler->handle($command);

            return $this->ok(['inscricao_id' => $id], 200, 0);
        } catch (RestException $e) {
            return $e->toResponse();
        } catch (DomainException $e) {
            return RestException::validation($e->getMessage())->toResponse();
        } catch (Throwable $e) {
            return $this->handleThrowable($e);
        }
    }

    /**
     * GET /inscricao/{id}
     *
     * @param object $request WP_REST_Request
     *
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function lerInscricao(object $request)
    {
        try {
            $this->enforceRateLimit('inscricao_ler', 60, 60);

            $id       = (int) $this->param($request, 'id', 0);
            $agenteId = $this->resolveAgenteId();

            $inscricao = $this->inscricoesRepo->findById($id);
            if ($inscricao === null) {
                throw RestException::notFound(
                    function_exists('__') ? \__('Inscrição não encontrada.', 'participe-ibram') : 'Inscrição não encontrada.'
                );
            }
            if ($inscricao->agenteId() !== $agenteId) {
                throw RestException::forbidden();
            }

            $documentos = $this->documentosRepo->findByInscricao($id);
            $docsSafe   = array_values(array_map(
                static function ($doc): array {
                    return [
                        'id'                => $doc->id(),
                        'tipo_documento_id' => $doc->tipoDocumentoId(),
                        'nome_original'     => $doc->nomeOriginal(),
                        'mime_real'         => $doc->mimeReal(),
                        'tamanho_bytes'     => $doc->tamanhoBytes(),
                        'uploaded_at'       => $doc->uploadedAt()->format(\DateTimeInterface::ATOM),
                    ];
                },
                $documentos
            ));

            return $this->ok([
                'id'           => $inscricao->id(),
                'edital_id'    => $inscricao->editalId(),
                'categoria_id' => $inscricao->categoriaId(),
                'status'       => $inscricao->status()->value(),
                'portfolio_md' => $inscricao->portfolioMd(),
                'inscrito_em'  => $inscricao->inscritoEm() !== null
                    ? $inscricao->inscritoEm()->format(\DateTimeInterface::ATOM) : null,
                'documentos'   => $docsSafe,
            ], 200, 0);
        } catch (RestException $e) {
            return $e->toResponse();
        } catch (Throwable $e) {
            return $this->handleThrowable($e);
        }
    }

    /**
     * POST /inscricao/submeter
     *
     * Valida documentos obrigatórios, dispara InscreverAgenteHandler.
     * Captura InscricaoDuplicada → 409.
     *
     * @param object $request WP_REST_Request
     *
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function submeterInscricao(object $request)
    {
        try {
            $this->enforceRateLimit('inscricao_submeter', 60, 60);

            $body        = $this->readJsonBody($request);
            $inscricaoId = (int) ($body['inscricao_id'] ?? $this->param($request, 'inscricao_id', 0));
            $agenteId    = $this->resolveAgenteId();

            if ($inscricaoId <= 0) {
                throw RestException::validation(
                    function_exists('__') ? \__('inscricao_id é obrigatório.', 'participe-ibram') : 'inscricao_id é obrigatório.'
                );
            }

            $inscricao = $this->inscricoesRepo->findById($inscricaoId);
            if ($inscricao === null) {
                throw RestException::notFound(
                    function_exists('__') ? \__('Inscrição não encontrada.', 'participe-ibram') : 'Inscrição não encontrada.'
                );
            }
            if ($inscricao->agenteId() !== $agenteId) {
                throw RestException::forbidden();
            }
            if ($inscricao->status()->value() !== StatusInscricao::RASCUNHO) {
                throw RestException::validation(
                    function_exists('__')
                        ? \__('Apenas inscrições em rascunho podem ser submetidas.', 'participe-ibram')
                        : 'Apenas inscrições em rascunho podem ser submetidas.'
                );
            }

            // Valida documentos obrigatórios da categoria.
            $this->validarDocumentosObrigatorios($inscricao->categoriaId(), $inscricaoId);

            // Delega ao handler (faz todas as validações de domínio e transição de status).
            $command = new InscreverAgenteCommand(
                $inscricao->editalId(),
                $inscricao->categoriaId(),
                $agenteId,
                $inscricao->portfolioMd()
            );

            try {
                $novoId = $this->inscreverHandler->handle($command);
            } catch (InscricaoDuplicada $e) {
                throw RestException::conflict(
                    function_exists('__')
                        ? \__('Você já está inscrito nesta categoria deste edital.', 'participe-ibram')
                        : 'Você já está inscrito nesta categoria deste edital.'
                );
            }

            if (function_exists('do_action')) {
                \do_action('pi_inscricao_recebida', $novoId, $agenteId);
            }

            return $this->ok(['inscricao_id' => $novoId], 201, 0);
        } catch (RestException $e) {
            return $e->toResponse();
        } catch (InscricaoDuplicada $e) {
            return RestException::conflict(
                function_exists('__')
                    ? \__('Você já está inscrito nesta categoria deste edital.', 'participe-ibram')
                    : 'Você já está inscrito nesta categoria deste edital.'
            )->toResponse();
        } catch (DomainException $e) {
            $msg = $e->getMessage();
            // Detecta DomainException de "agente não deferido" especificamente.
            if (stripos($msg, 'deferido') !== false) {
                return RestException::forbidden(
                    function_exists('__')
                        ? \__('Apenas agentes deferidos podem se inscrever.', 'participe-ibram')
                        : 'Apenas agentes deferidos podem se inscrever.'
                )->toResponse();
            }
            if (stripos($msg, 'elegível') !== false || stripos($msg, 'tipo') !== false) {
                return RestException::validation($msg)->toResponse();
            }

            return RestException::validation($msg)->toResponse();
        } catch (Throwable $e) {
            // Captura UNIQUE constraint violation (erro 1062 MySQL) não tratado acima.
            if (strpos($e->getMessage(), '1062') !== false) {
                return RestException::conflict(
                    function_exists('__')
                        ? \__('Você já está inscrito nesta categoria deste edital.', 'participe-ibram')
                        : 'Você já está inscrito nesta categoria deste edital.'
                )->toResponse();
            }

            return $this->handleThrowable($e);
        }
    }

    /**
     * POST /inscricao/{id}/upload-documento (multipart/form-data).
     *
     * @param object $request WP_REST_Request
     *
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function uploadDocumento(object $request)
    {
        try {
            $this->enforceRateLimit('inscricao_upload', 10, 60);

            $inscricaoId    = (int) $this->param($request, 'id', 0);
            $tipoDocId      = (int) $this->param($request, 'tipo_documento_id', 0);
            $agenteId       = $this->resolveAgenteId();
            $wpUserId       = $this->currentUserId();

            if ($tipoDocId <= 0) {
                throw RestException::validation(
                    function_exists('__') ? \__('tipo_documento_id é obrigatório.', 'participe-ibram') : 'tipo_documento_id é obrigatório.'
                );
            }

            $inscricao = $this->inscricoesRepo->findById($inscricaoId);
            if ($inscricao === null) {
                throw RestException::notFound(
                    function_exists('__') ? \__('Inscrição não encontrada.', 'participe-ibram') : 'Inscrição não encontrada.'
                );
            }
            if ($inscricao->agenteId() !== $agenteId) {
                throw RestException::forbidden();
            }
            if ($inscricao->status()->value() !== StatusInscricao::RASCUNHO) {
                throw RestException::validation(
                    function_exists('__')
                        ? \__('Documentos só podem ser enviados em inscrições em rascunho.', 'participe-ibram')
                        : 'Documentos só podem ser enviados em inscrições em rascunho.'
                );
            }

            // Lê arquivo do $_FILES (WP_REST_Request não encapsula files).
            $files = isset($_FILES['arquivo']) ? $_FILES['arquivo'] : null;
            if (!is_array($files) || empty($files['tmp_name'])) {
                throw RestException::validation(
                    function_exists('__') ? \__('Arquivo não enviado.', 'participe-ibram') : 'Arquivo não enviado.'
                );
            }

            $tmpPath      = (string) $files['tmp_name'];
            $originalName = function_exists('wp_unslash')
                ? (string) \wp_unslash((string) ($files['name'] ?? 'arquivo'))
                : (string) ($files['name'] ?? 'arquivo');

            $command = new UploadDocumentoCommand(
                $agenteId,
                $inscricaoId,
                $tipoDocId,
                $tmpPath,
                $originalName,
                $wpUserId
            );

            $docId = $this->uploadHandler->handle($command);

            return $this->ok(['documento_id' => $docId], 201, 0);
        } catch (RestException $e) {
            return $e->toResponse();
        } catch (DomainException $e) {
            return RestException::validation($e->getMessage())->toResponse();
        } catch (Throwable $e) {
            return $this->handleThrowable($e);
        }
    }

    /**
     * DELETE /inscricao/{id}/documento/{doc_id}
     *
     * @param object $request WP_REST_Request
     *
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function deletarDocumento(object $request)
    {
        try {
            $this->enforceRateLimit('inscricao_deletar_doc', 30, 60);

            $inscricaoId = (int) $this->param($request, 'id', 0);
            $docId       = (int) $this->param($request, 'doc_id', 0);
            $agenteId    = $this->resolveAgenteId();

            $inscricao = $this->inscricoesRepo->findById($inscricaoId);
            if ($inscricao === null) {
                throw RestException::notFound(
                    function_exists('__') ? \__('Inscrição não encontrada.', 'participe-ibram') : 'Inscrição não encontrada.'
                );
            }
            if ($inscricao->agenteId() !== $agenteId) {
                throw RestException::forbidden();
            }
            if ($inscricao->status()->value() !== StatusInscricao::RASCUNHO) {
                throw RestException::validation(
                    function_exists('__')
                        ? \__('Documentos só podem ser removidos de inscrições em rascunho.', 'participe-ibram')
                        : 'Documentos só podem ser removidos de inscrições em rascunho.'
                );
            }

            // Verifica que o documento pertence à inscrição.
            $documento = $this->documentosRepo->findById($docId);
            if ($documento->inscricaoId() !== $inscricaoId) {
                throw RestException::forbidden(
                    function_exists('__')
                        ? \__('Documento não pertence a esta inscrição.', 'participe-ibram')
                        : 'Documento não pertence a esta inscrição.'
                );
            }

            $this->documentosRepo->delete($docId);

            return $this->ok(['deleted' => true], 200, 0);
        } catch (RestException $e) {
            return $e->toResponse();
        } catch (Throwable $e) {
            return $this->handleThrowable($e);
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Resolve o agente_id do usuário logado via provider cross-domain.
     *
     * @throws RestException 401 se não logado, 403 se não tem agente associado.
     */
    private function resolveAgenteId(): int
    {
        $userId   = $this->currentUserId();
        $provider = $this->agenteIdByUserProvider;
        $agenteId = $provider($userId);
        if ($agenteId === null || $agenteId <= 0) {
            throw RestException::forbidden(
                function_exists('__')
                    ? \__('Nenhum agente associado ao usuário logado.', 'participe-ibram')
                    : 'Nenhum agente associado ao usuário logado.'
            );
        }

        return (int) $agenteId;
    }

    /**
     * Garante que o $agenteId informado pertence ao usuário logado.
     *
     * @throws RestException 403 se ownership falhar.
     */
    private function assertAgenteOwnership(int $agenteId): void
    {
        $userId         = $this->currentUserId();
        $provider       = $this->agenteIdByUserProvider;
        $agenteDoUsuario = $provider($userId);
        if ($agenteDoUsuario === null || (int) $agenteDoUsuario !== $agenteId) {
            throw RestException::forbidden(
                function_exists('__')
                    ? \__('O agente informado não pertence ao usuário logado.', 'participe-ibram')
                    : 'O agente informado não pertence ao usuário logado.'
            );
        }
    }

    /**
     * Retorna o ID do WP user atual.
     */
    private function currentUserId(): int
    {
        return function_exists('get_current_user_id') ? (int) \get_current_user_id() : 0;
    }

    /**
     * Valida que os documentos obrigatórios da categoria estão presentes.
     *
     * @throws RestException 422 se documentos faltando.
     */
    private function validarDocumentosObrigatorios(int $categoriaId, int $inscricaoId): void
    {
        // Busca a categoria para obter documentos exigidos.
        // Usa hook/filter para evitar acoplamento direto com o repositório de categoria
        // quando não está disponível.
        if (!function_exists('apply_filters')) {
            return;
        }

        /** @var array<int,string>|null $documentosExigidos */
        $documentosExigidos = \apply_filters(
            'pi_categoria_documentos_exigidos',
            null,
            $categoriaId
        );

        if (!is_array($documentosExigidos) || count($documentosExigidos) === 0) {
            return;
        }

        $documentosEnviados = $this->documentosRepo->findByInscricao($inscricaoId);
        $tiposEnviados      = array_map(
            static fn ($doc) => (string) $doc->tipoDocumentoId(),
            $documentosEnviados
        );

        $faltando = [];
        foreach ($documentosExigidos as $codigoExigido) {
            if (!in_array((string) $codigoExigido, $tiposEnviados, true)) {
                $faltando[] = $codigoExigido;
            }
        }

        if (count($faltando) > 0) {
            throw new RestException(
                function_exists('__')
                    ? \__('Documentos obrigatórios faltando.', 'participe-ibram')
                    : 'Documentos obrigatórios faltando.',
                'pi_documentos_obrigatorios',
                422,
                ['documentos_faltando' => $faltando]
            );
        }
    }

    /**
     * @param mixed $default
     *
     * @return mixed
     */
    private function param(object $request, string $key, $default)
    {
        if (method_exists($request, 'get_param')) {
            $v = $request->get_param($key);
            if ($v !== null) {
                return $v;
            }
        }

        return $default;
    }
}
