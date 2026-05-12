<?php
/**
 * Template — Fila de Análise (admin).
 *
 * Vars esperadas (preenchidas pelo FilaAnaliseController::render()):
 *  - \Ibram\ParticipeIbram\Presentation\Admin\ListTables\FilaAnaliseListTable $listTable
 *  - array<string,int>  $resumo            (status_code => total)
 *  - array<string,string> $listLabels       (status_code => label traduzido)
 *  - float              $tempoMedio        (dias)
 *  - array{type:string,message:string}|null $flash
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
use Ibram\ParticipeIbram\Presentation\Admin\Support\Notice;
use Ibram\ParticipeIbram\Presentation\Admin\Support\PageLayout;

/** @var \Ibram\ParticipeIbram\Presentation\Admin\ListTables\FilaAnaliseListTable $listTable */
/** @var array<string,int>                                                       $resumo */
/** @var array<string,string>                                                    $listLabels */
/** @var float                                                                   $tempoMedio */
/** @var array{type:string,message:string}|null                                  $flash */

$resumo     = isset($resumo) && is_array($resumo) ? $resumo : [];
$listLabels = isset($listLabels) && is_array($listLabels) ? $listLabels : [];
$tempoMedio = isset($tempoMedio) ? (float) $tempoMedio : 0.0;
$flash      = isset($flash) ? $flash : null;

PageLayout::open(
    __('Fila de Análise', 'participe-ibram'),
    [
        ['label' => __('Início', 'participe-ibram'), 'url' => admin_url('admin.php?page=participe-ibram')],
        ['label' => __('Análise de cadastros', 'participe-ibram'), 'url' => admin_url('admin.php?page=participe-ibram_cadastros')],
        ['label' => __('Fila de Análise', 'participe-ibram')],
    ]
);
?>
<a class="pi-skip-link" href="#pi-admin-main">
  <?php esc_html_e('Pular para o conteúdo', 'participe-ibram'); ?>
</a>

<?php
if ($flash !== null) {
    if ($flash['type'] === 'success') {
        Notice::success($flash['message'], true);
    } else {
        Notice::danger($flash['message'], true);
    }
}
?>

<main id="pi-admin-main" tabindex="-1" class="pi-admin-cadastros__main">
  <div class="pi-admin-cadastros__layout">
    <section class="pi-admin-cadastros__list pi-list-table" aria-labelledby="pi-fila-analise-h2">
      <h2 id="pi-fila-analise-h2" class="screen-reader-text"><?php esc_html_e('Lista de cadastros', 'participe-ibram'); ?></h2>

      <form method="get" action="">
        <input type="hidden" name="page" value="<?php echo esc_attr(MenuRegistry::SLUG_CADASTROS); ?>" />

        <p class="search-box">
          <label class="screen-reader-text" for="pi-search-cadastros"><?php esc_html_e('Buscar cadastros', 'participe-ibram'); ?></label>
          <input type="search" id="pi-search-cadastros" name="s" value="<?php
              echo isset($_GET['s'])
                  ? esc_attr((string) wp_unslash((string) $_GET['s'])) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                  : ''; ?>" />
          <input type="submit" class="button" value="<?php esc_attr_e('Buscar', 'participe-ibram'); ?>" />
        </p>

        <?php
        $totalNaFila = (int) ($resumo[StatusCadastro::SUBMETIDO] ?? 0) + (int) ($resumo[StatusCadastro::EM_ANALISE] ?? 0);
        if ($totalNaFila === 0) {
            EmptyState::render(
                __('Fila vazia', 'participe-ibram'),
                __('Não há cadastros aguardando ou em análise no momento.', 'participe-ibram'),
                [
                    'label' => __('Ver todos os agentes', 'participe-ibram'),
                    'url'   => admin_url('admin.php?page=' . MenuRegistry::SLUG_AGENTES),
                ],
                'dashicons-id'
            );
        } else {
            $listTable->display();
        }
        ?>
      </form>
    </section>

    <aside class="pi-admin-cadastros__sidebar" aria-labelledby="pi-fila-resumo-h2">
      <h2 id="pi-fila-resumo-h2" class="pi-card__title"><?php esc_html_e('Resumo', 'participe-ibram'); ?></h2>

      <article class="pi-card pi-card--sm" aria-live="polite">
        <header class="pi-card__header">
          <h3 class="pi-card__title"><?php esc_html_e('Aguardando análise', 'participe-ibram'); ?></h3>
        </header>
        <div class="pi-card__body">
          <p class="pi-card__metric-value">
            <?php echo esc_html((string) (int) ($resumo[StatusCadastro::SUBMETIDO] ?? 0)); ?>
          </p>
        </div>
      </article>

      <article class="pi-card pi-card--sm" aria-live="polite">
        <header class="pi-card__header">
          <h3 class="pi-card__title"><?php esc_html_e('Em análise', 'participe-ibram'); ?></h3>
        </header>
        <div class="pi-card__body">
          <p class="pi-card__metric-value">
            <?php echo esc_html((string) (int) ($resumo[StatusCadastro::EM_ANALISE] ?? 0)); ?>
          </p>
        </div>
      </article>

      <article class="pi-card pi-card--sm" aria-live="polite">
        <header class="pi-card__header">
          <h3 class="pi-card__title"><?php esc_html_e('Tempo médio', 'participe-ibram'); ?></h3>
        </header>
        <div class="pi-card__body">
          <p class="pi-card__metric-value">
            <?php
            printf(
                /* translators: %s: número de dias */
                esc_html__('%s dia(s)', 'participe-ibram'),
                esc_html(number_format_i18n($tempoMedio, 1))
            );
            ?>
          </p>
        </div>
      </article>
    </aside>
  </div>
</main>

<?php PageLayout::close(); ?>
