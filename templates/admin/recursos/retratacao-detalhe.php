<?php
/**
 * Template — Tela de decisão de Recurso em fase Retratação.
 *
 * Vars esperadas:
 *  - $recurso  Recurso
 *  - $analise  Analise|null
 *  - $agente   Agente|null
 *  - $flash    array{level:string,message:string}|null
 *  - $nonce    string
 *  - $listaUrl string
 *
 * @package Ibram\ParticipeIbram\Templates\Admin\Recursos
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use Ibram\ParticipeIbram\Presentation\Admin\Support\Notice;
use Ibram\ParticipeIbram\Presentation\Admin\Support\PageLayout;

/** @var \Ibram\ParticipeIbram\Domain\Analise\Recurso $recurso */
/** @var \Ibram\ParticipeIbram\Domain\Analise\Analise|null $analise */
/** @var \Ibram\ParticipeIbram\Domain\Agente\Agente|null $agente */
/** @var array{level:string,message:string}|null $flash */
/** @var string $nonce */
/** @var string $listaUrl */

$now             = new \DateTimeImmutable('now');
$prazoFim        = $recurso->prazoFim();
$prazoDiff       = $now->diff($prazoFim);
$diasRestantes   = (int) $prazoDiff->format('%r%a');
$severidade      = $diasRestantes < 0 ? 'vencido' : ($diasRestantes <= 2 ? 'urgente' : ($diasRestantes <= 5 ? 'atencao' : 'ok'));

$tipoLabel       = $agente !== null
    ? (string) $agente->getTipo()->value()
    : '—';
$emailMascarado  = $agente !== null
    ? \Ibram\ParticipeIbram\Core\Audit\PiiMasker::maskEmail($agente->getEmailPrincipal())
    : '—';

$recursoTitle = sprintf(
    /* translators: %d: ID do recurso */
    __('Recurso #%d — Decisão de Retratação', 'participe-ibram'),
    (int) $recurso->id()
);

PageLayout::open(
    $recursoTitle,
    [
        ['label' => __('Início', 'participe-ibram'), 'url' => admin_url('admin.php?page=participe-ibram')],
        ['label' => __('Análise de cadastros', 'participe-ibram'), 'url' => admin_url('admin.php?page=participe-ibram_cadastros')],
        ['label' => __('Recursos — Retratação', 'participe-ibram'), 'url' => esc_url($listaUrl)],
        ['label' => sprintf(__('Recurso #%d', 'participe-ibram'), (int) $recurso->id())],
    ]
);
?>
<a class="pi-skip-link" href="#pi-decisao-form"><?php esc_html_e('Pular para o formulário de decisão', 'participe-ibram'); ?></a>

<?php
if ($flash !== null) {
    if ($flash['level'] === 'success') {
        Notice::success($flash['message'], true);
    } else {
        Notice::danger($flash['message'], true);
    }
}
?>

<div class="pi-recurso-header">
  <span class="pi-status-badge pi-status-badge--em-retratacao">
    <?php esc_html_e('Em retratação', 'participe-ibram'); ?>
  </span>
</div>

