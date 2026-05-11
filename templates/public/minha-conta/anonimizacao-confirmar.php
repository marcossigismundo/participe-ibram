<?php
/**
 * Template — página de confirmação final de anonimização (LGPD Art. 18, IV).
 *
 * Acessada via `?pi_anonimizacao_token=...` — o controlador valida o token,
 * carrega esta página em uma rota dedicada (W8-A injeta este partial), e
 * coloca um botão "Confirmar" desabilitado por 30s (countdown). Após
 * confirmação, a página dispara POST para `/me/anonimizacao-confirmar`.
 *
 * Vars:
 *   array $vars = [
 *     'token'           => string,
 *     'expira_em'       => string (ISO8601),
 *     'rest_namespace'  => string,
 *     'rest_nonce'      => string,
 *     'minha_conta_url' => string,
 *   ];
 *
 * @package ParticipeIbram
 */
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$vars         = isset($vars) && is_array($vars) ? $vars : [];
$token        = isset($vars['token']) ? (string) $vars['token'] : '';
$expiraEm     = isset($vars['expira_em']) ? (string) $vars['expira_em'] : '';
$restBase     = isset($vars['rest_namespace']) ? (string) $vars['rest_namespace'] : '/wp-json/pi/v1';
$nonce        = isset($vars['rest_nonce']) ? (string) $vars['rest_nonce'] : '';
$minhaConta   = isset($vars['minha_conta_url']) ? (string) $vars['minha_conta_url'] : '';
?>
<div class="pi-privacidade pi-privacidade--confirm-anon participe-ibram-scope"
     data-pi-anon-confirm
     data-rest-base="<?php echo esc_attr($restBase); ?>"
     data-rest-nonce="<?php echo esc_attr($nonce); ?>"
     data-token="<?php echo esc_attr($token); ?>"
     data-countdown-seconds="30">

    <h1 class="pi-privacidade__danger-title">
        <?php echo esc_html__('Confirmação final — Anonimização IRREVERSÍVEL', 'participe-ibram'); ?>
    </h1>

    <div id="pi-anon-confirm-status" class="pi-sr-only" role="status" aria-live="assertive" aria-atomic="true"></div>

    <div class="pi-alert pi-alert--danger" role="region"
         aria-label="<?php echo esc_attr__('Aviso final', 'participe-ibram'); ?>" id="pi-anon-aviso-final">
        <p>
            <strong><?php echo esc_html__('Atenção: esta ação é IRREVERSÍVEL.', 'participe-ibram'); ?></strong>
        </p>
        <p>
            <?php echo esc_html__(
                'Ao confirmar, seus dados pessoais serão removidos ou substituídos por valores anônimos. '
                . 'Esta operação não pode ser desfeita. Por obrigação legal (LGPD Art. 16, II), '
                . 'a trilha de auditoria será preservada (sem seus dados pessoais).',
                'participe-ibram'
            ); ?>
        </p>
    </div>

    <section aria-labelledby="pi-anon-detalhes-h">
        <h2 id="pi-anon-detalhes-h"><?php echo esc_html__('O que acontecerá', 'participe-ibram'); ?></h2>

        <h3><?php echo esc_html__('Será removido ou substituído', 'participe-ibram'); ?></h3>
        <ul>
            <li><?php echo esc_html__('Nome completo, nome social', 'participe-ibram'); ?></li>
            <li><?php echo esc_html__('CPF, RG, Passaporte (campos criptografados)', 'participe-ibram'); ?></li>
            <li><?php echo esc_html__('Telefone e email (este último substituído por anon-<id>@participe-ibram.local)', 'participe-ibram'); ?></li>
            <li><?php echo esc_html__('Arquivos físicos de documentos enviados', 'participe-ibram'); ?></li>
        </ul>

        <h3><?php echo esc_html__('Será preservado (obrigação legal — LGPD Art. 16, II)', 'participe-ibram'); ?></h3>
        <ul>
            <li><?php echo esc_html__('Trilha de auditoria (audit log) com metadados técnicos sem PII', 'participe-ibram'); ?></li>
            <li><?php echo esc_html__('Registro de consentimentos pretérito (anonimizado)', 'participe-ibram'); ?></li>
            <li><?php echo esc_html__('Hash de documentos (sem o conteúdo)', 'participe-ibram'); ?></li>
        </ul>

        <?php if ($expiraEm !== ''): ?>
            <p>
                <strong><?php echo esc_html__('Este link expira em:', 'participe-ibram'); ?></strong>
                <?php echo esc_html($expiraEm); ?>
            </p>
        <?php endif; ?>
    </section>

    <div class="pi-anon-confirm__actions">
        <!-- Botão "Cancelar/Voltar" recebe foco default (WCAG: foco nunca em ação destrutiva) -->
        <a href="<?php echo esc_url($minhaConta); ?>"
           class="pi-btn pi-btn--secondary"
           data-action="cancelar-anon"
           autofocus>
            <?php echo esc_html__('Cancelar e voltar', 'participe-ibram'); ?>
        </a>

        <button type="button"
                class="pi-btn pi-btn--danger"
                data-action="confirmar-anon"
                aria-describedby="pi-anon-aviso-final"
                disabled
                data-original-label="<?php echo esc_attr__('Confirmar anonimização', 'participe-ibram'); ?>">
            <span data-region="countdown-label">
                <?php echo esc_html__('Confirmar anonimização', 'participe-ibram'); ?>
                <span data-region="countdown" aria-hidden="true"> (30s)</span>
            </span>
        </button>
    </div>

    <p class="pi-privacidade__hint">
        <?php echo esc_html__('O botão "Confirmar" só fica ativo após 30 segundos (proteção contra confirmação acidental).', 'participe-ibram'); ?>
    </p>

    <div data-region="anon-result" hidden></div>
</div>
