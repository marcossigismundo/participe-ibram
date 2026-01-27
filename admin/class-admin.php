<?php
/**
 * Classe de administração
 *
 * @package CRM_Developer
 */

if (!defined('ABSPATH')) {
    exit;
}

class CRM_Dev_Admin {

    /**
     * Página atual
     */
    private static $current_section = 'dashboard';

    /**
     * Seções disponíveis
     */
    private static $sections = array(
        'dashboard' => array(
            'title' => 'Dashboard',
            'icon' => 'fa-chart-pie',
            'capability' => 'view_crm_dashboard'
        ),
        'contacts' => array(
            'title' => 'Contatos',
            'icon' => 'fa-address-book',
            'capability' => 'edit_crm_contacts'
        ),
        'contact-new' => array(
            'title' => 'Novo Contato',
            'icon' => 'fa-user-plus',
            'capability' => 'edit_crm_contacts'
        ),
        'import-export' => array(
            'title' => 'Importar/Exportar',
            'icon' => 'fa-exchange-alt',
            'capability' => 'import_crm_contacts'
        ),
        'reports' => array(
            'title' => 'Relatórios',
            'icon' => 'fa-chart-bar',
            'capability' => 'view_crm_dashboard'
        ),
        'email' => array(
            'title' => 'Email',
            'icon' => 'fa-envelope',
            'capability' => 'manage_options'
        ),
        'settings' => array(
            'title' => 'Configurações',
            'icon' => 'fa-cog',
            'capability' => 'manage_options'
        ),
    );

    /**
     * Construtor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_init', array($this, 'handle_actions'));
    }

    /**
     * Registra menu de administração
     */
    public function register_menu() {
        // Menu principal único - usa manage_options para garantir visibilidade para admins
        add_menu_page(
            __('CRM Developer', 'crm-developer'),
            __('CRM', 'crm-developer'),
            'manage_options',
            'crm-developer',
            array($this, 'render_main_page'),
            'dashicons-groups',
            30
        );
    }

    /**
     * Processa ações
     */
    public function handle_actions() {
        // Excluir contato
        if (isset($_GET['page']) && $_GET['page'] === 'crm-developer' &&
            isset($_GET['action']) && $_GET['action'] === 'delete' &&
            isset($_GET['id']) && isset($_GET['_wpnonce'])) {

            if (wp_verify_nonce($_GET['_wpnonce'], 'delete_contact_' . $_GET['id'])) {
                CRM_Dev_Contacts::delete_contact(intval($_GET['id']));
                wp_safe_redirect(admin_url('admin.php?page=crm-developer&section=contacts&deleted=1'));
                exit;
            }
        }
    }

    /**
     * Renderiza página principal com sidebar
     */
    public function render_main_page() {
        // Determina seção atual
        $section = isset($_GET['section']) ? sanitize_text_field($_GET['section']) : 'dashboard';

        // Verifica se é edição ou visualização de contato
        if ($section === 'contacts' && isset($_GET['action'])) {
            if ($_GET['action'] === 'edit' || $_GET['action'] === 'view') {
                $section = $_GET['action'] === 'edit' ? 'contact-edit' : 'contact-view';
            }
        }

        self::$current_section = $section;

        ?>
        <div class="crm-dev-app">
            <!-- Sidebar -->
            <aside class="crm-dev-sidebar">
                <div class="sidebar-header">
                    <div class="sidebar-logo">
                        <i class="fas fa-users"></i>
                        <span>CRM Developer</span>
                    </div>
                    <span class="sidebar-version">v<?php echo CRM_DEV_VERSION; ?></span>
                </div>

                <nav class="sidebar-nav">
                    <?php foreach (self::$sections as $key => $sec) : ?>
                        <?php if (current_user_can($sec['capability']) || current_user_can('manage_options')) : ?>
                            <a href="<?php echo admin_url('admin.php?page=crm-developer&section=' . $key); ?>"
                               class="nav-item <?php echo (self::$current_section === $key ||
                                   (self::$current_section === 'contact-edit' && $key === 'contacts') ||
                                   (self::$current_section === 'contact-view' && $key === 'contacts')) ? 'active' : ''; ?>">
                                <i class="fas <?php echo esc_attr($sec['icon']); ?>"></i>
                                <span><?php echo esc_html($sec['title']); ?></span>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </nav>

                <div class="sidebar-footer">
                    <div class="sidebar-stats">
                        <?php
                        global $wpdb;
                        $tables = CRM_Dev_Database::get_tables();
                        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$tables['contacts']}'");
                        $total = $table_exists ? $wpdb->get_var("SELECT COUNT(*) FROM {$tables['contacts']}") : 0;
                        ?>
                        <div class="stat-mini">
                            <span class="stat-mini-value"><?php echo number_format_i18n($total); ?></span>
                            <span class="stat-mini-label"><?php _e('Contatos', 'crm-developer'); ?></span>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- Conteúdo Principal -->
            <main class="crm-dev-content">
                <?php $this->render_section($section); ?>
            </main>
        </div>
        <?php
    }

    /**
     * Renderiza seção específica
     */
    private function render_section($section) {
        switch ($section) {
            case 'dashboard':
                include CRM_DEV_PLUGIN_DIR . 'admin/views/dashboard.php';
                break;
            case 'contacts':
                include CRM_DEV_PLUGIN_DIR . 'admin/views/contacts-list.php';
                break;
            case 'contact-new':
            case 'contact-edit':
                include CRM_DEV_PLUGIN_DIR . 'admin/views/contact-form.php';
                break;
            case 'contact-view':
                include CRM_DEV_PLUGIN_DIR . 'admin/views/contact-view.php';
                break;
            case 'import-export':
                include CRM_DEV_PLUGIN_DIR . 'admin/views/import-export.php';
                break;
            case 'reports':
                include CRM_DEV_PLUGIN_DIR . 'admin/views/reports.php';
                break;
            case 'email':
                include CRM_DEV_PLUGIN_DIR . 'admin/views/email.php';
                break;
            case 'settings':
                include CRM_DEV_PLUGIN_DIR . 'admin/views/settings.php';
                break;
            default:
                include CRM_DEV_PLUGIN_DIR . 'admin/views/dashboard.php';
        }
    }

    /**
     * Retorna URL para uma seção
     */
    public static function get_section_url($section, $params = array()) {
        $url = admin_url('admin.php?page=crm-developer&section=' . $section);
        if (!empty($params)) {
            $url .= '&' . http_build_query($params);
        }
        return $url;
    }
}

// Inicializa
new CRM_Dev_Admin();
