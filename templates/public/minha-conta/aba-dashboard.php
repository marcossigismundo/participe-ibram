<?php
/**
 * Partial: aba Dashboard. Carregado pelo index.php quando $aba_atual === 'dashboard'.
 *
 * Os dados de status/proximos_passos/pendencias são hidratados via JavaScript
 * (chamada GET /pi/v1/me/dashboard com nonce). Aqui só renderizamos a
 * "casca semântica" + live regions.
 *
 * @package ParticipeIbram
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="pi-mc-dashboard" data-pi-mc-dashboard>
    <div class="pi-mc-dashboard__grid">

        <article class="pi-card pi-card--destaque" aria-labelledby="pi-mc-card-status-title">
            <header class="pi-card__header">
                <h2 id="pi-mc-card-status-title" class="pi-card__title">
                    <?php echo esc_html__('Status do cadastro', 'participe-ibram'); ?>
                </h2>
            </header>
            <div class="pi-card__body">
                <p class="pi-mc-status-badge" data-pi-mc-status>
                    <span class="pi-mc-status-badge__placeholder" aria-hidden="true">···</span>
                </p>
                <p class="pi-mc-numero-registro" data-pi-mc-numero-registro hidden>
                    <span class="pi-mc-numero-registro__label">
                        <?php echo esc_html__('Número de registro', 'participe-ibram'); ?>:
                    </span>
                    <code data-pi-mc-numero-registro-value></code>
                    <button
                        type="button"
                        class="pi-btn pi-btn--ghost pi-btn--sm"
                        data-pi-mc-copy
                        aria-label="<?php echo esc_attr__('Copiar número de registro', 'participe-ibram'); ?>"
                    >
                        <?php echo esc_html__('Copiar', 'participe-ibram'); ?>
                    </button>
                </p>
            </div>
        </article>

        <article class="pi-card" aria-labelledby="pi-mc-card-proximos-title">
            <header class="pi-card__header">
                <h2 id="pi-mc-card-proximos-title" class="pi-card__title">
                    <?php echo esc_html__('Próximos passos', 'participe-ibram'); ?>
                </h2>
            </header>
            <div class="pi-card__body">
                <ol class="pi-mc-proximos" data-pi-mc-proximos>
                    <li class="pi-mc-proximos__placeholder"><?php echo esc_html__('Carregando…', 'participe-ibram'); ?></li>
                </ol>
            </div>
        </article>

        <article class="pi-card" aria-labelledby="pi-mc-card-pendencias-title">
            <header class="pi-card__header">
                <h2 id="pi-mc-card-pendencias-title" class="pi-card__title">
                    <?php echo esc_html__('Pendências', 'participe-ibram'); ?>
                </h2>
            </header>
            <div class="pi-card__body">
                <ul class="pi-mc-pendencias" data-pi-mc-pendencias>
                    <li class="pi-mc-pendencias__placeholder"><?php echo esc_html__('Carregando…', 'participe-ibram'); ?></li>
                </ul>
            </div>
        </article>

        <article class="pi-card" aria-labelledby="pi-mc-card-timeline-title">
            <header class="pi-card__header">
                <h2 id="pi-mc-card-timeline-title" class="pi-card__title">
                    <?php echo esc_html__('Linha do tempo', 'participe-ibram'); ?>
                </h2>
            </header>
            <div class="pi-card__body">
                <ol class="pi-mc-timeline" data-pi-mc-timeline>
                    <li class="pi-mc-timeline__placeholder"><?php echo esc_html__('Carregando…', 'participe-ibram'); ?></li>
                </ol>
            </div>
        </article>

        <article class="pi-card" aria-labelledby="pi-mc-card-conta-title">
            <header class="pi-card__header">
                <h2 id="pi-mc-card-conta-title" class="pi-card__title">
                    <?php echo esc_html__('Configurações da conta', 'participe-ibram'); ?>
                </h2>
            </header>
            <div class="pi-card__body">
                <ul class="pi-mc-conta-links">
                    <?php
                    $url_senha = function_exists('admin_url') ? admin_url('profile.php') : '#';
                    ?>
                    <li>
                        <a href="<?php echo esc_url($url_senha); ?>">
                            <?php echo esc_html__('Alterar senha (perfil WordPress)', 'participe-ibram'); ?>
                        </a>
                    </li>
                    <li>
                        <?php
                        // 2FA: depende de plugin externo (e.g. Two-Factor); link só aparece se hook resolver URL.
                        $url_2fa = apply_filters('participe_ibram_two_factor_url', '');
                        if (is_string($url_2fa) && $url_2fa !== '') :
                        ?>
                            <a href="<?php echo esc_url($url_2fa); ?>">
                                <?php echo esc_html__('Configurar autenticação em duas etapas', 'participe-ibram'); ?>
                            </a>
                        <?php else : ?>
                            <span class="pi-mc-conta-links__muted">
                                <?php echo esc_html__('Autenticação em duas etapas: indisponível neste site.', 'participe-ibram'); ?>
                            </span>
                        <?php endif; ?>
                    </li>
                </ul>
            </div>
        </article>

    </div>
</div>
