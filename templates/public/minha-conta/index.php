<?php
/**
 * Entry template do shortcode [pi_minha_conta].
 *
 * Variáveis disponíveis (injetadas por MinhaContaShortcode):
 *  - $api_url    string  Base da API REST (https://.../wp-json/pi/v1).
 *  - $rest_nonce string  Nonce wp_rest.
 *  - $is_logado  bool    Usuário autenticado.
 *  - $login_url  string  URL p/ login.
 *  - $aba_atual  string  Uma de: dashboard|dados|documentos|privacidade|historico.
 *
 * @package ParticipeIbram
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!$is_logado) {
    echo '<div class="participe-ibram-scope">';
    echo '<div class="pi-alert pi-alert--info" role="alert">';
    echo '<p>' . esc_html__('Você precisa estar autenticado(a) para acessar sua conta.', 'participe-ibram') . '</p>';
    echo '<p><a class="pi-btn pi-btn--primario" href="' . esc_url($login_url) . '">'
        . esc_html__('Entrar com gov.br', 'participe-ibram')
        . '</a></p>';
    echo '</div></div>';

    return;
}

$user_id   = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
$agente_id = (int) apply_filters('pi_agente_id_by_user', 0, $user_id);

if ($agente_id <= 0) {
    echo '<div class="participe-ibram-scope">';
    echo '<div class="pi-minha-conta__empty" role="region" aria-labelledby="pi-mc-empty-title">';
    echo '<h2 id="pi-mc-empty-title">' . esc_html__('Você ainda não tem cadastro', 'participe-ibram') . '</h2>';
    echo '<p>' . esc_html__('Realize seu cadastro como agente do Sistema Brasileiro de Museus para acompanhar a análise e participar de editais.', 'participe-ibram') . '</p>';
    $cadastro_url = function_exists('get_permalink') ? get_permalink() : '#';
    /**
     * Filter the URL to start cadastro from "minha conta" empty state.
     */
    $cadastro_url = apply_filters('participe_ibram_cadastro_url', $cadastro_url);
    echo '<p><a class="pi-btn pi-btn--primario" href="' . esc_url($cadastro_url) . '">'
        . esc_html__('Cadastrar-se', 'participe-ibram')
        . '</a></p>';
    echo '</div></div>';

    return;
}

$abas = [
    'dashboard'   => __('Dashboard', 'participe-ibram'),
    'dados'       => __('Meus dados', 'participe-ibram'),
    'documentos'  => __('Documentos', 'participe-ibram'),
    'privacidade' => __('Privacidade', 'participe-ibram'),
    'historico'   => __('Histórico', 'participe-ibram'),
];

$config_minha_conta = [
    'apiUrl'   => (string) $api_url,
    'nonce'    => (string) $rest_nonce,
    'abaAtual' => (string) $aba_atual,
];

if (!class_exists(\Ibram\ParticipeIbram\Core\Helpers\Json::class)) {
    $config_json = wp_json_encode($config_minha_conta, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
} else {
    $config_json = \Ibram\ParticipeIbram\Core\Helpers\Json::encodeForScript($config_minha_conta);
}
?>
<div class="participe-ibram-scope">
    <div
        class="pi-minha-conta"
        data-pi-minha-conta
        data-aba-atual="<?php echo esc_attr($aba_atual); ?>"
    >
        <script type="application/json" id="pi-minha-conta-config"><?php echo $config_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON already safely encoded via Json::encodeForScript ?></script>

        <header class="pi-minha-conta__header" role="banner">
            <nav class="pi-breadcrumb" aria-label="<?php echo esc_attr__('Você está em', 'participe-ibram'); ?>">
                <ol>
                    <li><a href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html__('Início', 'participe-ibram'); ?></a></li>
                    <li aria-current="page"><?php echo esc_html__('Minha conta', 'participe-ibram'); ?></li>
                </ol>
            </nav>
            <h1 class="pi-minha-conta__title"><?php echo esc_html__('Minha conta', 'participe-ibram'); ?></h1>
            <p class="pi-minha-conta__subtitle"><?php echo esc_html__('Acompanhe seu cadastro e gerencie seus dados.', 'participe-ibram'); ?></p>
            <div id="pi-minha-conta-status-header" class="pi-minha-conta__status-line" aria-live="polite"></div>
        </header>

        <div
            id="pi-minha-conta-live"
            class="pi-sr-only"
            aria-live="polite"
            aria-atomic="true"
            role="status"
        ></div>

        <div class="pi-minha-conta__tabs" role="tablist" aria-label="<?php echo esc_attr__('Seções da minha conta', 'participe-ibram'); ?>">
            <?php foreach ($abas as $slug => $label) :
                $is_active = $slug === $aba_atual;
                $tab_id    = 'pi-mc-tab-' . $slug;
                $panel_id  = 'pi-mc-panel-' . $slug;
                $url       = add_query_arg(['aba' => $slug], remove_query_arg('aba'));
            ?>
                <a
                    role="tab"
                    href="<?php echo esc_url($url); ?>"
                    id="<?php echo esc_attr($tab_id); ?>"
                    class="pi-minha-conta__tab<?php echo $is_active ? ' is-active' : ''; ?>"
                    aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
                    aria-controls="<?php echo esc_attr($panel_id); ?>"
                    tabindex="<?php echo $is_active ? '0' : '-1'; ?>"
                    data-aba="<?php echo esc_attr($slug); ?>"
                >
                    <?php echo esc_html($label); ?>
                </a>
            <?php endforeach; ?>
        </div>

        <main class="pi-minha-conta__main">
            <?php
            $panel_dir = __DIR__;
            $panel_map = [
                'dashboard'   => 'aba-dashboard.php',
                'dados'       => 'aba-dados.php',
                'documentos'  => 'aba-documentos.php',
                'privacidade' => 'aba-privacidade.php',
                'historico'   => 'aba-historico.php',
            ];
            foreach ($panel_map as $slug => $file) :
                $is_active = $slug === $aba_atual;
                $panel_id  = 'pi-mc-panel-' . $slug;
                $tab_id    = 'pi-mc-tab-' . $slug;
                $partial   = $panel_dir . DIRECTORY_SEPARATOR . $file;
            ?>
                <section
                    id="<?php echo esc_attr($panel_id); ?>"
                    role="tabpanel"
                    aria-labelledby="<?php echo esc_attr($tab_id); ?>"
                    class="pi-minha-conta__panel<?php echo $is_active ? ' is-active' : ''; ?>"
                    <?php echo $is_active ? '' : 'hidden'; ?>
                    tabindex="0"
                >
                    <?php if ($is_active && is_file($partial)) {
                        include $partial;
                    } ?>
                </section>
            <?php endforeach; ?>
        </main>
    </div>
</div>
