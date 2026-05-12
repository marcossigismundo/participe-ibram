<?php
/**
 * Wires the AssetEnqueuer into the WordPress hook system.
 *
 * Kept as a standalone class so the Plugin integrator can call it
 * independently of PublicRegistration (e.g. if assets need to be loaded
 * before the rest of the public layer is ready).
 *
 * PublicRegistration also wires AssetEnqueuer as a convenience; callers
 * that use both MUST ensure only one of the two is called, or guard
 * against double-registration via the Container singleton.
 *
 * @package Ibram\ParticipeIbram\Bootstrap
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Bootstrap;

use Ibram\ParticipeIbram\Presentation\Assets\AssetEnqueuer;

/**
 * Registers and wires {@see AssetEnqueuer}.
 *
 * AssetEnqueuer::register() hooks:
 *  - wp_enqueue_scripts  → enqueuePublic()  (CSS/JS for shortcode pages only)
 *  - admin_enqueue_scripts → enqueueAdmin() (CSS/JS for plugin admin screens)
 *  - script_loader_tag   → asModule()       (adds type="module" to JS bundles)
 *
 * Requires:
 *  - PI_PLUGIN_DIR  constant (absolute path to plugin root, with trailing slash)
 *  - PI_PLUGIN_URL  constant (URL to plugin root, with trailing slash)
 *
 * Both constants are defined in participe-ibram.php at plugin load time.
 */
final class AssetRegistration
{
    public static function register(Container $container): void
    {
        if (!class_exists(AssetEnqueuer::class)) {
            return;
        }

        $pluginDir = defined('PI_PLUGIN_DIR') ? (string) \PI_PLUGIN_DIR : '';
        $pluginUrl = defined('PI_PLUGIN_URL') ? (string) \PI_PLUGIN_URL : '';

        if ($pluginDir === '' || $pluginUrl === '') {
            // Constants not yet defined — skip silently; Plugin.php defines them.
            return;
        }

        // Use the Container singleton so PublicRegistration and AssetRegistration
        // share the same instance (second call returns cached singleton).
        $container->singleton('public:asset_enqueuer', static function () use ($pluginDir, $pluginUrl): AssetEnqueuer {
            return new AssetEnqueuer(
                rtrim($pluginDir, '/\\'),
                rtrim($pluginUrl, '/') . '/'
            );
        });

        // register() is idempotent as long as add_action() is not called twice
        // with the same callback. Calling it from a singleton factory is safe.
        $container->get('public:asset_enqueuer')->register();
    }
}
