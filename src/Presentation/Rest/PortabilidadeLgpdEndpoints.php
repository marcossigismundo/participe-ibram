<?php
/**
 * Endpoints REST de Portabilidade LGPD (Art. 18 V).
 *
 * Exposicao do {@see ExportarPortabilidadeHandler} via REST API:
 *  - POST /pi/v1/me/portabilidade           - solicita novo export (ownership + reauth)
 *  - GET  /pi/v1/me/portabilidade/historico - lista exports anteriores
 *
 * Wave 9 W9-E (concluído manualmente — agente W9-E atingiu limite após gerar
 * o handler+LAI). Padrão herdado de MinhaContaLgpdEndpoints (W8-B).
 *
 * @package Ibram\ParticipeIbram\Presentation\Rest
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Rest;

use Ibram\ParticipeIbram\Application\Lgpd\ExportarPortabilidadeHandler;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Core\Helpers\RateLimiter;
use Ibram\ParticipeIbram\Domain\Consentimento\SolicitacaoTitular;
use Ibram\ParticipeIbram\Domain\Consentimento\SolicitacaoTitularRepository;
use Ibram\ParticipeIbram\Presentation\Public\MinhaConta\OwnershipResolver;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST endpoints para portabilidade LGPD.
 *
 * Diferente de {@see MinhaContaLgpdEndpoints::exportarDados()} (export simples),
 * estes endpoints geram o pacote enriquecido (JSON-LD + schema + CSV + README)
 * descrito em {@see ExportarPortabilidadeHandler}.
 */
final class PortabilidadeLgpdEndpoints
{
    use RestSupport;

    private const NAMESPACE = 'pi/v1';

    /** Rate limit: 1 export por dia/usuário (LGPD permite intervalos razoáveis). */
    private const MAX_EXPORTS_POR_DIA = 1;

    private OwnershipResolver $ownership;
    private ExportarPortabilidadeHandler $handler;
    private SolicitacaoTitularRepository $solicitacoes;
    private AuditLogger $audit;
    private RateLimiter $rateLimiter;

    public function __construct(
        OwnershipResolver $ownership,
        ExportarPortabilidadeHandler $handler,
        SolicitacaoTitularRepository $solicitacoes,
        AuditLogger $audit,
        RateLimiter $rateLimiter
    ) {
        $this->ownership    = $ownership;
        $this->handler      = $handler;
        $this->solicitacoes = $solicitacoes;
        $this->audit        = $audit;
        $this->rateLimiter  = $rateLimiter;
    }

