<?php
/**
 * Endpoints REST do histórico pessoal do agente — aba "Histórico" de Minha Conta.
 *
 * Segurança crítica (W8-C):
 *  - Voto SECRETO: o histórico de votos mostra FATO (votou no edital X
 *    categoria Y em data Z) mas NUNCA o candidato escolhido (anti-coerção).
 *    Ver §7.2 do HANDOFF.md e TD-06 do ARCHITECTURE.md.
 *  - Ownership rigoroso em CADA endpoint: o `agente_id` é resolvido SEMPRE a
 *    partir de `get_current_user_id()`. Nenhum endpoint aceita `agente_id` ou
 *    `user_id` como parâmetro — usuário A NUNCA consegue ler histórico de B.
 *  - Auditoria pessoal usa descrição AMIGÁVEL (jamais `dados_antes` /
 *    `dados_depois` / `ip_hash` / `user_agent`).
 *  - Whitelist defensiva em CADA GET — campos são sempre listados
 *    explicitamente, nunca há propagação cega de colunas do banco.
 *  - Rate limit 30/min/user.
 *
 * @package Ibram\ParticipeIbram\Presentation\Rest
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Rest;

use Ibram\ParticipeIbram\Application\MinhaConta\AuditTrailPessoalQuery;
use Ibram\ParticipeIbram\Application\MinhaConta\ListarHistoricoVotosCommand;
use Ibram\ParticipeIbram\Application\MinhaConta\ListarHistoricoVotosHandler;
use Ibram\ParticipeIbram\Application\MinhaConta\RegerarReciboVotoCommand;
use Ibram\ParticipeIbram\Application\MinhaConta\RegerarReciboVotoHandler;
use Ibram\ParticipeIbram\Domain\Agente\AgenteRepository;
use Throwable;

/**
 * Endpoints autenticados em `pi/v1/me/historico/*`:
 *  - GET  /pi/v1/me/historico/cadastro
 *  - GET  /pi/v1/me/historico/inscricoes
 *  - GET  /pi/v1/me/historico/recursos
 *  - GET  /pi/v1/me/historico/votos                       (voto secreto)
 *  - POST /pi/v1/me/historico/votos/{votacao_id}/recibo   (regerar recibo)
 *  - GET  /pi/v1/me/historico/auditoria                   (audit trail pessoal)
 */
final class MinhaContaHistoricoEndpoints
{
    use RestSupport;

    private AgenteRepository $agentes;

    /** @var \wpdb */
    private $wpdb;

    private ListarHistoricoVotosHandler $listarVotos;

    private RegerarReciboVotoHandler $regerarRecibo;

    private AuditTrailPessoalQuery $auditTrail;

    private string $tabelaStatusHistorico;

    private string $tabelaInscricoes;

    private string $tabelaEditais;

    private string $tabelaCategorias;

    private string $tabelaRecursos;

    private string $tabelaRecursosInabilitacao;

    private string $tabelaAnalises;

    /**
     * @param \wpdb $wpdb
     */
    public function __construct(
        AgenteRepository $agentes,
        $wpdb,
        ListarHistoricoVotosHandler $listarVotos,
        RegerarReciboVotoHandler $regerarRecibo,
        AuditTrailPessoalQuery $auditTrail
    ) {
        $this->agentes       = $agentes;
        $this->wpdb          = $wpdb;
        $this->listarVotos   = $listarVotos;
        $this->regerarRecibo = $regerarRecibo;
        $this->auditTrail    = $auditTrail;

        $prefix = isset($wpdb->prefix) && is_string($wpdb->prefix) ? $wpdb->prefix : 'wp_';
        $this->tabelaStatusHistorico      = $prefix . 'pi_status_historico';
        $this->tabelaInscricoes           = $prefix . 'pi_inscricoes';
        $this->tabelaEditais              = $prefix . 'pi_editais';
        $this->tabelaCategorias           = $prefix . 'pi_edital_categorias';
        $this->tabelaRecursos             = $prefix . 'pi_recursos';
        $this->tabelaRecursosInabilitacao = $prefix . 'pi_recursos_inabilitacao';
        $this->tabelaAnalises             = $prefix . 'pi_analises';
    }

