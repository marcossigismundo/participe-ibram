<?php
/**
 * Auth provider registration stub.
 *
 * @package Ibram\ParticipeIbram\Bootstrap
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Bootstrap;

/**
 * Registers the auth provider registry into the DI container.
 *
 * Wave 1 only ships the wiring; the concrete `AuthProviderRegistry`,
 * `WordPressAuth` and `GovBrAuth` (stub) classes are delivered by a later
 * Auth wave (see refactor-spec/research/R3-govbr-oidc.md).
 *
 * The factory must remain non-fatal when those classes are missing so the
 * plugin can boot before the Auth wave lands.
 */
final class AuthRegistration
{
    /**
     * Register the `auth.registry` singleton.
     *
     * @param Container $container DI container being assembled.
     */
    public static function register(Container $container): void
    {
        $container->singleton('auth.registry', static function (Container $c) {
            // TODO Wave Auth: replace with concrete AuthProviderRegistry that
            // wires WordPressAuth (default) and GovBrAuth (stub) and exposes
            // ::resolve(string $id), ::default(), ::all().
            $registry = 'Ibram\\ParticipeIbram\\Infrastructure\\Auth\\AuthProviderRegistry';
            if (class_exists($registry)) {
                /** @psalm-suppress MixedMethodCall */
                return new $registry($c);
            }
            return null;
        });
    }
}
