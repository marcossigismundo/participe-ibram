<?php
/**
 * Template — Tela de decisão de Recurso na Presidência.
 *
 * Vars esperadas:
 *  - $recurso     Recurso (fase=presidencia)
 *  - $analise     Analise|null
 *  - $agente      Agente|null
 *  - $retratacao  Recurso|null   (recurso de retratação anterior, p/ histórico)
 *  - $flash       array|null
 *  - $nonce       string
 *  - $listaUrl    string
 *
 * @package Ibram\ParticipeIbram\Templates\Admin\Recursos
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/** @var \Ibram\ParticipeIbram\Domain\Analise\Recurso $recurso */
/** @var \Ibram\ParticipeIbram\Domain\Analise\Analise|null $analise */
/** @var \Ibram\ParticipeIbram\Domain\Agente\Agente|null $agente */
/** @var \Ibram\ParticipeIbram\Domain\Analise\Recurso|null $retratacao */
/** @var array{level:string,message:string}|null $flash */
/** @var string $nonce */
/** @var string $listaUrl */

$now             = new \DateTimeImmutable('now');
$prazoFim        = $recurso->prazoFim();
$prazoDiff       = $now->diff($prazoFim);
$diasRestantes   = (int) $prazoDiff->format('%r%a');
$severidade      = $diasRestantes < 0 ? 'vencido' : ($diasRestantes <= 2 ? 'urgente' : ($diasRestantes <= 5 ? 'atencao' : 'ok'));
$tipoLabel       = $agente !== null ? (string) $agente->getTipo()->value() : '—';
$emailMascarado  = $agente !== null
    ? \Ibram\ParticipeIbram\Core\Audit\PiiMasker::maskEmail($agente->getEmailPrincipal())
    : '—';
