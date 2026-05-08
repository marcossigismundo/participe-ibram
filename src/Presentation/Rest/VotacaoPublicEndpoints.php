<?php
/**
 * Endpoints REST públicos de Votação (auditoria pública + resultados).
 *
 * Crítico (TD-06, TD-14):
 *  - Antes do encerramento, NUNCA expõe `total_votos` nem `hash_pre_apuracao`.
 *  - Após encerramento, `hash_pre_apuracao` é IMUTÁVEL (lido de wp_options
 *    publicado por {@see EncerrarVotacaoHandler}). Auditores podem recalcular
 *    via {@see VotoRepository::gerarHashPreApuracao()} e comparar com
 *    {@see hash_equals()}.
 *  - Após publicação do resultado, lista candidatos eleitos/suplentes —
 *    NUNCA lista de eleitores.
 *  - Whitelist defensiva por endpoint (replicar padrão `EditalPublicEndpoints`).
 *
 * @package Ibram\ParticipeIbram\Presentation\Rest
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Rest;

use Ibram\ParticipeIbram\Domain\Votacao\ResultadoRepository;
use Ibram\ParticipeIbram\Domain\Votacao\StatusVotacao;
use Ibram\ParticipeIbram\Domain\Votacao\VotacaoNotFound;
use Ibram\ParticipeIbram\Domain\Votacao\VotacaoRepository;
use Ibram\ParticipeIbram\Domain\Votacao\VotoRepository;
use Throwable;

/**
 * Endpoints públicos em pi/v1/publico/votacao:
 *  - GET /pi/v1/publico/votacao/{id}              — detalhe público.
 *  - GET /pi/v1/publico/votacao/{id}/auditoria    — hash + metodologia.
 *  - GET /pi/v1/publico/votacao/{id}/resultado    — após apuração.
 */
final class VotacaoPublicEndpoints
{
    use RestSupport;

    /**
     * URL da página de transparência publicada por W6-C.
     * Pode ser sobrescrito via filtro `pi_votacao_metodologia_url`.
     */
    private const METODOLOGIA_URL_DEFAULT = '/transparencia/votacao/metodologia';

    private VotacaoRepository $votacoesRepo;
    private VotoRepository $votosRepo;
    private ResultadoRepository $resultadosRepo;

    /**
     * Provider opcional de metadados públicos do candidato.
     * Recebe `(int $candidatoInscricaoId): array{numero_registro:string,nome_publico:string}|null`.
     *
     * @var callable(int): (array<string,string>|null)|null
     */
    private $candidatoPublicoProvider;

    /**
     * Provider opcional de nome humano da categoria.
     * Recebe `(int $categoriaId): string|null`.
     *
     * @var callable(int): (string|null)|null
     */
    private $categoriaNomeProvider;

    /**
     * @param callable(int): (array<string,string>|null)|null $candidatoPublicoProvider
     * @param callable(int): (string|null)|null               $categoriaNomeProvider
     */
    public function __construct(
        VotacaoRepository $votacoesRepo,
        VotoRepository $votosRepo,
        ResultadoRepository $resultadosRepo,
        ?callable $candidatoPublicoProvider = null,
        ?callable $categoriaNomeProvider = null
    ) {
        $this->votacoesRepo            = $votacoesRepo;
        $this->votosRepo               = $votosRepo;
        $this->resultadosRepo          = $resultadosRepo;
        $this->candidatoPublicoProvider = $candidatoPublicoProvider;
        $this->categoriaNomeProvider   = $categoriaNomeProvider;
    }

