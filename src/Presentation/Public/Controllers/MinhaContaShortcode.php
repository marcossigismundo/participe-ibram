<?php
/**
 * Registro do shortcode [pi_minha_conta] (área autenticada do agente).
 *
 * @package Ibram\ParticipeIbram\Presentation\Public\Controllers
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Public\Controllers;

/**
 * Registra `[pi_minha_conta]` mapeando para `templates/public/minha-conta/index.php`.
 *
 * O template é responsável por:
 *  - Validar autenticação (redirect login se não logado).
 *  - Consultar o filtro `pi_agente_id_by_user` para resolver o agente atual
 *    (ownership resolvido server-side; o cliente NÃO informa agente_id).
 *  - Renderizar tabs ARIA: Dashboard, Meus dados, Documentos, Privacidade, Histórico.
 *
 * Hook em `init` para garantir disponibilidade no front e em REST.
 */
final class MinhaContaShortcode
{
    private string $templatesDir;

    public function __construct(string $templatesDir)
    {
        $this->templatesDir = rtrim($templatesDir, '/\\');
    }

    public function register(): void
    {
        if (!function_exists('add_shortcode')) {
            return;
        }
        \add_shortcode('pi_minha_conta', [$this, 'render']);
    }

    /**
     * @param array<string,string>|string $atts
     */
    public function render($atts = []): string
    {
        $atts = is_array($atts) ? $atts : [];
        unset($atts); // sem parâmetros: tudo via querystring.

        $path = $this->templatesDir . DIRECTORY_SEPARATOR . 'index.php';
        if (!is_file($path)) {
            return '';
        }

        $api_url    = function_exists('get_rest_url') ? \get_rest_url(null, 'pi/v1') : '/wp-json/pi/v1';
        $rest_nonce = function_exists('wp_create_nonce') ? \wp_create_nonce('wp_rest') : '';
        $is_logado  = function_exists('is_user_logged_in') ? (bool) \is_user_logged_in() : false;
        $login_url  = function_exists('wp_login_url') && function_exists('get_permalink')
            ? \wp_login_url((string) \get_permalink())
            : '#';
        $aba_atual  = self::abaAtualFromRequest();

        $vars = compact('api_url', 'rest_nonce', 'is_logado', 'login_url', 'aba_atual');

        extract($vars, EXTR_SKIP); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
        ob_start();
        include $path;

        return (string) ob_get_clean();
    }

    private static function abaAtualFromRequest(): string
    {
        $allowed = ['dashboard', 'dados', 'documentos', 'privacidade', 'historico'];
        $raw     = '';
        if (isset($_GET['aba'])) {
            $raw = (string) (function_exists('wp_unslash') ? \wp_unslash($_GET['aba']) : $_GET['aba']); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if (function_exists('sanitize_key')) {
                $raw = (string) \sanitize_key($raw);
            }
        }

        return in_array($raw, $allowed, true) ? $raw : 'dashboard';
    }
}
