<?php
/**
 * Registro do shortcode [pi_cadastro] (wizard de cadastro público).
 *
 * Wave 3 — registra o shortcode que inicia o multi-step wizard de cadastro
 * de agentes (PF, OR, SM). Atua apenas como despachante de template; toda
 * validação e persistência acontece via REST (`/pi/v1/wizard/*`).
 *
 * Uso: [pi_cadastro tipo="PF|OR|SM"]
 *  - tipo: "PF" (Pessoa Física), "OR" (Organização), "SM" (Sociedade Mista).
 *    Default: "PF".
 *
 * Segurança:
 *  - O atributo `tipo` é validado contra whitelist antes de construir o path.
 *  - Sem dados sensíveis expostos server-side; o template lida com REST nonce.
 *
 * @package Ibram\ParticipeIbram\Presentation\Public\Controllers
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Presentation\Public\Controllers;

/**
 * Registra `[pi_cadastro tipo="PF|OR|SM"]` mapeando para os templates:
 *  - tipo=PF → templates/public/wizard/form-pf.php
 *  - tipo=OR → templates/public/wizard/form-or.php
 *  - tipo=SM → templates/public/wizard/form-sm.php
 */
final class CadastroShortcode
{
    /** Tipos válidos e seus templates correspondentes. */
    private const TIPO_MAP = [
        'PF' => 'form-pf.php',
        'OR' => 'form-or.php',
        'SM' => 'form-sm.php',
    ];

    /** Caminho absoluto para templates/public/wizard/ */
    private string $templatesDir;

    /**
     * @param string $templatesDir Caminho absoluto para `templates/public/wizard/`.
     */
    public function __construct(string $templatesDir)
    {
        $this->templatesDir = rtrim($templatesDir, '/\\');
    }

    /**
     * Registra o shortcode [pi_cadastro] no WordPress. Chamar em `init`.
     */
    public function register(): void
    {
        if (!function_exists('add_shortcode')) {
            return;
        }
        \add_shortcode('pi_cadastro', [$this, 'render']);
    }

    /**
     * Renderiza o wizard de cadastro.
     *
     * @param array<string,string>|string $atts
     */
    public function render($atts = []): string
    {
        $atts = function_exists('shortcode_atts')
            ? \shortcode_atts(['tipo' => 'PF'], is_array($atts) ? $atts : [])
            : (is_array($atts) ? $atts : ['tipo' => 'PF']);

        $tipo = strtoupper((string) ($atts['tipo'] ?? 'PF'));

        // Valida contra whitelist — jamais constrói path a partir de input não validado.
        if (!array_key_exists($tipo, self::TIPO_MAP)) {
            $tipo = 'PF';
        }

        $template = $this->templatesDir . DIRECTORY_SEPARATOR . self::TIPO_MAP[$tipo];

        if (!is_file($template)) {
            // Template não encontrado — retorna vazio sem erro visível no front.
            return '';
        }

        $api_url    = function_exists('get_rest_url') ? \get_rest_url(null, 'pi/v1') : '/wp-json/pi/v1';
        $rest_nonce = function_exists('wp_create_nonce') ? \wp_create_nonce('wp_rest') : '';
        $tipo_agente = $tipo;

        ob_start();
        include $template;
        return (string) ob_get_clean();
    }
}
