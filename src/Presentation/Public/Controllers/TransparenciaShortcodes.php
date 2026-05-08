<?php
/**
 * Shortcode `[pi_votacao_transparencia id="..."]`.
 *
 * @package Ibram\ParticipeIbram\Presentation\Public\Controllers
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Public\Controllers;

/**
 * Renderiza a página pública de transparência. Não chama nenhum repositório
 * server-side: o template carrega via REST `/pi/v1/publico/votacao/{id}/...`,
 * o que naturalmente reaproveita as whitelists e cache HTTP.
 */
final class TransparenciaShortcodes
{
    private string $templatesDir;

    /**
     * @param string $templatesDir Caminho absoluto para `templates/public/votacao/`.
     */
    public function __construct(string $templatesDir)
    {
        $this->templatesDir = rtrim($templatesDir, '/\\');
    }

    public function register(): void
    {
        if (!function_exists('add_shortcode')) {
            return;
        }
        \add_shortcode('pi_votacao_transparencia', [$this, 'renderTransparencia']);
    }

    /**
     * @param array<string,string>|string $atts
     */
    public function renderTransparencia($atts): string
    {
        $atts       = function_exists('shortcode_atts')
            ? \shortcode_atts(['id' => ''], is_array($atts) ? $atts : [])
            : (is_array($atts) ? $atts : []);
        $votacao_id = (int) (function_exists('absint')
            ? \absint($atts['id'] ?? '')
            : (int) ($atts['id'] ?? 0));

        if ($votacao_id <= 0) {
            $msg = function_exists('__')
                ? \__('ID de votação não informado.', 'participe-ibram')
                : 'ID de votação não informado.';
            return '<p class="pi-aviso">' . self::escHtml($msg) . '</p>';
        }

        $api_url    = $this->apiUrl();
        $rest_nonce = $this->restNonce();

        return $this->renderTemplate('transparencia.php', compact(
            'votacao_id',
            'api_url',
            'rest_nonce'
        ));
    }

    /**
     * @param array<string,mixed> $vars
     */
    private function renderTemplate(string $template, array $vars = []): string
    {
        $path = $this->templatesDir . DIRECTORY_SEPARATOR . $template;
        if (!is_file($path)) {
            return '';
        }
        extract($vars, EXTR_SKIP); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
        ob_start();
        include $path;
        return (string) ob_get_clean();
    }

    private function apiUrl(): string
    {
        if (function_exists('get_rest_url')) {
            return (string) \get_rest_url(null, 'pi/v1');
        }
        if (function_exists('home_url')) {
            return (string) \home_url('/wp-json/pi/v1');
        }
        return '/wp-json/pi/v1';
    }

    private function restNonce(): string
    {
        return function_exists('wp_create_nonce') ? (string) \wp_create_nonce('wp_rest') : '';
    }

    private static function escHtml(string $text): string
    {
        return function_exists('esc_html')
            ? (string) \esc_html($text)
            : htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
