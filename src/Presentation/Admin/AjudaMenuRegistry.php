<?php
/**
 * AjudaMenuRegistry — registers the "Ajuda" submenu.
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin;

use Ibram\ParticipeIbram\Presentation\Admin\Controllers\AjudaController;

/**
 * Submenu "Ajuda" under the Participe Ibram top-level menu.
 * Capability: read — any logged-in WP user can view help pages.
 */
final class AjudaMenuRegistry
{
    public const SLUG = 'participe-ibram_ajuda';
    public const CAP  = 'read';

    private AjudaController $controller;

    public function __construct(AjudaController $controller)
    {
        $this->controller = $controller;
    }

    public function registerHooks(): void
    {
        if (!function_exists('add_action')) {
            return;
        }
        \add_action('admin_menu', [$this, 'register']);
    }

    public function register(): void
    {
        if (!function_exists('add_submenu_page')) {
            return;
        }
        \add_submenu_page(
            MenuRegistry::SLUG_ROOT,
            self::translate('Ajuda'),
            self::translate('Ajuda'),
            self::CAP,
            self::SLUG,
            [$this, 'render']
        );
    }

    public function render(): void
    {
        $this->controller->render();
    }

    private static function translate(string $text): string
    {
        return function_exists('__') ? (string) \__($text, 'participe-ibram') : $text;
    }
}
