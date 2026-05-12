<?php
/**
 * Template — Listagem de Votações (admin).
 *
 * Vars injetadas por VotacaoListController::render():
 *  - VotacoesListTable $listTable
 *  - array<string,int> $resumo (status => contagem)
 *  - array{type:string,message:string}|null $flash
 *
 * @package Ibram\ParticipeIbram\Templates\Admin\Votacoes
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use Ibram\ParticipeIbram\Presentation\Admin\Support\Notice;
use Ibram\ParticipeIbram\Presentation\Admin\Support\PageLayout;
use Ibram\ParticipeIbram\Presentation\Admin\VotacaoMenuRegistry;

/** @var \Ibram\ParticipeIbram\Presentation\Admin\ListTables\VotacoesListTable $listTable */
/** @var array<string,int> $resumo */
/** @var array{type:string,message:string}|null $flash */

$resumo = isset($resumo) && is_array($resumo) ? $resumo : [];
$flash  = isset($flash) ? $flash : null;

PageLayout::open(
    __('Votações', 'participe-ibram'),
    [
        ['label' => __('Início', 'participe-ibram'), 'url' => admin_url()],
        ['label' => __('Votações', 'participe-ibram'), 'url' => admin_url('admin.php?page=' . VotacaoMenuRegistry::SLUG_VOTACOES)],
        ['label' => __('Votações', 'participe-ibram')],
    ]
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

<main id="pi-admin-main" tabindex="-1">
    <div role="status" id="pi-admin-votacoes-live" aria-live="polite" class="screen-reader-text"></div>

    <div class="pi-list-table">
        <form id="pi-votacoes-form" method="get">
            <input type="hidden" name="page" value="<?php echo esc_attr(VotacaoMenuRegistry::SLUG_VOTACOES); ?>">
            <?php $listTable->display(); ?>
        </form>
    </div>
</main>
<?php
PageLayout::close();
