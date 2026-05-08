<?php
/**
 * Template público — formulário para o agente protocolar recurso de inabilitação (W5-C).
 *
 * Vars esperadas (shortcode ou controller):
 *  - int $inscricaoId
 *  - string $prazoRecursoIso  (ISO 8601 da data limite, para countdown JS)
 *  - string $prazoRecursoLabel (exibição humana)
 *  - bool $prazoExpirado
 *  - string $nonce (pi_pub_recurso_inabilitacao_<userId>)
 *
 * @package Ibram\ParticipeIbram\Templates\Public\Inscricao
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/** @var int $inscricaoId */
/** @var string $prazoRecursoIso */
/** @var string $prazoRecursoLabel */
/** @var bool $prazoExpirado */
/** @var string $nonce */
?>
<div class="participe-ibram-scope pi-protocolar-recurso">

  <?php if ($prazoExpirado) : ?>
    <div class="pi-alert pi-alert--danger" role="alert">
      <strong><?php esc_html_e('Prazo encerrado.', 'participe-ibram'); ?></strong>
      <?php echo esc_html(sprintf(
          /* translators: %s data/hora de encerramento */
          __('O prazo para protocolar recurso encerrou em %s. Não é mais possível enviar.', 'participe-ibram'),
          $prazoRecursoLabel
      )); ?>
    </div>
    <?php return; ?>
  <?php endif; ?>

  <div class="pi-prazo-aviso pi-alert pi-alert--warning" role="status" aria-live="polite">
    <strong><?php esc_html_e('Atenção:', 'participe-ibram'); ?></strong>
    <?php echo esc_html(sprintf(
        /* translators: %s prazo de encerramento */
        __('O prazo para protocolar recurso encerra em %s.', 'participe-ibram'),
        $prazoRecursoLabel
    )); ?>
    <span class="pi-countdown" data-pi-prazo-countdown
          data-prazo-iso="<?php echo esc_attr($prazoRecursoIso); ?>"
          aria-live="off"></span>
  </div>

  <div role="status" aria-live="polite" id="pi-recurso-public-live" class="pi-sr-only"></div>

  <form id="pi-recurso-form" class="pi-form" novalidate
        data-pi-recurso-inabilitacao
        data-inscricao-id="<?php echo esc_attr((string) $inscricaoId); ?>"
        data-ajax-url="<?php echo esc_attr(function_exists('admin_url') ? admin_url('admin-ajax.php') : ''); ?>">

    <input type="hidden" name="action" value="<?php echo esc_attr(\Ibram\ParticipeIbram\Presentation\Public\Controllers\RecursoInabilitacaoPublicController::AJAX_ACTION); ?>">
    <input type="hidden" name="inscricao_id" value="<?php echo esc_attr((string) $inscricaoId); ?>">
    <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce); ?>">

    <div class="pi-form-row">
      <label for="pi-fundamentacao-md" class="pi-label">
        <strong><?php esc_html_e('Fundamentação do recurso', 'participe-ibram'); ?></strong>
        <span aria-hidden="true">*</span>
      </label>
      <p id="pi-fundamentacao-help" class="description">
        <?php esc_html_e('Explique com clareza os motivos do seu recurso (mínimo 50 caracteres). Markdown é aceito.', 'participe-ibram'); ?>
      </p>
      <textarea id="pi-fundamentacao-md" name="fundamentacao_md"
                rows="10" minlength="50" required
                aria-required="true"
                aria-describedby="pi-fundamentacao-help pi-char-counter"
                class="pi-input pi-input--full"></textarea>
      <p id="pi-char-counter" class="description" aria-live="polite">
        <?php esc_html_e('0 caracteres digitados (mínimo 50).', 'participe-ibram'); ?>
      </p>
    </div>

    <div class="pi-form-actions">
      <button type="submit" id="pi-recurso-submit"
              class="pi-button pi-button--primary"
              aria-controls="pi-modal-confirmar-recurso">
        <?php esc_html_e('Protocolar recurso', 'participe-ibram'); ?>
      </button>
    </div>
  </form>

  <?php /* Modal de confirmação ARIA (R4 §6) */ ?>
  <div id="pi-modal-confirmar-recurso" class="pi-modal" role="dialog" aria-modal="true"
       aria-labelledby="pi-modal-recurso-title" aria-describedby="pi-modal-recurso-desc" hidden>
    <div class="pi-modal__dialog">
      <header class="pi-modal__header">
        <h2 id="pi-modal-recurso-title"><?php esc_html_e('Confirmar envio do recurso', 'participe-ibram'); ?></h2>
        <button type="button" class="pi-modal__close" data-pi-modal-close
                aria-label="<?php esc_attr_e('Fechar', 'participe-ibram'); ?>">×</button>
      </header>
      <div class="pi-modal__body">
        <p id="pi-modal-recurso-desc">
          <?php esc_html_e('Você está prestes a protocolar um recurso contra a sua inabilitação. Esta ação não pode ser desfeita. Deseja confirmar?', 'participe-ibram'); ?>
        </p>
      </div>
      <footer class="pi-modal__footer">
        <button type="button" class="pi-button pi-button--secondary" data-pi-modal-close>
          <?php esc_html_e('Cancelar', 'participe-ibram'); ?>
        </button>
        <button type="button" class="pi-button pi-button--primary" data-pi-modal-confirm-recurso>
          <?php esc_html_e('Confirmar envio', 'participe-ibram'); ?>
        </button>
      </footer>
    </div>
  </div>
</div>
