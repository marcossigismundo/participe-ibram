<?php
/**
 * Template admin — Configurações DPO.
 *
 * Vars disponíveis:
 *  - $dpoEmail    (string)
 *  - $dpoNome     (string)
 *  - $dpoTelefone (string)
 *  - $nonce       (string)
 *  - $message     (string) — 'salvo' | 'nonce_falhou' | ''
 *
 * @package Ibram\ParticipeIbram\Presentation\Admin
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap pi-dpo-config" role="main">
    <h1><?php esc_html_e('Configurações do DPO (Encarregado LGPD)', 'participe-ibram'); ?></h1>
    <p class="description">
        <?php esc_html_e('Define o e-mail e dados de contato do Encarregado de Proteção de Dados (DPO) conforme LGPD Art. 41. Estas informações aparecem no rodapé dos e-mails institucionais e nos templates de alerta LGPD.', 'participe-ibram'); ?>
    </p>

    <?php if (isset($message) && $message === 'salvo') : ?>
    <div class="notice notice-success is-dismissible" role="status" aria-live="polite">
        <p><?php esc_html_e('Configuração salva com sucesso.', 'participe-ibram'); ?></p>
    </div>
    <?php elseif (isset($message) && $message === 'nonce_falhou') : ?>
    <div class="notice notice-error" role="alert">
        <p><?php esc_html_e('Token de segurança inválido. Por favor, tente novamente.', 'participe-ibram'); ?></p>
    </div>
    <?php endif; ?>

    <form method="post" action="" id="pi-dpo-config-form" novalidate>
        <?php wp_nonce_field('pi_dpo_config_nonce', 'pi_dpo_config_nonce'); ?>
        <input type="hidden" name="pi_dpo_config_submit" value="1">

        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="pi_dpo_email">
                            <?php esc_html_e('E-mail do DPO', 'participe-ibram'); ?>
                            <span aria-hidden="true" class="required">*</span>
                            <span class="screen-reader-text"><?php esc_html_e('(obrigatório)', 'participe-ibram'); ?></span>
                        </label>
                    </th>
                    <td>
                        <input
                            type="email"
                            id="pi_dpo_email"
                            name="email"
                            class="regular-text"
                            value="<?php echo esc_attr($dpoEmail ?? ''); ?>"
                            autocomplete="email"
                            aria-required="true"
                            aria-describedby="pi_dpo_email_desc"
                        >
                        <p id="pi_dpo_email_desc" class="description">
                            <?php esc_html_e('E-mail exibido nos templates de e-mail e usado para alertas LGPD.', 'participe-ibram'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="pi_dpo_nome">
                            <?php esc_html_e('Nome do DPO', 'participe-ibram'); ?>
                        </label>
                    </th>
                    <td>
                        <input
                            type="text"
                            id="pi_dpo_nome"
                            name="nome"
                            class="regular-text"
                            value="<?php echo esc_attr($dpoNome ?? ''); ?>"
                            autocomplete="off"
                        >
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="pi_dpo_telefone">
                            <?php esc_html_e('Telefone do DPO (opcional)', 'participe-ibram'); ?>
                        </label>
                    </th>
                    <td>
                        <input
                            type="tel"
                            id="pi_dpo_telefone"
                            name="telefone"
                            class="regular-text"
                            value="<?php echo esc_attr($dpoTelefone ?? ''); ?>"
                            autocomplete="tel"
                            aria-describedby="pi_dpo_telefone_desc"
                        >
                        <p id="pi_dpo_telefone_desc" class="description">
                            <?php esc_html_e('Opcional. Incluído no relatório de dados do titular.', 'participe-ibram'); ?>
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>

        <?php submit_button(esc_html__('Salvar configuração DPO', 'participe-ibram')); ?>
    </form>

    <hr>

    <h2><?php esc_html_e('Teste de e-mail DPO', 'participe-ibram'); ?></h2>
    <p class="description">
        <?php esc_html_e('Envia um e-mail de teste para o DPO configurado acima, usando dados fictícios, para verificar se o template e a entrega estão funcionando.', 'participe-ibram'); ?>
    </p>

    <button
        type="button"
        id="pi-dpo-test-email-btn"
        class="button button-secondary"
        <?php echo (($dpoEmail ?? '') === '') ? 'disabled aria-disabled="true"' : ''; ?>
        data-nonce="<?php echo esc_attr(wp_create_nonce('pi_admin_dpo_test_email')); ?>"
        data-action="pi_admin_dpo_test_email"
        aria-describedby="pi_dpo_test_desc"
    >
        <?php esc_html_e('Enviar e-mail de teste para DPO', 'participe-ibram'); ?>
    </button>
    <p id="pi_dpo_test_desc" class="description">
        <?php esc_html_e('O e-mail será enfileirado para entrega pelo worker de e-mail.', 'participe-ibram'); ?>
    </p>

    <div id="pi-dpo-test-result" aria-live="polite" role="status" style="margin-top:8px;"></div>
</div>

<script>
(function ($) {
    'use strict';
    $('#pi-dpo-test-email-btn').on('click', function () {
        var $btn    = $(this);
        var $result = $('#pi-dpo-test-result');
        $btn.prop('disabled', true).attr('aria-busy', 'true');
        $result.text('');

        $.post(ajaxurl, {
            action : $btn.data('action'),
            nonce  : $btn.data('nonce')
        })
        .done(function (resp) {
            if (resp.success) {
                $result.html('<span style="color:#00a32a;">' + wp.escapeHtml(resp.data.message) + '</span>');
            } else {
                $result.html('<span style="color:#d63638;">' + wp.escapeHtml((resp.data && resp.data.message) || 'Erro desconhecido.') + '</span>');
            }
        })
        .fail(function () {
            $result.html('<span style="color:#d63638;"><?php echo esc_js(__('Falha na comunicação com o servidor.', 'participe-ibram')); ?></span>');
        })
        .always(function () {
            $btn.prop('disabled', false).removeAttr('aria-busy');
        });
    });
}(jQuery));
</script>
