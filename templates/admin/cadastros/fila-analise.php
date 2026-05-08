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

/** @var \Ibram\ParticipeIbram\Presentation\Admin\ListTables\FilaAnaliseListTable $listTable */
/** @var array<string,int>                                                       $resumo */
/** @var array<string,string>                                                    $listLabels */
/** @var float                                                                   $tempoMedio */
/** @var array{type:string,message:string}|null                                  $flash */

$resumo     = isset($resumo) && is_array($resumo) ? $resumo : [];
$listLabels = isset($listLabels) && is_array($listLabels) ? $listLabels : [];
$tempoMedio = isset($tempoMedio) ? (float) $tempoMedio : 0.0;
$flash      = isset($flash) ? $flash : null;
?>
<div class="participe-ibram-scope wrap pi-admin-cadastros">
  <a class="pi-skip-link" href="#pi-admin-main">
    <?php esc_html_e('Pular para o conteúdo', 'participe-ibram'); ?>
  </a>

  <header role="banner">
    <h1><?php esc_html_e('Fila de Análise', 'participe-ibram'); ?></h1>
  </header>

  <nav class="pi-breadcrumb" aria-label="<?php esc_attr_e('Você está em', 'participe-ibram'); ?>">
    <ol class="pi-breadcrumb__list">
      <li class="pi-breadcrumb__item">
        <a href="<?php echo esc_url(admin_url('admin.php?page=' . MenuRegistry::SLUG_ROOT)); ?>">
          <?php esc_html_e('Participe Ibram', 'participe-ibram'); ?>
        </a>
      </li>
      <li class="pi-breadcrumb__item">
        <?php esc_html_e('Cadastros', 'participe-ibram'); ?>
      </li>
      <li class="pi-breadcrumb__item" aria-current="page">
        <?php esc_html_e('Fila de Análise', 'participe-ibram'); ?>
      </li>
    </ol>
  </nav>

  <?php if ($flash !== null) :
      $flashClass = $flash['type'] === 'success' ? 'pi-alert--success' : 'pi-alert--error';
      $flashRole  = $flash['type'] === 'success' ? 'status' : 'alert'; ?>
  <div class="pi-alert <?php echo esc_attr($flashClass); ?>" role="<?php echo esc_attr($flashRole); ?>">
    <p class="pi-alert__body"><?php echo esc_html($flash['message']); ?></p>
  </div>
  <?php endif; ?>

  <main id="pi-admin-main" tabindex="-1" class="pi-admin-cadastros__main">
    <div class="pi-admin-cadastros__layout">
      <section class="pi-admin-cadastros__list" aria-labelledby="pi-fila-analise-h2">
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

          <?php $listTable->display(); ?>
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
</div>