    /**
     * Registra rotas REST. Chamado por RestRegistration via rest_api_init.
     */
    public function register(): void
    {
        if (!function_exists('register_rest_route')) {
            return;
        }

        register_rest_route(self::NAMESPACE, '/me/portabilidade', [
            'methods'             => 'POST',
            'callback'            => [$this, 'criarExport'],
            'permission_callback' => $this->permissionLoggedIn(),
            'args'                => [
                'confirmacao_senha' => [
                    'type'     => 'string',
                    'required' => true,
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/me/portabilidade/historico', [
            'methods'             => 'GET',
            'callback'            => [$this, 'listarHistorico'],
            'permission_callback' => $this->permissionLoggedIn(),
        ]);
    }

    /**
     * POST /me/portabilidade — gera novo export para o agente logado.
     *
     * Fluxo:
     *  1. Ownership: resolve agente_id do user_id atual (NUNCA do payload).
     *  2. Reauth: valida senha via wp_check_password antes de gerar.
     *  3. Rate limit: 1 export por 24h.
     *  4. Invoca ExportarPortabilidadeHandler::handle(agente_id).
     *  5. Persiste SolicitacaoTitular tipo=portabilidade status=atendida.
     *  6. Retorna URL signed (TTL 24h gerada internamente pelo handler).
     */
    public function criarExport(WP_REST_Request $request)
    {
        $userId = get_current_user_id();
        if ($userId <= 0) {
            return RestException::unauthorized()->toResponse();
        }

        $agenteId = $this->ownership->resolveAgenteIdByUserId($userId);
        if ($agenteId === null) {
            return RestException::notFound(__('Você não possui cadastro vinculado.', 'participe-ibram'))->toResponse();
        }

        // Reauth password (defesa contra hijack de sessão).
        $senha = (string) $request->get_param('confirmacao_senha');
        if ($senha === '') {
            return RestException::validation([
                'confirmacao_senha' => __('Senha obrigatória.', 'participe-ibram'),
            ])->toResponse();
        }

        $user = get_userdata($userId);
        if (!$user || !wp_check_password($senha, $user->user_pass, $user->ID)) {
            $this->audit->log('lgpd_export', null, 'reauth_falhou', null, ['endpoint' => 'portabilidade'], $userId);
            return RestException::unauthorized(__('Senha incorreta.', 'participe-ibram'))->toResponse();
        }

        // Rate limit (1/dia/user).
        $rlKey = $this->rateLimiter->keyForUser('pi_portabilidade_export', $userId);
        if (!$this->rateLimiter->check($rlKey, self::MAX_EXPORTS_POR_DIA, DAY_IN_SECONDS)) {
            return RestException::tooManyRequests(
                __('Você já solicitou um export nas últimas 24 horas. Tente novamente amanhã.', 'participe-ibram')
            )->toResponse();
        }

        try {
            $result = $this->handler->handle($agenteId);
        } catch (\Throwable $e) {
            $this->audit->log('lgpd_export', null, 'portabilidade_falhou', null, [
                'agente_id' => $agenteId,
                'erro'      => substr($e->getMessage(), 0, 200),
            ], $userId);
            return RestException::internal(
                __('Erro ao gerar export. Tente novamente ou contate o DPO.', 'participe-ibram')
            )->toResponse();
        }

        // Persiste solicitação Art. 18 V para auditoria/histórico.
        $solicitacao = SolicitacaoTitular::criar(
            agenteId: $agenteId,
            tipo: 'portabilidade',
            detalhesMd: sprintf('Export gerado via REST /me/portabilidade. ZIP: %s', basename((string) $result['arquivo_path']))
        );
        $solicitacao = $solicitacao->responder(
            respostaMd: sprintf('Disponível em: %s', $result['download_url']),
            atorId: 0, // automático
            atendida: true
        );
        $this->solicitacoes->save($solicitacao);

        $this->audit->log('lgpd_export', null, 'portabilidade_atendido', null, [
            'agente_id'    => $agenteId,
            'arquivo_size' => $result['arquivo_size'] ?? 0,
            'schema_version' => $result['schema_version'] ?? null,
        ], $userId);

        // Whitelist de resposta — nunca expor path absoluto do servidor.
        return new WP_REST_Response([
            'solicitacao_id' => $solicitacao->id(),
            'download_url'   => $result['download_url'],
            'expira_em'      => $result['expira_em'],
            'mensagem'       => __('Export pronto. Faça o download em até 24 horas.', 'participe-ibram'),
        ], 200);
    }

    /**
     * GET /me/portabilidade/historico — lista exports anteriores do agente.
     *
     * Whitelist: id, tipo, status, protocolada_em, atendida_em (sem path,
     * sem URL — links expiram em 24h e não são re-emitidos).
     */
    public function listarHistorico(WP_REST_Request $request)
    {
        $userId = get_current_user_id();
        if ($userId <= 0) {
            return RestException::unauthorized()->toResponse();
        }

        $agenteId = $this->ownership->resolveAgenteIdByUserId($userId);
        if ($agenteId === null) {
            return new WP_REST_Response(['items' => []], 200);
        }

        $page    = max(1, (int) $request->get_param('page'));
        $perPage = min(50, max(1, (int) ($request->get_param('per_page') ?? 25)));

        // Filtra solicitações tipo=portabilidade.
        $solicitacoes = $this->solicitacoes->listarPorAgente($agenteId, $page, $perPage);
        $portabilidade = [];
        foreach ($solicitacoes as $s) {
            if ($s->tipo() !== 'portabilidade') {
                continue;
            }
            // Whitelist defensiva — nunca expor detalhes_md / resposta_md (podem ter paths).
            $portabilidade[] = [
                'id'              => $s->id(),
                'status'          => $s->status(),
                'protocolada_em'  => $s->protocoladaEm()->format(DATE_ATOM),
                'atendida_em'     => $s->atendidaEm() !== null ? $s->atendidaEm()->format(DATE_ATOM) : null,
            ];
        }

        return new WP_REST_Response(['items' => $portabilidade], 200);
    }
}
