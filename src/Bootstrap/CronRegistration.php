<?php
/**
 * Wave 9-D: registra handlers de WP-Cron no container e nos hooks WP.
 *
 * NÃO toca em Plugin.php — Plugin.php chama CronRegistration::register()
 * e ::boot() nos momentos adequados.
 *
 * Os crons são AGENDADOS em Activator::scheduleCrons() (ativação do plugin).
 * Aqui apenas REGISTRAMOS as callbacks — sem isso o WP-Cron dispara o hook
 * mas nenhum callback ouve.
 *
 * Schedules customizados registrados aqui:
 *  - `pi_every_5_minutes`  (300 s) — EmailQueueWorker usa CRON_SCHEDULE = 'pi_every_5_minutes'
 *  - `pi_dezminutos`       (600 s) — AutoEncerramentoVotacao usa SCHEDULE = 'pi_dezminutos'
 *  - `every_five_minutes`  (300 s) — Activator::scheduleCrons usa este slug
 *  - `every_ten_minutes`   (600 s) — Activator::scheduleCrons usa este slug
 *
 * @package Ibram\ParticipeIbram\Bootstrap
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Bootstrap;

use Ibram\ParticipeIbram\Application\Email\EmailQueueWorker;
use Ibram\ParticipeIbram\Application\Lgpd\Cron\DpoAlertsCron;
use Ibram\ParticipeIbram\Application\Votacao\AbrirVotacaoHandler;
use Ibram\ParticipeIbram\Application\Votacao\Cron\AutoAberturaVotacao;
use Ibram\ParticipeIbram\Application\Votacao\Cron\AutoEncerramentoVotacao;
use Ibram\ParticipeIbram\Application\Votacao\EncerrarVotacaoHandler;
use Ibram\ParticipeIbram\Presentation\Admin\Cron\RecursoPrazoAlerts;

if (class_exists(CronRegistration::class)) {
    return;
}

/**
 * Compõe e registra os handlers de cron WP no container.
 *
 * Cada cron handler expõe `registerHooks()` que chama `add_action(HOOK, [this, 'run'])`
 * e também `add_filter('cron_schedules', ...)` para schedules customizados.
 */
final class CronRegistration
{
    /**
     * Registra serviços de cron no container. Idempotente.
     * Chamar durante Plugin::registerCoreServices() ou equivalente.
     */
    public static function register(Container $container): void
    {
        // ------------------------------------------------------------------
        // cron:email_worker — EmailQueueWorker
        // Já registrado por EmailRegistration como 'email.worker'.
        // Reutilizamos esse ID; registramos alias apenas se necessário.
        // ------------------------------------------------------------------
        if (class_exists(EmailQueueWorker::class) && !$container->has('cron:email_worker')) {
            $container->singleton('cron:email_worker', static function (Container $c): EmailQueueWorker {
                // Reutiliza instância de EmailRegistration se disponível.
                if ($c->has('email.worker')) {
                    $w = $c->get('email.worker');
                    if ($w instanceof EmailQueueWorker) {
                        return $w;
                    }
                }
                return new EmailQueueWorker(
                    $c->get('email.queue.repository'),
                    $c->has('core:secure_logger') ? $c->get('core:secure_logger') : $c->get('email.logger')
                );
            });
        }

        // ------------------------------------------------------------------
        // cron:recurso_prazo — RecursoPrazoAlerts
        // ------------------------------------------------------------------
        if (class_exists(RecursoPrazoAlerts::class) && !$container->has('cron:recurso_prazo')) {
            $container->singleton('cron:recurso_prazo', static function (Container $c): RecursoPrazoAlerts {
                return new RecursoPrazoAlerts(
                    $c->get('repo:recurso')
                );
            });
        }

        // ------------------------------------------------------------------
        // cron:dpo_alerts — DpoAlertsCron
        // ------------------------------------------------------------------
        if (class_exists(DpoAlertsCron::class) && !$container->has('cron:dpo_alerts')) {
            $container->singleton('cron:dpo_alerts', static function (Container $c): DpoAlertsCron {
                return new DpoAlertsCron(
                    $c->get('core:wpdb'),
                    $c->get('repo:solicitacao_titular'),
                    $c->get('core:audit_logger'),
                    $c->has('core:secure_logger') ? $c->get('core:secure_logger') : $c->get('email.logger')
                );
            });
        }

        // ------------------------------------------------------------------
        // cron:auto_encerramento_votacao — AutoEncerramentoVotacao
        // ------------------------------------------------------------------
        if (class_exists(AutoEncerramentoVotacao::class) && !$container->has('cron:auto_encerramento_votacao')) {
            $container->singleton('cron:auto_encerramento_votacao', static function (Container $c): AutoEncerramentoVotacao {
                return new AutoEncerramentoVotacao(
                    $c->get('core:wpdb'),
                    $c->get('cron:encerrar_votacao_handler'),
                    $c->get('core:audit_logger')
                );
            });
        }

        // ------------------------------------------------------------------
        // cron:encerrar_votacao_handler — EncerrarVotacaoHandler (dep interna)
        // ------------------------------------------------------------------
        if (class_exists(EncerrarVotacaoHandler::class) && !$container->has('cron:encerrar_votacao_handler')) {
            $container->singleton('cron:encerrar_votacao_handler', static function (Container $c): EncerrarVotacaoHandler {
                return new EncerrarVotacaoHandler(
                    $c->get('repo:votacao'),
                    $c->get('repo:voto'),
                    $c->get('core:audit_logger')
                );
            });
        }

        // ------------------------------------------------------------------
        // cron:abrir_votacao_handler — AbrirVotacaoHandler (dep de AutoAberturaVotacao)
        // ------------------------------------------------------------------
        if (class_exists(AbrirVotacaoHandler::class) && !$container->has('cron:abrir_votacao_handler')) {
            $container->singleton('cron:abrir_votacao_handler', static function (Container $c): AbrirVotacaoHandler {
                return new AbrirVotacaoHandler(
                    $c->get('repo:votacao'),
                    $c->get('core:audit_logger')
                );
            });
        }

        // ------------------------------------------------------------------
        // cron:auto_abertura_votacao — AutoAberturaVotacao
        // ------------------------------------------------------------------
        if (class_exists(AutoAberturaVotacao::class) && !$container->has('cron:auto_abertura_votacao')) {
            $container->singleton('cron:auto_abertura_votacao', static function (Container $c): AutoAberturaVotacao {
                return new AutoAberturaVotacao(
                    $c->get('core:wpdb'),
                    $c->get('cron:abrir_votacao_handler'),
                    $c->get('core:audit_logger')
                );
            });
        }
    }

