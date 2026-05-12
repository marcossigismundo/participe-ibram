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

use Ibram\ParticipeIbram\Presentation\Admin\HabilitacaoMenuRegistry;
use Ibram\ParticipeIbram\Presentation\Admin\Support\PageLayout;

/** @var \Ibram\ParticipeIbram\Presentation\Admin\ListTables\RecursosInabilitacaoListTable $listTable */

PageLayout::open(
    __('Recursos de Inabilitação — Pendentes', 'participe-ibram'),
    [
        ['label' => __('Início', 'participe-ibram'), 'url' => admin_url()],
        ['label' => __('Editais & habilitações', 'participe-ibram'), 'url' => admin_url('admin.php?page=' . HabilitacaoMenuRegistry::SLUG_HABILITACOES)],
        ['label' => __('Recursos de inabilitação', 'participe-ibram')],
    ]
);
?>
<div class="pi-list-table">
    <form method="get" action="">
        <input type="hidden" name="page"
               value="<?php echo esc_attr(HabilitacaoMenuRegistry::SLUG_RECURSOS_INABILITACAO); ?>">
        <?php $listTable->views(); ?>
        <?php $listTable->display(); ?>
    </form>
</div>
<?php
PageLayout::close();
