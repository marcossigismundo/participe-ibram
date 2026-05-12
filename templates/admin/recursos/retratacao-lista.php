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

use Ibram\ParticipeIbram\Presentation\Admin\RecursoMenuRegistry;
use Ibram\ParticipeIbram\Presentation\Admin\Support\EmptyState;
use Ibram\ParticipeIbram\Presentation\Admin\Support\PageLayout;

/** @var \Ibram\ParticipeIbram\Presentation\Admin\ListTables\RecursosRetratacaoListTable $listTable */

PageLayout::open(
    __('Recursos — Em Retratação', 'participe-ibram'),
    [
        ['label' => __('Início', 'participe-ibram'), 'url' => admin_url('admin.php?page=participe-ibram')],
        ['label' => __('Análise de cadastros', 'participe-ibram'), 'url' => admin_url('admin.php?page=participe-ibram_cadastros')],
        ['label' => __('Recursos — Retratação', 'participe-ibram')],
    ]
);
?>
<a class="pi-skip-link" href="#pi-recursos-list"><?php esc_html_e('Pular para a lista', 'participe-ibram'); ?></a>

<p class="pi-admin-page__description">
  <?php esc_html_e('Recursos protocolados pelos agentes contra indeferimentos — primeiro decididos pela autoridade que indeferiu (Art. 7º, Portaria 3230/2024).', 'participe-ibram'); ?>
</p>

<main id="pi-recursos-list" tabindex="-1" class="pi-list-table">
  <form method="get" action="">
    <input type="hidden" name="page" value="<?php echo esc_attr(RecursoMenuRegistry::SLUG_RETRATACAO); ?>">
    <?php $listTable->display(); ?>
  </form>
</main>

<?php PageLayout::close(); ?>
