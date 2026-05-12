<?php
/**
 * Endpoints REST para Lei de Acesso à Informação (LAI — Lei 12.527/2011).
 *
 * Crítico (R2-lgpd §6 / Onda 10 auditoria PII):
 *  - Estes endpoints expõem APENAS dados de transparência ATIVA AGREGADOS.
 *  - NUNCA expõem PII: cpf, cpf_hash, rg, passaporte, email, telefone, raca_cor,
 *    genero, orientacao_sexual, povos_comunidades_tradicionais, deficiencia,
 *    ip_hash, user_agent, eleitor_hash, ou qualquer outro pseudonimizador.
 *  - Whitelist defensiva por chave fixa em CADA endpoint.
 *  - Cache HTTP agressivo (`max-age` >= 3600).
 *  - CORS `*` para permitir agregadores (CKAN/dados.gov.br).
 *  - Rate limit 60/min por IP (permissivo — dados públicos).
 *  - Formato `?format=csv` opcional com BOM UTF-8 e MIME `text/csv; charset=utf-8`.
 *
 * @package Ibram\ParticipeIbram\Presentation\Rest
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Rest;

use DateTimeImmutable;
use Ibram\ParticipeIbram\Domain\Edital\StatusEdital;
use Ibram\ParticipeIbram\Domain\Votacao\Resultado;
use Ibram\ParticipeIbram\Domain\Votacao\ResultadoRepository;
use Ibram\ParticipeIbram\Domain\Votacao\VotacaoRepository;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbCategoriaRepository;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbEditalRepository;
use Throwable;

/**
 * Endpoints públicos LAI registrados em `pi/v1/lai/*`:
 *
 *  - GET /pi/v1/lai/cadastros                       (estatísticas agregadas)
 *  - GET /pi/v1/lai/editais                         (todos os editais publicados)
 *  - GET /pi/v1/lai/edital/{id}/categorias          (categorias sem inscritos)
 *  - GET /pi/v1/lai/votacoes/resultados             (resultados finais publicados)
 *  - GET /pi/v1/lai/normativos                      (referências normativas)
 *  - GET /pi/v1/lai/contatos                        (contatos institucionais)
 *  - GET /pi/v1/lai/dados-abertos.json              (catalog JSON)
 *  - GET /pi/v1/lai/dados-abertos.csv               (catalog CSV)
 *
 * Convenções:
 *  - Auth: NENHUMA (transparência ativa).
 *  - Rate limit: 60 req/min por IP/usuário.
 *  - Cache: `max-age=3600` mínimo (até 1 dia para resultados encerrados).
 *  - CORS: `Access-Control-Allow-Origin: *` (permite agregadores externos).
 *  - Vary: `Accept, Accept-Encoding`.
 *  - Whitelist defensiva por chave fixa — NUNCA `wp_send_json($row)` direto.
 */
final class LaiPublicEndpoints
{
    use RestSupport;

    /** Whitelist de campos públicos de Edital (idêntica ao W5-B mas incluindo encerrados). */
    private const EDITAL_FIELDS = [
        'id',
        'titulo',
        'descricao_md',
        'status',
        'abertura',
        'encerramento_inscricoes',
        'abertura_votacao',
        'encerramento_votacao',
        'publicacao_resultado',
        'num_categorias',
        'criado_em',
    ];

    /** Whitelist de campos públicos de Categoria (sem documentos_exigidos). */
    private const CATEGORIA_FIELDS = [
        'id',
        'nome',
        'descricao_md',
        'num_vagas',
        'num_suplentes',
        'tipos_agente_elegivel',
        'criterios_md',
    ];

    /** Whitelist de campos públicos de Resultado de Votação. NUNCA agente_id, candidato_inscricao_id. */
    private const RESULTADO_FIELDS = [
        'numero_registro',
        'nome_publico',
        'categoria_nome',
        'posicao',
        'total_votos',
        'eleito',
        'suplente',
    ];

    /** Statuses públicos (LAI inclui encerrados — diferença de W5-B). */
    private const STATUSES_LAI = [
        StatusEdital::PUBLICADO,
        StatusEdital::INSCRICOES_ABERTAS,
        StatusEdital::EM_HABILITACAO,
        StatusEdital::EM_RECURSO,
        StatusEdital::VOTACAO_ABERTA,
        StatusEdital::VOTACAO_ENCERRADA,
        StatusEdital::ENCERRADO,
    ];

    /** @var \wpdb */
    private $wpdb;