    /**
     * Registra schedules customizados e os callbacks dos crons no WP.
     * Chamar em `init` (após register()).
     *
     * Cada handler é responsável por chamar:
     *   add_filter('cron_schedules', ...) — adiciona intervalo customizado
     *   add_action(HOOK, [$handler, 'run']) — ouve o evento WP-Cron
     *
     * Além disso, garantimos aqui que os schedules usados pelo
     * Activator::scheduleCrons() (`every_five_minutes`, `every_ten_minutes`)
     * estão registrados via cron_schedules antes que o WP precise deles.
     */
    public static function boot(Container $container): void
    {
        // Schedules genéricos que Activator::scheduleCrons() usa.
        // Registra antes dos handlers individuais para que o WP já os conheça.
        if (function_exists('add_filter')) {
            \add_filter('cron_schedules', static function (array $schedules): array {
                if (!isset($schedules['every_five_minutes'])) {
                    $schedules['every_five_minutes'] = [
                        'interval' => 300,
                        'display'  => 'A cada 5 minutos',
                    ];
                }
                if (!isset($schedules['every_ten_minutes'])) {
                    $schedules['every_ten_minutes'] = [
                        'interval' => 600,
                        'display'  => 'A cada 10 minutos',
                    ];
                }
                return $schedules;
            }, 5); // prioridade 5 — antes dos handlers
        }

        // ------------------------------------------------------------------
        // EmailQueueWorker — hook: pi_email_queue_tick / schedule: pi_every_5_minutes
        // O worker já registra seu próprio schedule (`pi_every_5_minutes`) e
        // o callback `tick` via registerHooks(). Chamamos aqui para garantir
        // registro mesmo que EmailRegistration::boot() não tenha sido chamado.
        // ------------------------------------------------------------------
        if ($container->has('cron:email_worker')) {
            try {
                $worker = $container->get('cron:email_worker');
                if (method_exists($worker, 'registerHooks')) {
                    $worker->registerHooks();
                }
            } catch (\Throwable $e) {
                // graceful degradation
            }
        }

        // ------------------------------------------------------------------
        // RecursoPrazoAlerts — hook: pi_recurso_prazo_check / schedule: daily
        // ------------------------------------------------------------------
        if ($container->has('cron:recurso_prazo')) {
            try {
                $recurso = $container->get('cron:recurso_prazo');
                if (method_exists($recurso, 'registerHooks')) {
                    $recurso->registerHooks();
                }
            } catch (\Throwable $e) {
                // graceful degradation
            }
        }

        // ------------------------------------------------------------------
        // DpoAlertsCron — hook: pi_dpo_alerts_check / schedule: daily
        // ------------------------------------------------------------------
        if ($container->has('cron:dpo_alerts')) {
            try {
                $dpo = $container->get('cron:dpo_alerts');
                if (method_exists($dpo, 'registerHooks')) {
                    $dpo->registerHooks();
                }
            } catch (\Throwable $e) {
                // graceful degradation
            }
        }

        // ------------------------------------------------------------------
        // AutoEncerramentoVotacao — hook: pi_votacao_auto_encerrar / schedule: pi_dezminutos
        // O handler também registra `cron_schedules` para `pi_dezminutos`.
        // ------------------------------------------------------------------
        if ($container->has('cron:auto_encerramento_votacao')) {
            try {
                $auto = $container->get('cron:auto_encerramento_votacao');
                if (method_exists($auto, 'registerHooks')) {
                    $auto->registerHooks();
                }
            } catch (\Throwable $e) {
                // graceful degradation
            }
        }

        // ------------------------------------------------------------------
        // AutoAberturaVotacao — hook: pi_votacao_auto_abrir / schedule: pi_dezminutos
        // ------------------------------------------------------------------
        if ($container->has('cron:auto_abertura_votacao')) {
            try {
                $autoAbertura = $container->get('cron:auto_abertura_votacao');
                if (method_exists($autoAbertura, 'registerHooks')) {
                    $autoAbertura->registerHooks();
                }
            } catch (\Throwable $e) {
                // graceful degradation
            }
        }
    }
}
