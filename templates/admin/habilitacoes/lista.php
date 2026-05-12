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

use Ibram\ParticipeIbram\Presentation\Admin\HabilitacaoMenuRegistry;
use Ibram\ParticipeIbram\Presentation\Admin\Support\PageLayout;

/** @var \Ibram\ParticipeIbram\Presentation\Admin\ListTables\InscricoesHabilitacaoListTable $listTable */

PageLayout::open(
    __('Habilitações — Pendentes', 'participe-ibram'),
    [
        ['label' => __('Início', 'participe-ibram'), 'url' => admin_url()],
        ['label' => __('Editais & habilitações', 'participe-ibram'), 'url' => admin_url('admin.php?page=' . HabilitacaoMenuRegistry::SLUG_HABILITACOES)],
        ['label' => __('Habilitações', 'participe-ibram')],
    ]
);
?>
<div class="pi-list-table">
    <form method="get" action="">
        <input type="hidden" name="page" value="<?php echo esc_attr(HabilitacaoMenuRegistry::SLUG_HABILITACOES); ?>">
        <?php $listTable->views(); ?>
        <?php $listTable->search_box(esc_html__('Buscar', 'participe-ibram'), 'pi-search'); ?>
        <?php $listTable->display(); ?>
    </form>
</div>
<?php
PageLayout::close();
