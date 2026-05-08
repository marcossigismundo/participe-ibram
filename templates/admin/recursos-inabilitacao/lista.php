<?php
/**
 * Template — listagem de recursos de inabilitação pendentes (W5-C).
 *
 * Vars injetadas:
 *  - RecursosInabilitacaoListTable $listTable
 *
 * @package Ibram\ParticipeIbram\Templates\Admin\RecursosInabilitacao
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/** @var \Ibram\ParticipeIbram\Presentation\Admin\ListTables\RecursosInabilitacaoListTable $listTable */
?>
<div class="participe-ibram-scope wrap pi-recursos-inabilitacao-lista">
  <h1 class="wp-heading-inline">
    <?php \esc_html_e('Recursos de Inabilitação — Pendentes', 'participe-ibram'); ?>
  </h1>

  <hr class="wp-header-end">

  <form method="get" action="">
    <input type="hidden" name="page"
           value="<?php echo \esc_attr(\Ibram\ParticipeIbram\Presentation\Admin\HabilitacaoMenuRegistry::SLUG_RECURSOS_INABILITACAO); ?>">
    <?php $listTable->views(); ?>
    <?php $listTable->display(); ?>
  </form>
</div>
