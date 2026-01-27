<?php
/**
 * Plugin Name: CRM Developer - Gestão de Relacionamento
 * Plugin URI: https://exemplo.com/crm-developer
 * Description: Sistema completo de CRM para gestão de relacionamento com participantes de conferências, eventos e mobilização social.
 * Version: 1.0.0
 * Author: Developer
 * Author URI: https://exemplo.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: crm-developer
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Impede acesso direto
if (!defined('ABSPATH')) {
    exit;
}

// Constantes do plugin
define('CRM_DEV_VERSION', '1.0.0');
define('CRM_DEV_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CRM_DEV_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CRM_DEV_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Classe principal do plugin
 */
class CRM_Developer {

    /**
     * Instância única (Singleton)
     */
    private static $instance = null;

    /**
     * Retorna a instância única
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Carrega dependências
     */
    private function load_dependencies() {
        // Classes do core
        require_once CRM_DEV_PLUGIN_DIR . 'includes/class-database.php';
        require_once CRM_DEV_PLUGIN_DIR . 'includes/class-contacts.php';
        require_once CRM_DEV_PLUGIN_DIR . 'includes/class-interactions.php';
        require_once CRM_DEV_PLUGIN_DIR . 'includes/class-import-export.php';
        require_once CRM_DEV_PLUGIN_DIR . 'includes/class-dashboard.php';
        require_once CRM_DEV_PLUGIN_DIR . 'includes/class-helpers.php';
        require_once CRM_DEV_PLUGIN_DIR . 'includes/class-email.php';

        // Admin
        if (is_admin()) {
            require_once CRM_DEV_PLUGIN_DIR . 'admin/class-admin.php';
        }

        // Frontend público
        require_once CRM_DEV_PLUGIN_DIR . 'public/class-public.php';
    }

