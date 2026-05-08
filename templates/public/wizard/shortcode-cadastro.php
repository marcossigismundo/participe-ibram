<?php
/**
 * Shortcode entry point para o cadastro Participe Ibram.
 *
 * Carrega o template do wizard apropriado conforme o atributo `tipo`.
 * Uso: [participe_ibram_cadastro tipo="PF"]
 *
 * Variaveis esperadas:
 *  - $atts (array)        Atributos sanitizados do shortcode
 *  - $agente_id (mixed)   ID se editando rascunho (opcional)
 *  - $sucesso_url (string) URL pos-submissao (opcional)
 *
 * @package ParticipeIbram
 */

if (! defined('ABSPATH')) {
    exit;
}

$atts = wp_parse_args(
    is_array($atts ?? null) ? $atts : [],
    [
        'tipo'        => 'PF',
        'agente_id'   => '',
        'sucesso_url' => '',
    ]
);

$tipo        = strtoupper(sanitize_key((string) $atts['tipo']));
$agente_id   = sanitize_text_field((string) $atts['agente_id']);
$sucesso_url = esc_url_raw((string) $atts['sucesso_url']);

$mapa = [
    'PF' => __DIR__ . '/form-pf.php',
    'OR' => __DIR__ . '/form-or.php',
    'SM' => __DIR__ . '/form-sm.php',
];

if (! isset($mapa[$tipo])) {
    echo '<div class="participe-ibram-scope" role="alert">';
    echo '<p>' . esc_html__('Tipo de agente inválido. Use tipo="PF", tipo="OR" ou tipo="SM".', 'participe-ibram') . '</p>';
    echo '</div>';
    return;
}

if (! is_user_logged_in()) {
    /**
     * Permite ao tema/configuracao customizar fluxo de login antes do cadastro.
     * Por padrao, exibe aviso convidando para autenticacao.
     */
    if (apply_filters('participe_ibram_require_login', true, $tipo)) {
        $login_url = wp_login_url(get_permalink());
        echo '<div class="participe-ibram-scope">';
        echo '<p>' . esc_html__('Para iniciar seu cadastro, é necessário autenticar-se.', 'participe-ibram') . '</p>';
        echo '<p><a class="pi-btn pi-btn--primario" href="' . esc_url($login_url) . '">' . esc_html__('Entrar com gov.br', 'participe-ibram') . '</a></p>';
        echo '</div>';
        return;
    }
}

include $mapa[$tipo];
