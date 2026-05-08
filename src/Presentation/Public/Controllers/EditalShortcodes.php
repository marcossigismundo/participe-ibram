<?php
/**
 * Registro de shortcodes de Edital público.
 *
 * @package Ibram\ParticipeIbram\Presentation\Public\Controllers
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Public\Controllers;

/**
 * Registra os shortcodes públicos de edital:
 *  - [pi_editais_publicos]                    → editais-lista.php
 *  - [pi_edital_detalhes id="..."]            → edital-detalhes.php
 *  - [pi_inscricao_edital edital_id="..."]    → inscricao-wizard.php
 *  - [pi_edital_resultados id="..."]          → edital-resultados-publico.php
 *
 * Segurança: todos os parâmetros de shortcode são sanitizados via absint/
 * sanitize_text_field antes de passar para os templates. Nenhum template
 * exibe PII além de nome_publico e numero_registro (whitelist aplicada antes).
 */
final class EditalShortcodes
{
    /** Caminho absoluto para a pasta de templates. */
    private string $templatesDir;

    /**
     * @param string $templatesDir Caminho absoluto para `templates/public/editais/`.
     */
    public function __construct(string $templatesDir)
    {
        $this->templatesDir = rtrim($templatesDir, '/\\');
    }

    /**
     * Registra todos os shortcodes no WordPress.
     */
    public function register(): void
    {
        if (!function_exists('add_shortcode')) {
            return;
        }

        \add_shortcode('pi_editais_publicos',  [$this, 'renderListaEditais']);
        \add_shortcode('pi_edital_detalhes',   [$this, 'renderDetalheEdital']);
        \add_shortcode('pi_inscricao_edital',  [$this, 'renderInscricaoWizard']);
        \add_shortcode('pi_edital_resultados', [$this, 'renderResultadosEdital']);
    }

    /**
     * [pi_editais_publicos]
     *
     * @param array<string,string>|string $atts
     */
    public function renderListaEditais($atts): string
    {
        $atts = is_array($atts) ? $atts : [];
        $api_url     = $this->apiUrl();
        $rest_nonce  = $this->restNonce();

        return $this->renderTemplate('editais-lista.php', compact('api_url', 'rest_nonce', 'atts'));
    }

    /**
     * [pi_edital_detalhes id="..."]
     *
     * @param array<string,string>|string $atts
     */
    public function renderDetalheEdital($atts): string
    {
        $atts     = shortcode_atts(['id' => ''], is_array($atts) ? $atts : []);
        $edital_id = (int) \absint($atts['id']);
        if ($edital_id <= 0) {
            return function_exists('__')
                ? '<p class="pi-aviso">' . esc_html(\__('ID de edital não informado.', 'participe-ibram')) . '</p>'
                : '<p class="pi-aviso">ID de edital não informado.</p>';
        }
        $api_url    = $this->apiUrl();
        $rest_nonce = $this->restNonce();
        $agente_id  = $this->currentAgenteId();
        $is_logado  = function_exists('is_user_logged_in') ? (bool) \is_user_logged_in() : false;

        return $this->renderTemplate('edital-detalhes.php', compact(
            'edital_id',
            'api_url',
            'rest_nonce',
            'agente_id',
            'is_logado'
        ));
    }

    /**
     * [pi_inscricao_edital edital_id="..."]
     *
     * @param array<string,string>|string $atts
     */
    public function renderInscricaoWizard($atts): string
    {
        $atts      = shortcode_atts(['edital_id' => ''], is_array($atts) ? $atts : []);
        $edital_id = (int) \absint($atts['edital_id']);
        if ($edital_id <= 0) {
            return function_exists('__')
                ? '<p class="pi-aviso">' . esc_html(\__('ID de edital não informado.', 'participe-ibram')) . '</p>'
                : '<p class="pi-aviso">ID de edital não informado.</p>';
        }

        if (!function_exists('is_user_logged_in') || !\is_user_logged_in()) {
            $login_url = function_exists('wp_login_url') ? esc_url(\wp_login_url(\get_permalink())) : '#login';
            $msg       = function_exists('__')
                ? \__('Você precisa estar logado para se inscrever.', 'participe-ibram')
                : 'Você precisa estar logado para se inscrever.';

            return '<p class="pi-aviso">' . esc_html($msg) . ' <a href="' . $login_url . '">'
                . (function_exists('__') ? esc_html(\__('Entrar', 'participe-ibram')) : 'Entrar')
                . '</a></p>';
        }

        $api_url    = $this->apiUrl();
        $rest_nonce = $this->restNonce();
        $agente_id  = $this->currentAgenteId();

        return $this->renderTemplate('inscricao-wizard.php', compact(
            'edital_id',
            'api_url',
            'rest_nonce',
            'agente_id'
        ));
    }

    /**
     * [pi_edital_resultados id="..."]
     *
     * @param array<string,string>|string $atts
     */
    public function renderResultadosEdital($atts): string
    {
        $atts     = shortcode_atts(['id' => ''], is_array($atts) ? $atts : []);
        $edital_id = (int) \absint($atts['id']);
        if ($edital_id <= 0) {
            return function_exists('__')
                ? '<p class="pi-aviso">' . esc_html(\__('ID de edital não informado.', 'participe-ibram')) . '</p>'
                : '<p class="pi-aviso">ID de edital não informado.</p>';
        }
        $api_url    = $this->apiUrl();
        $rest_nonce = $this->restNonce();

        return $this->renderTemplate('edital-resultados-publico.php', compact(
            'edital_id',
            'api_url',
            'rest_nonce'
        ));
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Renderiza um template PHP capturando o output em buffer.
     *
     * @param array<string,mixed> $vars Variáveis injetadas no escopo do template.
     */
    private function renderTemplate(string $template, array $vars = []): string
    {
        $path = $this->templatesDir . DIRECTORY_SEPARATOR . $template;
        if (!is_file($path)) {
            return '';
        }
        // Extrai variáveis sem sobrescrever $path.
        extract($vars, EXTR_SKIP); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
        ob_start();
        include $path;

        return (string) ob_get_clean();
    }

    private function apiUrl(): string
    {
        return function_exists('get_rest_url')
            ? \get_rest_url(null, 'pi/v1')
            : \home_url('/wp-json/pi/v1');
    }

    private function restNonce(): string
    {
        return function_exists('wp_create_nonce') ? \wp_create_nonce('wp_rest') : '';
    }

    /**
     * Retorna o agente_id associado ao usuário logado via filter cross-domain.
     */
    private function currentAgenteId(): int
    {
        if (!function_exists('get_current_user_id') || !function_exists('apply_filters')) {
            return 0;
        }
        $userId   = (int) \get_current_user_id();
        $agenteId = \apply_filters('pi_agente_id_by_user', null, $userId);

        return is_int($agenteId) && $agenteId > 0 ? $agenteId : 0;
    }
}
