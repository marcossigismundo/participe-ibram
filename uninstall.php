<?php
/**
 * Uninstall handler for Participe Ibram.
 *
 * Default policy: do NOT delete cadastro/edital/votação data on uninstall.
 * Records may be subject to retention obligations under LGPD legal bases
 * (cumprimento de obrigação legal, exercício regular de direitos, políticas
 * públicas). To purge data deliberately, run:
 *
 *     wp pi purge --confirm
 *
 * This script only removes plugin settings (`pi_*` options) and the platform
 * roles created by the activator.
 *
 * @package Ibram\ParticipeIbram
 */

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// 1) Remove plugin settings options (pi_* config only).
global $wpdb;

if (isset($wpdb)) {
    /*
     * Use a single prepared statement. We delete options whose name starts
     * with `pi_settings_` or `pi_config_` (settings keys reserved for
     * Participe Ibram). Domain options must use a different prefix to avoid
     * accidental purge here.
     */
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            $wpdb->esc_like('pi_settings_') . '%',
            $wpdb->esc_like('pi_config_') . '%'
        )
    );

    // Also remove transients used by the plugin (rate limiting, oidc state).
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            '_transient_pi_%',
            '_transient_timeout_pi_%'
        )
    );
}

// 2) Remove platform roles (delegated to Activator if available).
$activator = 'Ibram\\ParticipeIbram\\Bootstrap\\Activator';
if (!class_exists($activator)) {
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
    } else {
        $candidate = __DIR__ . '/src/Bootstrap/Activator.php';
        if (is_readable($candidate)) {
            require_once $candidate;
        }
    }
}
if (class_exists($activator) && method_exists($activator, 'removeRoles')) {
    /** @psalm-suppress MixedMethodCall */
    $activator::removeRoles();
}
