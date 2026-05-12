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

    // Pre-flight: o plugin precisa de 6 segredos em wp-config.php para
    // criptografia LGPD, HMAC de busca, anti-rastreio de voto e assinatura
    // de URLs. Sem eles, SodiumCipher/EleitorHasher abortam o admin com
    // fatal. Em vez disso, suprimimos o boot e mostramos um admin_notices
    // explicando o que falta.
    $pi_required = [
        'PI_ENC_KEY_V1',
        'PI_ENC_KEY_CURRENT',
        'PI_HMAC_KEY',
        'PI_IP_PEPPER',
        'PI_VOTING_SECRET',
        'PI_UNSUBSCRIBE_SECRET',
    ];
    $pi_missing = [];
    foreach ($pi_required as $pi_const) {
        if (!defined($pi_const) || (string) constant($pi_const) === '') {
            $pi_missing[] = $pi_const;
        }
    }
    if ($pi_missing !== []) {
        add_action('admin_notices', static function () use ($pi_missing): void {
            if (!function_exists('current_user_can') || !current_user_can('activate_plugins')) {
                return;
            }
            $list = '<ul style="margin:6px 0 6px 18px;list-style:disc;">';
            foreach ($pi_missing as $name) {
                $list .= '<li><code>' . esc_html($name) . '</code></li>';
            }
            $list .= '</ul>';
            $snippet = "define('PI_ENC_KEY_V1',         '<base64 32 bytes>');\n"
                     . "define('PI_ENC_KEY_CURRENT',    'v1');\n"
                     . "define('PI_HMAC_KEY',           '<base64 32 bytes, distinto>');\n"
                     . "define('PI_IP_PEPPER',          '<base64 32 bytes, distinto>');\n"
                     . "define('PI_VOTING_SECRET',      '<base64 32 bytes, distinto>');\n"
                     . "define('PI_UNSUBSCRIBE_SECRET', '<base64 32 bytes, distinto>');";
            echo '<div class="notice notice-error"><p><strong>'
                . esc_html__('Participe Ibram — configuração obrigatória ausente', 'participe-ibram')
                . '</strong></p><p>'
                . esc_html__('O plugin não foi inicializado porque as seguintes constantes não estão definidas em wp-config.php:', 'participe-ibram')
                . '</p>' . $list
                . '<p>'
                . esc_html__('Adicione o bloco abaixo antes de “/* That’s all, stop editing! */”, gerando cada valor com sodium_crypto_secretbox_keygen() ou random_bytes(32) → base64_encode. Cada chave deve ser independente (LGPD R2 §4.6).', 'participe-ibram')
                . '</p><pre style="background:#f6f7f7;padding:10px;border:1px solid #c3c4c7;overflow:auto;">'
                . esc_html($snippet)
                . '</pre></div>';
        });
        return; // não bota o boot — admin permanece utilizável.
    }

    \Ibram\ParticipeIbram\Bootstrap\Plugin::getInstance()->boot();
}, 5);
