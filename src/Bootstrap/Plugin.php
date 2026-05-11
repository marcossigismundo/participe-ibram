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

        // CRÍTICO: wire admin menus during plugins_loaded (NOT admin_init).
        // admin_menu fires BEFORE admin_init in WP admin lifecycle, então
        // chamar add_action('admin_menu', ...) a partir de initAdmin() seria
        // tarde demais. Isto roda agora porque boot() é invocado em
        // plugins_loaded priority 5.
        if (is_admin()) {
            $this->wireAdminMenus();
        }

        // Presentation entry points (admin, public, REST). They are stubs
        // for Wave 1 and will be filled by later waves.
        add_action('admin_init', [$this, 'initAdmin']);
        add_action('init', [$this, 'initPublic'], 20);
        add_action('rest_api_init', [$this, 'initRest']);
    }

    /**
     * Wire admin menus before admin_menu fires.
     *
     * Registries com dependências de controllers (MenuRegistry, Edital, Recurso,
     * Habilitacao, Votacao, Audit, Ajuda) precisam ser wireadas no Container
     * primeiro — fica para Wave 10. Esta versão registra apenas o top-level menu
     * + Setup de Teste (sem dependências).
     */
    private function wireAdminMenus(): void
    {
        // Top-level menu (parent slug 'participe-ibram') — outros submenus
        // dependem deste. Capability mínima `pi_listar_cadastros` permite que
        // todas as roles staff vejam o menu; pi_agente NÃO vê (acessa via
        // shortcode `[pi_minha_conta]` no front-end).
        add_action('admin_menu', [$this, 'registerTopLevelMenu'], 5);

        // Wave 4-C: admin de e-mail (menu de submenu próprio + AJAX).
        // Movido de initAdmin() para aqui — admin_menu callbacks precisam
        // estar registrados antes do hook disparar.
        if (class_exists(EmailRegistration::class)) {
            EmailRegistration::bootAdmin($this->container);
        }

        // Wave 8.5: Setup de Teste (registry static, sem dependências).
        if (class_exists('Ibram\\ParticipeIbram\\Presentation\\Admin\\SetupTeste\\SetupTesteMenuRegistry')) {
            \Ibram\ParticipeIbram\Presentation\Admin\SetupTeste\SetupTesteMenuRegistry::register();
        }
    }

    /**
     * Registra o menu top-level "Participe Ibram" no WordPress admin.
     */
    public function registerTopLevelMenu(): void
    {
        if (!function_exists('add_menu_page')) {
            return;
        }

        \add_menu_page(
            __('Participe Ibram', 'participe-ibram'),
            __('Participe Ibram', 'participe-ibram'),
            'pi_listar_cadastros',
            'participe-ibram',
            [$this, 'renderRootStub'],
            'dashicons-groups',
            26
        );

        // Renomeia o primeiro submenu auto-criado para "Painel".
        \add_submenu_page(
            'participe-ibram',
            __('Painel', 'participe-ibram'),
            __('Painel', 'participe-ibram'),
            'pi_listar_cadastros',
            'participe-ibram',
            [$this, 'renderRootStub']
        );
    }

    /**
     * Renderiza a página raiz "Painel". Stub que orienta o usuário até wave 10
     * wirar os controllers completos.
     */
    public function renderRootStub(): void
    {
        if (!function_exists('current_user_can') || !current_user_can('pi_listar_cadastros')) {
            wp_die(esc_html__('Sem permissão.', 'participe-ibram'), 403);
        }
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Participe Ibram', 'participe-ibram') . '</h1>';
        echo '<p>' . esc_html__('Plataforma federal de Cadastro de Agentes para Participação Social do Ibram (Portaria 3230/2024).', 'participe-ibram') . '</p>';
        echo '<div class="notice notice-info"><p><strong>' . esc_html__('Em desenvolvimento:', 'participe-ibram') . '</strong> ' . esc_html__('os painéis específicos (Cadastros, Editais, Recursos, Votações, Auditoria) serão wireados na Onda 10. Por enquanto, comece pelo "Setup de Teste" no submenu para popular dados de teste.', 'participe-ibram') . '</p></div>';
        echo '</div>';
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

        // EmailRegistration::bootAdmin e SetupTesteMenuRegistry::register
        // foram movidos para wireAdminMenus() (chamado em boot()) — admin_init
        // dispara DEPOIS de admin_menu, então registrar callbacks aqui era
        // tarde demais.

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
