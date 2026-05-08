<?php
/**
 * Main plugin singleton — wires the DI container and the WordPress hooks.
 *
 * @package Ibram\ParticipeIbram\Bootstrap
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Bootstrap;

/**
 * Plugin orchestration entry point.
 *
 * The Plugin class is intentionally thin: it builds the {@see Container}
 * once, registers the bare-minimum services for Wave 1 and exposes the
 * lifecycle hooks (`init`, `admin_init`, `rest_api_init`). Concrete features
 * are added by later waves through additional service registrations.
 */
final class Plugin
{
    private static ?Plugin $instance = null;

    private Container $container;

    private bool $booted = false;

    /**
     * Singleton accessor.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Constructor — assemble the container with the core service definitions.
     */
    private function __construct()
    {
        $this->container = new Container();
        $this->registerCoreServices($this->container);
    }

    /**
     * Boot the plugin: register hooks and dispatch presentation layer
     * initialisation. Safe to call multiple times.
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }
        $this->booted = true;

        // Translations.
        add_action('init', [$this, 'loadTextDomain'], 1);

        // Wave 4-C: registra listeners de e-mail, worker WP-Cron, SMTP filter
        // e endpoint de unsubscribe.
        if (class_exists(EmailRegistration::class)) {
            add_action('init', function (): void {
                EmailRegistration::boot($this->container);
            }, 9);
        }

        // Presentation entry points (admin, public, REST). They are stubs
        // for Wave 1 and will be filled by later waves.
        add_action('admin_init', [$this, 'initAdmin']);
        add_action('init', [$this, 'initPublic'], 20);
        add_action('rest_api_init', [$this, 'initRest']);
    }

    /**
     * Expose the container so other layers can resolve services.
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Load translation files.
     */
    public function loadTextDomain(): void
    {
        load_plugin_textdomain(
            PI_TEXT_DOMAIN,
            false,
            dirname(plugin_basename(PI_PLUGIN_FILE)) . '/languages'
        );
    }

    /**
     * Initialise admin presentation layer.
     *
     * Wave 1: stub. Wave Admin will register list tables, menu pages and
     * controllers via the container.
     */
    public function initAdmin(): void
    {
        if (!is_admin()) {
            return;
        }

        // Wave 4-C: admin de e-mail (menu + AJAX).
        if (class_exists(EmailRegistration::class)) {
            EmailRegistration::bootAdmin($this->container);
        }

        /**
         * Fires when the admin layer is initialised.
         *
         * @param Container $container DI container.
         */
        do_action('participe_ibram_admin_init', $this->container);
    }

    /**
     * Initialise public (front-end) presentation layer.
     */
    public function initPublic(): void
    {
        if (is_admin() && !wp_doing_ajax()) {
            return;
        }
        /**
         * Fires when the public layer is initialised.
         *
         * @param Container $container DI container.
         */
        do_action('participe_ibram_public_init', $this->container);
    }

    /**
     * Initialise REST controllers.
     */
    public function initRest(): void
    {
        /**
         * Fires when REST endpoints should be registered.
         *
         * @param Container $container DI container.
         */
        do_action('participe_ibram_rest_init', $this->container);
    }

    /**
     * Wire up the core services available since Wave 1.
     */
    private function registerCoreServices(Container $container): void
    {
        // Itself, so consumers can re-resolve services from the container.
        $container->instance('container', $container);

        // Auth registry stub (replaced by Wave Auth).
        AuthRegistration::register($container);

        // Wave 4-C: e-mail (queue, worker, listeners, admin, unsubscribe).
        if (class_exists(EmailRegistration::class)) {
            EmailRegistration::register($container);
        }
    }

    /**
     * Cloning the singleton is forbidden.
     */
    private function __clone()
    {
    }

    /**
     * Unserializing the singleton is forbidden.
     *
     * @throws \LogicException Always.
     */
    public function __wakeup()
    {
        throw new \LogicException('Cannot unserialize singleton.');
    }
}
