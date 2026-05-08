<?php
/**
 * Endpoints REST autenticados de Votação.
 *
 * Segurança crítica (TD-06, TD-14, TD-18):
 *  - Anti-rastreio rigoroso: NUNCA retorna `agente_id`, `user_id` ou
 *    `eleitor_hash` no response. NUNCA loga essa correlação no audit_log.
 *  - Atomicidade: UNIQUE(votacao_id, categoria_id, eleitor_hash) + check-then-act
 *    + rate limit por user (3/60s) + rate limit interno por eleitor_hash (no
 *    handler, 3/60s).
 *  - Constant-time: comparações de hash via {@see hash_equals} (em camadas de
 *    domínio); aqui só geramos recibos via sha256 sobre dados públicos.
 *  - Mensagens genéricas em violação UNIQUE (não revela `eleitor_hash`).
 *
 * @package Ibram\ParticipeIbram\Presentation\Rest
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Rest;

use DomainException;
use Ibram\ParticipeIbram\Application\Votacao\RegistrarVotoCommand;
use Ibram\ParticipeIbram\Application\Votacao\RegistrarVotoHandler;
use Ibram\ParticipeIbram\Application\Votacao\VerificarElegibilidadeHandler;
use Ibram\ParticipeIbram\Application\Votacao\VerificarElegibilidadeQuery;
use Ibram\ParticipeIbram\Domain\Votacao\EleitorInelegivel;
use Ibram\ParticipeIbram\Domain\Votacao\VotacaoNaoAberta;
use Ibram\ParticipeIbram\Domain\Votacao\VotacaoNotFound;
use Ibram\ParticipeIbram\Domain\Votacao\VotacaoRepository;
use Ibram\ParticipeIbram\Domain\Votacao\VotoDuplicado;
use Ibram\ParticipeIbram\Domain\Votacao\VotoRepository;
use Throwable;

/**
 * Endpoints autenticados em pi/v1/votacao:
 *  - POST /pi/v1/votacao/registrar               — registra voto.
 *  - GET  /pi/v1/votacao/{id}/elegibilidade      — verifica elegibilidade.
 *  - GET  /pi/v1/votacao/{id}/status             — status agregado (no PII).
 */
final class VotacaoEndpoints
{
    use RestSupport;

    private RegistrarVotoHandler $registrarHandler;
    private VerificarElegibilidadeHandler $elegibilidadeHandler;
    private VotacaoRepository $votacoesRepo;
    private VotoRepository $votosRepo;

    /**
     * Resolver cross-domain: WP user → agente_id.
     *
     * @var callable(int): (int|null)
     */
    private $agenteIdByUserResolver;

    /**
     * Provider opcional de total de eleitores aptos por votação (cross-domain).
     * Recebe `(int $votacaoId): int`. Quando null, retorna 0.
     *
     * @var callable(int): int|null
     */
    private $totalEleitoresAptosProvider;

    /**
     * @param callable(int): (int|null) $agenteIdByUserResolver
     * @param callable(int): int|null   $totalEleitoresAptosProvider
     */
    public function __construct(
        RegistrarVotoHandler $registrarHandler,
        VerificarElegibilidadeHandler $elegibilidadeHandler,
        VotacaoRepository $votacoesRepo,
        VotoRepository $votosRepo,
        callable $agenteIdByUserResolver,
        ?callable $totalEleitoresAptosProvider = null
    ) {
        $this->registrarHandler             = $registrarHandler;
        $this->elegibilidadeHandler         = $elegibilidadeHandler;
        $this->votacoesRepo                 = $votacoesRepo;
        $this->votosRepo                    = $votosRepo;
        $this->agenteIdByUserResolver       = $agenteIdByUserResolver;
        $this->totalEleitoresAptosProvider  = $totalEleitoresAptosProvider;
    }

