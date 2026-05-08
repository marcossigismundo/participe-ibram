<?php
/**
 * Plugin Name:       Participe Ibram
 * Plugin URI:        https://www.gov.br/museus/
 * Description:       Plataforma federal de Cadastro de Agentes para Participação Social do Ibram (Portaria 3230/2024).
 * Version:           0.1.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            IBRAM
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       participe-ibram
 * Domain Path:       /languages
 *
 * @package Ibram\ParticipeIbram
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants -----------------------------------------------------------
if (!defined('PI_VERSION')) {
    define('PI_VERSION', '0.1.0');
}
if (!defined('PI_PLUGIN_FILE')) {
    define('PI_PLUGIN_FILE', __FILE__);
}
if (!defined('PI_PLUGIN_DIR')) {
    define('PI_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
if (!defined('PI_PLUGIN_URL')) {
    define('PI_PLUGIN_URL', plugin_dir_url(__FILE__));
}
if (!defined('PI_TEXT_DOMAIN')) {
    define('PI_TEXT_DOMAIN', 'participe-ibram');
}
if (!defined('PI_MIN_PHP')) {
    define('PI_MIN_PHP', '7.4');
}
if (!defined('PI_MIN_WP')) {
    define('PI_MIN_WP', '6.2');
}

// Autoload -------------------------------------------------------------------
$pi_composer_autoload = PI_PLUGIN_DIR . 'vendor/autoload.php';
if (file_exists($pi_composer_autoload)) {
    require_once $pi_composer_autoload;
} else {
    /*
     * PSR-4 fallback for environments without Composer (e.g. shipping a built
     * artifact). Maps `Ibram\ParticipeIbram\` to `src/`.
     */
    spl_autoload_register(static function (string $class): void {
        $prefix   = 'Ibram\\ParticipeIbram\\';
        $base_dir = PI_PLUGIN_DIR . 'src' . DIRECTORY_SEPARATOR;
        $len      = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        $relative = substr($class, $len);
        $file     = $base_dir . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
        if (is_readable($file)) {
            require $file;
        }
    });
}

// Activation / Deactivation hooks -------------------------------------------
register_activation_hook(__FILE__, static function (): void {
    if (class_exists('Ibram\\ParticipeIbram\\Bootstrap\\Activator')) {
        \Ibram\ParticipeIbram\Bootstrap\Activator::activate();
    }
});

register_deactivation_hook(__FILE__, static function (): void {
    if (class_exists('Ibram\\ParticipeIbram\\Bootstrap\\Activator')) {
        \Ibram\ParticipeIbram\Bootstrap\Activator::deactivate();
    }
});

// Boot -----------------------------------------------------------------------
add_action('plugins_loaded', static function (): void {
    if (!class_exists('Ibram\\ParticipeIbram\\Bootstrap\\Plugin')) {
        return;
    }
    \Ibram\ParticipeIbram\Bootstrap\Plugin::getInstance()->boot();
}, 5);
