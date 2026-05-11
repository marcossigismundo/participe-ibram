<?php
/**
 * Partial: aba "Documentos". Lista documentos do agente; upload em modal.
 *
 * Server-side renderiza a casca; conteúdo via AJAX.
 * Estado RASCUNHO/INDEFERIDO_AGUARDANDO_RECURSO habilita botão "Adicionar".
 *
 * @package ParticipeIbram
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="pi-mc-documentos" data-pi-mc-documentos>
    <div class="pi-mc-documentos__toolbar">
        <button
            type="button"
            class="pi-btn pi-btn--secundario"
            data-pi-mc-doc-add
            data-pi-modal-open="pi-modal-mc-doc"
            hidden
        >
            <?php echo esc_html__('Adicionar documento', 'participe-ibram'); ?>
        </button>
    </div>

    <table class="pi-table pi-mc-documentos__table" aria-describedby="pi-mc-doc-resumo">
        <caption id="pi-mc-doc-resumo" class="pi-sr-only">
            <?php echo esc_html__('Documentos anexados ao seu cadastro.', 'participe-ibram'); ?>
        </caption>
        <thead>
            <tr>
                <th scope="col"><?php echo esc_html__('Tipo', 'participe-ibram'); ?></th>
                <th scope="col"><?php echo esc_html__('Nome do arquivo', 'participe-ibram'); ?></th>
                <th scope="col"><?php echo esc_html__('Enviado em', 'participe-ibram'); ?></th>
                <th scope="col"><?php echo esc_html__('Validação', 'participe-ibram'); ?></th>
                <th scope="col"><?php echo esc_html__('Ações', 'participe-ibram'); ?></th>
            </tr>
        </thead>
        <tbody data-pi-mc-doc-list>
            <tr class="pi-mc-documentos__placeholder">
                <td colspan="5"><?php echo esc_html__('Carregando…', 'participe-ibram'); ?></td>
            </tr>
        </tbody>
    </table>
</div>

<div
    id="pi-modal-mc-doc"
    class="pi-modal"
    role="dialog"
    aria-modal="true"
    aria-labelledby="pi-modal-mc-doc-title"
    data-pi-modal
    hidden
>
    <div class="pi-modal__overlay" tabindex="-1"></div>
    <div class="pi-modal__dialog">
        <header class="pi-modal__header">
            <h2 id="pi-modal-mc-doc-title" class="pi-modal__title">
                <?php echo esc_html__('Adicionar documento', 'participe-ibram'); ?>
            </h2>
            <button type="button" class="pi-modal__close" data-pi-modal-close aria-label="<?php echo esc_attr__('Fechar', 'participe-ibram'); ?>">×</button>
        </header>
        <div class="pi-modal__body">
            <p>
                <?php echo esc_html__('Reusa o componente FileUpload — somente os tipos exigidos no seu cadastro estão disponíveis.', 'participe-ibram'); ?>
            </p>
            <div data-pi-mc-doc-upload-slot></div>
        </div>
        <footer class="pi-modal__footer">
            <button type="button" class="pi-btn pi-btn--ghost" data-pi-modal-close>
                <?php echo esc_html__('Fechar', 'participe-ibram'); ?>
            </button>
        </footer>
    </div>
</div>
