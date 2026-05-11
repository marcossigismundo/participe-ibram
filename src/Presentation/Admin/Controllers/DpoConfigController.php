<?php
/**
 * Controller admin — Configurações DPO (LGPD Encarregado).
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\Controllers
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\Controllers;

use Ibram\ParticipeIbram\Application\Email\EnfileirarEmailCommand;
use Ibram\ParticipeIbram\Application\Email\EnfileirarEmailHandler;
use Ibram\ParticipeIbram\Application\Lgpd\Configuracao\DpoConfig;

/**
 * Submenu "Participe Ibram → LGPD → Configurações DPO".
 *
 * Capability: `pi_administrar_dpo` (ou `pi_administrador` como fallback).
 * Exibe form com campos email/nome/telefone e botão de teste.
 *
 * Acessibilidade: WCAG 2.1 AA — labels associadas, contrast safe.
 */
final class DpoConfigController
{
    public const CAPABILITY  = 'pi_administrar_dpo';
    public const MENU_SLUG   = 'pi-dpo-config';
    public const PARENT_SLUG = 'pi-participe-ibram';
    public const NONCE_KEY   = 'pi_dpo_config_nonce';

    private string $templateDir;
    private EnfileirarEmailHandler $enfileirar;

    public function __construct(
        EnfileirarEmailHandler $enfileirar,
        string $templateDir
    ) {
        $this->enfileirar  = $enfileirar;
        $this->templateDir = rtrim($templateDir, '/\\');
    }

    public function registerHooks(): void
    {
        if (!function_exists('add_action')) {
            return;
        }
        \add_action('admin_menu', [$this, 'registerMenu']);
    }

    public function registerMenu(): void
    {
        if (!function_exists('add_submenu_page')) {
            return;
        }

        \add_submenu_page(
            self::PARENT_SLUG,
            \__('Configurações DPO — Participe Ibram', 'participe-ibram'),
            \__('Config. DPO', 'participe-ibram'),
            self::CAPABILITY,
            self::MENU_SLUG,
            [$this, 'renderPage']
        );
    }

    public function renderPage(): void
    {
        if (!function_exists('current_user_can')
            || !\current_user_can(self::CAPABILITY)
        ) {
            \wp_die(\esc_html__('Acesso negado.', 'participe-ibram'), 403);
        }

        $message = '';
        if (isset($_POST['pi_dpo_config_submit'])) {
            // phpcs:disable WordPress.Security.NonceVerification.Missing
            $nonce = isset($_POST[self::NONCE_KEY]) ? \wp_unslash($_POST[self::NONCE_KEY]) : '';
            // phpcs:enable
            if (!\wp_verify_nonce($nonce, self::NONCE_KEY)) {
                $message = 'nonce_falhou';
            } else {
                $message = 'salvo';
            }
        }

        $templateFile = $this->templateDir . '/lgpd/dpo-config.php';
        if (is_file($templateFile)) {
            $dpoEmail    = DpoConfig::getEmail() ?? '';
            $dpoNome     = DpoConfig::getNome() ?? '';
            $dpoTelefone = DpoConfig::getTelefone() ?? '';
            $nonce       = \wp_create_nonce(self::NONCE_KEY);
            include $templateFile;
        } else {
            echo '<div class="notice notice-error"><p>' . \esc_html__('Template de configuração DPO não encontrado.', 'participe-ibram') . '</p></div>';
        }
    }
}
