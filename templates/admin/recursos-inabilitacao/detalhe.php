<?php
/**
 * Template — decisão de recurso de inabilitação (W5-C).
 *
 * Vars injetadas por RecursoInabilitacaoDetalhesController::render():
 *  - RecursoInabilitacao $recurso
 *  - Inscricao|null $inscricao
 *  - array{level:string,message:string}|null $flash
 *  - string $nonce
 *  - string $listaUrl
 *
 * @package Ibram\ParticipeIbram\Templates\Admin\RecursosInabilitacao
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use Ibram\ParticipeIbram\Domain\Edital\RecursoInabilitacao;
use Ibram\ParticipeIbram\Presentation\Admin\Controllers\RecursoInabilitacaoDetalhesController;

/** @var \Ibram\ParticipeIbram\Domain\Edital\RecursoInabilitacao $recurso */
/** @var \Ibram\ParticipeIbram\Domain\Edital\Inscricao|null $inscricao */
/** @var array{level:string,message:string}|null $flash */
/** @var string $nonce */
/** @var string $listaUrl */

$protocoladoEm = $recurso->protocoladoEm();
$now           = new \DateTimeImmutable('now');
$diff          = $now->diff($protocoladoEm);
$diasDecorridos = (int) $diff->days;
?>
<div class="participe-ibram-scope wrap pi-recurso-inabilitacao-detalhe">
  <a class="pi-skip-link" href="#pi-decisao-form"><?php esc_html_e('Pular para o formulário de decisão', 'participe-ibram'); ?></a>

  <nav class="pi-breadcrumb" aria-label="<?php esc_attr_e('Você está em', 'participe-ibram'); ?>">
    <ol class="pi-breadcrumb__list">
      <li class="pi-breadcrumb__item">
        <a href="<?php echo esc_url($listaUrl); ?>"><?php esc_html_e('Recursos de Inabilitação', 'participe-ibram'); ?></a>
      </li>
      <li class="pi-breadcrumb__item" aria-current="page">
        <?php echo esc_html(sprintf(__('Recurso #%d', 'participe-ibram'), (int) $recurso->id())); ?>
      </li>
    </ol>
  </nav>

  <?php if ($flash !== null) : ?>
    <div class="pi-alert pi-alert--<?php echo esc_attr($flash['level'] === 'success' ? 'success' : 'danger'); ?>"
         role="<?php echo $flash['level'] === 'success' ? 'status' : 'alert'; ?>"
         aria-live="polite">
      <?php echo esc_html($flash['message']); ?>
    </div>
  <?php endif; ?>

  <header role="banner" class="pi-recurso-header">
    <h1>
      <?php echo esc_html(sprintf(__('Recurso de Inabilitação #%d', 'participe-ibram'), (int) $recurso->id())); ?>
    </h1>
    <?php if ($inscricao !== null) : ?>
      <p class="description">
        <?php echo esc_html(sprintf(
            __('Inscrição #%d — Edital #%d — Categoria #%d', 'participe-ibram'),
            (int) $inscricao->id(),
            (int) $inscricao->editalId(),
            (int) $inscricao->categoriaId()
        )); ?>
      </p>
    <?php endif; ?>
  </header>

  <div class="pi-recurso-grid">
    <main id="pi-decisao-main" class="pi-recurso-grid__main" tabindex="-1">

      <?php /* Countdown do prazo */ ?>
      <div class="pi-prazo-countdown" data-pi-countdown
           data-protocolado-em="<?php echo esc_attr($protocoladoEm->format('c')); ?>"
           aria-live="off">
        <span class="pi-prazo pi-prazo--info">
          <?php echo esc_html(sprintf(
              /* translators: %d número de dias */
              __('Protocolado há %d dia(s)', 'participe-ibram'),
              $diasDecorridos
          )); ?>
        </span>
        <time datetime="<?php echo esc_attr($protocoladoEm->format('c')); ?>">
          <?php echo esc_html($protocoladoEm->format('d/m/Y H:i')); ?>
        </time>
      </div>

      <?php /* Fundamentação do recorrente */ ?>
      <section class="pi-card" aria-labelledby="recurso-fundamentacao-title">
        <header class="pi-card__header">
          <h2 id="recurso-fundamentacao-title" class="pi-card__title">
            <?php esc_html_e('Fundamentação do recorrente', 'participe-ibram'); ?>
          </h2>
        </header>
        <div class="pi-card__body pi-prose">
          <?php echo wp_kses_post(wpautop($recurso->fundamentacaoMd())); ?>
        </div>
      </section>

      <?php /* Motivo da inabilitação original */ ?>
      <?php if ($inscricao !== null && $inscricao->motivoInabilitacaoMd() !== null) : ?>
      <section class="pi-card" aria-labelledby="recurso-motivo-orig-title">
        <header class="pi-card__header">
          <h2 id="recurso-motivo-orig-title" class="pi-card__title">
            <?php esc_html_e('Motivo da inabilitação original', 'participe-ibram'); ?>
          </h2>
        </header>
        <div class="pi-card__body pi-prose">
          <?php echo wp_kses_post(wpautop($inscricao->motivoInabilitacaoMd())); ?>
        </div>
      </section>
      <?php endif; ?>

      <?php /* Formulário de decisão */ ?>
      <section id="pi-decisao-form" class="pi-card" aria-labelledby="recurso-decisao-title">
        <header class="pi-card__header">
          <h2 id="recurso-decisao-title" class="pi-card__title">
            <?php esc_html_e('Decisão do recurso', 'participe-ibram'); ?>
          </h2>
        </header>
        <div class="pi-card__body">
          <form method="post" action="" data-pi-decisao-form="recurso-inabilitacao">
            <input type="hidden" name="pi_action" value="decidir_recurso_inabilitacao">
            <input type="hidden" name="recurso_id" value="<?php echo esc_attr((string) $recurso->id()); ?>">
            <?php wp_nonce_field(RecursoInabilitacaoDetalhesController::NONCE_ACTION); ?>

            <fieldset class="pi-fieldset">
              <legend><?php esc_html_e('Qual é a sua decisão?', 'participe-ibram'); ?></legend>

              <div class="pi-radio">
                <input type="radio" id="opt-deferir" name="decisao"
                       value="<?php echo esc_attr(RecursoInabilitacao::DECISAO_DEFERIR); ?>" required>
                <label for="opt-deferir">
                  <strong><?php esc_html_e('Deferir (habilitar inscrição)', 'participe-ibram'); ?></strong>
                  <span class="description">
                    <?php esc_html_e('Acato o recurso; a inscrição será marcada como habilitada definitivamente.', 'participe-ibram'); ?>
                  </span>
                </label>
              </div>

              <div class="pi-radio">
                <input type="radio" id="opt-manter" name="decisao"
                       value="<?php echo esc_attr(RecursoInabilitacao::DECISAO_MANTER); ?>" required>
                <label for="opt-manter">
                  <strong><?php esc_html_e('Manter inabilitação', 'participe-ibram'); ?></strong>
                  <span class="description">
                    <?php esc_html_e('Mantenho a decisão de inabilitação; o recurso é indeferido.', 'participe-ibram'); ?>
                  </span>
                </label>
              </div>
            </fieldset>

            <div class="pi-form-row">
              <label for="decisao-md">
                <?php esc_html_e('Fundamentação da decisão (mín. 50 caracteres)', 'participe-ibram'); ?>
                <span aria-hidden="true">*</span>
              </label>
              <textarea id="decisao-md" name="decisao_md" rows="8" minlength="50" required
                        aria-describedby="decisao-md-help"></textarea>
              <p id="decisao-md-help" class="description">
                <?php esc_html_e('Markdown permitido. Não inclua dados pessoais sensíveis (CPF, RG).', 'participe-ibram'); ?>
              </p>
            </div>

            <div class="pi-form-actions">
              <button type="submit" class="button button-primary" data-pi-confirm-decisao>
                <?php esc_html_e('Decidir', 'participe-ibram'); ?>
              </button>
              <a class="button" href="<?php echo esc_url($listaUrl); ?>">
                <?php esc_html_e('Cancelar', 'participe-ibram'); ?>
              </a>
            </div>

            <div class="pi-sr-only" role="status" aria-live="polite" data-pi-live></div>
          </form>
        </div>
      </section>

    </main>
  </div>

  <?php /* Modal de confirmação acessível (R4 §6) */ ?>
  <div id="pi-confirm-decisao" class="pi-modal" role="dialog" aria-modal="true"
       aria-labelledby="pi-confirm-decisao-title" aria-describedby="pi-confirm-decisao-desc" hidden>
    <div class="pi-modal__dialog">
      <h2 id="pi-confirm-decisao-title" class="pi-modal__title">
        <?php esc_html_e('Confirmar decisão', 'participe-ibram'); ?>
      </h2>
      <p id="pi-confirm-decisao-desc" class="pi-modal__body">
        <?php esc_html_e('Esta ação é irreversível e atualizará o status da inscrição. Deseja confirmar?', 'participe-ibram'); ?>
      </p>
      <div class="pi-modal__actions">
        <button type="button" class="button" data-pi-modal-cancel>
          <?php esc_html_e('Cancelar', 'participe-ibram'); ?>
        </button>
        <button type="button" class="button button-primary" data-pi-modal-confirm>
          <?php esc_html_e('Confirmar', 'participe-ibram'); ?>
        </button>
      </div>
    </div>
  </div>
</div>