<div class="pi-recurso-grid">
  <main id="pi-decisao-main" class="pi-recurso-grid__main" tabindex="-1">

    <section class="pi-card" aria-labelledby="recurso-agente-title">
      <header class="pi-card__header">
        <h2 id="recurso-agente-title" class="pi-card__title">
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
          <?php if ($analise !== null && $analise->decididoEm()) : ?>
            <div class="pi-meta-list__row">
              <dt><?php esc_html_e('Indeferimento em', 'participe-ibram'); ?></dt>
              <dd><?php echo esc_html($analise->decididoEm()->format('d/m/Y H:i')); ?></dd>
            </div>
          <?php endif; ?>
        </dl>
      </div>
    </section>

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

    <?php if ($analise !== null && $analise->fundamentacaoMd() !== null) : ?>
      <section class="pi-card" aria-labelledby="recurso-analise-orig-title">
        <header class="pi-card__header">
          <h2 id="recurso-analise-orig-title" class="pi-card__title">
            <?php esc_html_e('Análise original indeferida', 'participe-ibram'); ?>
          </h2>
        </header>
        <div class="pi-card__body pi-prose">
          <?php echo wp_kses_post(wpautop((string) $analise->fundamentacaoMd())); ?>
        </div>
      </section>
    <?php endif; ?>

    <section id="pi-decisao-form" class="pi-card" aria-labelledby="recurso-decisao-title">
      <header class="pi-card__header">
        <h2 id="recurso-decisao-title" class="pi-card__title">
          <?php esc_html_e('Decisão de retratação', 'participe-ibram'); ?>
        </h2>
      </header>
      <div class="pi-card__body">
        <form method="post" action="" class="pi-form" data-pi-decisao-form="retratacao">
          <input type="hidden" name="pi_action" value="decidir_retratacao">
          <input type="hidden" name="recurso_id" value="<?php echo esc_attr((string) $recurso->id()); ?>">
          <?php wp_nonce_field(\Ibram\ParticipeIbram\Presentation\Admin\Controllers\RecursoRetratacaoController::NONCE_ACTION); ?>

          <div class="pi-field-group">
            <fieldset class="pi-fieldset">
              <legend><?php esc_html_e('Qual é a sua decisão?', 'participe-ibram'); ?></legend>

              <div class="pi-radio">
                <input type="radio" id="opt-reconsiderar" name="reconsiderar" value="1" required>
                <label for="opt-reconsiderar">
                  <strong><?php esc_html_e('Reconsiderar (deferir cadastro)', 'participe-ibram'); ?></strong>
                  <span class="description">
                    <?php esc_html_e('Reformo minha decisão anterior; o cadastro será deferido e receberá número de registro.', 'participe-ibram'); ?>
                  </span>
                </label>
              </div>
              <div class="pi-radio">
                <input type="radio" id="opt-manter" name="reconsiderar" value="0" required>
                <label for="opt-manter">
                  <strong><?php esc_html_e('Manter indeferimento (subir à Presidência)', 'participe-ibram'); ?></strong>
                  <span class="description">
                    <?php esc_html_e('Mantenho minha decisão; o recurso seguirá para julgamento pela Presidência.', 'participe-ibram'); ?>
                  </span>
                </label>
              </div>
            </fieldset>
          </div>

          <div class="pi-field-group">
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

  <aside class="pi-recurso-grid__sidebar" aria-label="<?php esc_attr_e('Status e prazo', 'participe-ibram'); ?>">
    <div class="pi-card pi-card--sidebar">
      <header class="pi-card__header">
        <h2 class="pi-card__title"><?php esc_html_e('Prazo do recurso', 'participe-ibram'); ?></h2>
      </header>
      <div class="pi-card__body">
        <p class="pi-prazo-countdown pi-prazo--<?php echo esc_attr($severidade); ?>"
           aria-label="<?php echo esc_attr(sprintf(__('Faltam %d dias', 'participe-ibram'), $diasRestantes)); ?>">
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
  </aside>
</div>

<?php /* Modal acessível de confirmação — gerenciado por Modal.js da Wave 3. */ ?>
<div id="pi-confirm-decisao" class="pi-modal" role="dialog" aria-modal="true"
     aria-labelledby="pi-confirm-decisao-title" aria-describedby="pi-confirm-decisao-desc" hidden>
  <div class="pi-modal__dialog">
    <h2 id="pi-confirm-decisao-title" class="pi-modal__title">
      <?php esc_html_e('Confirmar decisão', 'participe-ibram'); ?>
    </h2>
    <p id="pi-confirm-decisao-desc" class="pi-modal__body">
      <?php esc_html_e('Esta ação registrará sua decisão e seguirá o fluxo recursal previsto na Portaria 3230/2024. Deseja confirmar?', 'participe-ibram'); ?>
    </p>
    <div class="pi-modal__actions">
      <button type="button" class="button" data-pi-modal-cancel><?php esc_html_e('Cancelar', 'participe-ibram'); ?></button>
      <button type="button" class="button button-primary" data-pi-modal-confirm>
        <?php esc_html_e('Confirmar', 'participe-ibram'); ?>
      </button>
    </div>
  </div>
</div>

<?php PageLayout::close(); ?>
