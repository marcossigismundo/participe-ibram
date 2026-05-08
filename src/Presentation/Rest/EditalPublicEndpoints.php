<?php
/**
 * Endpoints REST públicos específicos de Edital.
 *
 * Crítico (R2-lgpd §6 / Onda 10 auditoria PII):
 *  - NUNCA expõe inscrições no detalhe do edital.
 *  - NUNCA expõe CPF, email, telefone, raça, gênero, orientação sexual, deficiência.
 *  - Toda resposta pública passa por whitelist explícita antes de serializar.
 *  - Cache HTTP: listagem 5min, detalhe 1h.
 *  - Rate limit: 60 req/min por IP (anônimo) ou usuário.
 *
 * @package Ibram\ParticipeIbram\Presentation\Rest
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Rest;

use Ibram\ParticipeIbram\Domain\Edital\StatusEdital;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbCategoriaRepository;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbEditalRepository;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbInscricaoRepository;
use Throwable;

/**
 * Endpoints públicos de edital (sem auth):
 *  - GET /pi/v1/publico/editais                              — listagem paginada.
 *  - GET /pi/v1/publico/edital/{id}                          — detalhe.
 *  - GET /pi/v1/publico/edital/{id}/categorias               — lista de categorias.
 *  - GET /pi/v1/publico/edital/{id}/inscritos-habilitados    — resultado público (cache 5min).
 */
final class EditalPublicEndpoints
{
    use RestSupport;

    /** Statuses visíveis ao público. */
    private const STATUSES_PUBLICOS = [
        StatusEdital::PUBLICADO,
        StatusEdital::INSCRICOES_ABERTAS,
        StatusEdital::EM_HABILITACAO,
        StatusEdital::EM_RECURSO,
        StatusEdital::VOTACAO_ABERTA,
        StatusEdital::VOTACAO_ENCERRADA,
        StatusEdital::ENCERRADO,
    ];

    /**
     * Whitelist de campos de edital na listagem e detalhe.
     * NUNCA adicionar campos com PII aqui.
     */
    private const EDITAL_FIELDS_WHITELIST = [
        'id',
        'titulo',
        'descricao_md',
        'status',
        'abertura',
        'encerramento_inscricoes',
        'abertura_votacao',
        'encerramento_votacao',
        'num_categorias',
    ];

    /**
     * Whitelist de campos de categoria pública.
     * NUNCA incluir documentos_exigidos_json bruto nem campos com PII.
     */
    private const CATEGORIA_FIELDS_WHITELIST = [
        'id',
        'nome',
        'descricao_md',
        'num_vagas',
        'num_suplentes',
        'tipos_agente_elegivel',
        'criterios_md',
    ];

    /**
     * Whitelist PII-free para inscritos habilitados.
     * NUNCA adicionar: cpf, cpf_enc, cpf_hash, rg, passaporte,
     * email, telefone, raca_cor, genero, orientacao_sexual,
     * povos_comunidades_tradicionais, deficiencia.
     */
    private const INSCRITO_FIELDS_WHITELIST = [
        'numero_registro',
        'nome_publico',
        'categoria_id',
        'candidato_inscricao_id',
    ];

    private WpdbEditalRepository $editaisRepo;
    private WpdbCategoriaRepository $categoriasRepo;
    private WpdbInscricaoRepository $inscricoesRepo;

    /**
     * Provider para dados públicos de inscritos habilitados (cross-domain).
     * Recebe (int $editalId): array<int,array<string,mixed>>
     *
     * @var callable(int): array<int,array<string,mixed>>|null
     */
    private $inscritosHabilitadosProvider;

    /**
     * @param callable|null $inscritosHabilitadosProvider Provider cross-domain opcional.
     */
    public function __construct(
        WpdbEditalRepository $editaisRepo,
        WpdbCategoriaRepository $categoriasRepo,
        WpdbInscricaoRepository $inscricoesRepo,
        ?callable $inscritosHabilitadosProvider = null
    ) {
        $this->editaisRepo                 = $editaisRepo;
        $this->categoriasRepo              = $categoriasRepo;
        $this->inscricoesRepo              = $inscricoesRepo;
        $this->inscritosHabilitadosProvider = $inscritosHabilitadosProvider;
    }

