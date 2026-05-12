<?php
/**
 * Template — Listagem de Editais (admin).
 *
 * Vars injetadas por EditalListController::render():
 *  - EditaisListTable $listTable
 *  - array<string,int> $resumo          (status => contagem)
 *  - bool $podeCriar
 *  - string $urlNovo
 *  - array{type:string,message:string}|null $flash
 *
 * @package Ibram\ParticipeIbram\Templates\Admin\Editais
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use Ibram\ParticipeIbram\Presentation\Admin\EditalMenuRegistry;
use Ibram\ParticipeIbram\Presentation\Admin\Support\Notice;
use Ibram\ParticipeIbram\Presentation\Admin\Support\PageLayout;

/** @var \Ibram\ParticipeIbram\Presentation\Admin\ListTables\EditaisListTable $listTable */
/** @var array<string,int> $resumo */
/** @var bool $podeCriar */
/** @var string $urlNovo */
/** @var array{type:string,message:string}|null $flash */

$resumo    = isset($resumo) && is_array($resumo) ? $resumo : [];
$flash     = isset($flash) ? $flash : null;
$podeCriar = isset($podeCriar) && $podeCriar;
$urlNovo   = isset($urlNovo) ? (string) $urlNovo : '';

$primaryAction = ($podeCriar && $urlNovo !== '')
    ? ['label' => __('+ Novo Edital', 'participe-ibram'), 'url' => $urlNovo]
    : null;

PageLayout::open(
    __('Editais', 'participe-ibram'),
    [
        ['label' => __('Início', 'participe-ibram'), 'url' => admin_url()],
        ['label' => __('Editais & habilitações', 'participe-ibram'), 'url' => admin_url('admin.php?page=' . EditalMenuRegistry::SLUG_EDITAIS)],
        ['label' => __('Editais', 'participe-ibram')],
    ],
    $primaryAction
);
?>
<a class="pi-skip-link" href="#pi-admin-main"><?php esc_html_e('Pular para o conteúdo', 'participe-ibram'); ?></a>

<?php if ($flash !== null) : ?>
    <?php
    if ($flash['type'] === 'success') {
        Notice::success($flash['message'], true);
    } else {
        Notice::danger($flash['message'], true);
    }
    ?>
<?php endif; ?>

<main id="pi-admin-main" tabindex="-1" class="pi-list-table">
    <form id="pi-editais-form" method="get">
        <input type="hidden" name="page" value="<?php echo esc_attr(EditalMenuRegistry::SLUG_EDITAIS); ?>">
        <?php
            $listTable->search_box(esc_html__('Buscar editais', 'participe-ibram'), 'pi-edital-search');
            $listTable->display();
        ?>
    </form>
</main>
<?php
PageLayout::close();
