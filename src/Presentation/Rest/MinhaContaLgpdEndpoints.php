<?php
/**
 * Endpoints REST self-service do titular (LGPD) — Wave 8 (W8-B).
 *
 * @package Ibram\ParticipeIbram\Presentation\Rest
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Rest;

use DateTimeImmutable;
use Ibram\ParticipeIbram\Application\Consentimento\RegistrarConsentimentoCommand;
use Ibram\ParticipeIbram\Application\Consentimento\RegistrarConsentimentoHandler;
use Ibram\ParticipeIbram\Application\Consentimento\RevogarConsentimentoHandler;
use Ibram\ParticipeIbram\Application\Lgpd\ConfirmarAnonimizacaoCommand;
use Ibram\ParticipeIbram\Application\Lgpd\ConfirmarAnonimizacaoHandler;
use Ibram\ParticipeIbram\Application\Lgpd\SolicitarAnonimizacaoCommand;
use Ibram\ParticipeIbram\Application\Lgpd\SolicitarAnonimizacaoHandler;
use Ibram\ParticipeIbram\Application\Lgpd\SolicitarExportDadosCommand;
use Ibram\ParticipeIbram\Application\Lgpd\SolicitarExportDadosHandler;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Core\Network\IpResolver;
use Ibram\ParticipeIbram\Domain\Consentimento\ConsentimentoRepository;
use Ibram\ParticipeIbram\Domain\Consentimento\Finalidade;
use Ibram\ParticipeIbram\Domain\Consentimento\SolicitacaoTitular;
use Ibram\ParticipeIbram\Domain\Consentimento\SolicitacaoTitularRepository;
use Ibram\ParticipeIbram\Domain\Consentimento\TermoRepository;
use Ibram\ParticipeIbram\Presentation\Public\MinhaConta\OwnershipDeniedException;
use Ibram\ParticipeIbram\Presentation\Public\MinhaConta\OwnershipResolver;
use Throwable;

/**
 * Wave 8 (W8-B) — endpoints "me/lgpd" autenticados:
 *
 *  - GET  /pi/v1/me/consentimentos
 *  - GET  /pi/v1/me/consentimentos/historico
 *  - POST /pi/v1/me/consentimentos/{finalidade}/revogar
 *  - POST /pi/v1/me/consentimentos/{finalidade}/reaceitar
 *  - GET  /pi/v1/me/solicitacoes
 *  - POST /pi/v1/me/solicitacoes
 *  - GET  /pi/v1/me/solicitacoes/{id}
 *  - POST /pi/v1/me/exportar-dados
 *  - POST /pi/v1/me/solicitar-anonimizacao
 *  - GET  /pi/v1/me/anonimizacao-confirmar  (recebe token)
 *
 * Política comum a todos:
 *  - `permissionLoggedIn` (autenticação WP)
 *  - Ownership resolvido SEMPRE via {@see OwnershipResolver::currentUserAgenteId()}
 *    (nunca aceita `agente_id` do cliente)
 *  - Rate limit por usuário
 *  - Whitelist defensiva nas respostas GET (sem expor campos não enumerados)
 *  - Audit log no início e no fim das ações destrutivas
 *
 * Os endpoints stubs antigos em {@see LgpdMeEndpoints} permanecem como
 * camada de compatibilidade (a Wave 8 expande, não substitui).
 */
final class MinhaContaLgpdEndpoints
{
    use RestSupport;

    /** Re-auth/export: 1 export por usuário a cada 24h. */
    private const EXPORT_RATE_MAX     = 1;
    private const EXPORT_RATE_WINDOW  = 86400;

    /** Solicitação de anonimização: 1 por dia (anti-spam de tokens). */
    private const ANON_RATE_MAX     = 1;
    private const ANON_RATE_WINDOW  = 86400;

    private OwnershipResolver $ownership;
    private ConsentimentoRepository $consentimentos;
    private TermoRepository $termos;
    private SolicitacaoTitularRepository $solicitacoes;
    private RegistrarConsentimentoHandler $registrar;
    private RevogarConsentimentoHandler $revogar;
    private SolicitarAnonimizacaoHandler $solicitarAnon;
    private ConfirmarAnonimizacaoHandler $confirmarAnon;
    private SolicitarExportDadosHandler $solicitarExport;
    private AuditLogger $audit;
    private IpResolver $ipResolver;

