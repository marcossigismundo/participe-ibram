<?php
/**
 * AjudaController — renders the Ajuda/Onboarding page.
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\Controllers
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\Controllers;

use Ibram\ParticipeIbram\Presentation\Admin\AjudaMenuRegistry;

/**
 * Capability: read — any logged-in WordPress user can see the help pages.
 * No PII is rendered; page is purely informational.
 */
final class AjudaController
{
    /**
     * Render the Ajuda index template with ARIA tabs.
     */
    public function render(): void
    {
        if (!function_exists('current_user_can')
            || !\current_user_can(AjudaMenuRegistry::CAP)
        ) {
            \wp_die(
                function_exists('esc_html__')
                    ? (string) \esc_html__('Você não tem permissão para acessar esta página.', 'participe-ibram')
                    : 'Acesso negado.',
                403
            );
            return;
        }

        $template = $this->templatePath('ajuda/index.php');
        if ($template !== null) {
            include $template;
            return;
        }

        echo '<div class="wrap"><p>' .
            (function_exists('esc_html__')
                ? (string) \esc_html__('Template não encontrado.', 'participe-ibram')
                : 'Template não encontrado.')
            . '</p></div>';
    }

    private function templatePath(string $relative): ?string
    {
        if (\defined('PI_PLUGIN_DIR')) {
            $base = (string) \PI_PLUGIN_DIR;
        } else {
            $base = dirname(__DIR__, 4);
        }
        $candidate = rtrim($base, '/\\') . '/templates/admin/' . ltrim($relative, '/');
        return file_exists($candidate) ? $candidate : null;
    }
}
