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

use Ibram\ParticipeIbram\Presentation\Admin\VotacaoMenuRegistry;

/** @var \Ibram\ParticipeIbram\Presentation\Admin\ListTables\VotacoesListTable $listTable */
/** @var array<string,int> $resumo */
/** @var array{type:string,message:string}|null $flash */

$resumo = isset($resumo) && is_array($resumo) ? $resumo : [];
$flash  = isset($flash) ? $flash : null;
?>
<div class="participe-ibram-scope wrap pi-admin-votacoes">
  <a class="pi-skip-link" href="#pi-admin-main"><?php esc_html_e('Pular para o conteúdo', 'participe-ibram'); ?></a>

  <header>
    <h1 class="wp-heading-inline"><?php esc_html_e('Votações', 'participe-ibram'); ?></h1>
  </header>

  <nav class="pi-breadcrumb" aria-label="<?php esc_attr_e('Você está em', 'participe-ibram'); ?>">
    <ol class="pi-breadcrumb__list">
      <li class="pi-breadcrumb__item">
        <a href="<?php echo esc_url(admin_url('admin.php?page=' . VotacaoMenuRegistry::SLUG_ROOT)); ?>">
          <?php esc_html_e('Participe Ibram', 'participe-ibram'); ?>
        </a>
      </li>
      <li class="pi-breadcrumb__item" aria-current="page">
        <?php esc_html_e('Votações', 'participe-ibram'); ?>
      </li>
    </ol>
  </nav>

  <?php if ($flash !== null) : ?>
    <div class="notice notice-<?php echo esc_attr($flash['type'] === 'success' ? 'success' : 'error'); ?> is-dismissible" role="alert">
      <p><?php echo esc_html($flash['message']); ?></p>
    </div>
  <?php endif; ?>

  <main id="pi-admin-main" tabindex="-1">
    <div role="status" id="pi-admin-votacoes-live" aria-live="polite" class="screen-reader-text"></div>

    <form id="pi-votacoes-form" method="get">
      <input type="hidden" name="page" value="<?php echo esc_attr(VotacaoMenuRegistry::SLUG_VOTACOES); ?>">
      <?php
        $listTable->display();
      ?>
    </form>
  </main>
</div>