    /** @var callable(string,string,int):bool wp_check_password($pass, $hash, $userId) */
    private $passwordChecker;

    /** @var callable(int):?array{user_login:string,user_pass:string,ID:int} user lookup. */
    private $userLookup;

    public function __construct(
        OwnershipResolver $ownership,
        ConsentimentoRepository $consentimentos,
        TermoRepository $termos,
        SolicitacaoTitularRepository $solicitacoes,
        RegistrarConsentimentoHandler $registrar,
        RevogarConsentimentoHandler $revogar,
        SolicitarAnonimizacaoHandler $solicitarAnon,
        ConfirmarAnonimizacaoHandler $confirmarAnon,
        SolicitarExportDadosHandler $solicitarExport,
        AuditLogger $audit,
        IpResolver $ipResolver,
        callable $passwordChecker,
        callable $userLookup
    ) {
        $this->ownership        = $ownership;
        $this->consentimentos   = $consentimentos;
        $this->termos           = $termos;
        $this->solicitacoes     = $solicitacoes;
        $this->registrar        = $registrar;
        $this->revogar          = $revogar;
        $this->solicitarAnon    = $solicitarAnon;
        $this->confirmarAnon    = $confirmarAnon;
        $this->solicitarExport  = $solicitarExport;
        $this->audit            = $audit;
        $this->ipResolver       = $ipResolver;
        $this->passwordChecker  = $passwordChecker;
        $this->userLookup       = $userLookup;
    }

