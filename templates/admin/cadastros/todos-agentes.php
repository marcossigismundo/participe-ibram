<?php
/**
 * Template — Todos os agentes (admin).
 *
 * Vars:
 *  - \Ibram\ParticipeIbram\Presentation\Admin\ListTables\TodosAgentesListTable $listTable
 *  - array<string,int>                                                         $resumo
 *  - array<string,string>                                                      $listLabels
 *
 * @package Ibram\ParticipeIbram\Templates\Admin\Cadastros
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use Ibram\ParticipeIbram\Domain\Agente\StatusCadastro;
use Ibram\ParticipeIbram\Presentation\Admin\MenuRegistry;

/** @var \Ibram\ParticipeIbram\Presentation\Admin\ListTables\TodosAgentesListTable $listTable */
/** @var array<string,int>                                                          $resumo */
/** @var array<string,string>                                                       $listLabels */

$resumo     = isset($resumo) && is_array($resumo) ? $resumo : [];
$listLabels = isset($listLabels) && is_array($listLabels) ? $listLabels : [];
?>
<div class="participe-ibram-scope wrap pi-admin-cadastros">
  <a class="pi-skip-link" href="#pi-admin-main">
    <?php esc_html_e('Pular para o conteúdo', 'participe-ibram'); ?>
  </a>

  <header role="banner">
    <h1><?php esc_html_e('Todos os agentes', 'participe-ibram'); ?></h1>
  </header>

  <nav class="pi-breadcrumb" aria-label="<?php esc_attr_e('Você está em', 'participe-ibram'); ?>">
    <ol class="pi-breadcrumb__list">
      <li class="pi-breadcrumb__item">
        <a href="<?php echo esc_url(admin_url('admin.php?page=' . MenuRegistry::SLUG_ROOT)); ?>">
          <?php esc_html_e('Participe Ibram', 'participe-ibram'); ?>
        </a>
      </li>
      <li class="pi-breadcrumb__item" aria-current="page">
        <?php esc_html_e('Todos os agentes', 'participe-ibram'); ?>
      </li>
    </ol>
  </nav>

  <main id="pi-admin-main" tabindex="-1">
    <section aria-labelledby="pi-todos-h2">
      <h2 id="pi-todos-h2" class="screen-reader-text"><?php esc_html_e('Lista de cadastros', 'participe-ibram'); ?></h2>
      <form method="get" action="">
        <input type="hidden" name="page" value="<?php echo esc_attr(MenuRegistry::SLUG_AGENTES); ?>" />
        <p class="search-box">
          <label class="screen-reader-text" for="pi-search-agentes"><?php esc_html_e('Buscar cadastros', 'participe-ibram'); ?></label>
          <input type="search" id="pi-search-agentes" name="s" value="<?php
              echo isset($_GET['s'])
                  ? esc_attr((string) wp_unslash((string) $_GET['s'])) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                  : ''; ?>" />
          <input type="submit" class="button" value="<?php esc_attr_e('Buscar', 'participe-ibram'); ?>" />
        </p>
        <?php $listTable->display(); ?>
      </form>
    </section>
  </main>
</div>