    /**
     * Inicializa hooks
     */
    private function init_hooks() {
        // Ativação e desativação
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Inicialização
        add_action('init', array($this, 'init'));
        add_action('plugins_loaded', array($this, 'load_textdomain'));

        // Scripts e estilos
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'public_scripts'));

        // AJAX handlers
        add_action('wp_ajax_crm_dev_get_contacts', array('CRM_Dev_Contacts', 'ajax_get_contacts'));
        add_action('wp_ajax_crm_dev_save_contact', array('CRM_Dev_Contacts', 'ajax_save_contact'));
        add_action('wp_ajax_crm_dev_delete_contact', array('CRM_Dev_Contacts', 'ajax_delete_contact'));
        add_action('wp_ajax_crm_dev_get_contact', array('CRM_Dev_Contacts', 'ajax_get_contact'));
        add_action('wp_ajax_crm_dev_import_contacts', array('CRM_Dev_Import_Export', 'ajax_import'));
        add_action('wp_ajax_crm_dev_export_contacts', array('CRM_Dev_Import_Export', 'ajax_export'));
        add_action('wp_ajax_crm_dev_save_interaction', array('CRM_Dev_Interactions', 'ajax_save_interaction'));
        add_action('wp_ajax_crm_dev_get_interactions', array('CRM_Dev_Interactions', 'ajax_get_interactions'));
        add_action('wp_ajax_crm_dev_get_dashboard_data', array('CRM_Dev_Dashboard', 'ajax_get_data'));
        add_action('wp_ajax_crm_dev_get_report_data', array('CRM_Dev_Dashboard', 'ajax_get_report_data'));

        // Email handlers
        add_action('wp_ajax_crm_dev_get_email_templates', array('CRM_Dev_Email', 'ajax_get_templates'));
        add_action('wp_ajax_crm_dev_save_email_template', array('CRM_Dev_Email', 'ajax_save_template'));
        add_action('wp_ajax_crm_dev_delete_email_template', array('CRM_Dev_Email', 'ajax_delete_template'));
        add_action('wp_ajax_crm_dev_send_mass_email', array('CRM_Dev_Email', 'ajax_send_mass_email'));
        add_action('wp_ajax_crm_dev_get_email_queue', array('CRM_Dev_Email', 'ajax_get_queue'));
        add_action('wp_ajax_crm_dev_preview_email', array('CRM_Dev_Email', 'ajax_preview_email'));
        add_action('wp_ajax_crm_dev_save_email_settings', array('CRM_Dev_Email', 'ajax_save_settings'));
        add_action('wp_ajax_crm_dev_test_smtp', array('CRM_Dev_Email', 'ajax_test_smtp'));
        add_action('wp_ajax_crm_dev_get_email_logs', array('CRM_Dev_Email', 'ajax_get_logs'));

        // AJAX público (sem autenticação)
        add_action('wp_ajax_nopriv_crm_dev_public_register', array('CRM_Dev_Public', 'ajax_register'));
        add_action('wp_ajax_crm_dev_public_register', array('CRM_Dev_Public', 'ajax_register'));
    }

    /**
     * Inicialização
     */
    public function init() {
        // Registra shortcodes
        add_shortcode('crm_cadastro', array('CRM_Dev_Public', 'shortcode_form'));
        add_shortcode('crm_dashboard_publico', array('CRM_Dev_Public', 'shortcode_dashboard'));
    }

    /**
     * Carrega traduções
     */
    public function load_textdomain() {
        load_plugin_textdomain('crm-developer', false, dirname(CRM_DEV_PLUGIN_BASENAME) . '/languages');
    }

    /**
     * Scripts e estilos do admin
     */
    public function admin_scripts($hook) {
        // Apenas nas páginas do plugin
        if (strpos($hook, 'crm-developer') === false && strpos($hook, 'crm_developer') === false) {
            return;
        }

        // CSS
        wp_enqueue_style('crm-dev-admin', CRM_DEV_PLUGIN_URL . 'assets/css/admin.css', array(), CRM_DEV_VERSION);
        wp_enqueue_style('crm-dev-icons', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css', array(), '6.4.0');

        // Chart.js para gráficos
        wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js', array(), '4.4.0', true);

        // SheetJS para Excel
        wp_enqueue_script('sheetjs', 'https://cdn.sheetjs.com/xlsx-0.20.0/package/dist/xlsx.full.min.js', array(), '0.20.0', true);

        // JS do admin
        wp_enqueue_script('crm-dev-admin', CRM_DEV_PLUGIN_URL . 'assets/js/admin.js', array('jquery', 'chartjs', 'sheetjs'), CRM_DEV_VERSION, true);

        // Dados para JS
        wp_localize_script('crm-dev-admin', 'crmDevAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('crm_dev_nonce'),
            'strings' => array(
                'confirmDelete' => __('Tem certeza que deseja excluir este contato?', 'crm-developer'),
                'saving' => __('Salvando...', 'crm-developer'),
                'saved' => __('Salvo com sucesso!', 'crm-developer'),
                'error' => __('Ocorreu um erro. Tente novamente.', 'crm-developer'),
                'importing' => __('Importando...', 'crm-developer'),
                'imported' => __('Importação concluída!', 'crm-developer'),
                'exporting' => __('Exportando...', 'crm-developer'),
            )
        ));
    }

    /**
     * Scripts e estilos públicos
     */
    public function public_scripts() {
        wp_enqueue_style('crm-dev-public', CRM_DEV_PLUGIN_URL . 'assets/css/public.css', array(), CRM_DEV_VERSION);
        wp_enqueue_script('crm-dev-public', CRM_DEV_PLUGIN_URL . 'assets/js/public.js', array('jquery'), CRM_DEV_VERSION, true);

        wp_localize_script('crm-dev-public', 'crmDevPublic', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('crm_dev_public_nonce'),
            'strings' => array(
                'sending' => __('Enviando...', 'crm-developer'),
                'success' => __('Cadastro realizado com sucesso!', 'crm-developer'),
                'error' => __('Ocorreu um erro. Tente novamente.', 'crm-developer'),
            )
        ));
    }

    /**
     * Ativação do plugin
     */
    public function activate() {
        // Cria tabelas
        CRM_Dev_Database::create_tables();

        // Cria roles e capabilities
        $this->create_capabilities();

        // Limpa cache
        flush_rewrite_rules();
    }

    /**
     * Desativação do plugin
     */
    public function deactivate() {
        flush_rewrite_rules();
    }

    /**
     * Cria capabilities
     */
    private function create_capabilities() {
        $admin = get_role('administrator');
        if ($admin) {
            $admin->add_cap('manage_crm_contacts');
            $admin->add_cap('edit_crm_contacts');
            $admin->add_cap('delete_crm_contacts');
            $admin->add_cap('export_crm_contacts');
            $admin->add_cap('import_crm_contacts');
            $admin->add_cap('view_crm_dashboard');
        }
    }
}

// Inicializa o plugin
function crm_developer_init() {
    return CRM_Developer::get_instance();
}
add_action('plugins_loaded', 'crm_developer_init', 0);
