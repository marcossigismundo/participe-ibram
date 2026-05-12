<?php
/**
 * Registers core infrastructure services in the DI container.
 *
 * Called by Plugin::registerCoreServices() during plugin bootstrap.
 * Consumed by: W9-B AdminRegistration, W9-C REST+shortcodes,
 *              W9-D events+crons, W9-E LAI+portabilidade.
 *
 * @package Ibram\ParticipeIbram\Bootstrap
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Bootstrap;

use Ibram\ParticipeIbram\Core\Audit\AccessTracker;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Core\Audit\PiiMasker;
use Ibram\ParticipeIbram\Core\Database\SequenceGenerator;
use Ibram\ParticipeIbram\Core\Encryption\KeyManager;
use Ibram\ParticipeIbram\Core\Encryption\SodiumCipher;
use Ibram\ParticipeIbram\Core\Helpers\Json;
use Ibram\ParticipeIbram\Core\Helpers\RateLimiter;
use Ibram\ParticipeIbram\Core\Helpers\RequestHelper;
use Ibram\ParticipeIbram\Core\Helpers\UuidGenerator;
use Ibram\ParticipeIbram\Core\Logger\SecureLogger;
use Ibram\ParticipeIbram\Core\Network\IpResolver;
use Ibram\ParticipeIbram\Domain\Votacao\EleitorHasher;
use Ibram\ParticipeIbram\Infrastructure\Storage\PrivateFileStorage;

/**
 * Registers all core:*, storage:*, and hasher:* services.
 *
 * All registrations use singleton() for lazy, cached resolution.
 * Each block is guarded by class_exists() for graceful degradation
 * when Wave 1 classes are not yet present.
 */
final class CoreRegistration
{
    public static function register(Container $container): void
    {
        // ------------------------------------------------------------------
        // core:wpdb — global $wpdb handle (consumed by all repositories)
        // ------------------------------------------------------------------
        $container->singleton('core:wpdb', static function (): object {
            return $GLOBALS['wpdb'];
        });

        // ------------------------------------------------------------------
        // core:ip_resolver — must be registered before core:audit_logger
        // ------------------------------------------------------------------
        if (!class_exists(IpResolver::class)) {
            return;
        }
        $container->singleton('core:ip_resolver', static function (): IpResolver {
            return IpResolver::fromConfig();
        });

        // ------------------------------------------------------------------
        // core:cipher — SodiumCipher(onDecryptCallback=null)
        // Consumed by: WpdbAgenteRepository, AgenteHydrator
        // ------------------------------------------------------------------
        if (!class_exists(SodiumCipher::class)) {
            return;
        }
        $container->singleton('core:cipher', static function (): SodiumCipher {
            return new SodiumCipher();
        });

        // ------------------------------------------------------------------
        // core:key_manager — static helper, exposed as instance for DI
        // Consumed by: admin health-check (W9-B)
        // ------------------------------------------------------------------
        if (!class_exists(KeyManager::class)) {
            return;
        }
        $container->singleton('core:key_manager', static function (): KeyManager {
            return new KeyManager();
        });

        // ------------------------------------------------------------------
        // core:audit_logger — depends on core:wpdb + core:ip_resolver
        // Consumed by: every repository that audits writes
        // ------------------------------------------------------------------
        if (!class_exists(AuditLogger::class)) {
            return;
        }
        $container->singleton('core:audit_logger', static function (Container $c): AuditLogger {
            return new AuditLogger(
                $c->get('core:wpdb'),
                $c->get('core:ip_resolver')
            );
        });

        // ------------------------------------------------------------------
        // core:access_tracker — depends on core:audit_logger
        // Consumed by: AgenteHydrator (tracks decrypt events)
        // ------------------------------------------------------------------
        if (!class_exists(AccessTracker::class)) {
            return;
        }
        $container->singleton('core:access_tracker', static function (Container $c): AccessTracker {
            return new AccessTracker($c->get('core:audit_logger'));
        });

        // ------------------------------------------------------------------
        // core:pii_masker — pure static class; register FQCN as string
        // Consumers call PiiMasker::maskEmail() etc. via the FQCN string
        // ------------------------------------------------------------------
        if (!class_exists(PiiMasker::class)) {
            return;
        }
        $container->singleton('core:pii_masker', static function (): string {
            return PiiMasker::class;
        });

        // ------------------------------------------------------------------
        // core:secure_logger — PiiMasker-aware wrapper over error_log
        // Consumed by: application handlers (W9-C, W9-D)
        // ------------------------------------------------------------------
        if (!class_exists(SecureLogger::class)) {
            return;
        }
        $container->singleton('core:secure_logger', static function (): SecureLogger {
            return new SecureLogger();
        });

        // ------------------------------------------------------------------
        // core:json — pure static class; register FQCN as string
        // Consumers call Json::encode() / Json::decode() via FQCN
        // ------------------------------------------------------------------
        if (!class_exists(Json::class)) {
            return;
        }
        $container->singleton('core:json', static function (): string {
            return Json::class;
        });

        // ------------------------------------------------------------------
        // core:request_helper — pure static class; register FQCN as string
        // Consumed by: REST controllers, shortcode handlers
        // ------------------------------------------------------------------
        if (!class_exists(RequestHelper::class)) {
            return;
        }
        $container->singleton('core:request_helper', static function (): string {
            return RequestHelper::class;
        });

        // ------------------------------------------------------------------
        // core:rate_limiter — pure static class; register FQCN as string
        // Consumed by: REST controllers (W9-C)
        // ------------------------------------------------------------------
        if (!class_exists(RateLimiter::class)) {
            return;
        }
        $container->singleton('core:rate_limiter', static function (): string {
            return RateLimiter::class;
        });

        // ------------------------------------------------------------------
        // core:uuid — pure static class; register FQCN as string
        // Consumed by: PrivateFileStorage, application handlers
        // ------------------------------------------------------------------
        if (!class_exists(UuidGenerator::class)) {
            return;
        }
        $container->singleton('core:uuid', static function (): string {
            return UuidGenerator::class;
        });

        // ------------------------------------------------------------------
        // core:sequence_gen — depends on core:wpdb
        // Consumed by: WpdbAgenteRepository (numero_registro allocation)
        // ------------------------------------------------------------------
        if (!class_exists(SequenceGenerator::class)) {
            return;
        }
        $container->singleton('core:sequence_gen', static function (Container $c): SequenceGenerator {
            return new SequenceGenerator($c->get('core:wpdb'));
        });

        // ------------------------------------------------------------------
        // storage:private_files — depends on core:audit_logger
        // Consumed by: documento upload handlers (W9-C)
        // ------------------------------------------------------------------
        if (!class_exists(PrivateFileStorage::class)) {
            return;
        }
        $container->singleton('storage:private_files', static function (Container $c): PrivateFileStorage {
            return new PrivateFileStorage($c->get('core:audit_logger'));
        });

        // ------------------------------------------------------------------
        // hasher:eleitor — depends on PI_VOTING_SECRET constant
        // Consumed by: RegistrarVotoHandler (W9-D)
        // ------------------------------------------------------------------
        if (!class_exists(EleitorHasher::class)) {
            return;
        }
        $container->singleton('hasher:eleitor', static function (): EleitorHasher {
            return new EleitorHasher();
        });
    }
}
