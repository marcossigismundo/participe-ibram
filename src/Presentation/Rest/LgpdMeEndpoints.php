<?php
/**
 * Endpoints REST self-service do titular (LGPD).
 *
 * @package Ibram\ParticipeIbram\Presentation\Rest
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Rest;

use Ibram\ParticipeIbram\Application\Consentimento\RevogarConsentimentoHandler;
use Ibram\ParticipeIbram\Core\Network\IpResolver;
use Ibram\ParticipeIbram\Domain\Agente\AgenteRepository;
use Ibram\ParticipeIbram\Domain\Consentimento\ConsentimentoRepository;
use Ibram\ParticipeIbram\Domain\Consentimento\Finalidade;
use Throwable;

/**
 * Endpoints "me" — operam sobre o usuário autenticado:
 *  - GET  /pi/v1/me/data-summary
 *  - GET  /pi/v1/me/consents
 *  - POST /pi/v1/me/consents/{purpose_code}/revoke
 *
 * Wave 9 entrega export ZIP / anonimização self-service. Aqui são fornecidos
 * stubs claros (501) e o handler de revogação (Wave 2 já entregou).
 */
final class LgpdMeEndpoints
{
    use RestSupport;

    private AgenteRepository $agentes;
    private ConsentimentoRepository $consentimentos;
    private RevogarConsentimentoHandler $revogar;
    private IpResolver $ipResolver;

    public function __construct(
        AgenteRepository $agentes,
        ConsentimentoRepository $consentimentos,
        RevogarConsentimentoHandler $revogar,
        IpResolver $ipResolver
    ) {
        $this->agentes        = $agentes;
        $this->consentimentos = $consentimentos;
        $this->revogar        = $revogar;
        $this->ipResolver     = $ipResolver;
    }

    public function register(string $namespace): void
    {
        if (!function_exists('register_rest_route')) {
            return;
        }

        \register_rest_route($namespace, '/me/data-summary', [
            'methods'             => 'GET',
            'callback'            => [$this, 'dataSummary'],
            'permission_callback' => $this->permissionLoggedIn(),
        ]);

        \register_rest_route($namespace, '/me/consents', [
            'methods'             => 'GET',
            'callback'            => [$this, 'consents'],
            'permission_callback' => $this->permissionLoggedIn(),
        ]);

        \register_rest_route($namespace, '/me/consents/(?P<purpose_code>[a-z0-9_]+)/revoke', [
            'methods'             => 'POST',
            'callback'            => [$this, 'revokeConsent'],
            'permission_callback' => $this->permissionLoggedIn(),
            'args'                => [
                'purpose_code' => [
                    'type'              => 'string',
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_key',
                ],
            ],
        ]);
    }

    /**
     * GET /me/data-summary — STUB (Wave 9 implementa export completo).
     *
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function dataSummary(object $request)
    {
        unset($request);
        try {
            $this->enforceRateLimit('me_data_summary', 30, 60);
            $userId = (int) \get_current_user_id();
            $agente = $this->agentes->findByUserId($userId);

            return $this->ok([
                'agente_id'      => $agente !== null ? $agente->getId() : null,
                'tipo'           => $agente !== null ? $agente->getTipo()->value() : null,
                'status'         => $agente !== null ? $agente->getStatusCadastro()->value() : null,
                'export_endpoint' => 'POST /pi/v1/me/export (Wave 9)',
                'message'         => function_exists('__')
                    ? \__('Resumo básico — exportação completa via Wave 9.', 'participe-ibram')
                    : 'Resumo básico.',
            ]);
        } catch (Throwable $e) {
            return $this->handleThrowable($e);
        }
    }

    /**
     * GET /me/consents — lista consentimentos vigentes do usuário logado.
     *
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function consents(object $request)
    {
        unset($request);
        try {
            $this->enforceRateLimit('me_consents', 60, 60);

            $userId = (int) \get_current_user_id();
            $agente = $this->agentes->findByUserId($userId);
            if ($agente === null || $agente->getId() === null) {
                return $this->ok(['items' => []]);
            }

            $todos = $this->consentimentos->findTodosPorAgente((int) $agente->getId());

            // Reduz à última decisão por finalidade.
            $byFinalidade = [];
            foreach ($todos as $c) {
                $byFinalidade[$c->finalidade()->value()] = $c;
            }
            $items = [];
            foreach ($byFinalidade as $finalidadeValor => $c) {
                $items[] = [
                    'finalidade'    => $finalidadeValor,
                    'status'        => $c->status()->value(),
                    'termo_id'      => $c->termoId(),
                    'registrado_em' => $c->registradoEm()->format(\DateTimeInterface::ATOM),
                    'revogavel'     => !$c->finalidade()->isObrigatoria(),
                ];
            }

            return $this->ok(['items' => $items]);
        } catch (Throwable $e) {
            return $this->handleThrowable($e);
        }
    }

    /**
     * POST /me/consents/{purpose_code}/revoke.
     *
     * @return \WP_REST_Response|array<string,mixed>
     */
    public function revokeConsent(object $request)
    {
        try {
            $this->enforceRateLimit('me_revoke_consent', 10, 60);

            $userId = (int) \get_current_user_id();
            $agente = $this->agentes->findByUserId($userId);
            if ($agente === null || $agente->getId() === null) {
                throw RestException::notFound();
            }

            $code = (string) $this->paramFromRequest($request, 'purpose_code', '');
            if ($code === '') {
                throw RestException::validation('purpose_code obrigatório.');
            }
            try {
                $finalidade = Finalidade::fromString($code);
            } catch (\InvalidArgumentException $e) {
                throw RestException::validation($e->getMessage());
            }

            $ip     = $this->ipResolver->resolve();
            $ipHash = $this->ipResolver->hashIp($ip);
            $ua     = $this->captureUserAgent($request);

            $id = $this->revogar->handle((int) $agente->getId(), $finalidade, $ipHash, $ua);

            return $this->ok([
                'consentimento_id' => $id,
                'finalidade'       => $finalidade->value(),
                'status'           => 'revogado',
            ]);
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
}
