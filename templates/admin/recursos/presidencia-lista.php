<?php
/**
 * Template — Listagem administrativa de Recursos em fase Presidência.
 *
 * Vars esperadas:
 *  - $listTable RecursosPresidenciaListTable
 *
 * @package Ibram\ParticipeIbram\Templates\Admin\Recursos
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/** @var \Ibram\ParticipeIbram\Presentation\Admin\ListTables\RecursosPresidenciaListTable $listTable */
?>
<div class="participe-ibram-scope wrap">
  <a class="pi-skip-link" href="#pi-recursos-list"><?php esc_html_e('Pular para a lista', 'participe-ibram'); ?></a>

  <header role="banner">
    <h1><?php esc_html_e('Recursos — Presidência (instância final)', 'participe-ibram'); ?></h1>
    <p class="description">
      <?php esc_html_e('Recursos em última instância: a Presidência defere (reformando a decisão) ou mantém o indeferimento como definitivo.', 'participe-ibram'); ?>
    </p>
  </header>

  <main id="pi-recursos-list" tabindex="-1">
    <form method="get" action="">
      <input type="hidden" name="page" value="<?php echo esc_attr(\Ibram\ParticipeIbram\Presentation\Admin\RecursoMenuRegistry::SLUG_PRESIDENCIA); ?>">
      <?php $listTable->display(); ?>
    </form>
  </main>
</div>
