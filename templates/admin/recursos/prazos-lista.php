<?php
/**
 * Template — Listagem priorizada de prazos de recursos.
 *
 * Vars esperadas:
 *  - $listTable RecursosPrazosListTable
 *  - $kpis      array{vencendo_hoje:int,vencidos:int,total_abertos:int}
 *
 * @package Ibram\ParticipeIbram\Templates\Admin\Recursos
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/** @var \Ibram\ParticipeIbram\Presentation\Admin\ListTables\RecursosPrazosListTable $listTable */
/** @var array{vencendo_hoje:int,vencidos:int,total_abertos:int} $kpis */
$slug = \Ibram\ParticipeIbram\Presentation\Admin\RecursoMenuRegistry::SLUG_PRAZOS;
?>
<div class="participe-ibram-scope wrap">
  <a class="pi-skip-link" href="#pi-prazos-list"><?php esc_html_e('Pular para a lista', 'participe-ibram'); ?></a>

  <header role="banner">
    <h1><?php esc_html_e('Recursos — Prazos vencendo', 'participe-ibram'); ?></h1>
    <p class="description">
      <?php esc_html_e('Painel priorizado de recursos abertos por prazo. Use os filtros para isolar prazos vencidos ou vencendo em até 2 dias.', 'participe-ibram'); ?>
    </p>
  </header>

  <section class="pi-admin-grid" aria-live="polite" aria-atomic="false" aria-label="<?php esc_attr_e('Indicadores de prazo', 'participe-ibram'); ?>">
    <article class="pi-card pi-card--kpi pi-card--urgente">
      <header class="pi-card__header"><h2 class="pi-card__title"><?php esc_html_e('Vencendo hoje', 'participe-ibram'); ?></h2></header>
      <div class="pi-card__body">
        <strong class="pi-card__metric-value"><?php echo esc_html((string) $kpis['vencendo_hoje']); ?></strong>
        <span class="pi-card__metric-label"><?php esc_html_e('recurso(s) sem decisão', 'participe-ibram'); ?></span>
      </div>
      <footer class="pi-card__footer">
        <a class="pi-button pi-button--secondary pi-button--sm"
           href="<?php echo esc_url(add_query_arg(['page' => $slug, 'prazo_status' => 'vencendo'], admin_url('admin.php'))); ?>">
          <?php esc_html_e('Filtrar', 'participe-ibram'); ?>
        </a>
      </footer>
    </article>

    <article class="pi-card pi-card--kpi pi-card--vencido">
      <header class="pi-card__header"><h2 class="pi-card__title"><?php esc_html_e('Vencidos sem decisão', 'participe-ibram'); ?></h2></header>
      <div class="pi-card__body">
        <strong class="pi-card__metric-value"><?php echo esc_html((string) $kpis['vencidos']); ?></strong>
        <span class="pi-card__metric-label"><?php esc_html_e('recurso(s) atrasados', 'participe-ibram'); ?></span>
      </div>
      <footer class="pi-card__footer">
        <a class="pi-button pi-button--secondary pi-button--sm"
           href="<?php echo esc_url(add_query_arg(['page' => $slug, 'prazo_status' => 'vencido'], admin_url('admin.php'))); ?>">
          <?php esc_html_e('Filtrar', 'participe-ibram'); ?>
        </a>
      </footer>
    </article>

    <article class="pi-card pi-card--kpi pi-card--ok">
      <header class="pi-card__header"><h2 class="pi-card__title"><?php esc_html_e('Total abertos', 'participe-ibram'); ?></h2></header>
      <div class="pi-card__body">
        <strong class="pi-card__metric-value"><?php echo esc_html((string) $kpis['total_abertos']); ?></strong>
        <span class="pi-card__metric-label"><?php esc_html_e('recurso(s) em andamento', 'participe-ibram'); ?></span>
      </div>
    </article>
  </section>

  <main id="pi-prazos-list" tabindex="-1">
    <form method="get" action="">
      <input type="hidden" name="page" value="<?php echo esc_attr($slug); ?>">
      <?php $listTable->display(); ?>
    </form>
  </main>
</div>
