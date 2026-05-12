<?php
/**
 * Wave 9-D: registra e inicializa os Event Listeners no container WP.
 *
 * NÃO toca em Plugin.php — Plugin.php chama EventListenerRegistration::register()
 * e ::boot() nos momentos adequados.
 *
 * @package Ibram\ParticipeIbram\Bootstrap
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Bootstrap;

use Ibram\ParticipeIbram\Application\Email\EventListeners;
use Ibram\ParticipeIbram\Application\Email\EventListenersWave7;
use Ibram\ParticipeIbram\Application\Lgpd\LgpdEventListeners;

if (class_exists(EventListenerRegistration::class)) {
    return;
}

/**
 * Compõe e registra no container todos os listeners de hooks de domínio.
 *
 * Ordem de boot:
 *  1. EmailRegistration::register() deve ter sido chamado antes (provê
 *     `email.enfileirar`, `email.event_listeners`, etc.).
 *  2. RepositoryRegistration::register() deve ter sido chamado antes (provê
 *     `repo:agente`, `repo:agente_broadcast`, `repo:consentimento`, etc.).
 *  3. Esta classe registra os listeners Wave 7 e Wave 8 (LGPD) que
 *     EmailRegistration não cobre.
 *
 * `email.event_listeners` (Wave 4-C / EventListeners) já está registrado e
 * iniciado por EmailRegistration::boot(). Aqui re-usamos via get().
 */
final class EventListenerRegistration
{
    /**
     * Registra serviços no container. Idempotente (class_exists guard no topo).
     * Chamar durante Plugin::registerCoreServices() ou equivalente.
     */
    public static function register(Container $container): void
    {
        // ------------------------------------------------------------------
        // events:email_w7 — EventListenersWave7
        // ------------------------------------------------------------------
        if (class_exists(EventListenersWave7::class) && !$container->has('events:email_w7')) {
            $container->singleton('events:email_w7', static function (Container $c): EventListenersWave7 {
                // BroadcastQuery é opcional — não falha se repo não registrado.
                $broadcastQuery = null;
                if ($c->has('repo:agente_broadcast')) {
                    $broadcastQuery = $c->get('repo:agente_broadcast');
                }

                return new EventListenersWave7(
                    $c->get('email.enfileirar'),
                    $c->has('core:secure_logger') ? $c->get('core:secure_logger') : $c->get('email.logger'),
                    $broadcastQuery
                    // resolvers opcionais: null por padrão — podem ser injetados via
                    // $container->instance('events:email_w7', ...) em contextos com ORM completo.
                );
            });
        }

        // ------------------------------------------------------------------
        // events:lgpd — LgpdEventListeners
        // ------------------------------------------------------------------
        if (class_exists(LgpdEventListeners::class) && !$container->has('events:lgpd')) {
            $container->singleton('events:lgpd', static function (Container $c): LgpdEventListeners {
                $homeUrl             = function_exists('home_url') ? (string) \home_url('/') : '/';
                $confirmAnonUrlBase  = $homeUrl; // ajustar via filtro pi_lgpd_confirm_anon_url se necessário
                $painelMinhaContaUrl = rtrim($homeUrl, '/') . '/painel/minha-conta/';

                if (function_exists('get_option')) {
                    $confirmAnonUrlBase  = (string) \get_option('pi_lgpd_confirm_anon_url', $homeUrl);
                    $painelMinhaContaUrl = (string) \get_option('pi_lgpd_painel_url', $painelMinhaContaUrl);
                }

                // agenteResolver: devolve ['nome' => string, 'email' => string]
                $agenteResolver = static function (int $agenteId) use ($c): ?array {
                    try {
                        $repo   = $c->get('repo:agente');
                        $agente = $repo->findById($agenteId);
                        if ($agente === null) {
                            return null;
                        }
                        $email = method_exists($agente, 'getEmailPrincipal') ? (string) $agente->getEmailPrincipal() : '';
                        $nome  = method_exists($agente, 'getNomeExibicao') ? (string) $agente->getNomeExibicao() : 'Titular';
                        return ['nome' => $nome, 'email' => $email];
                    } catch (\Throwable $e) {
                        return null;
                    }
                };

                // solicitacaoResolver: devolve ['nome' => string, 'email' => string, 'tipo' => string]
                $solicitacaoResolver = static function (int $solicitacaoId) use ($c): ?array {
                    try {
                        $repo = $c->get('repo:solicitacao_titular');
                        $sol  = $repo->findById($solicitacaoId);
                        if ($sol === null) {
                            return null;
                        }
                        // Carrega agente via agenteId da solicitação.
                        $agenteId = method_exists($sol, 'agenteId') ? (int) $sol->agenteId() : 0;
                        if ($agenteId < 1) {
                            return null;
                        }
                        $agente = $c->get('repo:agente')->findById($agenteId);
                        if ($agente === null) {
                            return null;
                        }
                        $email = method_exists($agente, 'getEmailPrincipal') ? (string) $agente->getEmailPrincipal() : '';
                        $nome  = method_exists($agente, 'getNomeExibicao') ? (string) $agente->getNomeExibicao() : 'Titular';
                        $tipo  = method_exists($sol, 'tipo') ? (string) $sol->tipo() : '';
                        return ['nome' => $nome, 'email' => $email, 'tipo' => $tipo];
                    } catch (\Throwable $e) {
                        return null;
                    }
                };

                return new LgpdEventListeners(
                    $c->get('email.enfileirar'),
                    $c->has('core:secure_logger') ? $c->get('core:secure_logger') : $c->get('email.logger'),
                    $homeUrl,
                    $confirmAnonUrlBase,
                    $painelMinhaContaUrl,
                    $agenteResolver,
                    $solicitacaoResolver
                );
            });
        }
    }

    /**
     * Liga os hooks WP. Chamar em `init` (após register()).
     *
     * EventListeners (Wave 4-C) já é iniciado por EmailRegistration::boot().
     * Aqui iniciamos apenas os listeners adicionais (W7, LGPD).
     */
    public static function boot(Container $container): void
    {
        // Wave 4-C — re-usa o que EmailRegistration::boot() já registrou.
        // Chamamos registerHooks() novamente é seguro pois WP não duplica
        // add_action com mesma callable; mas EmailRegistration::boot() já
        // faz isso, portanto saltamos aqui para não duplicar.

        // Wave 7
        if ($container->has('events:email_w7')) {
            try {
                $w7 = $container->get('events:email_w7');
                if (method_exists($w7, 'registerHooks')) {
                    $w7->registerHooks();
                }
            } catch (\Throwable $e) {
                // graceful degradation
            }
        }

        // Wave 8 — LGPD
        if ($container->has('events:lgpd')) {
            try {
                $lgpd = $container->get('events:lgpd');
                if (method_exists($lgpd, 'register')) {
                    $lgpd->register();
                }
            } catch (\Throwable $e) {
                // graceful degradation
            }
        }
    }
}
