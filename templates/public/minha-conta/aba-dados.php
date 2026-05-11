<?php
/**
 * Partial: aba "Meus dados". Carregado quando $aba_atual === 'dados'.
 *
 * Toda a exibição usa AJAX (GET /me/cadastro). Renderizamos somente a casca
 * acessível e o modal de edição (reusa Modal.js).
 *
 * Visibilidade do botão "Editar" é controlada client-side com fallback de
 * autorização server-side (PATCH responde 423 para estados bloqueados).
 *
 * @package ParticipeIbram
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="pi-mc-dados" data-pi-mc-dados>
    <div class="pi-mc-dados__toolbar">
        <button
            type="button"
            class="pi-btn pi-btn--secundario"
            data-pi-mc-editar
            data-pi-modal-open="pi-modal-mc-editar"
            hidden
        >
            <?php echo esc_html__('Editar dados', 'participe-ibram'); ?>
        </button>
        <p class="pi-mc-dados__estado-msg" data-pi-mc-estado-msg role="status" aria-live="polite"></p>
    </div>

    <section aria-labelledby="pi-mc-dados-basicos-title">
        <h2 id="pi-mc-dados-basicos-title" class="pi-mc-dados__section-title">
            <?php echo esc_html__('Dados básicos', 'participe-ibram'); ?>
        </h2>
        <dl class="pi-mc-dl" data-pi-mc-secao="basicos">
            <div class="pi-mc-dl__row"><dt><?php echo esc_html__('E-mail principal', 'participe-ibram'); ?></dt><dd data-campo="email_principal">—</dd></div>
            <div class="pi-mc-dl__row"><dt><?php echo esc_html__('Telefone', 'participe-ibram'); ?></dt><dd data-campo="telefone">—</dd></div>
            <div class="pi-mc-dl__row"><dt><?php echo esc_html__('Tipo de agente', 'participe-ibram'); ?></dt><dd data-campo="tipo">—</dd></div>
        </dl>
    </section>

    <section aria-labelledby="pi-mc-dados-sensiveis-title">
        <h2 id="pi-mc-dados-sensiveis-title" class="pi-mc-dados__section-title">
            <?php echo esc_html__('Dados sensíveis', 'participe-ibram'); ?>
        </h2>
        <p class="pi-mc-dados__note">
            <?php echo esc_html__('Para sua proteção, esses dados aparecem mascarados. Cada visualização é registrada em auditoria.', 'participe-ibram'); ?>
        </p>
        <dl class="pi-mc-dl" data-pi-mc-secao="sensiveis">
            <!-- Linhas adicionadas dinamicamente: cpf, rg, passaporte (PF) | cnpj (OR) | representante_cpf (SM) -->
        </dl>
    </section>

    <section aria-labelledby="pi-mc-dados-endereco-title">
        <h2 id="pi-mc-dados-endereco-title" class="pi-mc-dados__section-title">
            <?php echo esc_html__('Endereço', 'participe-ibram'); ?>
        </h2>
        <dl class="pi-mc-dl" data-pi-mc-secao="endereco">
            <!-- Preenchido conforme tipo (residencia PF / sede OR) -->
        </dl>
    </section>

    <section aria-labelledby="pi-mc-dados-perfil-title">
        <h2 id="pi-mc-dados-perfil-title" class="pi-mc-dados__section-title">
            <?php echo esc_html__('Perfil público', 'participe-ibram'); ?>
        </h2>
        <dl class="pi-mc-dl" data-pi-mc-secao="perfil">
            <!-- nome_social, apresentacao_md -->
        </dl>
    </section>
</div>

<!-- Modal de edição (reusa Modal.js). Inicialmente hidden. -->
<div
    id="pi-modal-mc-editar"
    class="pi-modal"
    role="dialog"
    aria-modal="true"
    aria-labelledby="pi-modal-mc-editar-title"
    aria-describedby="pi-modal-mc-editar-desc"
    data-pi-modal
    hidden
>
    <div class="pi-modal__overlay" tabindex="-1"></div>
    <div class="pi-modal__dialog">
        <header class="pi-modal__header">
            <h2 id="pi-modal-mc-editar-title" class="pi-modal__title">
                <?php echo esc_html__('Editar dados', 'participe-ibram'); ?>
            </h2>
            <button type="button" class="pi-modal__close" data-pi-modal-close aria-label="<?php echo esc_attr__('Fechar', 'participe-ibram'); ?>">×</button>
        </header>
        <div class="pi-modal__body">
            <p id="pi-modal-mc-editar-desc" class="pi-modal__desc">
                <?php echo esc_html__('Apenas campos editáveis no estado atual do seu cadastro estão visíveis abaixo.', 'participe-ibram'); ?>
            </p>
            <form id="pi-mc-form-editar" data-pi-mc-form novalidate>
                <!-- Campos injetados via JS conforme estado/tipo. -->
                <div data-pi-mc-form-fields></div>
                <div class="pi-mc-form__errors" role="alert" aria-live="assertive" data-pi-mc-form-errors></div>
            </form>
        </div>
        <footer class="pi-modal__footer">
            <button type="button" class="pi-btn pi-btn--ghost" data-pi-modal-close>
                <?php echo esc_html__('Cancelar', 'participe-ibram'); ?>
            </button>
            <button type="submit" form="pi-mc-form-editar" class="pi-btn pi-btn--primario" data-pi-mc-submit>
                <?php echo esc_html__('Salvar', 'participe-ibram'); ?>
            </button>
        </footer>
    </div>
</div>
