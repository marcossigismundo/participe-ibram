<?php
/**
 * Template — Painel (Visão Geral) do Participe Ibram (Onda 11-A).
 *
 * Renderiza a página raiz do menu admin. Mostra KPIs do plugin e um painel
 * "Próximo passo" role-aware. NUNCA exibe PII — apenas agregados numéricos.
 *
 * Variáveis injetadas (todas obrigatórias; defaults sanos abaixo):
 *  - int   $kpi_cadastros_pendentes
 *  - int   $kpi_editais_publicados
 *  - int   $kpi_recursos_abertos
 *  - int   $kpi_votacoes_em_curso
 *  - int   $kpi_lgpd_pendentes
 *  - int   $kpi_emails_pendentes
 *  - array $proximo_passo  ['titulo'=>string,'descricao'=>string,'url'=>string|null,'label'=>string]
 *
 * Design: classes scoped under `.participe-ibram-scope` com tokens DSGov 3.7.
 *
 * @package Ibram\ParticipeIbram\Templates\Admin
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

// ----- defaults -----------------------------------------------------------
$kpi_cadastros_pendentes = isset($kpi_cadastros_pendentes) ? (int) $kpi_cadastros_pendentes : 0;
$kpi_editais_publicados  = isset($kpi_editais_publicados)  ? (int) $kpi_editais_publicados  : 0;
$kpi_recursos_abertos    = isset($kpi_recursos_abertos)    ? (int) $kpi_recursos_abertos    : 0;
$kpi_votacoes_em_curso   = isset($kpi_votacoes_em_curso)   ? (int) $kpi_votacoes_em_curso   : 0;
$kpi_lgpd_pendentes      = isset($kpi_lgpd_pendentes)      ? (int) $kpi_lgpd_pendentes      : 0;
$kpi_emails_pendentes    = isset($kpi_emails_pendentes)    ? (int) $kpi_emails_pendentes    : 0;
$proximo_passo           = isset($proximo_passo) && is_array($proximo_passo) ? $proximo_passo : null;

// Cards data — uma fonte só, fácil de manter.
$cards = [
    [
        'id'    => 'cadastros',
        'titulo' => __('Cadastros aguardando análise', 'participe-ibram'),
        'valor' => $kpi_cadastros_pendentes,
        'empty' => __('Nenhum cadastro aguardando no momento.', 'participe-ibram'),
        'url'   => admin_url('admin.php?page=participe-ibram_cadastros'),
        'cta'   => __('Abrir fila de análise', 'participe-ibram'),
        'icone' => 'dashicons-id',
    ],
    [
        'id'    => 'editais',
        'titulo' => __('Editais publicados', 'participe-ibram'),
        'valor' => $kpi_editais_publicados,
        'empty' => __('Nenhum edital publicado.', 'participe-ibram'),
        'url'   => admin_url('admin.php?page=participe-ibram_editais'),
        'cta'   => __('Ver editais', 'participe-ibram'),
        'icone' => 'dashicons-megaphone',
    ],
    [
        'id'    => 'recursos',
        'titulo' => __('Recursos abertos', 'participe-ibram'),
        'valor' => $kpi_recursos_abertos,
        'empty' => __('Nenhum recurso pendente.', 'participe-ibram'),
        'url'   => admin_url('admin.php?page=participe-ibram_recursos_retratacao'),
        'cta'   => __('Ver recursos em retratação', 'participe-ibram'),
        'icone' => 'dashicons-undo',
    ],
    [
        'id'    => 'votacoes',
        'titulo' => __('Votações em curso', 'participe-ibram'),
        'valor' => $kpi_votacoes_em_curso,
        'empty' => __('Nenhuma votação aberta no momento.', 'participe-ibram'),
        'url'   => admin_url('admin.php?page=participe-ibram_votacoes'),
        'cta'   => __('Ver votações', 'participe-ibram'),
        'icone' => 'dashicons-yes-alt',
    ],
    [
        'id'    => 'lgpd',
        'titulo' => __('Solicitações LGPD pendentes', 'participe-ibram'),
        'valor' => $kpi_lgpd_pendentes,
        'empty' => __('Nenhuma solicitação no momento.', 'participe-ibram'),
        'url'   => admin_url('admin.php?page=pi-dpo-config'),
        'cta'   => __('Abrir painel do DPO', 'participe-ibram'),
        'icone' => 'dashicons-shield',
    ],
    [
        'id'    => 'emails',
        'titulo' => __('Alertas de prazo (fila de e-mail)', 'participe-ibram'),
        'valor' => $kpi_emails_pendentes,
        'empty' => __('Nenhum e-mail pendente.', 'participe-ibram'),
        'url'   => admin_url('admin.php?page=pi-participe-ibram-email'),
        'cta'   => __('Ver fila de e-mail', 'participe-ibram'),
        'icone' => 'dashicons-email-alt',
    ],
];

?>
<div class="participe-ibram-scope wrap pi-painel">

  <style id="pi-painel-inline-css">
    /* === Painel Participe Ibram — scoped CSS (DSGov 3.7 tokens) ============ */
    .participe-ibram-scope.pi-painel {
        --pi-color-primary: #1351B4;       /* --blue-warm-vivid-70 */
        --pi-color-primary-dark: #0C326F;  /* --blue-warm-vivid-80 */
        --pi-color-primary-soft: #C5D4EB;  /* --blue-warm-20 */
        --pi-color-success: #168821;       /* --green-cool-vivid-50 */
        --pi-color-warning: #FFCD07;       /* --yellow-vivid-20 */
        --pi-color-danger:  #E52207;       /* --red-vivid-50 */
        --pi-color-ink-90:  #1B1B1B;
        --pi-color-ink-80:  #333333;
        --pi-color-ink-60:  #636363;
        --pi-color-ink-40:  #9E9E9E;
        --pi-color-bg-soft: #F8F8F8;
        --pi-color-bg:      #FFFFFF;
        --pi-color-border:  #CCCCCC;
        --pi-space-1: 0.5rem;
        --pi-space-2: 1rem;
        --pi-space-3: 1.5rem;
        --pi-space-4: 2rem;
        --pi-radius:  8px;
        --pi-shadow:  0 1px 4px rgba(0,0,0,0.16);
        font-family: "Rawline","Raleway","Segoe UI",Arial,sans-serif;
        color: var(--pi-color-ink-90);
    }
    .participe-ibram-scope.pi-painel .pi-painel__header {
        display:flex; flex-direction:column; gap: var(--pi-space-1);
        padding: var(--pi-space-3) 0 var(--pi-space-2);
        border-bottom: 1px solid var(--pi-color-border);
        margin-bottom: var(--pi-space-3);
    }
    .participe-ibram-scope.pi-painel .pi-painel__title {
        font-size: 1.875rem;   /* h2: --font-size-scale-up-08 */
        line-height: 1.15;
        font-weight: 600;
        margin: 0;
        color: var(--pi-color-primary-dark);
    }
    .participe-ibram-scope.pi-painel .pi-painel__subtitle {
        font-size: 1rem;
        color: var(--pi-color-ink-60);
        margin: 0;
    }
    .participe-ibram-scope.pi-painel .pi-breadcrumb {
        font-size: 0.875rem;
        color: var(--pi-color-ink-60);
        margin: 0 0 var(--pi-space-1);
    }
    .participe-ibram-scope.pi-painel .pi-breadcrumb a {
        color: var(--pi-color-primary);
        text-decoration: none;
    }
    .participe-ibram-scope.pi-painel .pi-breadcrumb a:hover { text-decoration: underline; }
    .participe-ibram-scope.pi-painel .pi-breadcrumb__sep {
        margin: 0 0.4rem;
        color: var(--pi-color-ink-40);
    }
    .participe-ibram-scope.pi-painel .pi-kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
        gap: var(--pi-space-2);
        margin-bottom: var(--pi-space-4);
    }
    .participe-ibram-scope.pi-painel .pi-kpi-card {
        background: var(--pi-color-bg);
        border: 1px solid var(--pi-color-border);
        border-left: 4px solid var(--pi-color-primary);
        border-radius: var(--pi-radius);
        padding: var(--pi-space-2);
        box-shadow: var(--pi-shadow);
        display: flex; flex-direction: column;
        gap: var(--pi-space-1);
        min-height: 140px;
    }
    .participe-ibram-scope.pi-painel .pi-kpi-card[data-empty="true"] {
        border-left-color: var(--pi-color-border);
        background: var(--pi-color-bg-soft);
    }
    .participe-ibram-scope.pi-painel .pi-kpi-card__title {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--pi-color-ink-80);
        margin: 0;
        text-transform: none;
    }
    .participe-ibram-scope.pi-painel .pi-kpi-card__value {
        font-size: 2.25rem;     /* h1 weight */
        line-height: 1.15;
        font-weight: 700;
        color: var(--pi-color-primary-dark);
        margin: 0;
        font-variant-numeric: tabular-nums;
    }
    .participe-ibram-scope.pi-painel .pi-kpi-card[data-empty="true"] .pi-kpi-card__value {
        color: var(--pi-color-ink-40);
        font-size: 1rem;
        font-weight: 500;
        font-style: italic;
    }
    .participe-ibram-scope.pi-painel .pi-kpi-card__cta {
        margin-top: auto;
        align-self: flex-start;
        color: var(--pi-color-primary);
        font-size: 0.875rem;
        text-decoration: none;
        font-weight: 600;
    }
    .participe-ibram-scope.pi-painel .pi-kpi-card__cta:hover { text-decoration: underline; }
    .participe-ibram-scope.pi-painel .pi-kpi-card__icon {
        color: var(--pi-color-primary);
        font-size: 1.25rem;
    }
    .participe-ibram-scope.pi-painel .pi-kpi-card[data-empty="true"] .pi-kpi-card__icon {
        color: var(--pi-color-ink-40);
    }
    .participe-ibram-scope.pi-painel .pi-kpi-card__head {
        display:flex; align-items:center; gap: var(--pi-space-1);
    }
    .participe-ibram-scope.pi-painel .pi-next-step {
        background: var(--pi-color-primary-soft);
        border-left: 4px solid var(--pi-color-primary);
        border-radius: var(--pi-radius);
        padding: var(--pi-space-3);
        display:flex; gap: var(--pi-space-3);
        align-items: flex-start;
        margin-bottom: var(--pi-space-3);
    }
    .participe-ibram-scope.pi-painel .pi-next-step__icon {
        font-size: 2rem;
        color: var(--pi-color-primary-dark);
    }
    .participe-ibram-scope.pi-painel .pi-next-step__body { flex: 1 1 auto; }
    .participe-ibram-scope.pi-painel .pi-next-step__title {
        margin: 0 0 var(--pi-space-1);
        font-size: 1.125rem;
        color: var(--pi-color-primary-dark);
    }
    .participe-ibram-scope.pi-painel .pi-next-step__desc {
        margin: 0 0 var(--pi-space-2);
        font-size: 1rem;
        color: var(--pi-color-ink-80);
    }
    .participe-ibram-scope.pi-painel .pi-button {
        display: inline-block;
        background: var(--pi-color-primary);
        color: #fff;
        padding: 0.5rem 1rem;
        border-radius: 100em;        /* --surface-rounder-pill */
        text-decoration: none;
        font-weight: 600;
        font-size: 0.875rem;
        border: 0;
        line-height: 1.45;
    }
    .participe-ibram-scope.pi-painel .pi-button:hover,
    .participe-ibram-scope.pi-painel .pi-button:focus {
        background: var(--pi-color-primary-dark);
        color: #fff;
    }
    .participe-ibram-scope.pi-painel .pi-painel__footer-note {
        font-size: 0.8125rem;
        color: var(--pi-color-ink-60);
        margin-top: var(--pi-space-2);
    }
  </style>

  <header class="pi-painel__header">
    <nav class="pi-breadcrumb" aria-label="<?php esc_attr_e('Localização', 'participe-ibram'); ?>">
      <a href="<?php echo esc_url(admin_url()); ?>"><?php esc_html_e('Início', 'participe-ibram'); ?></a>
      <span class="pi-breadcrumb__sep" aria-hidden="true">›</span>
      <span aria-current="page"><?php esc_html_e('Painel', 'participe-ibram'); ?></span>
    </nav>
    <h1 class="pi-painel__title"><?php esc_html_e('Painel — Participe Ibram', 'participe-ibram'); ?></h1>
    <p class="pi-painel__subtitle">
      <?php esc_html_e('Plataforma federal de Cadastro de Agentes para Participação Social do Ibram (Portaria 3230/2024).', 'participe-ibram'); ?>
    </p>
  </header>

  <?php if (is_array($proximo_passo)): ?>
  <section class="pi-next-step" aria-labelledby="pi-next-step-title">
    <span class="pi-next-step__icon dashicons dashicons-arrow-right-alt" aria-hidden="true"></span>
    <div class="pi-next-step__body">
      <h2 id="pi-next-step-title" class="pi-next-step__title">
        <?php echo esc_html((string) ($proximo_passo['titulo'] ?? __('Próximo passo', 'participe-ibram'))); ?>
      </h2>
      <p class="pi-next-step__desc">
        <?php echo esc_html((string) ($proximo_passo['descricao'] ?? '')); ?>
      </p>
      <?php if (!empty($proximo_passo['url']) && !empty($proximo_passo['label'])): ?>
        <a class="pi-button" href="<?php echo esc_url((string) $proximo_passo['url']); ?>">
          <?php echo esc_html((string) $proximo_passo['label']); ?>
        </a>
      <?php endif; ?>
    </div>
  </section>
  <?php endif; ?>

  <h2 class="screen-reader-text"><?php esc_html_e('Indicadores principais', 'participe-ibram'); ?></h2>
  <div class="pi-kpi-grid" role="list">
    <?php foreach ($cards as $card):
        $is_empty = ((int) $card['valor']) === 0;
    ?>
      <article class="pi-kpi-card" data-empty="<?php echo $is_empty ? 'true' : 'false'; ?>" role="listitem">
        <div class="pi-kpi-card__head">
          <span class="pi-kpi-card__icon dashicons <?php echo esc_attr((string) $card['icone']); ?>" aria-hidden="true"></span>
          <h3 class="pi-kpi-card__title"><?php echo esc_html((string) $card['titulo']); ?></h3>
        </div>
        <p class="pi-kpi-card__value">
          <?php if ($is_empty): ?>
            <?php echo esc_html((string) $card['empty']); ?>
          <?php else: ?>
            <?php echo esc_html(number_format_i18n((int) $card['valor'])); ?>
          <?php endif; ?>
        </p>
        <a class="pi-kpi-card__cta" href="<?php echo esc_url((string) $card['url']); ?>">
          <?php echo esc_html((string) $card['cta']); ?> ›
        </a>
      </article>
    <?php endforeach; ?>
  </div>

  <p class="pi-painel__footer-note">
    <?php esc_html_e('Os números são atualizados a cada acesso ao painel. Para detalhes, use os menus à esquerda.', 'participe-ibram'); ?>
  </p>
</div>
