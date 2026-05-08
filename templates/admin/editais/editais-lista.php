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

/** @var \Ibram\ParticipeIbram\Presentation\Admin\ListTables\EditaisListTable $listTable */
/** @var array<string,int> $resumo */
/** @var bool $podeCriar */
/** @var string $urlNovo */
/** @var array{type:string,message:string}|null $flash */

$resumo    = isset($resumo) && is_array($resumo) ? $resumo : [];
$flash     = isset($flash) ? $flash : null;
$podeCriar = isset($podeCriar) && $podeCriar;
$urlNovo   = isset($urlNovo) ? (string) $urlNovo : '';
?>
<div class="participe-ibram-scope wrap pi-admin-editais">
  <a class="pi-skip-link" href="#pi-admin-main"><?php esc_html_e('Pular para o conteúdo', 'participe-ibram'); ?></a>

  <header>
    <h1 class="wp-heading-inline"><?php esc_html_e('Editais', 'participe-ibram'); ?></h1>
    <?php if ($podeCriar && $urlNovo !== '') : ?>
      <a href="<?php echo esc_url($urlNovo); ?>" class="page-title-action">
        <?php esc_html_e('+ Novo Edital', 'participe-ibram'); ?>
      </a>
    <?php endif; ?>
  </header>

  <nav class="pi-breadcrumb" aria-label="<?php esc_attr_e('Você está em', 'participe-ibram'); ?>">
    <ol class="pi-breadcrumb__list">
      <li class="pi-breadcrumb__item">
        <a href="<?php echo esc_url(admin_url('admin.php?page=' . EditalMenuRegistry::SLUG_ROOT)); ?>">
          <?php esc_html_e('Participe Ibram', 'participe-ibram'); ?>
        </a>
      </li>
      <li class="pi-breadcrumb__item" aria-current="page">
        <?php esc_html_e('Editais', 'participe-ibram'); ?>
      </li>
    </ol>
  </nav>

  <?php if ($flash !== null) : ?>
    <div class="notice notice-<?php echo esc_attr($flash['type'] === 'success' ? 'success' : 'error'); ?> is-dismissible" role="alert">
      <p><?php echo esc_html($flash['message']); ?></p>
    </div>
  <?php endif; ?>

  <main id="pi-admin-main" tabindex="-1">
    <form id="pi-editais-form" method="get">
      <input type="hidden" name="page" value="<?php echo esc_attr(EditalMenuRegistry::SLUG_EDITAIS); ?>">
      <?php
        $listTable->search_box(esc_html__('Buscar editais', 'participe-ibram'), 'pi-edital-search');
        $listTable->display();
      ?>
    </form>
  </main>
</div>