    public function register(string $namespace): void
    {
        if (!function_exists('register_rest_route')) {
            return;
        }

        \register_rest_route($namespace, '/me/consentimentos', [
            'methods'             => 'GET',
            'callback'            => [$this, 'listarConsentimentos'],
            'permission_callback' => $this->permissionLoggedIn(),
        ]);

        \register_rest_route($namespace, '/me/consentimentos/historico', [
            'methods'             => 'GET',
            'callback'            => [$this, 'historicoConsentimentos'],
            'permission_callback' => $this->permissionLoggedIn(),
        ]);

        \register_rest_route($namespace, '/me/consentimentos/(?P<finalidade>[a-z0-9_]+)/revogar', [
            'methods'             => 'POST',
            'callback'            => [$this, 'revogarConsentimento'],
            'permission_callback' => $this->permissionLoggedIn(),
            'args'                => [
                'finalidade' => ['type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_key'],
            ],
        ]);

        \register_rest_route($namespace, '/me/consentimentos/(?P<finalidade>[a-z0-9_]+)/reaceitar', [
            'methods'             => 'POST',
            'callback'            => [$this, 'reaceitarConsentimento'],
            'permission_callback' => $this->permissionLoggedIn(),
            'args'                => [
                'finalidade' => ['type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_key'],
            ],
        ]);

        \register_rest_route($namespace, '/me/solicitacoes', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'listarSolicitacoes'],
                'permission_callback' => $this->permissionLoggedIn(),
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'criarSolicitacao'],
                'permission_callback' => $this->permissionLoggedIn(),
            ],
        ]);

        \register_rest_route($namespace, '/me/solicitacoes/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'detalheSolicitacao'],
            'permission_callback' => $this->permissionLoggedIn(),
            'args'                => [
                'id' => ['type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint'],
            ],
        ]);

        \register_rest_route($namespace, '/me/exportar-dados', [
            'methods'             => 'POST',
            'callback'            => [$this, 'exportarDados'],
            'permission_callback' => $this->permissionLoggedIn(),
        ]);

        \register_rest_route($namespace, '/me/solicitar-anonimizacao', [
            'methods'             => 'POST',
            'callback'            => [$this, 'solicitarAnonimizacao'],
            'permission_callback' => $this->permissionLoggedIn(),
        ]);

        \register_rest_route($namespace, '/me/anonimizacao-confirmar', [
            'methods'             => 'POST',
            'callback'            => [$this, 'confirmarAnonimizacao'],
            'permission_callback' => $this->permissionLoggedIn(),
        ]);
    }

    // ─── GET /me/consentimentos ──────────────────────────────────────────

    /**
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function listarConsentimentos(object $request)
    {
        unset($request);
        try {
            $this->enforceRateLimit('me_lgpd_consents', 60, 60);
            $agenteId = $this->currentAgenteIdOr403();

            $todos = $this->consentimentos->findTodosPorAgente($agenteId);
            $byFin = [];
            foreach ($todos as $c) {
                $byFin[$c->finalidade()->value()] = $c; // último = vigente
            }

            $items = [];
            foreach (Finalidade::all() as $fin) {
                $vigente = $byFin[$fin->value()] ?? null;
                $items[] = $this->whitelistConsentimento($fin, $vigente);
            }

            return $this->ok(['items' => $items]);
        } catch (Throwable $e) {
            return $this->handleThrowable($e);
        }
    }

    // ─── GET /me/consentimentos/historico ────────────────────────────────

    /**
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function historicoConsentimentos(object $request)
    {
        unset($request);
        try {
            $this->enforceRateLimit('me_lgpd_consents_history', 30, 60);
            $agenteId = $this->currentAgenteIdOr403();

            $todos = $this->consentimentos->findTodosPorAgente($agenteId);
            $items = [];
            foreach ($todos as $c) {
                $items[] = [
                    'finalidade'    => $c->finalidade()->value(),
                    'status'        => $c->status()->value(),
                    'termo_id'      => $c->termoId(),
                    'registrado_em' => $c->registradoEm()->format(\DateTimeInterface::ATOM),
                    'revogado_em'   => $c->revogadoEm() !== null ? $c->revogadoEm()->format(\DateTimeInterface::ATOM) : null,
                ];
            }

            return $this->ok(['items' => $items]);
        } catch (Throwable $e) {
            return $this->handleThrowable($e);
        }
    }

    // ─── POST /me/consentimentos/{finalidade}/revogar ────────────────────

    /**
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function revogarConsentimento(object $request)
    {
        try {
            $this->enforceRateLimit('me_lgpd_revoke', 10, 60);
            $agenteId = $this->currentAgenteIdOr403();

            $code = (string) $this->paramFromRequest($request, 'finalidade', '');
            if ($code === '') {
                throw RestException::validation('finalidade obrigatória.');
            }
            try {
                $finalidade = Finalidade::fromString($code);
            } catch (\InvalidArgumentException $e) {
                throw RestException::validation($e->getMessage());
            }
            if ($finalidade->isObrigatoria()) {
                throw new RestException(
                    sprintf(
                        'A finalidade "%s" é obrigatória (base legal: %s) e não pode ser revogada individualmente. '
                        . 'Para encerrar seu cadastro use "Anonimizar minha conta".',
                        $finalidade->label(),
                        $finalidade->baseLegal()
                    ),
                    'pi_finalidade_obrigatoria',
                    422,
                    ['finalidade' => $finalidade->value(), 'obrigatoria' => true]
                );
            }

            $ipHash = $this->ipResolver->hashIp($this->ipResolver->resolve());
            $ua     = $this->captureUserAgent($request);

            $this->audit->log(
                'consentimento',
                null,
                'revogar_consentimento_request',
                null,
                ['agente_id' => $agenteId, 'finalidade' => $finalidade->value()],
                (int) \get_current_user_id()
            );

            $id = $this->revogar->handle($agenteId, $finalidade, $ipHash, $ua);

            return $this->ok([
                'consentimento_id' => $id,
                'finalidade'       => $finalidade->value(),
                'status'           => 'revogado',
            ]);
        } catch (Throwable $e) {
            return $this->handleThrowable($e);
        }
    }

    // ─── POST /me/consentimentos/{finalidade}/reaceitar ──────────────────

    /**
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function reaceitarConsentimento(object $request)
    {
        try {
            $this->enforceRateLimit('me_lgpd_reaccept', 10, 60);
            $agenteId = $this->currentAgenteIdOr403();
            $userId   = (int) \get_current_user_id();

            $code = (string) $this->paramFromRequest($request, 'finalidade', '');
            if ($code === '') {
                throw RestException::validation('finalidade obrigatória.');
            }
            try {
                $finalidade = Finalidade::fromString($code);
            } catch (\InvalidArgumentException $e) {
                throw RestException::validation($e->getMessage());
            }
            if ($finalidade->isObrigatoria()) {
                // Reaceitar uma "obrigatória" não faz sentido (ela nunca foi revogável).
                throw RestException::validation('Finalidade obrigatória já está sempre aceita.');
            }

            $termo = $this->termos->findAtivoCorrente();
            if ($termo === null || $termo->id() === null) {
                throw new RestException(
                    'Não há termo de privacidade vigente. Tente novamente mais tarde.',
                    'pi_termo_indisponivel',
                    503
                );
            }

            $ipHash = $this->ipResolver->hashIp($this->ipResolver->resolve());
            $ua     = $this->captureUserAgent($request);

            $this->audit->log(
                'consentimento',
                null,
                'reaceitar_consentimento_request',
                null,
                ['agente_id' => $agenteId, 'finalidade' => $finalidade->value(), 'termo_id' => $termo->id()],
                $userId
            );

            $command = new RegistrarConsentimentoCommand(
                $agenteId,
                (int) $termo->id(),
                [$finalidade->value()], // aceitas
                [],                       // negadas
                $ipHash,
                $ua
            );
            $mapa = $this->registrar->handle($command);

            return $this->ok([
                'consentimento_id' => $mapa[$finalidade->value()] ?? null,
                'finalidade'       => $finalidade->value(),
                'status'           => 'aceito',
                'termo_id'         => $termo->id(),
                'termo_versao'     => $termo->versao(),
            ]);
        } catch (Throwable $e) {
            return $this->handleThrowable($e);
        }
    }

    // ─── GET /me/solicitacoes ────────────────────────────────────────────

    /**
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function listarSolicitacoes(object $request)
    {
        unset($request);
        try {
            $this->enforceRateLimit('me_lgpd_solic_list', 30, 60);
            $agenteId = $this->currentAgenteIdOr403();

            $abertas = $this->solicitacoes->findAbertasPorAgente($agenteId);
            $items = [];
            foreach ($abertas as $s) {
                $items[] = $this->whitelistSolicitacao($s);
            }

            return $this->ok(['items' => $items]);
        } catch (Throwable $e) {
            return $this->handleThrowable($e);
        }
    }

    // ─── POST /me/solicitacoes ───────────────────────────────────────────

    /**
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function criarSolicitacao(object $request)
    {
        try {
            $this->enforceRateLimit('me_lgpd_solic_create', 5, 600); // 5 / 10min
            $agenteId = $this->currentAgenteIdOr403();
            $userId   = (int) \get_current_user_id();

            $body = $this->readJsonBody($request);
            $tipo = isset($body['tipo']) && is_string($body['tipo']) ? trim(strtolower($body['tipo'])) : '';
            $detalhes = isset($body['detalhes_md']) && is_string($body['detalhes_md']) ? $body['detalhes_md'] : '';

            $tiposValidos = SolicitacaoTitular::tiposValidos();
            if (!in_array($tipo, $tiposValidos, true)) {
                throw RestException::validation(
                    sprintf('tipo deve ser um de: %s.', implode(', ', $tiposValidos)),
                    ['tipo' => $tipo]
                );
            }
            // Para anonimização e portabilidade, redireciona para o fluxo próprio.
            if ($tipo === SolicitacaoTitular::TIPO_ANONIMIZACAO) {
                throw RestException::validation(
                    'Use POST /me/solicitar-anonimizacao (exige re-autenticação por senha).'
                );
            }
            if ($tipo === SolicitacaoTitular::TIPO_PORTABILIDADE) {
                throw RestException::validation(
                    'Use POST /me/exportar-dados (exige re-autenticação por senha).'
                );
            }

            $detalhes = function_exists('sanitize_textarea_field')
                ? (string) \sanitize_textarea_field($detalhes)
                : trim(strip_tags($detalhes));
            if (mb_strlen($detalhes) > 5000) {
                throw RestException::validation('detalhes_md excede 5000 caracteres.');
            }

            $solic = SolicitacaoTitular::protocolar($agenteId, $tipo, $detalhes !== '' ? $detalhes : null);
            $id    = $this->solicitacoes->save($solic);

            $this->audit->log(
                'lgpd_solicitacao_titular',
                $id,
                'solicitacao_titular_protocolada',
                null,
                ['agente_id' => $agenteId, 'tipo' => $tipo],
                $userId
            );

            if (function_exists('do_action')) {
                \do_action('pi_solicitacao_titular_protocolada', $id, $agenteId, $tipo);
            }

            return $this->ok($this->whitelistSolicitacao($solic->withId($id)), 201);
        } catch (Throwable $e) {
            return $this->handleThrowable($e);
        }
    }

    // ─── GET /me/solicitacoes/{id} ───────────────────────────────────────

    /**
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function detalheSolicitacao(object $request)
    {
        try {
            $this->enforceRateLimit('me_lgpd_solic_detail', 30, 60);
            $agenteId = $this->currentAgenteIdOr403();
            $id       = (int) $this->paramFromRequest($request, 'id', 0);
            if ($id < 1) {
                throw RestException::notFound();
            }
            $solic = $this->solicitacoes->findById($id);
            if ($solic === null) {
                throw RestException::notFound();
            }
            if ($solic->agenteId() !== $agenteId) {
                // Audita tentativa cross-user.
                $this->audit->log(
                    'lgpd_solicitacao_titular',
                    $id,
                    'ownership_denied',
                    null,
                    ['tentou_user_id' => (int) \get_current_user_id(), 'tentou_agente_id' => $agenteId],
                    (int) \get_current_user_id()
                );
                throw RestException::forbidden();
            }

            return $this->ok($this->whitelistSolicitacao($solic, true));
        } catch (Throwable $e) {
            return $this->handleThrowable($e);
        }
    }

    // ─── POST /me/exportar-dados ─────────────────────────────────────────

    /**
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function exportarDados(object $request)
    {
        try {
            // Rate limit GLOBAL (LGPD-compliant: 1 export grátis / 24h).
            $this->enforceRateLimit('me_lgpd_export', self::EXPORT_RATE_MAX, self::EXPORT_RATE_WINDOW);

            $agenteId = $this->currentAgenteIdOr403();
            $userId   = (int) \get_current_user_id();

            // Re-autenticação por senha.
            $body  = $this->readJsonBody($request);
            $senha = isset($body['confirmacao_senha']) && is_string($body['confirmacao_senha'])
                ? $body['confirmacao_senha']
                : '';
            $this->reauthenticateOrThrow($userId, $senha);

            $ipHash = $this->ipResolver->hashIp($this->ipResolver->resolve());
            $cmd    = new SolicitarExportDadosCommand($agenteId, $userId, $ipHash);
            $resp   = $this->solicitarExport->handle($cmd);

            return $this->ok([
                'solicitacao_id' => $resp['solicitacao_id'],
                'download_url'   => $resp['download_url'],
                'expira_em'      => $resp['expira_em'],
                'mensagem'       => function_exists('__')
                    ? \__('Seu pacote de dados está pronto. O link é pessoal e expira em 24 horas.', 'participe-ibram')
                    : 'Seu pacote de dados está pronto.',
            ]);
        } catch (Throwable $e) {
            return $this->handleThrowable($e);
        }
    }

    // ─── POST /me/solicitar-anonimizacao ─────────────────────────────────

    /**
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function solicitarAnonimizacao(object $request)
    {
        try {
            $this->enforceRateLimit('me_lgpd_anon_request', self::ANON_RATE_MAX, self::ANON_RATE_WINDOW);

            $agenteId = $this->currentAgenteIdOr403();
            $userId   = (int) \get_current_user_id();

            $body   = $this->readJsonBody($request);
            $senha  = isset($body['confirmacao_senha']) && is_string($body['confirmacao_senha'])
                ? $body['confirmacao_senha']
                : '';
            $motivo = isset($body['motivo']) && is_string($body['motivo']) ? $body['motivo'] : null;

            $this->reauthenticateOrThrow($userId, $senha);

            if ($motivo !== null) {
                $motivo = function_exists('sanitize_textarea_field')
                    ? (string) \sanitize_textarea_field($motivo)
                    : trim(strip_tags($motivo));
                if ($motivo === '') {
                    $motivo = null;
                }
            }

            $ipHash = $this->ipResolver->hashIp($this->ipResolver->resolve());
            $ua     = $this->captureUserAgent($request);
            $cmd    = new SolicitarAnonimizacaoCommand($agenteId, $userId, $motivo, $ipHash, $ua);

            $resp = $this->solicitarAnon->handle($cmd);

            return $this->ok([
                'solicitacao_id' => $resp['solicitacao_id'],
                'expira_em'      => $resp['expira_em'],
                'mensagem'       => function_exists('__')
                    ? \__('Enviamos um link de confirmação para seu email. Acesse-o em até 24h para concluir a anonimização.', 'participe-ibram')
                    : 'Enviamos um link de confirmação para seu email.',
            ], 202);
        } catch (Throwable $e) {
            return $this->handleThrowable($e);
        }
    }

    // ─── POST /me/anonimizacao-confirmar ─────────────────────────────────

    /**
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function confirmarAnonimizacao(object $request)
    {
        try {
            $this->enforceRateLimit('me_lgpd_anon_confirm', 5, 60);
            $userId = (int) \get_current_user_id();
            if ($userId < 1) {
                throw RestException::unauthorized();
            }
            // Ownership não pode ser confirmado aqui porque após anon o agente_id
            // ainda existe, mas a verificação é feita pelo handler ao decodificar
            // o token e cruzar com o solicitacao.agente_id.
            $body  = $this->readJsonBody($request);
            $token = isset($body['token']) && is_string($body['token']) ? $body['token'] : '';
            if ($token === '') {
                throw RestException::validation('token obrigatório.');
            }

            $ipHash = $this->ipResolver->hashIp($this->ipResolver->resolve());
            $cmd    = new ConfirmarAnonimizacaoCommand($token, $userId, $ipHash);
            $resp   = $this->confirmarAnon->handle($cmd);

            return $this->ok([
                'solicitacao_id' => $resp['solicitacao_id'],
                'agente_id'      => $resp['agente_id'],
                'mensagem'       => function_exists('__')
                    ? \__('Sua conta foi anonimizada. Você será desconectado.', 'participe-ibram')
                    : 'Sua conta foi anonimizada.',
            ]);
        } catch (Throwable $e) {
            return $this->handleThrowable($e);
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    /**
     * Resolve o agente_id do usuário logado. Lança 403 se não for o dono de cadastro.
     *
     * @throws RestException
     */
    private function currentAgenteIdOr403(): int
    {
        $userId = (int) \get_current_user_id();
        if ($userId < 1) {
            throw RestException::unauthorized();
        }
        $agenteId = $this->ownership->resolveAgenteIdByUserId($userId);
        if ($agenteId === null) {
            throw RestException::forbidden(
                function_exists('__')
                    ? \__('Você não possui cadastro no Participe Ibram.', 'participe-ibram')
                    : 'Sem cadastro.'
            );
        }

        return $agenteId;
    }

    /**
     * Verifica senha do usuário (re-autenticação). Audita tentativa malsucedida.
     *
     * @throws RestException 401 quando senha incorreta.
     */
    private function reauthenticateOrThrow(int $userId, string $senha): void
    {
        if ($senha === '') {
            throw RestException::validation('confirmacao_senha obrigatória.');
        }
        $user = ($this->userLookup)($userId);
        if ($user === null || !isset($user['user_pass'])) {
            throw RestException::unauthorized();
        }
        $hash = (string) $user['user_pass'];
        $ok   = (bool) ($this->passwordChecker)($senha, $hash, $userId);
        if (!$ok) {
            $this->audit->log(
                'lgpd_reauth',
                null,
                'reauth_falhou',
                null,
                ['user_id' => $userId, 'ip_hash' => $this->ipResolver->hashIp($this->ipResolver->resolve())],
                $userId
            );
            throw RestException::unauthorized(
                function_exists('__')
                    ? \__('Senha incorreta.', 'participe-ibram')
                    : 'Senha incorreta.'
            );
        }
        $this->audit->log('lgpd_reauth', null, 'reauth_ok', null, ['user_id' => $userId], $userId);
    }

    /**
     * Whitelist: nunca expor mais que estes campos.
     *
     * @param Finalidade                                                     $fin
     * @param \Ibram\ParticipeIbram\Domain\Consentimento\Consentimento|null  $vigente
     *
     * @return array<string,mixed>
     */
    private function whitelistConsentimento(Finalidade $fin, $vigente): array
    {
        $status        = 'sem_registro';
        $registradoEm  = null;
        $termoVersao   = null;
        $termoId       = null;
        if ($vigente !== null) {
            $status       = $vigente->status()->value();
            $registradoEm = $vigente->registradoEm()->format(\DateTimeInterface::ATOM);
            $termoId      = $vigente->termoId();
            $termo        = $this->termos->findById($vigente->termoId());
            if ($termo !== null) {
                $termoVersao = $termo->versao();
            }
        }
        if ($fin->isObrigatoria() && $status === 'sem_registro') {
            // Obrigatórias sempre são consideradas aceitas (base legal: política pública).
            $status = 'aceito';
        }

        return [
            'finalidade'    => $fin->value(),
            'label'         => $fin->label(),
            'descricao'     => $fin->descricao(),
            'status'        => $status,
            'registrado_em' => $registradoEm,
            'termo_id'      => $termoId,
            'termo_versao'  => $termoVersao,
            'base_legal'    => $fin->baseLegal(),
            'obrigatoria'   => $fin->isObrigatoria(),
            'sensivel'      => $fin->isSensivel(),
            'revogavel'     => !$fin->isObrigatoria() && $status === 'aceito',
            'reaceitavel'   => !$fin->isObrigatoria() && in_array($status, ['revogado', 'negado'], true),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function whitelistSolicitacao(SolicitacaoTitular $s, bool $incluirResposta = false): array
    {
        $out = [
            'id'             => $s->id(),
            'tipo'           => $s->tipo(),
            'status'         => $s->status(),
            'protocolada_em' => $s->protocoladaEm()->format(\DateTimeInterface::ATOM),
            'prazo_final'    => $s->prazoFinal()->format(\DateTimeInterface::ATOM),
            'atendida_em'    => $s->atendidaEm() !== null ? $s->atendidaEm()->format(\DateTimeInterface::ATOM) : null,
        ];
        if ($incluirResposta) {
            $out['detalhes_md'] = $s->detalhesMd();
            $out['resposta_md'] = $s->respostaMd();
        }

        return $out;
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
        if (!is_string($ua) || $ua === '') {
            return null;
        }
        if (function_exists('sanitize_text_field')) {
            $ua = (string) \sanitize_text_field($ua);
        }

        return mb_substr($ua, 0, 1024);
    }

    /**
     * Override handleThrowable para tratar OwnershipDeniedException → 403.
     *
     * @return \WP_REST_Response|array<string,mixed>
     */
    protected function handleThrowable(Throwable $e)
    {
        if ($e instanceof OwnershipDeniedException) {
            return RestException::forbidden()->toResponse();
        }
        if ($e instanceof RestException) {
            return $e->toResponse();
        }
        if ($e instanceof \DomainException) {
            // Domínio: 422 mais semântico para regras de negócio.
            return (new RestException($e->getMessage(), 'pi_domain', 422))->toResponse();
        }
        if ($e instanceof \InvalidArgumentException) {
            return RestException::validation($e->getMessage())->toResponse();
        }

        $debug = \defined('WP_DEBUG') && \WP_DEBUG;
        $msg = $debug ? $e->getMessage() : (function_exists('__')
            ? \__('Erro interno. Tente novamente mais tarde.', 'participe-ibram')
            : 'Erro interno.');

        return RestException::internal($msg)->toResponse();
    }
}