    public function register(string $namespace): void
    {
        if (!function_exists('register_rest_route')) {
            return;
        }

        // GET /publico/editais — listagem paginada com filtros.
        \register_rest_route($namespace, '/publico/editais', [
            'methods'             => 'GET',
            'callback'            => [$this, 'listarEditais'],
            'permission_callback' => $this->permissionPublic(),
            'args'                => [
                'status' => [
                    'type'              => 'string',
                    'required'          => false,
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'abertura_desde' => [
                    'type'              => 'string',
                    'required'          => false,
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'encerramento_ate' => [
                    'type'              => 'string',
                    'required'          => false,
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
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

        // GET /publico/edital/{id} — detalhe.
        \register_rest_route($namespace, '/publico/edital/(?P<id>\\d+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'detalheEdital'],
            'permission_callback' => $this->permissionPublic(),
            'args'                => [
                'id' => [
                    'type'              => 'integer',
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // GET /publico/edital/{id}/categorias — lista categorias.
        \register_rest_route($namespace, '/publico/edital/(?P<id>\\d+)/categorias', [
            'methods'             => 'GET',
            'callback'            => [$this, 'listarCategorias'],
            'permission_callback' => $this->permissionPublic(),
            'args'                => [
                'id' => [
                    'type'              => 'integer',
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // GET /publico/edital/{id}/inscritos-habilitados — lista pública (fase votação).
        \register_rest_route($namespace, '/publico/edital/(?P<id>\\d+)/inscritos-habilitados', [
            'methods'             => 'GET',
            'callback'            => [$this, 'inscritosHabilitados'],
            'permission_callback' => $this->permissionPublic(),
            'args'                => [
                'id' => [
                    'type'              => 'integer',
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
    }

    /**
     * GET /publico/editais
     *
     * @param object $request WP_REST_Request
     *
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function listarEditais(object $request)
    {
        try {
            $this->enforceRateLimit('publico_editais_lista', 60, 60);

            $page    = max(1, (int) $this->param($request, 'page', 1));
            $perPage = (int) $this->param($request, 'per_page', 25);
            if ($perPage < 1 || $perPage > 100) {
                $perPage = 25;
            }

            $statusFiltro    = (string) $this->param($request, 'status', '');
            $aberturaDesde   = (string) $this->param($request, 'abertura_desde', '');
            $encerramentoAte = (string) $this->param($request, 'encerramento_ate', '');

            // Valida status contra whitelist de statuses públicos.
            if ($statusFiltro !== '' && !in_array($statusFiltro, self::STATUSES_PUBLICOS, true)) {
                $statusFiltro = '';
            }

            $rows = $this->queryEditaisPublicos($statusFiltro, $aberturaDesde, $encerramentoAte, $page, $perPage);
            $total = $this->countEditaisPublicos($statusFiltro, $aberturaDesde, $encerramentoAte);

            // Whitelist defensiva — filtra chave por chave.
            $safe = array_values(array_map([$this, 'whitelistEdital'], $rows));

            $response = $this->ok([
                'items'    => $safe,
                'total'    => $total,
                'page'     => $page,
                'per_page' => $perPage,
            ], 200, 300);

            $this->addVaryHeader($response);

            return $response;
        } catch (Throwable $e) {
            return $this->handleThrowable($e);
        }
    }

    /**
     * GET /publico/edital/{id}
     *
     * @param object $request WP_REST_Request
     *
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function detalheEdital(object $request)
    {
        try {
            $this->enforceRateLimit('publico_edital_detalhe', 60, 60);

            $id = (int) $this->param($request, 'id', 0);
            if ($id <= 0) {
                throw RestException::notFound();
            }

            $edital = $this->editaisRepo->findById($id);
            if ($edital === null || !in_array($edital->status()->value(), self::STATUSES_PUBLICOS, true)) {
                throw RestException::notFound(
                    function_exists('__') ? \__('Edital não encontrado.', 'participe-ibram') : 'Edital não encontrado.'
                );
            }

            $categorias = $this->categoriasRepo->findByEdital($id);

            // Whitelist detalhe edital — NÃO inclui inscrições.
            $safeEdital            = $this->whitelistEdital($this->editalToArray($edital));
            $safeEdital['categorias'] = array_values(array_map(
                [$this, 'whitelistCategoria'],
                array_map([$this, 'categoriaToArray'], $categorias)
            ));

            $response = $this->ok($safeEdital, 200, 3600);
            $this->addVaryHeader($response);

            return $response;
        } catch (Throwable $e) {
            return $this->handleThrowable($e);
        }
    }

    /**
     * GET /publico/edital/{id}/categorias
     *
     * @param object $request WP_REST_Request
     *
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function listarCategorias(object $request)
    {
        try {
            $this->enforceRateLimit('publico_edital_categorias', 60, 60);

            $id = (int) $this->param($request, 'id', 0);
            if ($id <= 0) {
                throw RestException::notFound();
            }

            $edital = $this->editaisRepo->findById($id);
            if ($edital === null || !in_array($edital->status()->value(), self::STATUSES_PUBLICOS, true)) {
                throw RestException::notFound(
                    function_exists('__') ? \__('Edital não encontrado.', 'participe-ibram') : 'Edital não encontrado.'
                );
            }

            $categorias = $this->categoriasRepo->findByEdital($id);
            $safe       = array_values(array_map(
                [$this, 'whitelistCategoria'],
                array_map([$this, 'categoriaToArray'], $categorias)
            ));

            $response = $this->ok(['items' => $safe], 200, 300);
            $this->addVaryHeader($response);

            return $response;
        } catch (Throwable $e) {
            return $this->handleThrowable($e);
        }
    }

    /**
     * GET /publico/edital/{id}/inscritos-habilitados
     *
     * Apenas disponível quando edital está em votacao_aberta, votacao_encerrada ou encerrado.
     * Whitelist PII-free: somente numero_registro, nome_publico, categoria_id, candidato_inscricao_id.
     *
     * @param object $request WP_REST_Request
     *
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function inscritosHabilitados(object $request)
    {
        try {
            $this->enforceRateLimit('publico_inscritos_habilitados', 60, 60);

            $id = (int) $this->param($request, 'id', 0);
            if ($id <= 0) {
                throw RestException::notFound();
            }

            $edital = $this->editaisRepo->findById($id);
            if ($edital === null || !in_array($edital->status()->value(), self::STATUSES_PUBLICOS, true)) {
                throw RestException::notFound(
                    function_exists('__') ? \__('Edital não encontrado.', 'participe-ibram') : 'Edital não encontrado.'
                );
            }

            // Disponível somente nas fases de votação/encerramento.
            $statusVotacao = [
                StatusEdital::VOTACAO_ABERTA,
                StatusEdital::VOTACAO_ENCERRADA,
                StatusEdital::ENCERRADO,
            ];
            if (!in_array($edital->status()->value(), $statusVotacao, true)) {
                throw RestException::validation(
                    function_exists('__')
                        ? \__('Lista de inscritos habilitados disponível apenas durante a fase de votação.', 'participe-ibram')
                        : 'Lista disponível apenas durante a fase de votação.',
                    ['status_atual' => $edital->status()->value()]
                );
            }

            $items = [];
            if ($this->inscritosHabilitadosProvider !== null) {
                $provider = $this->inscritosHabilitadosProvider;
                $raw      = (array) $provider($id);
                // Whitelist defensiva crítica — PII-safe (Onda 10 auditará esta linha).
                $items = array_values(array_map(
                    [$this, 'whitelistInscrito'],
                    $raw
                ));
            }

            $response = $this->ok(['items' => $items], 200, 300);
            $this->addVaryHeader($response);

            return $response;
        } catch (Throwable $e) {
            return $this->handleThrowable($e);
        }
    }

    // ─── Whitelist helpers ────────────────────────────────────────────────────

    /**
     * Filtra um array de edital para a whitelist pública.
     * NUNCA expõe criadoPor, criadoPor_id, last_error ou outros campos internos.
     *
     * @param array<string,mixed> $item
     *
     * @return array<string,mixed>
     */
    private function whitelistEdital(array $item): array
    {
        return [
            'id'                      => isset($item['id']) ? (int) $item['id'] : 0,
            'titulo'                  => isset($item['titulo']) ? (string) $item['titulo'] : '',
            'descricao_md'            => isset($item['descricao_md']) ? (string) $item['descricao_md'] : null,
            'status'                  => isset($item['status']) ? (string) $item['status'] : '',
            'abertura'                => isset($item['abertura']) ? (string) $item['abertura'] : null,
            'encerramento_inscricoes' => isset($item['encerramento_inscricoes'])
                ? (string) $item['encerramento_inscricoes'] : null,
            'abertura_votacao'        => isset($item['abertura_votacao']) ? (string) $item['abertura_votacao'] : null,
            'encerramento_votacao'    => isset($item['encerramento_votacao'])
                ? (string) $item['encerramento_votacao'] : null,
            'num_categorias'          => isset($item['num_categorias']) ? (int) $item['num_categorias'] : 0,
        ];
    }

    /**
     * Filtra um array de categoria para a whitelist pública.
     * NUNCA expõe documentos_exigidos (lista interna de tipos obrigatórios).
     *
     * @param array<string,mixed> $item
     *
     * @return array<string,mixed>
     */
    private function whitelistCategoria(array $item): array
    {
        return [
            'id'                   => isset($item['id']) ? (int) $item['id'] : 0,
            'nome'                 => isset($item['nome']) ? (string) $item['nome'] : '',
            'descricao_md'         => isset($item['descricao_md']) ? (string) $item['descricao_md'] : null,
            'num_vagas'            => isset($item['num_vagas']) ? (int) $item['num_vagas'] : 0,
            'num_suplentes'        => isset($item['num_suplentes']) ? (int) $item['num_suplentes'] : 0,
            'tipos_agente_elegivel' => isset($item['tipos_agente_elegivel'])
                ? (string) $item['tipos_agente_elegivel'] : '',
            'criterios_md'         => isset($item['criterios_md']) ? (string) $item['criterios_md'] : null,
        ];
    }

    /**
     * Whitelist PII-free para inscritos habilitados.
     *
     * Chaves NUNCA permitidas: cpf, cpf_enc, cpf_hash, rg, passaporte,
     * email, telefone, raca_cor, genero, orientacao_sexual,
     * povos_comunidades_tradicionais, deficiencia.
     *
     * @param mixed $item
     *
     * @return array<string,mixed>
     */
    private function whitelistInscrito($item): array
    {
        if (!is_array($item)) {
            return [
                'numero_registro'       => '',
                'nome_publico'          => '',
                'categoria_id'          => 0,
                'candidato_inscricao_id' => 0,
            ];
        }

        return [
            'numero_registro'        => isset($item['numero_registro']) ? (string) $item['numero_registro'] : '',
            'nome_publico'           => isset($item['nome_publico']) ? (string) $item['nome_publico'] : '',
            'categoria_id'           => isset($item['categoria_id']) ? (int) $item['categoria_id'] : 0,
            'candidato_inscricao_id' => isset($item['candidato_inscricao_id'])
                ? (int) $item['candidato_inscricao_id'] : 0,
        ];
    }

    // ─── Queries ─────────────────────────────────────────────────────────────

    /**
     * @return array<int,array<string,mixed>>
     */
    private function queryEditaisPublicos(
        string $status,
        string $aberturaDesde,
        string $encerramentoAte,
        int $page,
        int $perPage
    ): array {
        $statuses = $status !== '' ? [$status] : self::STATUSES_PUBLICOS;
        $rows     = [];

        foreach ($statuses as $s) {
            try {
                $statusVO = StatusEdital::fromString($s);
            } catch (\InvalidArgumentException $e) {
                continue;
            }
            $partial = $this->editaisRepo->findByStatus($statusVO, $page, $perPage);
            foreach ($partial as $edital) {
                $aberturaEdital = $edital->abertura();
                $encInscricoes  = $edital->encerramentoInscricoes();

                if ($aberturaDesde !== '') {
                    try {
                        $filtroAbertura = new \DateTimeImmutable($aberturaDesde);
                        if ($aberturaEdital !== null && $aberturaEdital < $filtroAbertura) {
                            continue;
                        }
                    } catch (\Exception $ignored) {
                        // Ignora data inválida.
                    }
                }
                if ($encerramentoAte !== '') {
                    try {
                        $filtroEncerramento = new \DateTimeImmutable($encerramentoAte);
                        if ($encInscricoes !== null && $encInscricoes > $filtroEncerramento) {
                            continue;
                        }
                    } catch (\Exception $ignored) {
                        // Ignora data inválida.
                    }
                }
                $rows[] = $this->editalToArray($edital);
            }
        }

        // Ordena por abertura DESC.
        usort($rows, static function (array $a, array $b): int {
            $dateA = isset($a['abertura']) ? (string) $a['abertura'] : '';
            $dateB = isset($b['abertura']) ? (string) $b['abertura'] : '';

            return strcmp($dateB, $dateA);
        });

        return array_slice($rows, 0, $perPage);
    }

    /**
     * Contagem total para paginação (simplificada — usa array já carregado).
     */
    private function countEditaisPublicos(string $status, string $aberturaDesde, string $encerramentoAte): int
    {
        $statuses = $status !== '' ? [$status] : self::STATUSES_PUBLICOS;
        $count    = 0;
        foreach ($statuses as $s) {
            try {
                $statusVO = StatusEdital::fromString($s);
            } catch (\InvalidArgumentException $e) {
                continue;
            }
            // Aproximação: busca até 500 e conta.
            $partial = $this->editaisRepo->findByStatus($statusVO, 1, 500);
            foreach ($partial as $edital) {
                $aberturaEdital = $edital->abertura();
                $encInscricoes  = $edital->encerramentoInscricoes();
                if ($aberturaDesde !== '') {
                    try {
                        $filtroAbertura = new \DateTimeImmutable($aberturaDesde);
                        if ($aberturaEdital !== null && $aberturaEdital < $filtroAbertura) {
                            continue;
                        }
                    } catch (\Exception $ignored) {
                    }
                }
                if ($encerramentoAte !== '') {
                    try {
                        $filtroEnc = new \DateTimeImmutable($encerramentoAte);
                        if ($encInscricoes !== null && $encInscricoes > $filtroEnc) {
                            continue;
                        }
                    } catch (\Exception $ignored) {
                    }
                }
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return array<string,mixed>
     */
    private function editalToArray(\Ibram\ParticipeIbram\Domain\Edital\Edital $edital): array
    {
        $categorias = $this->categoriasRepo->findByEdital((int) $edital->id());

        return [
            'id'                      => $edital->id(),
            'titulo'                  => $edital->titulo(),
            'descricao_md'            => $edital->descricaoMd(),
            'status'                  => $edital->status()->value(),
            'abertura'                => $edital->abertura() !== null
                ? $edital->abertura()->format(\DateTimeInterface::ATOM) : null,
            'encerramento_inscricoes' => $edital->encerramentoInscricoes() !== null
                ? $edital->encerramentoInscricoes()->format(\DateTimeInterface::ATOM) : null,
            'abertura_votacao'        => $edital->aberturaVotacao() !== null
                ? $edital->aberturaVotacao()->format(\DateTimeInterface::ATOM) : null,
            'encerramento_votacao'    => $edital->encerramentoVotacao() !== null
                ? $edital->encerramentoVotacao()->format(\DateTimeInterface::ATOM) : null,
            'num_categorias'          => count($categorias),
            // criadoPor NUNCA exposto publicamente.
        ];
    }

    /**
     * @param \Ibram\ParticipeIbram\Domain\Edital\Categoria $categoria
     *
     * @return array<string,mixed>
     */
    private function categoriaToArray(\Ibram\ParticipeIbram\Domain\Edital\Categoria $categoria): array
    {
        return [
            'id'                    => $categoria->id(),
            'nome'                  => $categoria->nome(),
            'descricao_md'          => $categoria->descricaoMd(),
            'num_vagas'             => $categoria->numVagas(),
            'num_suplentes'         => $categoria->numSuplentes(),
            'tipos_agente_elegivel' => $categoria->tiposAgenteElegivel(),
            'criterios_md'          => $categoria->criteriosMd(),
            // documentosExigidos() NUNCA exposto publicamente.
        ];
    }

    /**
     * Adiciona Vary: Accept para suporte correto a caches de CDN/proxy.
     *
     * @param mixed $response WP_REST_Response ou array.
     */
    private function addVaryHeader($response): void
    {
        if ($response instanceof \WP_REST_Response) {
            $response->header('Vary', 'Accept');
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
