<?php
/**
 * Roteador REST: registra todas as rotas `pi/v1` no `rest_api_init`.
 *
 * @package Ibram\ParticipeIbram\Presentation\Rest
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Rest;

/**
 * Composição central das rotas REST do plugin.
 *
 * Recebe via DI as classes de endpoints (uma por agregado/feature) e delega
 * a registração de cada rota a esses objetos. O router é o único ponto que
 * conhece o namespace `pi/v1`, simplificando refactor/versão futura.
 *
 * Convenção:
 *  - Namespace: `pi/v1` (TD-04).
 *  - `permission_callback` SEMPRE definida (nunca `__return_true` em rota
 *    autenticada; nunca `__return_true` em mutação).
 *  - Erros são `RestException` → `WP_REST_Response` consistente.
 *  - Rate limiting é responsabilidade do endpoint, com `RateLimiter`.
 */
final class RestRouter
{
    public const NAMESPACE = 'pi/v1';

    private WizardEndpoints $wizard;
    private PublicEndpoints $publico;
    private RecursoEndpoints $recursos;
    private LgpdMeEndpoints $lgpdMe;

    public function __construct(
        WizardEndpoints $wizard,
        PublicEndpoints $publico,
        RecursoEndpoints $recursos,
        LgpdMeEndpoints $lgpdMe
    ) {
        $this->wizard   = $wizard;
        $this->publico  = $publico;
        $this->recursos = $recursos;
        $this->lgpdMe   = $lgpdMe;
    }

    /**
     * Registra o hook `rest_api_init`. Idempotente.
     */
    public function registerHooks(): void
    {
        if (!function_exists('add_action')) {
            return;
        }
        \add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    /**
     * Callback do `rest_api_init` — delega para cada grupo de endpoints.
     */
    public function registerRoutes(): void
    {
        if (!function_exists('register_rest_route')) {
            return;
        }

        $this->wizard->register(self::NAMESPACE);
        $this->publico->register(self::NAMESPACE);
        $this->recursos->register(self::NAMESPACE);
        $this->lgpdMe->register(self::NAMESPACE);
    }
}
