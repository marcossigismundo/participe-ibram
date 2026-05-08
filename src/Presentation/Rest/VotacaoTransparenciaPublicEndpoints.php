<?php
/**
 * Endpoints REST públicos para transparência da votação (sem auth).
 *
 * Crítico:
 *  - Nenhum endpoint expõe `agente_id`, `user_id`, `ator_id`, `cpf`, `email`,
 *    `telefone`, `raca_cor`, `genero`, `orientacao_sexual`, `deficiencia` ou
 *    qualquer outro campo capaz de quebrar pseudonimização.
 *  - `audit-public` expõe APENAS `eleitor_hash` (já anonimizado por HMAC) e
 *    `ip_hash` (idem).
 *  - Whitelist defensiva campo a campo em ambos os endpoints.
 *  - Cache HTTP: 3600s para transparência, 300s para audit-public.
 *  - Rate limit: 30 req/min por IP/usuário.
 *
 * @package Ibram\ParticipeIbram\Presentation\Rest
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Rest;

use Ibram\ParticipeIbram\Domain\Votacao\StatusVotacao;
use Ibram\ParticipeIbram\Domain\Votacao\VotacaoNotFound;
use Ibram\ParticipeIbram\Domain\Votacao\VotacaoRepository;
use Ibram\ParticipeIbram\Domain\Votacao\VotoRepository;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbEditalRepository;
use Ibram\ParticipeIbram\Presentation\Admin\Support\VotacaoAuditQuery;
use Throwable;

/**
 * Endpoints registrados:
 *   GET /pi/v1/publico/votacao/{id}/transparencia   (cache 1h)
 *   GET /pi/v1/publico/votacao/{id}/audit-public    (cache 5min, paginado)
 *
 * Tie-break rule documentada (string fixa): vide `tie_break_rule()`.
 */
final class VotacaoTransparenciaPublicEndpoints
{
    use RestSupport;

    /** Whitelist (TRANSPARENCIA) — apenas estes campos saem. */
    private const TRANSP_FIELDS = [
        'votacao_id',
        'edital_id',
        'edital_titulo',
        'abertura',
        'encerramento',
        'status',
        'modo',
        'total_votos',
        'hash_pre_apuracao',
        'algoritmo',
        'metodologia_url',
        'apurado_em',
        'publicado_em',
        'tie_break_rule',
    ];

    /** Whitelist (AUDIT-PUBLIC) — apenas estes campos por evento. */
    private const AUDIT_FIELDS = [
        'ocorrido_em',
        'categoria_id',
        'eleitor_hash',
        'candidato_inscricao_id',
        'ip_hash',
    ];

    private VotacaoRepository $votacoesRepo;

    private VotoRepository $votosRepo;

    private WpdbEditalRepository $editaisRepo;

    private VotacaoAuditQuery $auditQuery;

    public function __construct(
        VotacaoRepository $votacoesRepo,
        VotoRepository $votosRepo,
        WpdbEditalRepository $editaisRepo,
        VotacaoAuditQuery $auditQuery
    ) {
        $this->votacoesRepo = $votacoesRepo;
        $this->votosRepo    = $votosRepo;
        $this->editaisRepo  = $editaisRepo;
        $this->auditQuery   = $auditQuery;
    }