    public function register(string $namespace): void
    {
        if (!function_exists('register_rest_route')) {
            return;
        }

        // POST /votacao/registrar
        \register_rest_route($namespace, '/votacao/registrar', [
            'methods'             => 'POST',
            'callback'            => [$this, 'registrarVoto'],
            'permission_callback' => $this->permissionLoggedIn(),
            'args'                => [
                'votacao_id' => [
                    'type'              => 'integer',
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                ],
                'categoria_id' => [
                    'type'              => 'integer',
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                ],
                'candidato_inscricao_id' => [
                    'type'              => 'integer',
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // GET /votacao/{id}/elegibilidade
        \register_rest_route($namespace, '/votacao/(?P<id>\\d+)/elegibilidade', [
            'methods'             => 'GET',
            'callback'            => [$this, 'verificarElegibilidade'],
            'permission_callback' => $this->permissionLoggedIn(),
            'args'                => [
                'id' => [
                    'type'              => 'integer',
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // GET /votacao/{id}/status
        \register_rest_route($namespace, '/votacao/(?P<id>\\d+)/status', [
            'methods'             => 'GET',
            'callback'            => [$this, 'statusVotacao'],
            'permission_callback' => $this->permissionLoggedIn(),
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
     * POST /votacao/registrar
     *
     * Body: {votacao_id, categoria_id, candidato_inscricao_id}
     *
     * Pipeline:
     *  1. Rate limit por user (3 req/60s) — defesa em profundidade.
     *  2. Lê body JSON; valida ints positivos.
     *  3. Resolve agente_id via gateway (cross-domain).
     *  4. Invoca {@see RegistrarVotoHandler} — TODO o domínio é validado lá.
     *  5. Captura exceções de domínio em códigos HTTP apropriados.
     *  6. Resposta 201 contém apenas o RECIBO (hash sha256 público dos dados não-PII).
     *
     * O recibo `hash_voto` é determinístico mas NÃO permite reverter para
     * `agente_id` — combina dados PÚBLICOS (votacao_id, categoria_id,
     * candidato_inscricao_id, votado_em). É apenas prova de que o voto foi
     * gravado naquele instante; o eleitor pode armazenar para um eventual
     * questionamento futuro.
     *
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function registrarVoto(object $request)
    {
        try {
            // 1. Rate limit por usuário — UNIQUE constraint é o gatekeeper final,
            // mas aqui defendemos contra flood antes de chegar no banco.
            $this->enforceRateLimit('pi_voto', 3, 60);

            $userId = $this->currentUserId();
            if ($userId <= 0) {
                throw RestException::unauthorized();
            }

            // 2. Lê body JSON.
            $body                  = $this->readJsonBody($request);
            $votacaoId             = (int) ($body['votacao_id'] ?? $this->paramFromRequest($request, 'votacao_id', 0));
            $categoriaId           = (int) ($body['categoria_id'] ?? $this->paramFromRequest($request, 'categoria_id', 0));
            $candidatoInscricaoId  = (int) ($body['candidato_inscricao_id'] ?? $this->paramFromRequest($request, 'candidato_inscricao_id', 0));

            if ($votacaoId <= 0 || $categoriaId <= 0 || $candidatoInscricaoId <= 0) {
                throw RestException::validation(
                    function_exists('__')
                        ? \__('votacao_id, categoria_id e candidato_inscricao_id são obrigatórios.', 'participe-ibram')
                        : 'Parâmetros obrigatórios ausentes.'
                );
            }

            // 3. Resolve agente_id (NUNCA loga ou retorna).
            $resolver = $this->agenteIdByUserResolver;
            $agenteId = $resolver($userId);
            if (!is_int($agenteId) || $agenteId <= 0) {
                throw RestException::forbidden(
                    function_exists('__')
                        ? \__('Nenhum agente associado ao usuário logado.', 'participe-ibram')
                        : 'Sem agente associado.'
                );
            }

            // 4. Invoca handler de Application.
            $command = new RegistrarVotoCommand(
                $votacaoId,
                $categoriaId,
                $agenteId,
                $candidatoInscricaoId
            );
            $voto = $this->registrarHandler->handle($command);

            // 6. Recibo. Combina apenas dados que JÁ ESTÃO no banco (e portanto
            // já são reveláveis a quem tem acesso à urna). NÃO inclui agente_id
            // nem eleitor_hash. O timestamp é o gravado pelo handler.
            $recibo = hash(
                'sha256',
                sprintf(
                    '%d|%d|%d|%s',
                    $votacaoId,
                    $categoriaId,
                    $candidatoInscricaoId,
                    $voto->votadoEm()->format('Y-m-d H:i:s.u')
                )
            );

            // Resposta 201. Sem agente_id, sem eleitor_hash, sem voto_id.
            return $this->ok([
                'votacao_id'   => $votacaoId,
                'categoria_id' => $categoriaId,
                'registrado_em' => $voto->votadoEm()->format(\DateTimeInterface::ATOM),
                'hash_voto'    => $recibo,
            ], 201, 0);
        } catch (RestException $e) {
            return $e->toResponse();
        } catch (VotoDuplicado $e) {
            // 409 — mensagem genérica, NÃO inclui eleitor_hash.
            return RestException::conflict(
                function_exists('__')
                    ? \__('Você já registrou voto nesta categoria desta votação.', 'participe-ibram')
                    : 'Voto já registrado nesta categoria.'
            )->toResponse();
        } catch (VotacaoNaoAberta $e) {
            // 410 Gone — votação encerrada/agendada/fora-da-janela.
            return (new RestException(
                function_exists('__')
                    ? \__('Esta votação não está aberta no momento.', 'participe-ibram')
                    : 'Votação não aberta.',
                'pi_votacao_nao_aberta',
                410
            ))->toResponse();
        } catch (EleitorInelegivel $e) {
            // 403 — mensagem genérica.
            return RestException::forbidden(
                function_exists('__')
                    ? \__('Você não está habilitado a votar nesta categoria.', 'participe-ibram')
                    : 'Inelegível.'
            )->toResponse();
        } catch (VotacaoNotFound $e) {
            return RestException::notFound(
                function_exists('__') ? \__('Votação não encontrada.', 'participe-ibram') : 'Votação não encontrada.'
            )->toResponse();
        } catch (DomainException $e) {
            // Defesa em profundidade — UNIQUE constraint pode emergir como DomainException.
            $msg = $e->getMessage();
            if (
                stripos($msg, 'duplicado') !== false
                || stripos($msg, '1062') !== false
                || stripos($msg, 'duplicate entry') !== false
            ) {
                return RestException::conflict(
                    function_exists('__')
                        ? \__('Você já registrou voto nesta categoria desta votação.', 'participe-ibram')
                        : 'Voto já registrado.'
                )->toResponse();
            }
            return RestException::validation($msg)->toResponse();
        } catch (Throwable $e) {
            // Defesa em profundidade — UNIQUE constraint MySQL 1062 pode escapar.
            if (strpos($e->getMessage(), '1062') !== false) {
                return RestException::conflict(
                    function_exists('__')
                        ? \__('Você já registrou voto nesta categoria desta votação.', 'participe-ibram')
                        : 'Voto já registrado.'
                )->toResponse();
            }
            return $this->handleThrowable($e);
        }
    }

    /**
     * GET /votacao/{id}/elegibilidade
     *
     * Retorna `{elegivel, motivo?, votacao_status, categorias_elegiveis: [{id, nome, ja_votou}]}`.
     *
     * `agente_id` JAMAIS aparece no response (validado em testes).
     *
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function verificarElegibilidade(object $request)
    {
        try {
            $this->enforceRateLimit('pi_voto_elegibilidade', 30, 60);

            $userId = $this->currentUserId();
            if ($userId <= 0) {
                throw RestException::unauthorized();
            }

            $votacaoId = (int) $this->paramFromRequest($request, 'id', 0);
            if ($votacaoId <= 0) {
                throw RestException::notFound();
            }

            $query = new VerificarElegibilidadeQuery($userId, $votacaoId);
            $result = $this->elegibilidadeHandler->handle($query);

            // Defesa em profundidade — strip de chaves "perigosas" mesmo que
            // surjam por engano em refator futuro.
            $safeCategorias = [];
            $list = $result['categorias_elegiveis'] ?? [];
            if (is_array($list)) {
                foreach ($list as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $safeCategorias[] = [
                        'id'       => isset($row['id']) ? (int) $row['id'] : 0,
                        'nome'     => isset($row['nome']) ? (string) $row['nome'] : '',
                        'ja_votou' => isset($row['ja_votou']) ? (bool) $row['ja_votou'] : false,
                    ];
                }
            }

            return $this->ok([
                'elegivel'             => (bool) ($result['elegivel'] ?? false),
                'motivo'               => isset($result['motivo']) ? $result['motivo'] : null,
                'votacao_status'       => isset($result['votacao_status']) ? $result['votacao_status'] : null,
                'categorias_elegiveis' => $safeCategorias,
            ], 200, 0);
        } catch (RestException $e) {
            return $e->toResponse();
        } catch (Throwable $e) {
            return $this->handleThrowable($e);
        }
    }

    /**
     * GET /votacao/{id}/status
     *
     * Retorna status agregado da votação. SEM PII. Cache 30s.
     *
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function statusVotacao(object $request)
    {
        try {
            $this->enforceRateLimit('pi_voto_status', 60, 60);

            $userId = $this->currentUserId();
            if ($userId <= 0) {
                throw RestException::unauthorized();
            }

            $votacaoId = (int) $this->paramFromRequest($request, 'id', 0);
            if ($votacaoId <= 0) {
                throw RestException::notFound();
            }

            $votacao = $this->votacoesRepo->findById($votacaoId);

            $totalEleitores = 0;
            if ($this->totalEleitoresAptosProvider !== null) {
                $provider       = $this->totalEleitoresAptosProvider;
                $totalEleitores = (int) $provider($votacaoId);
            }

            $totalVotos = $this->votosRepo->contarTotalDaVotacao($votacaoId);

            return $this->ok([
                'votacao_id'                 => $votacaoId,
                'status'                     => $votacao->status()->value(),
                'modo'                       => $votacao->modo()->value(),
                'abertura'                   => $votacao->abertura()->format(\DateTimeInterface::ATOM),
                'encerramento'               => $votacao->encerramento()->format(\DateTimeInterface::ATOM),
                'total_eleitores_aptos'      => $totalEleitores,
                'total_votos_registrados'    => $totalVotos,
            ], 200, 30);
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

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function currentUserId(): int
    {
        return function_exists('get_current_user_id') ? (int) \get_current_user_id() : 0;
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