    public function register(string $namespace): void
    {
        if (!function_exists('register_rest_route')) {
            return;
        }

        \register_rest_route($namespace, '/me/historico/cadastro', [
            'methods'             => 'GET',
            'callback'            => [$this, 'historicoCadastro'],
            'permission_callback' => $this->permissionLoggedIn(),
        ]);

        \register_rest_route($namespace, '/me/historico/inscricoes', [
            'methods'             => 'GET',
            'callback'            => [$this, 'historicoInscricoes'],
            'permission_callback' => $this->permissionLoggedIn(),
            'args'                => [
                'page'     => ['type' => 'integer', 'required' => false, 'sanitize_callback' => 'absint'],
                'per_page' => ['type' => 'integer', 'required' => false, 'sanitize_callback' => 'absint'],
            ],
        ]);

        \register_rest_route($namespace, '/me/historico/recursos', [
            'methods'             => 'GET',
            'callback'            => [$this, 'historicoRecursos'],
            'permission_callback' => $this->permissionLoggedIn(),
        ]);

        \register_rest_route($namespace, '/me/historico/votos', [
            'methods'             => 'GET',
            'callback'            => [$this, 'historicoVotos'],
            'permission_callback' => $this->permissionLoggedIn(),
        ]);

        \register_rest_route($namespace, '/me/historico/votos/(?P<votacao_id>\\d+)/recibo', [
            'methods'             => 'POST',
            'callback'            => [$this, 'regerarRecibo'],
            'permission_callback' => $this->permissionLoggedIn(),
            'args'                => [
                'votacao_id' => [
                    'type'              => 'integer',
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        \register_rest_route($namespace, '/me/historico/auditoria', [
            'methods'             => 'GET',
            'callback'            => [$this, 'historicoAuditoria'],
            'permission_callback' => $this->permissionLoggedIn(),
            'args'                => [
                'page'     => ['type' => 'integer', 'required' => false, 'sanitize_callback' => 'absint'],
                'per_page' => ['type' => 'integer', 'required' => false, 'sanitize_callback' => 'absint'],
            ],
        ]);
    }

    // ------------------------------------------------------------------
    // Endpoints
    // ------------------------------------------------------------------

    /**
     * GET /me/historico/cadastro — transições de status do próprio cadastro.
     *
     * Whitelist: { status_anterior, status_novo, ocorrido_em, observacao }.
     * NÃO retorna `ator_id` (poderia revelar quem analisou — não é objetivo
     * da aba de minha conta).
     *
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function historicoCadastro(object $request)
    {
        unset($request);
        try {
            $this->enforceRateLimit('me_historico_cadastro', 30, 60);
            $agenteId = $this->ownershipAgenteId();

            // SELECT explícito — sem ator_id, sem id.
            $sql = $this->wpdb->prepare(
                "SELECT status_anterior, status_novo, ocorrido_em, observacao
                 FROM {$this->tabelaStatusHistorico}
                 WHERE agente_id = %d
                 ORDER BY ocorrido_em ASC, id ASC",
                $agenteId
            );
            $rows = $this->wpdb->get_results($sql, ARRAY_A);
            if (!is_array($rows)) {
                $rows = [];
            }

            $items = [];
            foreach ($rows as $row) {
                $items[] = [
                    'status_anterior' => (string) ($row['status_anterior'] ?? ''),
                    'status_novo'     => (string) ($row['status_novo'] ?? ''),
                    'ocorrido_em'     => (string) ($row['ocorrido_em'] ?? ''),
                    'observacao'      => isset($row['observacao']) && $row['observacao'] !== null
                        ? (string) $row['observacao']
                        : null,
                ];
            }

            return $this->ok(['items' => $items]);
        } catch (Throwable $e) {
            return $this->handleThrowable($e);
        }
    }

    /**
     * GET /me/historico/inscricoes — inscrições próprias com whitelist + nome de
     * categoria/edital.
     *
     * Whitelist: { id, edital_id, edital_titulo, categoria_nome, status,
     * inscrito_em, habilitado_em, inabilitado_em, motivo_inabilitacao_md }.
     *
     * `motivo_inabilitacao_md` só vai populado quando o status for final.
     *
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function historicoInscricoes(object $request)
    {
        try {
            $this->enforceRateLimit('me_historico_inscricoes', 30, 60);
            $agenteId = $this->ownershipAgenteId();

            [$page, $perPage] = $this->paginationParams($request);
            $offset           = ($page - 1) * $perPage;

            $sql = $this->wpdb->prepare(
                "SELECT i.id, i.edital_id, i.status,
                        i.inscrito_em, i.habilitado_em, i.inabilitado_em,
                        i.motivo_inabilitacao_md,
                        e.titulo AS edital_titulo,
                        c.nome AS categoria_nome
                 FROM {$this->tabelaInscricoes} i
                 LEFT JOIN {$this->tabelaEditais}    e ON e.id = i.edital_id
                 LEFT JOIN {$this->tabelaCategorias} c ON c.id = i.categoria_id
                 WHERE i.agente_id = %d
                 ORDER BY i.created_at DESC, i.id DESC
                 LIMIT %d OFFSET %d",
                $agenteId,
                $perPage,
                $offset
            );
            $rows = $this->wpdb->get_results($sql, ARRAY_A);
            if (!is_array($rows)) {
                $rows = [];
            }

            $finais = ['final_habilitado', 'final_inabilitado'];

            $items = [];
            foreach ($rows as $row) {
                $status      = (string) ($row['status'] ?? '');
                $isStatusFin = in_array($status, $finais, true);
                $items[]     = [
                    'id'                     => (int) ($row['id'] ?? 0),
                    'edital_id'              => (int) ($row['edital_id'] ?? 0),
                    'edital_titulo'          => (string) ($row['edital_titulo'] ?? ''),
                    'categoria_nome'         => (string) ($row['categoria_nome'] ?? ''),
                    'status'                 => $status,
                    'inscrito_em'            => self::nullableStr($row['inscrito_em'] ?? null),
                    'habilitado_em'          => self::nullableStr($row['habilitado_em'] ?? null),
                    'inabilitado_em'         => self::nullableStr($row['inabilitado_em'] ?? null),
                    // Motivo de inabilitação só vai exposto quando o status for FINAL —
                    // antes disso pode haver recurso pendente que muda a situação.
                    'motivo_inabilitacao_md' => $isStatusFin
                        ? (isset($row['motivo_inabilitacao_md']) && $row['motivo_inabilitacao_md'] !== null
                            ? (string) $row['motivo_inabilitacao_md']
                            : null)
                        : null,
                ];
            }

            return $this->ok([
                'items'    => $items,
                'page'     => $page,
                'per_page' => $perPage,
            ]);
        } catch (Throwable $e) {
            return $this->handleThrowable($e);
        }
    }

    /**
     * GET /me/historico/recursos — recursos de cadastro e de inabilitação.
     *
     * Whitelist por item: { id, tipo: cadastro|inabilitacao, fase,
     * protocolado_em, prazo_fim, decisao, decisao_md, decidido_em }.
     *
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function historicoRecursos(object $request)
    {
        unset($request);
        try {
            $this->enforceRateLimit('me_historico_recursos', 30, 60);
            $agenteId = $this->ownershipAgenteId();

            // 1) Recursos de cadastro — JOIN com analises pelo agente_id.
            $sqlCadastro = $this->wpdb->prepare(
                "SELECT r.id, r.fase, r.protocolado_em, r.prazo_fim,
                        r.decisao, r.decisao_md, r.decidido_em
                 FROM {$this->tabelaRecursos} r
                 INNER JOIN {$this->tabelaAnalises} a ON a.id = r.analise_id
                 WHERE a.agente_id = %d
                 ORDER BY r.protocolado_em DESC, r.id DESC",
                $agenteId
            );
            $rowsCadastro = $this->wpdb->get_results($sqlCadastro, ARRAY_A);
            if (!is_array($rowsCadastro)) {
                $rowsCadastro = [];
            }

            // 2) Recursos de inabilitação de inscrição — JOIN com inscricoes pelo agente_id.
            $sqlInab = $this->wpdb->prepare(
                "SELECT ri.id, ri.protocolado_em, ri.decisao, ri.decisao_md, ri.decidido_em
                 FROM {$this->tabelaRecursosInabilitacao} ri
                 INNER JOIN {$this->tabelaInscricoes} ins ON ins.id = ri.inscricao_id
                 WHERE ins.agente_id = %d
                 ORDER BY ri.protocolado_em DESC, ri.id DESC",
                $agenteId
            );
            $rowsInab = $this->wpdb->get_results($sqlInab, ARRAY_A);
            if (!is_array($rowsInab)) {
                $rowsInab = [];
            }

            $items = [];
            foreach ($rowsCadastro as $r) {
                $items[] = [
                    'id'             => (int) ($r['id'] ?? 0),
                    'tipo'           => 'cadastro',
                    'fase'           => (string) ($r['fase'] ?? ''),
                    'protocolado_em' => (string) ($r['protocolado_em'] ?? ''),
                    'prazo_fim'      => self::nullableStr($r['prazo_fim'] ?? null),
                    'decisao'        => self::nullableStr($r['decisao'] ?? null),
                    'decisao_md'     => self::nullableStr($r['decisao_md'] ?? null),
                    'decidido_em'    => self::nullableStr($r['decidido_em'] ?? null),
                ];
            }
            foreach ($rowsInab as $r) {
                $items[] = [
                    'id'             => (int) ($r['id'] ?? 0),
                    'tipo'           => 'inabilitacao',
                    'fase'           => 'unica',
                    'protocolado_em' => (string) ($r['protocolado_em'] ?? ''),
                    'prazo_fim'      => null,
                    'decisao'        => self::nullableStr($r['decisao'] ?? null),
                    'decisao_md'     => self::nullableStr($r['decisao_md'] ?? null),
                    'decidido_em'    => self::nullableStr($r['decidido_em'] ?? null),
                ];
            }

            // Ordena por protocolado_em desc.
            usort($items, static function (array $a, array $b): int {
                return strcmp((string) $b['protocolado_em'], (string) $a['protocolado_em']);
            });

            return $this->ok(['items' => $items]);
        } catch (Throwable $e) {
            return $this->handleThrowable($e);
        }
    }

    /**
     * GET /me/historico/votos — VOTO SECRETO.
     *
     * Mostra apenas FATO (votação, edital, categoria, data). NUNCA candidato.
     * Delegado para {@see ListarHistoricoVotosHandler}.
     *
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function historicoVotos(object $request)
    {
        unset($request);
        try {
            $this->enforceRateLimit('me_historico_votos', 30, 60);
            $agenteId = $this->ownershipAgenteId();

            $items = $this->listarVotos->handle(new ListarHistoricoVotosCommand($agenteId));

            // Whitelist final defensiva — mesmo que o handler vaze por bug,
            // aqui filtramos explicitamente os campos. SEM candidato_inscricao_id.
            $safe = [];
            foreach ($items as $it) {
                $safe[] = [
                    'votacao_id'         => (int) ($it['votacao_id'] ?? 0),
                    'edital_titulo'      => (string) ($it['edital_titulo'] ?? ''),
                    'categoria_nome'     => (string) ($it['categoria_nome'] ?? ''),
                    'votado_em'          => (string) ($it['votado_em'] ?? ''),
                    'recibo_recuperavel' => (bool) ($it['recibo_recuperavel'] ?? false),
                ];
            }

            $aviso = function_exists('__')
                ? \__('Seu voto é secreto. O sistema mostra apenas que você votou, não em quem.', 'participe-ibram')
                : 'Seu voto é secreto. O sistema mostra apenas que você votou, não em quem.';

            return $this->ok([
                'items'         => $safe,
                'aviso_secreto' => $aviso,
            ]);
        } catch (Throwable $e) {
            return $this->handleThrowable($e);
        }
    }

    /**
     * POST /me/historico/votos/{votacao_id}/recibo — regenera o hash do recibo.
     *
     * NÃO retorna candidato. Audita o re-acesso.
     *
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function regerarRecibo(object $request)
    {
        try {
            // Limite intencionalmente mais baixo (anti-abuso / forense).
            $this->enforceRateLimit('me_historico_recibo', 10, 60);
            $agenteId = $this->ownershipAgenteId();

            $votacaoId = (int) $this->paramFromRequest($request, 'votacao_id', 0);
            if ($votacaoId <= 0) {
                throw RestException::validation('votacao_id obrigatorio.');
            }

            $resultado = $this->regerarRecibo->handle(
                new RegerarReciboVotoCommand($agenteId, $votacaoId)
            );
            if ($resultado === null) {
                // Mensagem genérica — NUNCA diferencia "nao votou" de "votacao inexistente".
                throw RestException::notFound(
                    function_exists('__')
                        ? \__('Recibo não encontrado para esta votação.', 'participe-ibram')
                        : 'Recibo não encontrado.'
                );
            }

            return $this->ok([
                'hash_voto' => (string) ($resultado['hash_voto'] ?? ''),
                'votado_em' => (string) ($resultado['votado_em'] ?? ''),
            ]);
        } catch (Throwable $e) {
            return $this->handleThrowable($e);
        }
    }

    /**
     * GET /me/historico/auditoria — trilha pessoal com descrição amigável.
     *
     * Whitelist: { entidade, acao, ocorrido_em, descricao_amigavel }.
     * NUNCA retorna `dados_antes` / `dados_depois` / `ip_hash` / `user_agent` /
     * `ator_id`.
     *
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function historicoAuditoria(object $request)
    {
        try {
            $this->enforceRateLimit('me_historico_auditoria', 30, 60);
            $agenteId = $this->ownershipAgenteId();
            $userId   = (int) \get_current_user_id();

            [$page, $perPage] = $this->paginationParams($request);

            // IDs filhos (inscricoes, recursos, etc.) — busca mínima para
            // ampliar a janela do audit pessoal.
            $entidadeIds = $this->coletarEntidadeIdsDoAgente($agenteId);

            $resultado = $this->auditTrail->listar(
                $agenteId,
                $userId,
                $entidadeIds,
                $page,
                $perPage
            );

            // Whitelist final defensiva — re-filtra as chaves esperadas.
            $safeItems = [];
            foreach ($resultado['items'] ?? [] as $it) {
                $safeItems[] = [
                    'entidade'           => (string) ($it['entidade'] ?? ''),
                    'acao'               => (string) ($it['acao'] ?? ''),
                    'ocorrido_em'        => (string) ($it['ocorrido_em'] ?? ''),
                    'descricao_amigavel' => (string) ($it['descricao_amigavel'] ?? ''),
                ];
            }

            return $this->ok([
                'items'    => $safeItems,
                'total'    => (int) ($resultado['total'] ?? 0),
                'page'     => (int) ($resultado['page'] ?? $page),
                'per_page' => (int) ($resultado['per_page'] ?? $perPage),
            ]);
        } catch (Throwable $e) {
            return $this->handleThrowable($e);
        }
    }

    // ------------------------------------------------------------------
    // Ownership / helpers
    // ------------------------------------------------------------------

    /**
     * Resolve o `agente_id` do usuário autenticado.
     *
     * Padrão de ownership aplicado: **NUNCA** aceita `agente_id` por parâmetro —
     * o id sai sempre de `get_current_user_id()` + `AgenteRepository::findByUserId`.
     * Tentativa de um usuário ler histórico de outro (forjando query string)
     * é impossível porque o parâmetro não existe na assinatura.
     *
     * Compatível com o middleware `OwnershipResolver` de W8-A: quando esse
     * resolver evoluir para uma classe injetada, substitui-se a chamada interna
     * sem alterar a assinatura pública dos endpoints.
     *
     * @throws RestException 401/404 quando não há sessão ou agente associado.
     */
    private function ownershipAgenteId(): int
    {
        $userId = function_exists('get_current_user_id') ? (int) \get_current_user_id() : 0;
        if ($userId <= 0) {
            throw RestException::unauthorized();
        }
        $agente = $this->agentes->findByUserId($userId);
        if ($agente === null || $agente->getId() === null) {
            throw RestException::notFound(
                function_exists('__')
                    ? \__('Cadastro do agente não localizado.', 'participe-ibram')
                    : 'Cadastro do agente não localizado.'
            );
        }

        return (int) $agente->getId();
    }

    /**
     * Coleta os IDs filhos do agente (inscrições próprias, recursos próprios)
     * para ampliar a janela do audit pessoal sem expor entidades de terceiros.
     *
     * @return list<int>
     */
    private function coletarEntidadeIdsDoAgente(int $agenteId): array
    {
        $ids = [];

        // Inscrições próprias.
        $sqlIns = $this->wpdb->prepare(
            "SELECT id FROM {$this->tabelaInscricoes} WHERE agente_id = %d",
            $agenteId
        );
        $rows = $this->wpdb->get_col($sqlIns);
        if (is_array($rows)) {
            foreach ($rows as $r) {
                $ids[] = (int) $r;
            }
        }

        // Recursos de cadastro do agente (via analises).
        $sqlRec = $this->wpdb->prepare(
            "SELECT r.id
             FROM {$this->tabelaRecursos} r
             INNER JOIN {$this->tabelaAnalises} a ON a.id = r.analise_id
             WHERE a.agente_id = %d",
            $agenteId
        );
        $rowsRec = $this->wpdb->get_col($sqlRec);
        if (is_array($rowsRec)) {
            foreach ($rowsRec as $r) {
                $ids[] = (int) $r;
            }
        }

        // Recursos de inabilitação (via inscricoes).
        $sqlInab = $this->wpdb->prepare(
            "SELECT ri.id
             FROM {$this->tabelaRecursosInabilitacao} ri
             INNER JOIN {$this->tabelaInscricoes} ins ON ins.id = ri.inscricao_id
             WHERE ins.agente_id = %d",
            $agenteId
        );
        $rowsInab = $this->wpdb->get_col($sqlInab);
        if (is_array($rowsInab)) {
            foreach ($rowsInab as $r) {
                $ids[] = (int) $r;
            }
        }

        return array_values(array_unique(array_filter($ids, static fn (int $i): bool => $i > 0)));
    }

    /**
     * Extrai (page, per_page) do request com defaults seguros.
     *
     * @return array{0:int,1:int}
     */
    private function paginationParams(object $request): array
    {
        $page    = (int) $this->paramFromRequest($request, 'page', 1);
        $perPage = (int) $this->paramFromRequest($request, 'per_page', 20);
        $page    = max(1, $page);
        $perPage = max(1, min(100, $perPage));

        return [$page, $perPage];
    }

    /**
     * @param mixed $default
     *
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
        if (method_exists($request, 'get_url_params')) {
            $params = $request->get_url_params();
            if (is_array($params) && isset($params[$key])) {
                return $params[$key];
            }
        }
        if (method_exists($request, 'get_query_params')) {
            $params = $request->get_query_params();
            if (is_array($params) && isset($params[$key])) {
                return $params[$key];
            }
        }

        return $default;
    }

    /**
     * @param mixed $raw
     */
    private static function nullableStr($raw): ?string
    {
        if ($raw === null || $raw === '' || $raw === '0000-00-00 00:00:00') {
            return null;
        }

        return (string) $raw;
    }
}