?>
<div class="participe-ibram-scope wrap pi-recurso-detalhe">
  <a class="pi-skip-link" href="#pi-decisao-form"><?php esc_html_e('Pular para o formulário de decisão', 'participe-ibram'); ?></a>

  <nav class="pi-breadcrumb" aria-label="<?php esc_attr_e('Você está em', 'participe-ibram'); ?>">
    <ol class="pi-breadcrumb__list">
      <li class="pi-breadcrumb__item">
        <a href="<?php echo esc_url($listaUrl); ?>"><?php esc_html_e('Recursos — Presidência', 'participe-ibram'); ?></a>
      </li>
      <li class="pi-breadcrumb__item" aria-current="page">
        <?php echo esc_html(sprintf(__('Recurso #%d', 'participe-ibram'), (int) $recurso->id())); ?>
      </li>
    </ol>
  </nav>

  <?php if ($flash !== null) : ?>
    <div class="pi-alert pi-alert--<?php echo esc_attr($flash['level'] === 'success' ? 'success' : 'danger'); ?>" role="<?php echo $flash['level'] === 'success' ? 'status' : 'alert'; ?>" aria-live="polite">
      <?php echo esc_html($flash['message']); ?>
    </div>
  <?php endif; ?>

  <header role="banner" class="pi-recurso-header">
    <h1><?php echo esc_html(sprintf(__('Recurso #%d — Presidência (final)', 'participe-ibram'), (int) $recurso->id())); ?></h1>
    <span class="pi-status-badge pi-status-badge--em-presidencia">
      <?php esc_html_e('Em recurso da Presidência', 'participe-ibram'); ?>
    </span>
  </header>

  <div class="pi-recurso-grid">
    <main id="pi-decisao-main" class="pi-recurso-grid__main" tabindex="-1">

      <section class="pi-card" aria-labelledby="pres-agente-title">
        <header class="pi-card__header">
          <h2 id="pres-agente-title" class="pi-card__title">
            <?php esc_html_e('Agente recorrente', 'participe-ibram'); ?>
          </h2>
        </header>
        <div class="pi-card__body">
          <dl class="pi-meta-list">
            <div class="pi-meta-list__row">
              <dt><?php esc_html_e('Tipo', 'participe-ibram'); ?></dt>
              <dd><?php echo esc_html($tipoLabel); ?></dd>
            </div>
            <div class="pi-meta-list__row">
              <dt><?php esc_html_e('E-mail (mascarado)', 'participe-ibram'); ?></dt>
              <dd><code><?php echo esc_html($emailMascarado); ?></code></dd>
            </div>
            <div class="pi-meta-list__row">
              <dt><?php esc_html_e('Análise originária', 'participe-ibram'); ?></dt>
              <dd>#<?php echo esc_html((string) $recurso->analiseId()); ?></dd>
            </div>
          </dl>
        </div>
      </section>

      <section class="pi-card" aria-labelledby="pres-fund-title">
        <header class="pi-card__header">
          <h2 id="pres-fund-title" class="pi-card__title">
            <?php esc_html_e('Fundamentação do recorrente', 'participe-ibram'); ?>
          </h2>
        </header>
        <div class="pi-card__body pi-prose">
          <?php echo wp_kses_post(wpautop($recurso->fundamentacaoMd())); ?>
        </div>
      </section>

      <?php if ($retratacao !== null && $retratacao->isDecidido()) : ?>
        <section class="pi-card" aria-labelledby="pres-retratacao-title">
          <header class="pi-card__header">
            <h2 id="pres-retratacao-title" class="pi-card__title">
              <?php esc_html_e('Decisão da retratação (1ª instância)', 'participe-ibram'); ?>
            </h2>
          </header>
          <div class="pi-card__body">
            <p>
              <strong><?php esc_html_e('Resultado:', 'participe-ibram'); ?></strong>
              <?php echo esc_html((string) $retratacao->decisao()); ?>
              <?php if ($retratacao->decididoEm()) : ?>
                — <?php echo esc_html($retratacao->decididoEm()->format('d/m/Y H:i')); ?>
              <?php endif; ?>
            </p>
            <?php if ($retratacao->decisaoMd() !== null) : ?>
              <div class="pi-prose">
                <?php echo wp_kses_post(wpautop((string) $retratacao->decisaoMd())); ?>
              </div>
            <?php endif; ?>
          </div>
        </section>
      <?php endif; ?>

      <?php if ($analise !== null && $analise->fundamentacaoMd() !== null) : ?>
        <section class="pi-card" aria-labelledby="pres-analise-title">
          <header class="pi-card__header">
            <h2 id="pres-analise-title" class="pi-card__title">
              <?php esc_html_e('Indeferimento original', 'participe-ibram'); ?>
            </h2>
          </header>
          <div class="pi-card__body pi-prose">
            <?php echo wp_kses_post(wpautop((string) $analise->fundamentacaoMd())); ?>
          </div>
        </section>
      <?php endif; ?>

      <section id="pi-decisao-form" class="pi-card" aria-labelledby="pres-decisao-title">
        <header class="pi-card__header">
          <h2 id="pres-decisao-title" class="pi-card__title">
            <?php esc_html_e('Decisão da Presidência', 'participe-ibram'); ?>
          </h2>
        </header>
        <div class="pi-card__body">
          <form method="post" action="" data-pi-decisao-form="presidencia">
            <input type="hidden" name="pi_action" value="decidir_presidencia">
            <input type="hidden" name="recurso_id" value="<?php echo esc_attr((string) $recurso->id()); ?>">
            <?php wp_nonce_field(\Ibram\ParticipeIbram\Presentation\Admin\Controllers\RecursoPresidenciaController::NONCE_ACTION); ?>

            <fieldset class="pi-fieldset">
              <legend><?php esc_html_e('Qual é a decisão final?', 'participe-ibram'); ?></legend>

              <div class="pi-radio">
                <input type="radio" id="pres-deferir" name="deferir" value="1" required>
                <label for="pres-deferir">
                  <strong><?php esc_html_e('Deferir (reformar decisão e gerar nº de registro)', 'participe-ibram'); ?></strong>
                  <span class="description">
                    <?php esc_html_e('A Presidência reforma o indeferimento; o cadastro será deferido e receberá número de registro.', 'participe-ibram'); ?>
                  </span>
                </label>
              </div>
              <div class="pi-radio">
                <input type="radio" id="pres-indeferir" name="deferir" value="0" required>
                <label for="pres-indeferir">
                  <strong><?php esc_html_e('Manter indeferimento (final)', 'participe-ibram'); ?></strong>
                  <span class="description">
                    <?php esc_html_e('O indeferimento se torna definitivo. Não cabem novos recursos.', 'participe-ibram'); ?>
                  </span>
                </label>
              </div>
            </fieldset>

            <div class="pi-form-row">
              <label for="pres-decisao-md">
                <?php esc_html_e('Fundamentação da decisão (mín. 50 caracteres)', 'participe-ibram'); ?>
                <span aria-hidden="true">*</span>
              </label>
              <textarea id="pres-decisao-md" name="decisao_md" rows="8" minlength="50" required
                aria-describedby="pres-decisao-md-help"></textarea>
              <p id="pres-decisao-md-help" class="description">
                <?php esc_html_e('Markdown permitido. Não inclua dados pessoais sensíveis.', 'participe-ibram'); ?>
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

    <aside class="pi-recurso-grid__sidebar" aria-label="<?php esc_attr_e('Status e prazo', 'participe-ibram'); ?>">
      <div class="pi-card pi-card--sidebar">
        <header class="pi-card__header">
          <h2 class="pi-card__title"><?php esc_html_e('Prazo', 'participe-ibram'); ?></h2>
        </header>
        <div class="pi-card__body">
          <p class="pi-prazo-countdown pi-prazo--<?php echo esc_attr($severidade); ?>">
            <strong>
              <?php
              if ($diasRestantes < 0) {
                  echo esc_html(sprintf(__('Vencido há %d dia(s)', 'participe-ibram'), abs($diasRestantes)));
              } else {
                  echo esc_html(sprintf(__('%d dia(s) restantes', 'participe-ibram'), $diasRestantes));
              }
              ?>
            </strong>
          </p>
          <dl class="pi-meta-list">
            <div class="pi-meta-list__row">
              <dt><?php esc_html_e('Protocolado em', 'participe-ibram'); ?></dt>
              <dd><?php echo esc_html($recurso->protocoladoEm()->format('d/m/Y H:i')); ?></dd>
            </div>
            <div class="pi-meta-list__row">
              <dt><?php esc_html_e('Vence em', 'participe-ibram'); ?></dt>
              <dd><?php echo esc_html($prazoFim->format('d/m/Y H:i')); ?></dd>
            </div>
          </dl>
        </div>
      </div>

      <div class="pi-card pi-card--sidebar pi-recurso-timeline" aria-label="<?php esc_attr_e('Linha do tempo', 'participe-ibram'); ?>">
        <header class="pi-card__header">
          <h2 class="pi-card__title"><?php esc_html_e('Linha do tempo', 'participe-ibram'); ?></h2>
        </header>
        <ol class="pi-card__body pi-timeline">
          <?php if ($analise !== null && $analise->decididoEm()) : ?>
            <li class="pi-timeline__item pi-timeline__item--done">
              <strong><?php esc_html_e('Indeferimento', 'participe-ibram'); ?></strong>
              <time datetime="<?php echo esc_attr($analise->decididoEm()->format('c')); ?>">
                <?php echo esc_html($analise->decididoEm()->format('d/m/Y')); ?>
              </time>
            </li>
          <?php endif; ?>
          <?php if ($retratacao !== null) : ?>
            <li class="pi-timeline__item <?php echo $retratacao->isDecidido() ? 'pi-timeline__item--done' : 'pi-timeline__item--current'; ?>">
              <strong><?php esc_html_e('Retratação', 'participe-ibram'); ?></strong>
              <time datetime="<?php echo esc_attr($retratacao->protocoladoEm()->format('c')); ?>">
                <?php echo esc_html($retratacao->protocoladoEm()->format('d/m/Y')); ?>
              </time>
            </li>
          <?php endif; ?>
          <li class="pi-timeline__item pi-timeline__item--current">
            <strong><?php esc_html_e('Presidência', 'participe-ibram'); ?></strong>
            <time datetime="<?php echo esc_attr($recurso->protocoladoEm()->format('c')); ?>">
              <?php echo esc_html($recurso->protocoladoEm()->format('d/m/Y')); ?>
            </time>
          </li>
        </ol>
      </div>
    </aside>
  </div>

  <div id="pi-confirm-decisao" class="pi-modal" role="dialog" aria-modal="true"
       aria-labelledby="pi-confirm-decisao-title" aria-describedby="pi-confirm-decisao-desc" hidden>
    <div class="pi-modal__dialog">
      <h2 id="pi-confirm-decisao-title" class="pi-modal__title">
        <?php esc_html_e('Confirmar decisão final', 'participe-ibram'); ?>
      </h2>
      <p id="pi-confirm-decisao-desc" class="pi-modal__body">
        <?php esc_html_e('Esta decisão é definitiva. Deseja confirmar?', 'participe-ibram'); ?>
      </p>
      <div class="pi-modal__actions">
        <button type="button" class="button" data-pi-modal-cancel><?php esc_html_e('Cancelar', 'participe-ibram'); ?></button>
        <button type="button" class="button button-primary" data-pi-modal-confirm>
          <?php esc_html_e('Confirmar', 'participe-ibram'); ?>
        </button>
      </div>
    </div>
  </div>
</div>
