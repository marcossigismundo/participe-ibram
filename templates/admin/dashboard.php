<?php
/**
 * Template — Admin Dashboard Participe Ibram (STUB).
 *
 * Layout administrativo padrão DSGov: header, breadcrumb, alert de
 * boas-vindas e grid de cards de métricas com `aria-live="polite"`.
 *
 * Wave 3 / W3-D — STUB visual. Wave 4 popula contadores reais via
 * AdminDashboardController + repositórios.
 *
 * Vars esperadas (todas opcionais — fallback para 0):
 *  - $cadastros_pendentes  int
 *  - $editais_ativos       int
 *  - $solicitacoes_lgpd    int
 *  - $current_user_name    string
 *
 * @package Ibram\ParticipeIbram\Templates\Admin
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/** @var int $cadastros_pendentes */
$cadastros_pendentes = isset($cadastros_pendentes) ? (int) $cadastros_pendentes : 0;
/** @var int $editais_ativos */
$editais_ativos      = isset($editais_ativos) ? (int) $editais_ativos : 0;
/** @var int $solicitacoes_lgpd */
$solicitacoes_lgpd   = isset($solicitacoes_lgpd) ? (int) $solicitacoes_lgpd : 0;
/** @var string $current_user_name */
$current_user_name   = $current_user_name ?? wp_get_current_user()->display_name;
?>
<div class="participe-ibram-scope wrap">
  <a class="pi-skip-link" href="#pi-admin-main">
    <?php esc_html_e('Pular para o conteúdo', 'participe-ibram'); ?>
  </a>

  <header role="banner">
    <h1><?php esc_html_e('Participe Ibram — Painel administrativo', 'participe-ibram'); ?></h1>
  </header>

  <nav class="pi-breadcrumb" aria-label="<?php esc_attr_e('Você está em', 'participe-ibram'); ?>">
    <ol class="pi-breadcrumb__list">
      <li class="pi-breadcrumb__item">
        <a href="<?php echo esc_url(admin_url()); ?>">
          <?php esc_html_e('WordPress', 'participe-ibram'); ?>
        </a>
      </li>
      <li class="pi-breadcrumb__item" aria-current="page">
        <?php esc_html_e('Participe Ibram', 'participe-ibram'); ?>
      </li>
    </ol>
  </nav>

  <main id="pi-admin-main" tabindex="-1">
    <div class="pi-alert pi-alert--info" role="status">
      <span class="pi-alert__icon" aria-hidden="true"></span>
      <div class="pi-alert__content">
        <h2 class="pi-alert__title">
          <?php
          printf(
              /* translators: %s: nome do usuário logado */
              esc_html__('Bem-vindo(a), %s!', 'participe-ibram'),
              esc_html($current_user_name)
          );
          ?>
        </h2>
        <p class="pi-alert__body">
          <?php esc_html_e('Este é o painel administrativo da plataforma Participe Ibram. Use o menu lateral para acessar cadastros, editais e solicitações LGPD.', 'participe-ibram'); ?>
        </p>
      </div>
    </div>

    <h2 class="pi-sr-only"><?php esc_html_e('Indicadores', 'participe-ibram'); ?></h2>

    <?php /* WCAG 4.1.3 — live region para anúncio de mudanças nas métricas */ ?>
    <div class="pi-admin-grid" aria-live="polite" aria-atomic="false">
      <article class="pi-card" aria-labelledby="metric-cadastros">
        <header class="pi-card__header">
          <h3 id="metric-cadastros" class="pi-card__title">
            <?php esc_html_e('Cadastros pendentes', 'participe-ibram'); ?>
          </h3>
        </header>
        <div class="pi-card__body">
          <div class="pi-card__metric">
            <span class="pi-card__metric-value" data-pi-metric="cadastros_pendentes">
              <?php echo esc_html((string) $cadastros_pendentes); ?>
            </span>
            <span class="pi-card__metric-label">
              <?php esc_html_e('aguardando análise', 'participe-ibram'); ?>
            </span>
          </div>
        </div>
        <footer class="pi-card__footer">
          <a class="pi-button pi-button--secondary pi-button--sm"
             href="<?php echo esc_url(admin_url('admin.php?page=pi-cadastros')); ?>">
            <?php esc_html_e('Ver cadastros', 'participe-ibram'); ?>
          </a>
        </footer>
      </article>

      <article class="pi-card" aria-labelledby="metric-editais">
        <header class="pi-card__header">
          <h3 id="metric-editais" class="pi-card__title">
            <?php esc_html_e('Editais ativos', 'participe-ibram'); ?>
          </h3>
        </header>
        <div class="pi-card__body">
          <div class="pi-card__metric">
            <span class="pi-card__metric-value" data-pi-metric="editais_ativos">
              <?php echo esc_html((string) $editais_ativos); ?>
            </span>
            <span class="pi-card__metric-label">
              <?php esc_html_e('em andamento', 'participe-ibram'); ?>
            </span>
          </div>
        </div>
        <footer class="pi-card__footer">
          <a class="pi-button pi-button--secondary pi-button--sm"
             href="<?php echo esc_url(admin_url('admin.php?page=pi-editais')); ?>">
            <?php esc_html_e('Ver editais', 'participe-ibram'); ?>
          </a>
        </footer>
      </article>

      <article class="pi-card" aria-labelledby="metric-lgpd">
        <header class="pi-card__header">
          <h3 id="metric-lgpd" class="pi-card__title">
            <?php esc_html_e('Solicitações LGPD', 'participe-ibram'); ?>
          </h3>
        </header>
        <div class="pi-card__body">
          <div class="pi-card__metric">
            <span class="pi-card__metric-value" data-pi-metric="solicitacoes_lgpd">
              <?php echo esc_html((string) $solicitacoes_lgpd); ?>
            </span>
            <span class="pi-card__metric-label">
              <?php esc_html_e('aguardando resposta', 'participe-ibram'); ?>
            </span>
          </div>
        </div>
        <footer class="pi-card__footer">
          <a class="pi-button pi-button--secondary pi-button--sm"
             href="<?php echo esc_url(admin_url('admin.php?page=pi-lgpd')); ?>">
            <?php esc_html_e('Ver solicitações', 'participe-ibram'); ?>
          </a>
        </footer>
      </article>
    </div>
  </main>
</div>
