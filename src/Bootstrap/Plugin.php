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

        // Wave 9 W9-D: registra event listeners e crons (init priority 10).
        if (class_exists('Ibram\\ParticipeIbram\\Bootstrap\\EventListenerRegistration')) {
            add_action('init', function (): void {
                \Ibram\ParticipeIbram\Bootstrap\EventListenerRegistration::register($this->container);
                if (method_exists('Ibram\\ParticipeIbram\\Bootstrap\\EventListenerRegistration', 'boot')) {
                    \Ibram\ParticipeIbram\Bootstrap\EventListenerRegistration::boot($this->container);
                }
            }, 10);
        }
        if (class_exists('Ibram\\ParticipeIbram\\Bootstrap\\CronRegistration')) {
            add_action('init', function (): void {
                \Ibram\ParticipeIbram\Bootstrap\CronRegistration::register($this->container);
                if (method_exists('Ibram\\ParticipeIbram\\Bootstrap\\CronRegistration', 'boot')) {
                    \Ibram\ParticipeIbram\Bootstrap\CronRegistration::boot($this->container);
                }
            }, 5);
        }

        // CRÍTICO: wire admin menus during plugins_loaded (NOT admin_init).
        // admin_menu fires BEFORE admin_init in WP admin lifecycle.
        if (is_admin()) {
            $this->wireAdminMenus();
        }

        // Public layer (shortcodes + assets).
        if (class_exists('Ibram\\ParticipeIbram\\Bootstrap\\PublicRegistration')) {
            \Ibram\ParticipeIbram\Bootstrap\PublicRegistration::register($this->container);
        }
        if (class_exists('Ibram\\ParticipeIbram\\Bootstrap\\AssetRegistration')) {
            \Ibram\ParticipeIbram\Bootstrap\AssetRegistration::register($this->container);
        }

        // REST endpoints (W9-C) — hookam rest_api_init internamente.
        if (class_exists('Ibram\\ParticipeIbram\\Bootstrap\\RestRegistration')) {
            \Ibram\ParticipeIbram\Bootstrap\RestRegistration::register($this->container);
        }

        // Presentation entry points (legacy stubs, mantidos para extensão).
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
        // Top-level menu fallback. AdminRegistration (W9-B) registra o top-level
        // completo via MenuRegistry. Se W9-B classe não existir, mantém este stub.
        $hasAdminRegistration = class_exists('Ibram\\ParticipeIbram\\Bootstrap\\AdminRegistration');
        if (!$hasAdminRegistration) {
            add_action('admin_menu', [$this, 'registerTopLevelMenu'], 5);
        }

        // Wave 4-C: admin de e-mail (menu próprio + AJAX).
        if (class_exists(EmailRegistration::class)) {
            EmailRegistration::bootAdmin($this->container);
        }

        // Wave 9 W9-B: TODOS os menus admin (Cadastros, Editais, Recursos,
        // Habilitações, Votações, Auditoria, Ajuda) + Controllers + AJAX
        // handlers. ESTA É A ONDA DE INTEGRAÇÃO PRINCIPAL.
        if ($hasAdminRegistration) {
            \Ibram\ParticipeIbram\Bootstrap\AdminRegistration::register($this->container);
        }

        // Wave 8.5: Setup de Teste (registry static, sem dependências).
        if (class_exists('Ibram\\ParticipeIbram\\Presentation\\Admin\\SetupTeste\\SetupTesteMenuRegistry')) {
            \Ibram\ParticipeIbram\Presentation\Admin\SetupTeste\SetupTesteMenuRegistry::register();
        }

        // Wave 11 hot-fix: separadores visuais de grupo no submenu lateral.
        // WP admin só suporta 2 níveis; injetamos cabeçalhos via CSS no
        // ::before do primeiro item de cada grupo da IA W11-A.
        add_action('admin_head', [$this, 'printSubmenuGroupSeparators']);
    }

    /**
     * Imprime CSS que adiciona títulos de grupo visuais (ANÁLISE, EDITAIS &
     * HABILITAÇÕES, VOTAÇÕES, CONFORMIDADE & LGPD, FERRAMENTAS) antes do
     * primeiro item de cada grupo no submenu Participe Ibram.
     *
     * Hooked em admin_head. CSS-only, sem JS — usa pseudo-elemento ::before
     * com selectors de atributo href$=.
     */
    public function printSubmenuGroupSeparators(): void
    {
        // Cada item: [slug-do-primeiro-item-do-grupo, label-do-cabeçalho]
        $groups = [
            ['participe-ibram_cadastros',   __('ANÁLISE DE CADASTROS',   'participe-ibram')],
            ['participe-ibram_editais',     __('EDITAIS & HABILITAÇÕES', 'participe-ibram')],
            ['participe-ibram_votacoes',    __('VOTAÇÕES',                'participe-ibram')],
            ['participe-ibram_audit_log',   __('CONFORMIDADE & LGPD',     'participe-ibram')],
            ['participe-ibram_setup_teste', __('FERRAMENTAS',             'participe-ibram')],
        ];

        echo '<style id="pi-admin-menu-groups">' . "\n";
        foreach ($groups as [$slug, $label]) {
            $sel = '#toplevel_page_participe-ibram .wp-submenu a[href$="page='
                 . esc_attr($slug) . '"]';
            // 1) Cria espaço visual antes do <a>
            echo $sel . ' { position: relative; margin-top: 18px !important; }' . "\n";
            // 2) Header textual via ::before
            echo $sel . '::before {' . "\n"
               . '  content: "' . esc_attr($label) . '";' . "\n"
               . '  position: absolute;' . "\n"
               . '  top: -16px;' . "\n"
               . '  left: 0; right: 0;' . "\n"
               . '  padding: 4px 12px 2px;' . "\n"
               . '  font-size: 10px;' . "\n"
               . '  font-weight: 600;' . "\n"
               . '  letter-spacing: .06em;' . "\n"
               . '  color: #8c8f94;' . "\n"
               . '  border-top: 1px solid rgba(255,255,255,.06);' . "\n"
               . '  pointer-events: none;' . "\n"
               . '}' . "\n";
        }
        echo '</style>' . "\n";
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
     * Renderiza a página raiz "Painel" (W11-A).
     *
     * Delega para {@see PainelRenderer::render()} que coleta KPIs do plugin
     * (cadastros, editais, recursos, votações, LGPD, fila de e-mail) e produz
     * uma página DSGov-themed com CTA role-aware. Cai num fallback texto-puro
     * se a classe não estiver presente (boot incompleto).
     */
    public function renderRootStub(): void
    {
        if (!function_exists('current_user_can') || !current_user_can('pi_listar_cadastros')) {
            wp_die(esc_html__('Sem permissão.', 'participe-ibram'), 403);
        }

        if (class_exists('Ibram\\ParticipeIbram\\Presentation\\Admin\\Support\\PainelRenderer')) {
            \Ibram\ParticipeIbram\Presentation\Admin\Support\PainelRenderer::render();
            return;
        }

        // Fallback defensivo se o autoloader ainda não carregou a classe.
        echo '<div class="participe-ibram-scope wrap">';
        echo '<h1>' . esc_html__('Painel — Participe Ibram', 'participe-ibram') . '</h1>';
        echo '<p>' . esc_html__('Plataforma federal de Cadastro de Agentes para Participação Social do Ibram (Portaria 3230/2024).', 'participe-ibram') . '</p>';
        echo '<div class="notice notice-warning"><p>' . esc_html__('Painel indisponível: PainelRenderer não pôde ser carregado.', 'participe-ibram') . '</p></div>';
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

        // Wave 9 W9-A: Core services (cipher, audit, masker, ip, etc.),
        // Repositories (todos Wpdb*Repository) e Cross-domain Adapters.
        // 41 service IDs registrados aqui — fundação para outras Registrations.
        if (class_exists('Ibram\\ParticipeIbram\\Bootstrap\\CoreRegistration')) {
            \Ibram\ParticipeIbram\Bootstrap\CoreRegistration::register($container);
        }
        if (class_exists('Ibram\\ParticipeIbram\\Bootstrap\\RepositoryRegistration')) {
            \Ibram\ParticipeIbram\Bootstrap\RepositoryRegistration::register($container);
        }
        if (class_exists('Ibram\\ParticipeIbram\\Bootstrap\\AdaptersRegistration')) {
            \Ibram\ParticipeIbram\Bootstrap\AdaptersRegistration::register($container);
        }

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
