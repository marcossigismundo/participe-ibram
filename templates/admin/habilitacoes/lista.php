<?php
/**
 * Template — listagem de inscrições em fase de habilitação (W5-C).
 *
 * Vars injetadas:
 *  - InscricoesHabilitacaoListTable $listTable
 *
 * @package Ibram\ParticipeIbram\Templates\Admin\Habilitacoes
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/** @var \Ibram\ParticipeIbram\Presentation\Admin\ListTables\InscricoesHabilitacaoListTable $listTable */
?>
<div class="participe-ibram-scope wrap pi-habilitacao-lista">
  <h1 class="wp-heading-inline">
    <?php \esc_html_e('Habilitações — Pendentes', 'participe-ibram'); ?>
  </h1>

  <hr class="wp-header-end">

  <form method="get" action="">
    <input type="hidden" name="page" value="<?php echo \esc_attr(\Ibram\ParticipeIbram\Presentation\Admin\HabilitacaoMenuRegistry::SLUG_HABILITACOES); ?>">
    <?php $listTable->views(); ?>
    <?php $listTable->search_box(\__('Buscar', 'participe-ibram'), 'pi-search'); ?>
    <?php $listTable->display(); ?>
  </form>
</div>
