<?php
/**
 * SetupTesteMenuRegistry — registra o submenu "Setup de Teste" sob "Participe Ibram".
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin\SetupTeste
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Admin\SetupTeste;

/**
 * Registra a página de Setup de Teste no admin do WordPress.
 *
 * Cap: pi_administrador OU manage_options (defesa em profundidade — admin WP
 * puro também acessa caso o activator ainda não tenha rodado).
 */
final class SetupTesteMenuRegistry
{
    public const SLUG = 'participe-ibram_setup_teste';

    /** Capability primária (role pi_administrador). */
    public const CAP = 'pi_administrador';

    /**
     * Registra hooks. Chamado uma única vez pelo Plugin bootstrap.
     */
    public static function register(): void
    {
        if (!function_exists('add_action')) {
            return;
        }

        add_action('admin_menu', [self::class, 'registerMenu'], 99);
        add_action('admin_init', [self::class, 'handlePost']);
    }

    /**
     * Adiciona o submenu. Prioridade 99 → aparece por último.
     */
    public static function registerMenu(): void
    {
        if (!function_exists('add_submenu_page')) {
            return;
        }

        // Determina a cap efetiva para o contexto atual.
        $cap = self::effectiveCap();

        add_submenu_page(
            'participe-ibram',                          // parent slug
            __('Setup de Teste', 'participe-ibram'),    // page title
            __('Setup de Teste', 'participe-ibram'),    // menu title
            $cap,
            self::SLUG,
            [self::class, 'render']
        );
    }

    /**
     * Renderiza a página chamando o controller.
     */
    public static function render(): void
    {
        $controller = new SetupTesteController();
        $controller->render();
    }

    /**
     * Processa POST actions de cada card.
     */
    public static function handlePost(): void
    {
        if (!isset($_POST['pi_setup_action'])) {
            return;
        }

        // Cap check antes de qualquer processamento.
        if (!function_exists('current_user_can')) {
            return;
        }
        if (!current_user_can(self::effectiveCap())) {
            wp_die(
                esc_html__('Sem permissão.', 'participe-ibram'),
                403
            );
        }

        $controller = new SetupTesteController();
        $controller->handlePost();
    }

    /**
     * Retorna pi_administrador se a role existe, senão manage_options.
     *
     * Permite que um admin WordPress puro acesse antes do activator rodar.
     */
    public static function effectiveCap(): string
    {
        if (!function_exists('current_user_can')) {
            return self::CAP;
        }
        if (current_user_can(self::CAP)) {
            return self::CAP;
        }
        // Fallback defensivo.
        return 'manage_options';
    }
}
