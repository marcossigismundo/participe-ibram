<?php
/**
 * Template — Listagem administrativa de Recursos em fase Retratação.
 *
 * Vars esperadas:
 *  - $listTable RecursosRetratacaoListTable (já com prepare_items() chamado).
 *
 * @package Ibram\ParticipeIbram\Templates\Admin\Recursos
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/** @var \Ibram\ParticipeIbram\Presentation\Admin\ListTables\RecursosRetratacaoListTable $listTable */
?>
<div class="participe-ibram-scope wrap">
  <a class="pi-skip-link" href="#pi-recursos-list"><?php esc_html_e('Pular para a lista', 'participe-ibram'); ?></a>

  <header role="banner">
    <h1><?php esc_html_e('Recursos — Em Retratação', 'participe-ibram'); ?></h1>
    <p class="description">
      <?php esc_html_e('Recursos protocolados pelos agentes contra indeferimentos — primeiro decididos pela autoridade que indeferiu (Art. 7º, Portaria 3230/2024).', 'participe-ibram'); ?>
    </p>
  </header>

  <main id="pi-recursos-list" tabindex="-1">
    <form method="get" action="">
      <input type="hidden" name="page" value="<?php echo esc_attr(\Ibram\ParticipeIbram\Presentation\Admin\RecursoMenuRegistry::SLUG_RETRATACAO); ?>">
      <?php $listTable->display(); ?>
    </form>
  </main>
</div>
