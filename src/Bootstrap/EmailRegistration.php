<?php
/**
 * Wave 4-C: registra serviços de E-mail no container e liga os hooks WP.
 *
 * @package Ibram\ParticipeIbram\Bootstrap
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Bootstrap;

use Ibram\ParticipeIbram\Application\Consentimento\RevogarConsentimentoHandler;
use Ibram\ParticipeIbram\Application\Email\EmailQueueWorker;
use Ibram\ParticipeIbram\Application\Email\EnfileirarEmailHandler;
use Ibram\ParticipeIbram\Application\Email\EventListeners;
use Ibram\ParticipeIbram\Application\Email\SmtpConfig;
use Ibram\ParticipeIbram\Application\Email\Templates\EmailRenderer;
use Ibram\ParticipeIbram\Application\Email\Templates\UnsubscribeTokenizer;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Core\Encryption\SodiumCipher;
use Ibram\ParticipeIbram\Core\Logger\SecureLogger;
use Ibram\ParticipeIbram\Domain\Email\EmailQueueRepository;
use Ibram\ParticipeIbram\Infrastructure\Repository\WpdbEmailQueueRepository;
use Ibram\ParticipeIbram\Presentation\Admin\Ajax\EmailAdminAjax;
use Ibram\ParticipeIbram\Presentation\Admin\Controllers\EmailController;
use Ibram\ParticipeIbram\Presentation\Public\Controllers\UnsubscribeController;

/**
 * Compõe os serviços do módulo de e-mail e os registra nos hooks WP
 * (admin_menu, init para cron + listeners + unsubscribe, phpmailer_init).
 *
 * Defensivo: nada falha quando alguma dependência (e.g. wpdb) ainda não está
 * disponível. Útil em testes e em cenários de boot precoce.
 */
final class EmailRegistration
{
    public static function register(Container $container): void
    {
        // ---------- Repositórios / VOs / Renderer ----------
        $container->singleton('email.queue.repository', static function (Container $c): EmailQueueRepository {
            global $wpdb;
            if (!isset($wpdb)) {
                throw new \RuntimeException('wpdb indisponivel para EmailQueueRepository.');
            }
            return new WpdbEmailQueueRepository($wpdb);
        });

        $container->singleton('email.renderer', static function (Container $c): EmailRenderer {
            return new EmailRenderer(\PI_PLUGIN_DIR . 'templates/emails');
        });

        $container->singleton('email.tokenizer', static function (Container $c): UnsubscribeTokenizer {
            return new UnsubscribeTokenizer();
        });

        $container->singleton('email.logger', static function (Container $c): SecureLogger {
            return new SecureLogger();
        });

        // ---------- Cipher (compartilhado com Wave 2/3 quando registrado) ----------
        $container->singleton('email.cipher', static function (Container $c): SodiumCipher {
            if ($c->has('encryption.cipher')) {
                $shared = $c->get('encryption.cipher');
                if ($shared instanceof SodiumCipher) {
                    return $shared;
                }
            }
            return new SodiumCipher();
        });

        $container->singleton('email.smtp', static function (Container $c): SmtpConfig {
            return new SmtpConfig($c->get('email.cipher'), $c->get('email.logger'));
        });

        // ---------- Application services ----------
        $container->singleton('email.enfileirar', static function (Container $c): EnfileirarEmailHandler {
            return new EnfileirarEmailHandler(
                $c->get('email.queue.repository'),
                $c->get('email.renderer'),
                $c->get('email.logger'),
                $c->has('email.broadcast.query') ? $c->get('email.broadcast.query') : null
            );
        });

        $container->singleton('email.worker', static function (Container $c): EmailQueueWorker {
            return new EmailQueueWorker(
                $c->get('email.queue.repository'),
                $c->get('email.logger')
            );
        });

        $container->singleton('email.event_listeners', static function (Container $c): EventListeners {
            return new EventListeners(
                $c->get('email.enfileirar'),
                $c->get('repo:agente'),
                $c->get('email.tokenizer'),
                $c->get('email.logger'),
                $c->has('repo:agente_detalhes_loader') ? $c->get('repo:agente_detalhes_loader') : null
            );
        });

        // ---------- Presentation: Admin ----------
        $container->singleton('email.admin.controller', static function (Container $c): EmailController {
            return new EmailController(
                $c->get('email.smtp'),
                $c->get('email.queue.repository'),
                $c->get('email.renderer'),
                \PI_PLUGIN_DIR . 'templates/emails'
            );
        });

        $container->singleton('email.admin.ajax', static function (Container $c): EmailAdminAjax {
            return new EmailAdminAjax(
                $c->get('email.smtp'),
                $c->get('email.queue.repository'),
                $c->get('core:audit_logger'),
                $c->get('email.logger')
            );
        });

        // ---------- Presentation: Public ----------
        $container->singleton('email.public.unsubscribe', static function (Container $c): UnsubscribeController {
            $revogarHandler = new RevogarConsentimentoHandler(
                $c->get('repo:consentimento'),
                $c->get('core:audit_logger')
            );
            return new UnsubscribeController(
                $c->get('email.tokenizer'),
                $revogarHandler,
                $c->get('repo:agente'),
                $c->get('core:audit_logger'),
                $c->get('email.logger'),
                \PI_PLUGIN_DIR . 'templates/public/unsubscribe.php'
            );
        });
    }

    /**
     * Liga os hooks WP. Chamar a partir do Plugin::boot via init/admin_init.
     */
    public static function boot(Container $container): void
    {
        // Listeners ouvem hooks de domínio — registrar cedo (init).
        try {
            $listeners = $container->get('email.event_listeners');
            if (method_exists($listeners, 'registerHooks')) {
                $listeners->registerHooks();
            }
        } catch (\Throwable $e) {
            // graceful: módulo ainda não disponível
        }

        // Worker WP-Cron — registrado em init.
        try {
            $worker = $container->get('email.worker');
            if (method_exists($worker, 'registerHooks')) {
                $worker->registerHooks();
            }
        } catch (\Throwable $e) {
        }

        // SMTP phpmailer_init.
        try {
            $smtp = $container->get('email.smtp');
            if (method_exists($smtp, 'registerHooks')) {
                $smtp->registerHooks();
            }
        } catch (\Throwable $e) {
        }

        // Unsubscribe público.
        try {
            $unsub = $container->get('email.public.unsubscribe');
            if (method_exists($unsub, 'registerHooks')) {
                $unsub->registerHooks();
            }
        } catch (\Throwable $e) {
        }
    }

    /**
     * Liga os hooks de admin (admin_menu + AJAX). Chamar em admin_init.
     */
    public static function bootAdmin(Container $container): void
    {
        try {
            $controller = $container->get('email.admin.controller');
            if (method_exists($controller, 'registerHooks')) {
                $controller->registerHooks();
            }
        } catch (\Throwable $e) {
        }

        try {
            $ajax = $container->get('email.admin.ajax');
            if (method_exists($ajax, 'registerHooks')) {
                $ajax->registerHooks();
            }
        } catch (\Throwable $e) {
        }
    }
}
