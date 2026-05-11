<?php
/**
 * Template — Auditoria: Log de eventos (admin).
 *
 * Vars esperadas (AuditLogController::render()):
 *  - \Ibram\ParticipeIbram\Presentation\Admin\ListTables\AuditLogListTable $listTable
 *  - array{hoje:int, ontem:int, mes:int} $stats
 *  - array{type:string,message:string}|null $flash
 *
 * @package Ibram\ParticipeIbram\Templates\Admin\Audit
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use Ibram\ParticipeIbram\Presentation\Admin\AuditMenuRegistry;

/** @var \Ibram\ParticipeIbram\Presentation\Admin\ListTables\AuditLogListTable $listTable */
/** @var array{hoje:int, ontem:int, mes:int} $stats */
/** @var array{type:string,message:string}|null $flash */

$stats = isset($stats) && is_array($stats) ? $stats : ['hoje' => 0, 'ontem' => 0, 'mes' => 0];
$flash = isset($flash) ? $flash : null;

$currentUserId = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
$exportNonce   = function_exists('wp_create_nonce')
    ? esc_attr(wp_create_nonce('pi_audit_export_' . $currentUserId))
    : '';
?>
<div class="participe-ibram-scope wrap pi-admin-audit">
  <a class="pi-skip-link" href="#pi-admin-main">
    <?php esc_html_e('Pular para o conteúdo', 'participe-ibram'); ?>
  </a>

  <header role="banner">
    <h1><?php esc_html_e('Auditoria — Log de eventos', 'participe-ibram'); ?></h1>
  </header>

  <nav class="pi-breadcrumb" aria-label="<?php esc_attr_e('Você está em', 'participe-ibram'); ?>">
    <ol class="pi-breadcrumb__list">
      <li class="pi-breadcrumb__item">
        <a href="<?php echo esc_url(admin_url('admin.php?page=' . AuditMenuRegistry::SLUG_ROOT)); ?>">
          <?php esc_html_e('Participe Ibram', 'participe-ibram'); ?>
        </a>
      </li>
      <li class="pi-breadcrumb__item" aria-current="page">
        <?php esc_html_e('Log de eventos', 'participe-ibram'); ?>
      </li>
    </ol>
  </nav>

  <?php if ($flash !== null) : ?>
    <div class="notice notice-<?php echo esc_attr($flash['type']); ?> is-dismissible" role="alert">
      <p><?php echo esc_html($flash['message']); ?></p>
    </div>
  <?php endif; ?>

  <div class="pi-admin-layout">
    <!-- Conteúdo principal -->
    <main id="pi-admin-main" class="pi-admin-layout__main" tabindex="-1">
      <form method="get" action="">
        <input type="hidden" name="page" value="<?php echo esc_attr(AuditMenuRegistry::SLUG_LOG); ?>" />
        <?php $listTable->extra_tablenav('top'); ?>
        <?php $listTable->display(); ?>
      </form>
    </main>

    <!-- Sidebar com KPIs -->
    <aside class="pi-admin-layout__sidebar" aria-label="<?php esc_attr_e('Estatísticas', 'participe-ibram'); ?>">
      <div class="pi-audit-kpi-card">
        <h2><?php esc_html_e('Eventos', 'participe-ibram'); ?></h2>
        <dl class="pi-audit-kpi-list">
          <div class="pi-audit-kpi-item">
            <dt><?php esc_html_e('Hoje', 'participe-ibram'); ?></dt>
            <dd><strong><?php echo esc_html((string) $stats['hoje']); ?></strong></dd>
          </div>
          <div class="pi-audit-kpi-item">
            <dt><?php esc_html_e('Ontem', 'participe-ibram'); ?></dt>
            <dd><?php echo esc_html((string) $stats['ontem']); ?></dd>
          </div>
          <div class="pi-audit-kpi-item">
            <dt><?php esc_html_e('Últimos 30 dias', 'participe-ibram'); ?></dt>
            <dd><?php echo esc_html((string) $stats['mes']); ?></dd>
          </div>
        </dl>
      </div>

      <div class="pi-audit-kpi-card">
        <h2><?php esc_html_e('Exportar', 'participe-ibram'); ?></h2>
        <p class="description">
          <?php esc_html_e('Exporta os registros filtrados (máx. 100k linhas).', 'participe-ibram'); ?>
        </p>
        <button type="button"
                class="button button-primary pi-audit-export-open"
                aria-haspopup="dialog"
                aria-controls="pi-audit-export-modal">
          <?php esc_html_e('Exportar resultados', 'participe-ibram'); ?>
        </button>
      </div>
    </aside>
  </div><!-- .pi-admin-layout -->

  <!-- Modal de export (acessível WCAG 2.1 AA) -->
  <div id="pi-audit-export-modal"
       class="pi-modal"
       role="dialog"
       aria-modal="true"
       aria-labelledby="pi-audit-export-modal-title"
       hidden>
    <div class="pi-modal__backdrop" aria-hidden="true"></div>
    <div class="pi-modal__dialog">
      <div class="pi-modal__header">
        <h2 id="pi-audit-export-modal-title"><?php esc_html_e('Exportar log de auditoria', 'participe-ibram'); ?></h2>
        <button type="button"
                class="pi-modal__close"
                aria-label="<?php esc_attr_e('Fechar', 'participe-ibram'); ?>">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="pi-modal__body">
        <form id="pi-audit-export-form" class="pi-audit-export-form">
          <p><?php esc_html_e('Os filtros ativos serão aplicados ao export.', 'participe-ibram'); ?></p>
          <fieldset>
            <legend><?php esc_html_e('Formato', 'participe-ibram'); ?></legend>
            <label>
              <input type="radio" name="formato" value="csv" checked />
              <?php esc_html_e('CSV (Excel)', 'participe-ibram'); ?>
            </label>
            <label>
              <input type="radio" name="formato" value="json" />
              <?php esc_html_e('JSON', 'participe-ibram'); ?>
            </label>
          </fieldset>
          <input type="hidden" name="action" value="pi_admin_audit_export" />
          <input type="hidden" name="_wpnonce" value="<?php echo $exportNonce; ?>" />
          <!-- Live region para feedback -->
          <div id="pi-audit-export-feedback"
               class="pi-audit-feedback"
               role="status"
               aria-live="polite"
               aria-atomic="true"></div>
        </form>
      </div>
      <div class="pi-modal__footer">
        <button type="submit" form="pi-audit-export-form" class="button button-primary">
          <?php esc_html_e('Gerar export', 'participe-ibram'); ?>
        </button>
        <button type="button" class="button pi-modal__close">
          <?php esc_html_e('Cancelar', 'participe-ibram'); ?>
        </button>
      </div>
    </div>
  </div><!-- #pi-audit-export-modal -->

  <!-- Container para modal de detalhe (preenchido via AJAX) -->
  <div id="pi-audit-detalhe-modal"
       class="pi-modal"
       role="dialog"
       aria-modal="true"
       aria-labelledby="pi-audit-detalhe-title"
       hidden>
    <div class="pi-modal__backdrop" aria-hidden="true"></div>
    <div class="pi-modal__dialog pi-modal__dialog--wide">
      <div class="pi-modal__header">
        <h2 id="pi-audit-detalhe-title"><?php esc_html_e('Detalhe do registro', 'participe-ibram'); ?></h2>
        <button type="button"
                class="pi-modal__close"
                aria-label="<?php esc_attr_e('Fechar', 'participe-ibram'); ?>">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="pi-modal__body" id="pi-audit-detalhe-body">
        <!-- Preenchido via AJAX (audit.js) -->
        <p class="pi-audit-loading" aria-live="polite"><?php esc_html_e('Carregando…', 'participe-ibram'); ?></p>
      </div>
      <div class="pi-modal__footer">
        <button type="button" class="button pi-modal__close">
          <?php esc_html_e('Fechar', 'participe-ibram'); ?>
        </button>
      </div>
    </div>
  </div><!-- #pi-audit-detalhe-modal -->
</div><!-- .participe-ibram-scope -->