    private WpdbEditalRepository $editaisRepo;
    private WpdbCategoriaRepository $categoriasRepo;
    private VotacaoRepository $votacoesRepo;
    private ResultadoRepository $resultadosRepo;

    /**
     * Provider para nome público (cross-domain): recebe candidato_inscricao_id,
     * devolve `{numero_registro, nome_publico}`. Wave 9-A injeta — fallback
     * vazio quando ausente.
     *
     * @var callable(int): array{numero_registro:string,nome_publico:string}|null
     */
    private $inscricaoNomePublicoProvider;

    /**
     * @param callable|null $inscricaoNomePublicoProvider
     */
    public function __construct(
        $wpdb,
        WpdbEditalRepository $editaisRepo,
        WpdbCategoriaRepository $categoriasRepo,
        VotacaoRepository $votacoesRepo,
        ResultadoRepository $resultadosRepo,
        ?callable $inscricaoNomePublicoProvider = null
    ) {
        $this->wpdb                         = $wpdb;
        $this->editaisRepo                  = $editaisRepo;
        $this->categoriasRepo               = $categoriasRepo;
        $this->votacoesRepo                 = $votacoesRepo;
        $this->resultadosRepo               = $resultadosRepo;
        $this->inscricaoNomePublicoProvider = $inscricaoNomePublicoProvider;
    }

