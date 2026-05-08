<?php
/**
 * Passo final compartilhado: LGPD & Submissao.
 *
 * Variaveis disponiveis:
 *  - $step_id (string)  ID do panel (ex: 'pi-passo-pf-lgpd')
 *  - $step_num (int)    Numero do passo (1-based)
 *  - $step_total (int)  Total de passos
 *  - $is_first (bool)
 *  - $is_last (bool)    sempre true neste step
 *
 * O componente <div data-pi-consent> e renderizado dinamicamente pelo JS
 * (ConsentForm.js) com 10 finalidades.
 *
 * @package ParticipeIbram
 */

if (! defined('ABSPATH')) {
    exit;
}

$step_id    = $step_id ?? 'pi-passo-lgpd';
$step_num   = $step_num ?? 99;
$step_total = $step_total ?? 99;
?>
<section
    id="<?php echo esc_attr($step_id); ?>"
    class="pi-wizard-panel"
    aria-labelledby="<?php echo esc_attr($step_id); ?>-titulo"
    hidden
>
    <h2 id="<?php echo esc_attr($step_id); ?>-titulo" tabindex="-1">
        <?php
        printf(
            /* translators: 1: numero do passo, 2: total de passos */
            esc_html__('Passo %1$d de %2$d: LGPD &amp; Submissão', 'participe-ibram'),
            (int) $step_num,
            (int) $step_total
        );
        ?>
    </h2>

    <p class="pi-passo-instrucoes">
        <?php esc_html_e('Antes de submeter, leia e selecione as finalidades de tratamento de dados. As finalidades obrigatórias garantem a manutenção do seu cadastro.', 'participe-ibram'); ?>
    </p>

    <div
        class="pi-consent"
        data-pi-consent
        data-versao-termo="<?php echo esc_attr(apply_filters('participe_ibram_termo_versao', '1.0')); ?>"
        role="group"
        aria-label="<?php echo esc_attr__('Consentimento granular LGPD', 'participe-ibram'); ?>"
    >
        <h3>
            <?php esc_html_e('Consentimento granular (LGPD - Lei 13.709/2018)', 'participe-ibram'); ?>
            <button
                type="button"
                class="pi-help-button"
                aria-label="<?php echo esc_attr__('Ver termo completo de consentimento LGPD', 'participe-ibram'); ?>"
                aria-haspopup="dialog"
                aria-controls="pi-modal-help-lgpd"
                data-pi-modal-open="pi-modal-help-lgpd"
            >?</button>
        </h3>
        <?php /* O conteudo dinamico (10 finalidades) e injetado pelo ConsentForm.js */ ?>
    </div>

    <div class="pi-revisao">
        <h3><?php esc_html_e('Revisão antes do envio', 'participe-ibram'); ?></h3>
        <p><?php esc_html_e('Confira os dados informados nos passos anteriores. Você pode voltar a qualquer passo concluído clicando no item correspondente do indicador de progresso.', 'participe-ibram'); ?></p>
    </div>

    <nav class="pi-wizard__acoes" aria-label="<?php echo esc_attr__('Navegação do formulário', 'participe-ibram'); ?>">
        <button type="button" class="pi-btn pi-btn--secundario" data-acao="voltar">
            <span aria-hidden="true">&larr;</span>
            <?php esc_html_e('Voltar', 'participe-ibram'); ?>
        </button>
        <button type="button" class="pi-btn pi-btn--terciario" data-acao="salvar">
            <?php esc_html_e('Salvar rascunho', 'participe-ibram'); ?>
        </button>
        <button type="submit" class="pi-btn pi-btn--primario" data-acao="submeter">
            <?php esc_html_e('Enviar cadastro', 'participe-ibram'); ?>
        </button>
    </nav>
</section>