    public function register(string $namespace): void
    {
        if (!function_exists('register_rest_route')) {
            return;
        }

        \register_rest_route($namespace, '/publico/votacao/(?P<id>\\d+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'detalhePublico'],
            'permission_callback' => $this->permissionPublic(),
            'args'                => [
                'id' => [
                    'type'              => 'integer',
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        \register_rest_route($namespace, '/publico/votacao/(?P<id>\\d+)/auditoria', [
            'methods'             => 'GET',
            'callback'            => [$this, 'auditoriaPublica'],
            'permission_callback' => $this->permissionPublic(),
            'args'                => [
                'id' => [
                    'type'              => 'integer',
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        \register_rest_route($namespace, '/publico/votacao/(?P<id>\\d+)/resultado', [
            'methods'             => 'GET',
            'callback'            => [$this, 'resultadoPublico'],
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
     * GET /publico/votacao/{id}
     *
     * Whitelist: id, edital_id, abertura, encerramento, status, modo,
     * hash_pre_apuracao (apenas após encerramento), total_votos (apenas após
     * encerramento). NUNCA total de votos antes do encerramento (poderia
     * sinalizar tendência sem auditoria do hash).
     *
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function detalhePublico(object $request)
    {
        try {
            $this->enforceRateLimit('publico_votacao_detalhe', 60, 60);

            $id = (int) $this->paramFromRequest($request, 'id', 0);
            if ($id <= 0) {
                throw RestException::notFound();
            }

            $votacao = $this->votacoesRepo->findById($id);
            $status  = $votacao->status()->value();

            // Inclui hash + total apenas após encerrada/apurada.
            $exporHash = in_array($status, [StatusVotacao::ENCERRADA, StatusVotacao::APURADA], true);

            $payload = [
                'id'                => (int) $votacao->id(),
                'edital_id'         => $votacao->editalId(),
                'status'            => $status,
                'modo'              => $votacao->modo()->value(),
                'abertura'          => $votacao->abertura()->format(\DateTimeInterface::ATOM),
                'encerramento'      => $votacao->encerramento()->format(\DateTimeInterface::ATOM),
                'hash_pre_apuracao' => $exporHash ? $votacao->hashPreApuracao() : null,
                'total_votos'       => $exporHash ? $this->votosRepo->contarTotalDaVotacao($id) : null,
                'apurado_em'        => $votacao->apuradoEm() !== null
                    ? $votacao->apuradoEm()->format(\DateTimeInterface::ATOM)
                    : null,
            ];

            $response = $this->ok($payload, 200, $exporHash ? 3600 : 60);
            if ($response instanceof \WP_REST_Response) {
                $response->header('Vary', 'Accept');
            }

            return $response;
        } catch (VotacaoNotFound $e) {
            return RestException::notFound(
                function_exists('__') ? \__('Votação não encontrada.', 'participe-ibram') : 'Votação não encontrada.'
            )->toResponse();
        } catch (Throwable $e) {
            return $this->handleThrowable($e);
        }
    }

    /**
     * GET /publico/votacao/{id}/auditoria
     *
     * Para verificação de terceiros. Retorna hash imutável publicado em
     * {@see EncerrarVotacaoHandler} via `wp_options['pi_votacao_{id}_hash']`,
     * mais a metodologia. Auditores podem recalcular o hash a partir dos votos
     * disponíveis (consulta SQL pública via DBA) e usar `hash_equals()`.
     *
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function auditoriaPublica(object $request)
    {
        try {
            $this->enforceRateLimit('publico_votacao_auditoria', 60, 60);

            $id = (int) $this->paramFromRequest($request, 'id', 0);
            if ($id <= 0) {
                throw RestException::notFound();
            }

            $votacao = $this->votacoesRepo->findById($id);
            $status  = $votacao->status()->value();

            // Auditoria só faz sentido após encerrada.
            $disponivel = in_array($status, [StatusVotacao::ENCERRADA, StatusVotacao::APURADA], true);

            $hash       = null;
            $totalVotos = null;
            $calculadoEm = null;

            if ($disponivel) {
                // Hash imutável publicado em wp_options.
                $option = function_exists('get_option')
                    ? \get_option('pi_votacao_' . $id . '_hash', null)
                    : null;
                if (is_array($option)) {
                    $hash       = isset($option['hash_pre_apuracao']) ? (string) $option['hash_pre_apuracao'] : null;
                    $totalVotos = isset($option['total_votos']) ? (int) $option['total_votos'] : null;
                    $calculadoEm = isset($option['calculado_em']) ? (string) $option['calculado_em'] : null;
                }

                // Fallback: hash do agregado se o option ainda não foi gravado.
                if ($hash === null && $votacao->hashPreApuracao() !== null) {
                    $hash       = $votacao->hashPreApuracao();
                    $totalVotos = $this->votosRepo->contarTotalDaVotacao($id);
                }
            }

            $metodologiaUrl = self::METODOLOGIA_URL_DEFAULT;
            if (function_exists('apply_filters')) {
                $filtered = \apply_filters('pi_votacao_metodologia_url', $metodologiaUrl, $id);
                if (is_string($filtered) && $filtered !== '') {
                    $metodologiaUrl = $filtered;
                }
            }

            $payload = [
                'votacao_id'        => $id,
                'status'            => $status,
                'disponivel'        => $disponivel,
                'hash_pre_apuracao' => $hash,
                'algoritmo'         => 'sha256(categoria_id|eleitor_hash|candidato_inscricao_id|votado_em ordenado asc, separado por \\n)',
                'total_votos'       => $totalVotos,
                'calculado_em'      => $calculadoEm,
                'apurado_em'        => $votacao->apuradoEm() !== null
                    ? $votacao->apuradoEm()->format(\DateTimeInterface::ATOM)
                    : null,
                'metodologia_url'   => $metodologiaUrl,
            ];

            $response = $this->ok($payload, 200, $disponivel ? 3600 : 60);
            if ($response instanceof \WP_REST_Response) {
                $response->header('Vary', 'Accept');
            }

            return $response;
        } catch (VotacaoNotFound $e) {
            return RestException::notFound(
                function_exists('__') ? \__('Votação não encontrada.', 'participe-ibram') : 'Votação não encontrada.'
            )->toResponse();
        } catch (Throwable $e) {
            return $this->handleThrowable($e);
        }
    }

    /**
     * GET /publico/votacao/{id}/resultado
     *
     * Apenas disponível após `apurada` e publicação do resultado.
     * NUNCA lista de eleitores. Cache 1h.
     *
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function resultadoPublico(object $request)
    {
        try {
            $this->enforceRateLimit('publico_votacao_resultado', 60, 60);

            $id = (int) $this->paramFromRequest($request, 'id', 0);
            if ($id <= 0) {
                throw RestException::notFound();
            }

            $votacao = $this->votacoesRepo->findById($id);
            if (!$votacao->status()->isApurada()) {
                throw new RestException(
                    function_exists('__')
                        ? \__('Resultado ainda não publicado.', 'participe-ibram')
                        : 'Resultado nao disponivel.',
                    'pi_resultado_indisponivel',
                    409
                );
            }

            $resultados = $this->resultadosRepo->findByVotacao($id);

            // Agrupa por categoria e separa eleitos/suplentes.
            /** @var array<int, array{eleitos: list<array<string,mixed>>, suplentes: list<array<string,mixed>>}> $porCategoria */
            $porCategoria = [];
            foreach ($resultados as $r) {
                $catId = $r->categoriaId();
                if (!isset($porCategoria[$catId])) {
                    $porCategoria[$catId] = ['eleitos' => [], 'suplentes' => []];
                }
                $candidato   = $r->candidatoInscricaoId();
                $publico     = $this->resolveCandidatoPublico($candidato);
                $itemSeguro  = [
                    'numero_registro'        => $publico['numero_registro'],
                    'nome_publico'           => $publico['nome_publico'],
                    'candidato_inscricao_id' => $candidato,
                    'total_votos'            => $r->totalVotos(),
                    'posicao'                => $r->posicao(),
                ];

                if ($r->eleito()) {
                    $porCategoria[$catId]['eleitos'][] = $itemSeguro;
                } elseif ($r->suplente()) {
                    $porCategoria[$catId]['suplentes'][] = $itemSeguro;
                }
            }

            $items = [];
            foreach ($porCategoria as $catId => $grupos) {
                $items[] = [
                    'categoria_id'   => (int) $catId,
                    'categoria_nome' => $this->resolveCategoriaNome((int) $catId),
                    'eleitos'        => $grupos['eleitos'],
                    'suplentes'      => $grupos['suplentes'],
                ];
            }

            $response = $this->ok([
                'votacao_id' => $id,
                'apurado_em' => $votacao->apuradoEm() !== null
                    ? $votacao->apuradoEm()->format(\DateTimeInterface::ATOM)
                    : null,
                'categorias' => $items,
            ], 200, 3600);

            if ($response instanceof \WP_REST_Response) {
                $response->header('Vary', 'Accept');
            }

            return $response;
        } catch (RestException $e) {
            return $e->toResponse();
        } catch (VotacaoNotFound $e) {
            return RestException::notFound(
                function_exists('__') ? \__('Votação não encontrada.', 'participe-ibram') : 'Votação não encontrada.'
            )->toResponse();
        } catch (Throwable $e) {
            return $this->handleThrowable($e);
        }
    }

    /**
     * @return array{numero_registro:string,nome_publico:string}
     */
    private function resolveCandidatoPublico(int $candidatoInscricaoId): array
    {
        $default = ['numero_registro' => '', 'nome_publico' => ''];
        if ($this->candidatoPublicoProvider === null) {
            return $default;
        }
        $provider = $this->candidatoPublicoProvider;
        $raw      = $provider($candidatoInscricaoId);
        if (!is_array($raw)) {
            return $default;
        }

        return [
            'numero_registro' => isset($raw['numero_registro']) ? (string) $raw['numero_registro'] : '',
            'nome_publico'    => isset($raw['nome_publico']) ? (string) $raw['nome_publico'] : '',
        ];
    }

    private function resolveCategoriaNome(int $categoriaId): string
    {
        if ($this->categoriaNomeProvider === null) {
            return '';
        }
        $provider = $this->categoriaNomeProvider;
        $nome     = $provider($categoriaId);

        return is_string($nome) ? $nome : '';
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

        return $default;
    }
}