    public function register(string $namespace): void
    {
        if (!function_exists('register_rest_route')) {
            return;
        }

        \register_rest_route($namespace, '/publico/votacao/(?P<id>\\d+)/transparencia', [
            'methods'             => 'GET',
            'callback'            => [$this, 'transparencia'],
            'permission_callback' => $this->permissionPublic(),
            'args'                => [
                'id' => [
                    'type'              => 'integer',
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        \register_rest_route($namespace, '/publico/votacao/(?P<id>\\d+)/audit-public', [
            'methods'             => 'GET',
            'callback'            => [$this, 'auditPublic'],
            'permission_callback' => $this->permissionPublic(),
            'args'                => [
                'id' => [
                    'type'              => 'integer',
                    'required'          => true,
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
                    'default'           => 100,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
    }

    /**
     * @param object $request WP_REST_Request
     *
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function transparencia(object $request)
    {
        try {
            $this->enforceRateLimit('publico_votacao_transparencia', 30, 60);

            $id = (int) $this->param($request, 'id', 0);
            if ($id <= 0) {
                throw RestException::notFound();
            }

            try {
                $votacao = $this->votacoesRepo->findById($id);
            } catch (VotacaoNotFound $e) {
                throw RestException::notFound(self::tr('Votação não encontrada.'));
            }

            $edital = $this->editaisRepo->findById($votacao->editalId());

            $isEncerrada = $votacao->status()->isEncerrada()
                || $votacao->status()->isApurada();

            // Total de votos só após encerramento.
            $totalVotos = $isEncerrada
                ? $this->votosRepo->contarTotalDaVotacao($id)
                : null;

            // hash_pre_apuracao só existe após `encerrar()`.
            $payload = [
                'votacao_id'        => (int) $votacao->id(),
                'edital_id'         => $votacao->editalId(),
                'edital_titulo'     => $edital !== null ? (string) $edital->titulo() : '',
                'abertura'          => $votacao->abertura()->format(\DateTimeInterface::ATOM),
                'encerramento'      => $votacao->encerramento()->format(\DateTimeInterface::ATOM),
                'status'            => $votacao->status()->value(),
                'modo'              => $votacao->modo()->value(),
                'total_votos'       => $totalVotos,
                'hash_pre_apuracao' => $votacao->hashPreApuracao(),
                'algoritmo'         => 'sha256',
                'metodologia_url'   => self::metodologiaUrl(),
                'apurado_em'        => $votacao->apuradoEm() !== null
                    ? $votacao->apuradoEm()->format(\DateTimeInterface::ATOM)
                    : null,
                'publicado_em'      => $edital !== null && $edital->publicacaoResultado() !== null
                    ? $edital->publicacaoResultado()->format(\DateTimeInterface::ATOM)
                    : null,
                'tie_break_rule'    => self::tieBreakRule(),
            ];

            // Whitelist defensiva.
            $safe = self::pick($payload, self::TRANSP_FIELDS);

            $response = $this->ok($safe, 200, 3600);
            $this->addVaryHeader($response);
            return $response;
        } catch (Throwable $e) {
            return $this->handleThrowable($e);
        }
    }

    /**
     * @param object $request WP_REST_Request
     *
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function auditPublic(object $request)
    {
        try {
            $this->enforceRateLimit('publico_votacao_audit', 30, 60);

            $id = (int) $this->param($request, 'id', 0);
            if ($id <= 0) {
                throw RestException::notFound();
            }

            try {
                $votacao = $this->votacoesRepo->findById($id);
            } catch (VotacaoNotFound $e) {
                throw RestException::notFound(self::tr('Votação não encontrada.'));
            }

            // Disponibilizar apenas após o encerramento — antes, divulgar
            // poderia incentivar coerção de eleitor.
            if (!($votacao->status()->isEncerrada() || $votacao->status()->isApurada())) {
                throw RestException::validation(
                    self::tr('Auditoria pública disponível apenas após o encerramento da votação.'),
                    ['status_atual' => $votacao->status()->value()]
                );
            }

            $page    = max(1, (int) $this->param($request, 'page', 1));
            $perPage = (int) $this->param($request, 'per_page', 100);
            if ($perPage < 1 || $perPage > 500) {
                $perPage = 100;
            }

            $offset = ($page - 1) * $perPage;
            $rows   = $this->auditQuery->listarVotos($id, $perPage, $offset);
            $total  = $this->votosRepo->contarTotalDaVotacao($id);

            // Whitelist defensiva crítica — Onda 10 audita esta linha.
            $items = array_values(array_map(
                static function (array $ev): array {
                    // Garantia de não-vazamento: somente estas chaves saem.
                    return [
                        'ocorrido_em'             => isset($ev['ocorrido_em']) ? (string) $ev['ocorrido_em'] : '',
                        'categoria_id'            => isset($ev['categoria_id']) ? (int) $ev['categoria_id'] : 0,
                        'eleitor_hash'            => isset($ev['eleitor_hash']) ? (string) $ev['eleitor_hash'] : '',
                        'candidato_inscricao_id'  => isset($ev['candidato_inscricao_id'])
                            ? (int) $ev['candidato_inscricao_id'] : 0,
                        'ip_hash'                 => isset($ev['ip_hash']) && $ev['ip_hash'] !== null
                            ? (string) $ev['ip_hash']
                            : null,
                    ];
                },
                $rows
            ));

            $response = $this->ok([
                'items'     => $items,
                'total'     => $total,
                'page'      => $page,
                'per_page'  => $perPage,
                'algoritmo' => 'sha256',
                'tie_break_rule' => self::tieBreakRule(),
            ], 200, 300);
            $this->addVaryHeader($response);
            return $response;
        } catch (Throwable $e) {
            return $this->handleThrowable($e);
        }
    }

    /**
     * Whitelist field pick — só passa as chaves listadas, na ordem.
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
            if (array_key_exists($k, $src)) {
                $out[$k] = $src[$k];
            }
        }
        return $out;
    }

    private static function tieBreakRule(): string
    {
        return function_exists('__')
            ? (string) \__('total_votos DESC, inscrito_em ASC, candidato_inscricao_id ASC', 'participe-ibram')
            : 'total_votos DESC, inscrito_em ASC, candidato_inscricao_id ASC';
    }

    private static function metodologiaUrl(): string
    {
        if (function_exists('apply_filters')) {
            $u = (string) \apply_filters('pi_votacao_metodologia_url', '');
            if ($u !== '') {
                return $u;
            }
        }
        if (function_exists('home_url')) {
            return (string) \home_url('/transparencia/metodologia');
        }
        return '/transparencia/metodologia';
    }

    private static function tr(string $text): string
    {
        return function_exists('__') ? (string) \__($text, 'participe-ibram') : $text;
    }

    /**
     * @param mixed $response
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
