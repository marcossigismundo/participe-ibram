<?php
/**
 * Endpoints REST públicos (sem autenticação).
 *
 * @package Ibram\ParticipeIbram\Presentation\Rest
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Rest;

use Ibram\ParticipeIbram\Domain\Consentimento\TermoRepository;
use Throwable;

/**
 * Endpoints públicos:
 *  - GET /pi/v1/publico/agentes-deferidos — vitrine pública (sem CPF/email).
 *  - GET /pi/v1/publico/termo-vigente     — texto + versão + hash da política.
 *  - GET /pi/v1/publico/editais           — lista publicados.
 *
 * Crítico (R2-lgpd §6 / TD-18):
 *  - NUNCA expõe `cpf`, `cpf_enc`, `email_principal`, `telefone`.
 *  - "Nome público" é o nome social (PF) ou nome da organização (OR/SM).
 *  - Cache pode ser distribuído por CDN: agentes-deferidos 5min, termo 1h.
 */
final class PublicEndpoints
{
    use RestSupport;

    /**
     * Provedor de listagem pública dos agentes deferidos.
     *
     * Recebe `[page, per_page]` e devolve `[items: [...], total: int]`.
     * Cada item já vem em formato sanitizado (sem PII).
     *
     * @var callable(int,int): array{items: array<int,array<string,mixed>>, total:int}
     */
    private $deferidosProvider;

    /**
     * Provedor de listagem pública de editais publicados.
     *
     * @var callable(): array<int,array<string,mixed>>
     */
    private $editaisProvider;

    private TermoRepository $termos;

    /**
     * @param callable|null $deferidosProvider Quando null, endpoint retorna lista vazia.
     * @param callable|null $editaisProvider   Quando null, retorna lista vazia.
     */
    public function __construct(
        TermoRepository $termos,
        ?callable $deferidosProvider = null,
        ?callable $editaisProvider = null
    ) {
        $this->termos            = $termos;
        $this->deferidosProvider = $deferidosProvider;
        $this->editaisProvider   = $editaisProvider;
    }

    public function register(string $namespace): void
    {
        if (!function_exists('register_rest_route')) {
            return;
        }

        \register_rest_route($namespace, '/publico/agentes-deferidos', [
            'methods'             => 'GET',
            'callback'            => [$this, 'listarDeferidos'],
            'permission_callback' => $this->permissionPublic(),
            'args'                => [
                'page' => [
                    'type'              => 'integer',
                    'required'          => false,
                    'default'           => 1,
                    'sanitize_callback' => 'absint',
                ],
                'per_page' => [
                    'type'              => 'integer',
                    'required'          => false,
                    'default'           => 25,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        \register_rest_route($namespace, '/publico/termo-vigente', [
            'methods'             => 'GET',
            'callback'            => [$this, 'termoVigente'],
            'permission_callback' => $this->permissionPublic(),
        ]);

        \register_rest_route($namespace, '/publico/editais', [
            'methods'             => 'GET',
            'callback'            => [$this, 'listarEditais'],
            'permission_callback' => $this->permissionPublic(),
        ]);
    }

    /**
     * GET /publico/agentes-deferidos.
     *
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function listarDeferidos(object $request)
    {
        try {
            $this->enforceRateLimit('publico_deferidos', 60, 60);

            $page    = (int) $this->paramFromRequest($request, 'page', 1);
            $perPage = (int) $this->paramFromRequest($request, 'per_page', 25);
            if ($page < 1) {
                $page = 1;
            }
            if ($perPage < 1 || $perPage > 100) {
                $perPage = 25;
            }

            if ($this->deferidosProvider === null) {
                $items = [];
                $total = 0;
            } else {
                $provider = $this->deferidosProvider;
                $result   = (array) $provider($page, $perPage);
                $items    = isset($result['items']) && is_array($result['items']) ? $result['items'] : [];
                $total    = isset($result['total']) ? (int) $result['total'] : count($items);
            }

            // Defesa em profundidade: filtra cada item para garantir que
            // somente as chaves seguras saiam.
            $safe = array_values(array_map(
                static function ($item): array {
                    if (!is_array($item)) {
                        return [];
                    }
                    return [
                        'numero_registro' => isset($item['numero_registro']) ? (string) $item['numero_registro'] : '',
                        'nome_publico'    => isset($item['nome_publico']) ? (string) $item['nome_publico'] : '',
                        'tipo_agente'     => isset($item['tipo_agente']) ? (string) $item['tipo_agente'] : '',
                        'deferido_em'     => isset($item['deferido_em']) ? (string) $item['deferido_em'] : null,
                    ];
                },
                $items
            ));

            return $this->ok([
                'items'    => $safe,
                'total'    => $total,
                'page'     => $page,
                'per_page' => $perPage,
            ], 200, 300);
        } catch (Throwable $e) {
            return $this->handleThrowable($e);
        }
    }

    /**
     * GET /publico/termo-vigente.
     *
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function termoVigente(object $request)
    {
        unset($request);
        try {
            $this->enforceRateLimit('publico_termo', 60, 60);

            $termo = $this->termos->findAtivoCorrente();
            if ($termo === null) {
                throw RestException::notFound(
                    function_exists('__') ? \__('Termo vigente não encontrado.', 'participe-ibram') : 'Termo não encontrado.'
                );
            }

            return $this->ok([
                'versao'        => $termo->versao(),
                'hash_sha256'   => $termo->hashConteudo(),
                'ativo_em'      => $termo->ativoEm()->format(\DateTimeInterface::ATOM),
                'conteudo_md'   => $termo->conteudoMd(),
            ], 200, 3600);
        } catch (Throwable $e) {
            return $this->handleThrowable($e);
        }
    }

    /**
     * GET /publico/editais.
     *
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function listarEditais(object $request)
    {
        unset($request);
        try {
            $this->enforceRateLimit('publico_editais', 60, 60);

            if ($this->editaisProvider === null) {
                $items = [];
            } else {
                $provider = $this->editaisProvider;
                $items    = (array) $provider();
            }

            $safe = array_values(array_map(
                static function ($item): array {
                    if (!is_array($item)) {
                        return [];
                    }
                    return [
                        'id'                       => isset($item['id']) ? (int) $item['id'] : 0,
                        'titulo'                   => isset($item['titulo']) ? (string) $item['titulo'] : '',
                        'status'                   => isset($item['status']) ? (string) $item['status'] : '',
                        'abertura'                 => isset($item['abertura']) ? (string) $item['abertura'] : null,
                        'encerramento_inscricoes'  => isset($item['encerramento_inscricoes']) ? (string) $item['encerramento_inscricoes'] : null,
                        'descricao_md'             => isset($item['descricao_md']) ? (string) $item['descricao_md'] : null,
                    ];
                },
                $items
            ));

            return $this->ok(['items' => $safe], 200, 300);
        } catch (Throwable $e) {
            return $this->handleThrowable($e);
        }
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    private function paramFromRequest(object $request, string $key, $default)
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
