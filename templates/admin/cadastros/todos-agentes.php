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
use Ibram\ParticipeIbram\Presentation\Admin\Support\EmptyState;
use Ibram\ParticipeIbram\Presentation\Admin\Support\PageLayout;

/** @var \Ibram\ParticipeIbram\Presentation\Admin\ListTables\TodosAgentesListTable $listTable */
/** @var array<string,int>                                                          $resumo */
/** @var array<string,string>                                                       $listLabels */

$resumo     = isset($resumo) && is_array($resumo) ? $resumo : [];
$listLabels = isset($listLabels) && is_array($listLabels) ? $listLabels : [];

PageLayout::open(
    __('Todos os agentes', 'participe-ibram'),
    [
        ['label' => __('Início', 'participe-ibram'), 'url' => admin_url('admin.php?page=participe-ibram')],
        ['label' => __('Análise de cadastros', 'participe-ibram'), 'url' => admin_url('admin.php?page=participe-ibram_cadastros')],
        ['label' => __('Todos os agentes', 'participe-ibram')],
    ]
);
?>
<a class="pi-skip-link" href="#pi-admin-main">
  <?php esc_html_e('Pular para o conteúdo', 'participe-ibram'); ?>
</a>

<main id="pi-admin-main" tabindex="-1">
  <section class="pi-list-table" aria-labelledby="pi-todos-h2">
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

      <?php
      $totalAgentes = array_sum($resumo);
      if ($totalAgentes === 0) {
          EmptyState::render(
              __('Nenhum agente cadastrado', 'participe-ibram'),
              __('Ainda não há cadastros submetidos no sistema.', 'participe-ibram'),
              [
                  'label' => __('Ir para a Fila de Análise', 'participe-ibram'),
                  'url'   => admin_url('admin.php?page=' . MenuRegistry::SLUG_CADASTROS),
              ],
              'dashicons-groups'
          );
      } else {
          $listTable->display();
      }
      ?>
    </form>
  </section>
</main>

<?php PageLayout::close(); ?>