    public function register(string $namespace): void
    {
        if (!function_exists('register_rest_route')) {
            return;
        }

        \register_rest_route($namespace, '/lai/cadastros', [
            'methods'             => 'GET',
            'callback'            => [$this, 'cadastros'],
            'permission_callback' => $this->permissionPublic(),
            'args'                => [
                'format' => [
                    'type'              => 'string',
                    'required'          => false,
                    'default'           => 'json',
                    'sanitize_callback' => 'sanitize_key',
                ],
            ],
        ]);

        \register_rest_route($namespace, '/lai/editais', [
            'methods'             => 'GET',
            'callback'            => [$this, 'editais'],
            'permission_callback' => $this->permissionPublic(),
            'args'                => [
                'ano' => [
                    'type'              => 'integer',
                    'required'          => false,
                    'sanitize_callback' => 'absint',
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
                'format' => [
                    'type'              => 'string',
                    'required'          => false,
                    'default'           => 'json',
                    'sanitize_callback' => 'sanitize_key',
                ],
            ],
        ]);

        \register_rest_route($namespace, '/lai/edital/(?P<id>\\d+)/categorias', [
            'methods'             => 'GET',
            'callback'            => [$this, 'editalCategorias'],
            'permission_callback' => $this->permissionPublic(),
            'args'                => [
                'id' => [
                    'type'              => 'integer',
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                ],
                'format' => [
                    'type'              => 'string',
                    'required'          => false,
                    'default'           => 'json',
                    'sanitize_callback' => 'sanitize_key',
                ],
            ],
        ]);

        \register_rest_route($namespace, '/lai/votacoes/resultados', [
            'methods'             => 'GET',
            'callback'            => [$this, 'votacoesResultados'],
            'permission_callback' => $this->permissionPublic(),
            'args'                => [
                'format' => [
                    'type'              => 'string',
                    'required'          => false,
                    'default'           => 'json',
                    'sanitize_callback' => 'sanitize_key',
                ],
            ],
        ]);

        \register_rest_route($namespace, '/lai/normativos', [
            'methods'             => 'GET',
            'callback'            => [$this, 'normativos'],
            'permission_callback' => $this->permissionPublic(),
        ]);

        \register_rest_route($namespace, '/lai/contatos', [
            'methods'             => 'GET',
            'callback'            => [$this, 'contatos'],
            'permission_callback' => $this->permissionPublic(),
        ]);

        \register_rest_route($namespace, '/lai/dados-abertos\\.json', [
            'methods'             => 'GET',
            'callback'            => [$this, 'dadosAbertosJson'],
            'permission_callback' => $this->permissionPublic(),
        ]);

        \register_rest_route($namespace, '/lai/dados-abertos\\.csv', [
            'methods'             => 'GET',
            'callback'            => [$this, 'dadosAbertosCsv'],
            'permission_callback' => $this->permissionPublic(),
        ]);
    }

    // ─── GET /lai/cadastros ────────────────────────────────────────────────

    /**
     * Estatísticas agregadas do cadastro de agentes — SEM PII, apenas COUNT(*).
     *
     * Como esta camada não conhece a tabela exata de cadastros (poderia evoluir
     * via PII-masking), construímos a consulta com nomes canônicos descritos
     * em SCHEMA.md. O resultado é estritamente um conjunto de pares
     * `(rótulo, count)` por agrupador.
     *
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function cadastros(object $request)
    {
        try {
            $this->enforceRateLimit('lai_cadastros', 60, 60);

            $prefix = $this->prefix();
            $totalDeferidos = $this->safeCount(
                "SELECT COUNT(*) FROM {$prefix}pi_agentes WHERE status_cadastro IN ('deferido','deferido_em_retratacao','deferido_em_recurso') AND deleted_at IS NULL"
            );

            $porTipo = $this->safeGroupedCount(
                "SELECT tipo AS rotulo, COUNT(*) AS total FROM {$prefix}pi_agentes WHERE deleted_at IS NULL GROUP BY tipo"
            );

            $porStatus = $this->safeGroupedCount(
                "SELECT status_cadastro AS rotulo, COUNT(*) AS total FROM {$prefix}pi_agentes WHERE deleted_at IS NULL GROUP BY status_cadastro"
            );

            // UF: tentamos a coluna `uf` em wp_pi_agentes_pf / _or / _sm. Se não existir,
            // devolvemos lista vazia (defensivo, não vaza tabela alheia).
            $porUf = $this->safeGroupedCount(
                "SELECT uf AS rotulo, COUNT(*) AS total FROM (
                    SELECT pf.uf FROM {$prefix}pi_agentes_pf pf
                    UNION ALL SELECT o.uf FROM {$prefix}pi_agentes_or o
                    UNION ALL SELECT s.uf FROM {$prefix}pi_agentes_sm s
                ) u WHERE uf IS NOT NULL AND uf <> '' GROUP BY uf"
            );

            // Série mensal últimos 24 meses.
            $serieMensal = $this->safeRows(
                "SELECT DATE_FORMAT(created_at, '%Y-%m') AS mes, COUNT(*) AS total
                 FROM {$prefix}pi_agentes
                 WHERE created_at >= DATE_SUB(CURRENT_DATE, INTERVAL 24 MONTH) AND deleted_at IS NULL
                 GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                 ORDER BY mes ASC"
            );
            $serieMensalSafe = array_values(array_map(
                static fn (array $r): array => [
                    'mes'   => isset($r['mes']) ? (string) $r['mes'] : '',
                    'total' => isset($r['total']) ? (int) $r['total'] : 0,
                ],
                $serieMensal
            ));

            $payload = [
                'total_deferidos' => (int) $totalDeferidos,
                'distribuicao_por_tipo'     => $porTipo,
                'distribuicao_por_status'   => $porStatus,
                'distribuicao_por_uf'       => $porUf,
                'serie_mensal_24m'          => $serieMensalSafe,
                'gerado_em'                 => (new DateTimeImmutable('now'))->format(\DateTimeInterface::ATOM),
            ];

            $format = strtolower((string) $this->param($request, 'format', 'json'));
            if ($format === 'csv') {
                $rows = [['campo', 'valor']];
                $rows[] = ['total_deferidos', (string) $payload['total_deferidos']];
                foreach ($payload['distribuicao_por_tipo'] as $row) {
                    $rows[] = ['tipo:' . (string) $row['rotulo'], (string) $row['total']];
                }
                foreach ($payload['distribuicao_por_status'] as $row) {
                    $rows[] = ['status:' . (string) $row['rotulo'], (string) $row['total']];
                }
                foreach ($payload['distribuicao_por_uf'] as $row) {
                    $rows[] = ['uf:' . (string) $row['rotulo'], (string) $row['total']];
                }
                foreach ($payload['serie_mensal_24m'] as $row) {
                    $rows[] = ['mes:' . (string) $row['mes'], (string) $row['total']];
                }
                return $this->csvResponse($rows, 3600);
            }

            return $this->jsonResponse($payload, 3600);
        } catch (Throwable $e) {
            return $this->handleThrowable($e);
        }
    }

    // ─── GET /lai/editais ──────────────────────────────────────────────────

    /**
     * Todos os editais publicados (incluindo encerrados).
     *
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function editais(object $request)
    {
        try {
            $this->enforceRateLimit('lai_editais', 60, 60);

            $page    = max(1, (int) $this->param($request, 'page', 1));
            $perPage = (int) $this->param($request, 'per_page', 25);
            if ($perPage < 1 || $perPage > 100) {
                $perPage = 25;
            }
            $ano = (int) $this->param($request, 'ano', 0);

            $allRows = [];
            foreach (self::STATUSES_LAI as $statusValue) {
                try {
                    $statusVO = StatusEdital::fromString($statusValue);
                } catch (\InvalidArgumentException $e) {
                    continue;
                }
                $parcial = $this->editaisRepo->findByStatus($statusVO, 1, 500);
                foreach ($parcial as $edital) {
                    if ($ano > 0) {
                        $criadoYear = (int) $edital->createdAt()->format('Y');
                        $aberturaYear = $edital->abertura() !== null
                            ? (int) $edital->abertura()->format('Y') : 0;
                        if ($criadoYear !== $ano && $aberturaYear !== $ano) {
                            continue;
                        }
                    }
                    $allRows[] = $this->editalToArray($edital);
                }
            }

            // Sort created_at DESC.
            usort($allRows, static function (array $a, array $b): int {
                return strcmp((string) ($b['criado_em'] ?? ''), (string) ($a['criado_em'] ?? ''));
            });

            $total = count($allRows);
            $offset = ($page - 1) * $perPage;
            $paginated = array_slice($allRows, $offset, $perPage);

            // Whitelist defensiva — Onda 10 audita.
            $safe = array_values(array_map(
                fn (array $r): array => self::pick($r, self::EDITAL_FIELDS),
                $paginated
            ));

            $format = strtolower((string) $this->param($request, 'format', 'json'));
            if ($format === 'csv') {
                return $this->csvFromRows($safe, self::EDITAL_FIELDS, 3600);
            }

            return $this->jsonResponse([
                'items'    => $safe,
                'total'    => $total,
                'page'     => $page,
                'per_page' => $perPage,
            ], 3600);
        } catch (Throwable $e) {
            return $this->handleThrowable($e);
        }
    }

    // ─── GET /lai/edital/{id}/categorias ───────────────────────────────────

    /**
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function editalCategorias(object $request)
    {
        try {
            $this->enforceRateLimit('lai_edital_categorias', 60, 60);

            $id = (int) $this->param($request, 'id', 0);
            if ($id <= 0) {
                throw RestException::notFound();
            }

            $edital = $this->editaisRepo->findById($id);
            if ($edital === null || !in_array($edital->status()->value(), self::STATUSES_LAI, true)) {
                throw RestException::notFound(self::tr('Edital não encontrado.'));
            }

            $cats = $this->categoriasRepo->findByEdital($id);
            $rows = array_map(
                static fn ($c): array => [
                    'id'                    => (int) $c->id(),
                    'nome'                  => (string) $c->nome(),
                    'descricao_md'          => $c->descricaoMd(),
                    'num_vagas'             => (int) $c->numVagas(),
                    'num_suplentes'         => (int) $c->numSuplentes(),
                    'tipos_agente_elegivel' => (string) $c->tiposAgenteElegivel(),
                    'criterios_md'          => $c->criteriosMd(),
                ],
                $cats
            );

            $safe = array_values(array_map(
                fn (array $r): array => self::pick($r, self::CATEGORIA_FIELDS),
                $rows
            ));

            $format = strtolower((string) $this->param($request, 'format', 'json'));
            if ($format === 'csv') {
                return $this->csvFromRows($safe, self::CATEGORIA_FIELDS, 3600);
            }

            return $this->jsonResponse(['items' => $safe, 'edital_id' => $id], 3600);
        } catch (Throwable $e) {
            return $this->handleThrowable($e);
        }
    }

    // ─── GET /lai/votacoes/resultados ──────────────────────────────────────

    /**
     * Resultados finais publicados de TODAS as votações apuradas/encerradas.
     *
     * Estrutura: edital → categorias → eleitos+suplentes.
     * NUNCA expõe votantes (eleitor_hash, agente_id).
     *
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function votacoesResultados(object $request)
    {
        try {
            $this->enforceRateLimit('lai_votacoes_resultados', 60, 60);

            $editaisEncerrados = [];
            foreach ([StatusEdital::VOTACAO_ENCERRADA, StatusEdital::ENCERRADO] as $statusValue) {
                try {
                    $statusVO = StatusEdital::fromString($statusValue);
                } catch (\InvalidArgumentException $e) {
                    continue;
                }
                foreach ($this->editaisRepo->findByStatus($statusVO, 1, 500) as $edital) {
                    $editaisEncerrados[] = $edital;
                }
            }

            $output = [];
            foreach ($editaisEncerrados as $edital) {
                $editalId = (int) ($edital->id() ?? 0);
                $votacao  = $this->votacoesRepo->findByEdital($editalId);
                if ($votacao === null) {
                    continue;
                }
                $resultados = $this->resultadosRepo->findByVotacao((int) ($votacao->id() ?? 0));
                if ($resultados === []) {
                    continue;
                }

                // Pre-load categorias para mapear id->nome.
                $cats = $this->categoriasRepo->findByEdital($editalId);
                $catNomeById = [];
                foreach ($cats as $c) {
                    $catNomeById[(int) $c->id()] = (string) $c->nome();
                }

                $categoriasOut = [];
                foreach ($resultados as $r) {
                    /** @var Resultado $r */
                    if (!$r->eleito() && !$r->suplente()) {
                        continue; // Só eleitos/suplentes na visão pública.
                    }
                    $catId   = $r->categoriaId();
                    $catNome = $catNomeById[$catId] ?? '';
                    $nomePub = $this->resolveNomePublico($r->candidatoInscricaoId());

                    $row = [
                        'numero_registro' => $nomePub['numero_registro'],
                        'nome_publico'    => $nomePub['nome_publico'],
                        'categoria_nome'  => $catNome,
                        'posicao'         => (int) $r->posicao(),
                        'total_votos'     => (int) $r->totalVotos(),
                        'eleito'          => (bool) $r->eleito(),
                        'suplente'        => (bool) $r->suplente(),
                    ];

                    if (!isset($categoriasOut[$catId])) {
                        $categoriasOut[$catId] = [
                            'categoria_nome' => $catNome,
                            'resultados'     => [],
                        ];
                    }
                    // Whitelist defensiva (mesmo o helper interno passa pelo filtro).
                    $categoriasOut[$catId]['resultados'][] = self::pick($row, self::RESULTADO_FIELDS);
                }

