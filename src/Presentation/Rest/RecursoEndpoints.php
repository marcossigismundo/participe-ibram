<?php
/**
 * Endpoints REST para protocolo de recursos (cadastro/inabilitação).
 *
 * @package Ibram\ParticipeIbram\Presentation\Rest
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Rest;

use Ibram\ParticipeIbram\Application\Edital\ProtocolarRecursoInabilitacaoHandler;
use Ibram\ParticipeIbram\Domain\Agente\AgenteRepository;
use Throwable;

/**
 * Endpoints de recursos:
 *  - POST /pi/v1/recursos/protocolar
 *
 * O endpoint orquestra dois fluxos diferenciados pelo campo `tipo`:
 *   - `cadastro`     → invoca W3-A `ProtocolarRecursoCommand` (recurso contra
 *                       indeferimento de cadastro). Stub 503 quando handler
 *                       de Cadastro ainda não existir.
 *   - `inabilitacao` → invoca {@see ProtocolarRecursoInabilitacaoHandler}.
 *
 * Auth: usuário logado E proprietário do agente/inscrição (validação no handler).
 */
final class RecursoEndpoints
{
    use RestSupport;

    private AgenteRepository $agentes;

    private ProtocolarRecursoInabilitacaoHandler $recursoInabilitacao;

    /**
     * @var callable(array<string,mixed>, int): array<string,mixed>|null
     *      Recebe (dados, userId) → ['recurso_id'=>int, 'protocolo'=>string].
     */
    private $recursoCadastroFactory;

    public function __construct(
        AgenteRepository $agentes,
        ProtocolarRecursoInabilitacaoHandler $recursoInabilitacao,
        ?callable $recursoCadastroFactory = null
    ) {
        $this->agentes                = $agentes;
        $this->recursoInabilitacao    = $recursoInabilitacao;
        $this->recursoCadastroFactory = $recursoCadastroFactory;
    }

    public function register(string $namespace): void
    {
        if (!function_exists('register_rest_route')) {
            return;
        }

        \register_rest_route($namespace, '/recursos/protocolar', [
            'methods'             => 'POST',
            'callback'            => [$this, 'protocolar'],
            'permission_callback' => $this->permissionLoggedIn(),
            'args'                => [
                'tipo' => [
                    'type'     => 'string',
                    'required' => true,
                    'enum'     => ['cadastro', 'inabilitacao'],
                ],
                'agente_id' => [
                    'type'              => 'integer',
                    'required'          => false,
                    'sanitize_callback' => 'absint',
                ],
                'inscricao_id' => [
                    'type'              => 'integer',
                    'required'          => false,
                    'sanitize_callback' => 'absint',
                ],
                'fundamentacao_md' => [
                    'type'     => 'string',
                    'required' => true,
                ],
            ],
        ]);
    }

    /**
     * POST /recursos/protocolar.
     *
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function protocolar(object $request)
    {
        try {
            $this->enforceRateLimit('recurso_protocolar', 5, 60);

            $userId = function_exists('get_current_user_id') ? (int) \get_current_user_id() : 0;
            if ($userId <= 0) {
                throw RestException::unauthorized();
            }

            $body      = $this->readJsonBody($request);
            $sanitized = Sanitizer::sanitizeNested(
                $body,
                ['tipo', 'agente_id', 'inscricao_id', 'fundamentacao_md'],
                [
                    'tipo'             => Sanitizer::KIND_KEY,
                    'agente_id'        => Sanitizer::KIND_INT,
                    'inscricao_id'     => Sanitizer::KIND_INT,
                    'fundamentacao_md' => Sanitizer::KIND_HTML_MD,
                ]
            );

            $tipo = isset($sanitized['tipo']) ? (string) $sanitized['tipo'] : '';
            if (!in_array($tipo, ['cadastro', 'inabilitacao'], true)) {
                throw RestException::validation(
                    function_exists('__') ? \__('Tipo de recurso inválido.', 'participe-ibram') : 'tipo inválido.',
                    ['campo' => 'tipo']
                );
            }
            $fund = isset($sanitized['fundamentacao_md']) ? trim((string) $sanitized['fundamentacao_md']) : '';
            if ($fund === '') {
                throw RestException::validation(
                    function_exists('__') ? \__('Fundamentação obrigatória.', 'participe-ibram') : 'fundamentacao_md obrigatório.',
                    ['campo' => 'fundamentacao_md']
                );
            }

            if ($tipo === 'cadastro') {
                $agenteId = isset($sanitized['agente_id']) ? (int) $sanitized['agente_id'] : 0;
                if ($agenteId <= 0) {
                    throw RestException::validation(
                        function_exists('__') ? \__('Identificador do agente obrigatório.', 'participe-ibram') : 'agente_id obrigatório.',
                        ['campo' => 'agente_id']
                    );
                }
                $agente = $this->agentes->findById($agenteId);
                if ($agente === null) {
                    throw RestException::notFound();
                }
                if ($agente->getUserId() !== $userId) {
                    throw RestException::forbidden();
                }

                if ($this->recursoCadastroFactory === null) {
                    throw new RestException(
                        function_exists('__') ? \__('Recurso de cadastro ainda não disponível.', 'participe-ibram') : 'Indisponível.',
                        'pi_not_ready',
                        503
                    );
                }
                $factory = $this->recursoCadastroFactory;
                $out     = (array) $factory(
                    [
                        'agente_id'        => $agenteId,
                        'fundamentacao_md' => $fund,
                    ],
                    $userId
                );

                return $this->ok([
                    'recurso_id' => isset($out['recurso_id']) ? (int) $out['recurso_id'] : 0,
                    'protocolo'  => isset($out['protocolo']) ? (string) $out['protocolo'] : '',
                    'tipo'       => 'cadastro',
                ], 201);
            }

            // inabilitacao
            $inscricaoId = isset($sanitized['inscricao_id']) ? (int) $sanitized['inscricao_id'] : 0;
            if ($inscricaoId <= 0) {
                throw RestException::validation(
                    function_exists('__') ? \__('Identificador da inscrição obrigatório.', 'participe-ibram') : 'inscricao_id obrigatório.',
                    ['campo' => 'inscricao_id']
                );
            }
            $recursoId = $this->recursoInabilitacao->handle($inscricaoId, $fund, $userId);

            return $this->ok([
                'recurso_id'   => $recursoId,
                'inscricao_id' => $inscricaoId,
                'tipo'         => 'inabilitacao',
            ], 201);
        } catch (Throwable $e) {
            return $this->handleThrowable($e);
        }
    }
}