                $output[] = [
                    'edital_id'            => $editalId,
                    'edital_titulo'        => (string) $edital->titulo(),
                    'publicacao_resultado' => $edital->publicacaoResultado() !== null
                        ? $edital->publicacaoResultado()->format(\DateTimeInterface::ATOM)
                        : null,
                    'votacao_id'           => (int) ($votacao->id() ?? 0),
                    'categorias'           => array_values($categoriasOut),
                ];
            }

            $format = strtolower((string) $this->param($request, 'format', 'json'));
            if ($format === 'csv') {
                $flat = [];
                foreach ($output as $ed) {
                    foreach ($ed['categorias'] as $cat) {
                        foreach ($cat['resultados'] as $rr) {
                            $flat[] = array_merge(
                                ['edital_id' => $ed['edital_id'], 'edital_titulo' => $ed['edital_titulo']],
                                $rr
                            );
                        }
                    }
                }
                $cols = ['edital_id', 'edital_titulo', 'categoria_nome', 'numero_registro', 'nome_publico', 'posicao', 'total_votos', 'eleito', 'suplente'];
                return $this->csvFromRows($flat, $cols, 86400);
            }

            return $this->jsonResponse(['items' => $output], 86400);
        } catch (Throwable $e) {
            return $this->handleThrowable($e);
        }
    }

    // ─── GET /lai/normativos ───────────────────────────────────────────────

    /**
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function normativos(object $request)
    {
        unset($request);
        try {
            $this->enforceRateLimit('lai_normativos', 60, 60);

            $items = [
                [
                    'titulo'        => 'Portaria IBRAM nº 3.230/2024',
                    'ementa'        => 'Institui o Cadastro de Agentes Culturais Museológicos.',
                    'data'          => '2024-09-19',
                    'link_oficial'  => 'https://www.gov.br/museus/pt-br/acesso-a-informacao/legislacao/portarias',
                    'descricao_md'  => 'Regulamento administrativo do Cadastro e do CCDEM.',
                ],
                [
                    'titulo'        => 'Despacho IBRAM/DDFEM nº 98/2025',
                    'ementa'        => 'Fluxo operacional do cadastro, habilitação, votação e recursos.',
                    'data'          => '2025-02-27',
                    'link_oficial'  => 'https://www.gov.br/museus/pt-br/acesso-a-informacao/legislacao/despachos',
                    'descricao_md'  => 'Detalhamento dos prazos e etapas dos processos do CCDEM.',
                ],
                [
                    'titulo'        => 'Lei nº 13.709/2018 — LGPD',
                    'ementa'        => 'Lei Geral de Proteção de Dados Pessoais.',
                    'data'          => '2018-08-14',
                    'link_oficial'  => 'https://www.planalto.gov.br/ccivil_03/_ato2015-2018/2018/lei/l13709.htm',
                    'descricao_md'  => 'Base legal para tratamento de dados pessoais no Participe Ibram.',
                ],
                [
                    'titulo'        => 'Lei nº 12.527/2011 — LAI',
                    'ementa'        => 'Lei de Acesso à Informação Pública.',
                    'data'          => '2011-11-18',
                    'link_oficial'  => 'https://www.planalto.gov.br/ccivil_03/_ato2011-2014/2011/lei/l12527.htm',
                    'descricao_md'  => 'Base legal da transparência ativa e passiva expostas neste módulo.',
                ],
                [
                    'titulo'        => 'Decreto nº 8.124/2013',
                    'ementa'        => 'Regulamenta dispositivos da Lei 11.904/2009 (Estatuto de Museus).',
                    'data'          => '2013-10-17',
                    'link_oficial'  => 'https://www.planalto.gov.br/ccivil_03/_ato2011-2014/2013/decreto/d8124.htm',
                    'descricao_md'  => 'Disciplina museus federais; base legal para a representação setorial museológica.',
                ],
                [
                    'titulo'        => 'Decreto nº 8.750/2016',
                    'ementa'        => 'Cria o Conselho Nacional dos Povos e Comunidades Tradicionais (PCT).',
                    'data'          => '2016-05-09',
                    'link_oficial'  => 'https://www.planalto.gov.br/ccivil_03/_ato2015-2018/2016/decreto/d8750.htm',
                    'descricao_md'  => 'Reconhece PCT e fundamenta o tratamento diferenciado a esses agentes culturais.',
                ],
            ];

            return $this->jsonResponse(['items' => $items], 86400);
        } catch (Throwable $e) {
            return $this->handleThrowable($e);
        }
    }

    // ─── GET /lai/contatos ─────────────────────────────────────────────────

    /**
     * NUNCA expor endereço/CPF de servidor — apenas funções e canais oficiais.
     *
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function contatos(object $request)
    {
        unset($request);
        try {
            $this->enforceRateLimit('lai_contatos', 60, 60);

            $dpoEmail = function_exists('get_option') ? (string) \get_option('pi_dpo_email', '') : '';
            $dpoNome  = function_exists('get_option') ? (string) \get_option('pi_dpo_nome', '') : '';
            $dpoTel   = function_exists('get_option') ? (string) \get_option('pi_dpo_telefone', '') : '';

            $contatos = [
                'dpo' => [
                    'funcao'   => self::tr('Encarregado/a pelo Tratamento de Dados Pessoais (DPO)'),
                    'nome'     => $dpoNome,
                    'email'    => $dpoEmail !== '' ? $dpoEmail : 'encarregado@museus.gov.br',
                    'telefone' => $dpoTel,
                    'base_legal' => 'LGPD Art. 41',
                ],
                'cgsim' => [
                    'funcao'  => self::tr('Coordenação-Geral do Sistema Brasileiro de Museus (CGSIM)'),
                    'email'   => 'cgsim@museus.gov.br',
                    'unidade' => 'IBRAM/DDFEM/CGSIM',
                ],
                'esic' => [
                    'funcao' => self::tr('Serviço de Informação ao Cidadão (e-SIC) — LAI'),
                    'url'    => 'https://www.gov.br/acessoainformacao/pt-br',
                    'base_legal' => 'Lei 12.527/2011, Art. 9º',
                ],
                'ouvidoria' => [
                    'funcao' => self::tr('Ouvidoria do IBRAM'),
                    'url'    => 'https://www.gov.br/ouvidoria',
                ],
            ];

            return $this->jsonResponse($contatos, 86400);
        } catch (Throwable $e) {
            return $this->handleThrowable($e);
        }
    }

    // ─── GET /lai/dados-abertos.json ───────────────────────────────────────

    /**
     * Catalog único de datasets disponíveis (compatível com agregadores).
     *
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function dadosAbertosJson(object $request)
    {
        unset($request);
        try {
            $this->enforceRateLimit('lai_catalog', 60, 60);
            return $this->jsonResponse($this->buildCatalog(), 86400);
        } catch (Throwable $e) {
            return $this->handleThrowable($e);
        }
    }

    /**
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function dadosAbertosCsv(object $request)
    {
        unset($request);
        try {
            $this->enforceRateLimit('lai_catalog_csv', 60, 60);
            $catalog = $this->buildCatalog();
            $rows = [['id', 'title', 'endpoint', 'formats', 'license', 'updated_at']];
            foreach ($catalog['datasets'] as $ds) {
                $rows[] = [
                    (string) ($ds['id'] ?? ''),
                    (string) ($ds['title'] ?? ''),
                    (string) ($ds['endpoint'] ?? ''),
                    is_array($ds['formats'] ?? null) ? implode('|', $ds['formats']) : '',
                    (string) ($ds['license'] ?? ''),
                    (string) ($ds['updated_at'] ?? ''),
                ];
            }
            return $this->csvResponse($rows, 86400);
        } catch (Throwable $e) {
            return $this->handleThrowable($e);
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function buildCatalog(): array
    {
        $now = (new DateTimeImmutable('now'))->format(\DateTimeInterface::ATOM);
        return [
            'version'   => '1.0',
            'publisher' => 'IBRAM',
            'license'   => 'CC0-1.0',
            'datasets'  => [
                [
                    'id'         => 'cadastros',
                    'title'      => 'Estatísticas agregadas do Cadastro de Agentes Culturais',
                    'endpoint'   => '/wp-json/pi/v1/lai/cadastros',
                    'formats'    => ['json', 'csv'],
                    'license'    => 'CC0-1.0',
                    'updated_at' => $now,
                ],
                [
                    'id'         => 'editais',
                    'title'      => 'Editais publicados (todos)',
                    'endpoint'   => '/wp-json/pi/v1/lai/editais',
                    'formats'    => ['json', 'csv'],
                    'license'    => 'CC0-1.0',
                    'updated_at' => $now,
                ],
                [
                    'id'         => 'resultados',
                    'title'      => 'Resultados de votações encerradas',
                    'endpoint'   => '/wp-json/pi/v1/lai/votacoes/resultados',
                    'formats'    => ['json', 'csv'],
                    'license'    => 'CC0-1.0',
                    'updated_at' => $now,
                ],
                [
                    'id'         => 'normativos',
                    'title'      => 'Referências normativas',
                    'endpoint'   => '/wp-json/pi/v1/lai/normativos',
                    'formats'    => ['json'],
                    'license'    => 'CC0-1.0',
                    'updated_at' => $now,
                ],
                [
                    'id'         => 'contatos',
                    'title'      => 'Contatos institucionais',
                    'endpoint'   => '/wp-json/pi/v1/lai/contatos',
                    'formats'    => ['json'],
                    'license'    => 'CC0-1.0',
                    'updated_at' => $now,
                ],
            ],
        ];
    }

    // ─── Helpers ───────────────────────────────────────────────────────────

    /**
     * @param array<int,string>|array<string,mixed> $row
     * @return array{numero_registro:string,nome_publico:string}
     */
    private function resolveNomePublico(int $inscricaoId): array
    {
        if ($this->inscricaoNomePublicoProvider !== null) {
            $r = ($this->inscricaoNomePublicoProvider)($inscricaoId);
            return [
                'numero_registro' => isset($r['numero_registro']) ? (string) $r['numero_registro'] : '',
                'nome_publico'    => isset($r['nome_publico']) ? (string) $r['nome_publico'] : '',
            ];
        }
        return ['numero_registro' => '', 'nome_publico' => ''];
    }

    /**
     * @return array<string,mixed>
     */
    private function editalToArray(\Ibram\ParticipeIbram\Domain\Edital\Edital $e): array
    {
        $id = (int) ($e->id() ?? 0);
        $cats = $this->categoriasRepo->findByEdital($id);

        return [
            'id'                      => $id,
            'titulo'                  => (string) $e->titulo(),
            'descricao_md'            => $e->descricaoMd(),
            'status'                  => $e->status()->value(),
            'abertura'                => $e->abertura() !== null ? $e->abertura()->format(\DateTimeInterface::ATOM) : null,
            'encerramento_inscricoes' => $e->encerramentoInscricoes() !== null
                ? $e->encerramentoInscricoes()->format(\DateTimeInterface::ATOM) : null,
            'abertura_votacao'        => $e->aberturaVotacao() !== null
                ? $e->aberturaVotacao()->format(\DateTimeInterface::ATOM) : null,
            'encerramento_votacao'    => $e->encerramentoVotacao() !== null
                ? $e->encerramentoVotacao()->format(\DateTimeInterface::ATOM) : null,
            'publicacao_resultado'    => $e->publicacaoResultado() !== null
                ? $e->publicacaoResultado()->format(\DateTimeInterface::ATOM) : null,
            'num_categorias'          => count($cats),
            'criado_em'               => $e->createdAt()->format(\DateTimeInterface::ATOM),
            // criadoPor NUNCA exposto publicamente.
        ];
    }

    /**
     * Whitelist defensiva — só passa as chaves listadas (na ordem).
     *
     * @param array<string,mixed> $src
     * @param list<string>        $keys
     *
     * @return array<string,mixed>
     */
    private static function pick(array $src, array $keys): array
    {
        $out = [];
        foreach ($keys as $k) {
            $out[$k] = array_key_exists($k, $src) ? $src[$k] : null;
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $payload
     * @return \WP_REST_Response|array<string,mixed>
     */
    private function jsonResponse(array $payload, int $cacheSeconds)
    {
        $response = $this->ok($payload, 200, $cacheSeconds);
        $this->addLaiHeaders($response, 'application/json; charset=utf-8');
        return $response;
    }

    /**
     * @param list<list<string>> $rows  Primeira linha = header.
     * @return \WP_REST_Response|array<string,mixed>
     */
    private function csvResponse(array $rows, int $cacheSeconds)
    {
        $fp = fopen('php://temp', 'r+');
        if ($fp === false) {
            return $this->ok(['error' => 'csv_unavailable'], 500);
        }
        fwrite($fp, "\xEF\xBB\xBF"); // BOM UTF-8
        foreach ($rows as $row) {
            fputcsv($fp, array_map(static fn ($v): string => (string) $v, $row), ',', '"');
        }
        rewind($fp);
        $csv = stream_get_contents($fp) ?: '';
        fclose($fp);

        if (class_exists(\WP_REST_Response::class)) {
            $response = new \WP_REST_Response($csv, 200);
            $response->header('Content-Type', 'text/csv; charset=utf-8');
            $response->header('Cache-Control', 'public, max-age=' . max(1, $cacheSeconds));
            $this->addLaiHeaders($response, 'text/csv; charset=utf-8');
            return $response;
        }
        return ['data' => $csv, 'status' => 200];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string>              $cols
     */
    private function csvFromRows(array $rows, array $cols, int $cacheSeconds)
    {
        $out = [];
        $out[] = $cols;
        foreach ($rows as $row) {
            $line = [];
            foreach ($cols as $col) {
                $v = $row[$col] ?? '';
                if (is_bool($v)) {
                    $line[] = $v ? 'true' : 'false';
                } elseif (is_array($v)) {
                    $line[] = (string) json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                } else {
                    $line[] = (string) $v;
                }
            }
            $out[] = $line;
        }
        return $this->csvResponse($out, $cacheSeconds);
    }

    /**
     * @param mixed $response
     */
    private function addLaiHeaders($response, string $contentType): void
    {
        if (!$response instanceof \WP_REST_Response) {
            return;
        }
        $response->header('Content-Type', $contentType);
        $response->header('Access-Control-Allow-Origin', '*');
        $response->header('Access-Control-Allow-Methods', 'GET, OPTIONS');
        $response->header('Vary', 'Accept, Accept-Encoding');
        $response->header('X-Content-Type-Options', 'nosniff');
        $response->header('X-Robots-Tag', 'all');
    }

    /**
     * @param mixed $default
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

    private static function tr(string $text): string
    {
        return function_exists('__') ? (string) \__($text, 'participe-ibram') : $text;
    }

    private function prefix(): string
    {
        $w = $this->wpdb;
        if (is_object($w) && isset($w->prefix) && is_string($w->prefix)) {
            return $w->prefix;
        }
        return 'wp_';
    }

    private function safeCount(string $sql): int
    {
        $w = $this->wpdb;
        if (!is_object($w) || !method_exists($w, 'get_var')) {
            return 0;
        }
        try {
            $v = $w->get_var($sql);
            return is_numeric($v) ? (int) $v : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * @return array<int,array{rotulo:string,total:int}>
     */
    private function safeGroupedCount(string $sql): array
    {
        $w = $this->wpdb;
        if (!is_object($w) || !method_exists($w, 'get_results')) {
            return [];
        }
        try {
            $rows = $w->get_results($sql, ARRAY_A);
            if (!is_array($rows)) {
                return [];
            }
            $out = [];
            foreach ($rows as $r) {
                if (!is_array($r)) {
                    continue;
                }
                $out[] = [
                    'rotulo' => isset($r['rotulo']) ? (string) $r['rotulo'] : '',
                    'total'  => isset($r['total']) ? (int) $r['total'] : 0,
                ];
            }
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function safeRows(string $sql): array
    {
        $w = $this->wpdb;
        if (!is_object($w) || !method_exists($w, 'get_results')) {
            return [];
        }
        try {
            $rows = $w->get_results($sql, ARRAY_A);
            return is_array($rows) ? $rows : [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}
