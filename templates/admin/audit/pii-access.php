<?php
/**
 * Template — Auditoria: Acessos a PII (admin).
 *
 * Vars esperadas (AuditLogController::render()):
 *  - \Ibram\ParticipeIbram\Presentation\Admin\ListTables\AuditPiiAccessListTable $listTable
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

/** @var \Ibram\ParticipeIbram\Presentation\Admin\ListTables\AuditPiiAccessListTable $listTable */
/** @var array{hoje:int, ontem:int, mes:int} $stats */
/** @var array{type:string,message:string}|null $flash */

$stats = isset($stats) && is_array($stats) ? $stats : ['hoje' => 0, 'ontem' => 0, 'mes' => 0];
$flash = isset($flash) ? $flash : null;
?>
<div class="participe-ibram-scope wrap pi-admin-audit pi-admin-audit--pii">
  <a class="pi-skip-link" href="#pi-admin-main">
    <?php esc_html_e('Pular para o conteúdo', 'participe-ibram'); ?>
  </a>

  <header role="banner">
    <h1><?php esc_html_e('Auditoria — Acessos a dados PII', 'participe-ibram'); ?></h1>
  </header>

  <nav class="pi-breadcrumb" aria-label="<?php esc_attr_e('Você está em', 'participe-ibram'); ?>">
    <ol class="pi-breadcrumb__list">
      <li class="pi-breadcrumb__item">
        <a href="<?php echo esc_url(admin_url('admin.php?page=' . AuditMenuRegistry::SLUG_ROOT)); ?>">
          <?php esc_html_e('Participe Ibram', 'participe-ibram'); ?>
        </a>
      </li>
      <li class="pi-breadcrumb__item">
        <a href="<?php echo esc_url(AuditMenuRegistry::urlLog()); ?>">
          <?php esc_html_e('Log de eventos', 'participe-ibram'); ?>
        </a>
      </li>
      <li class="pi-breadcrumb__item" aria-current="page">
        <?php esc_html_e('Acessos a PII', 'participe-ibram'); ?>
      </li>
    </ol>
  </nav>

  <?php if ($flash !== null) : ?>
    <div class="notice notice-<?php echo esc_attr($flash['type']); ?> is-dismissible" role="alert">
      <p><?php echo esc_html($flash['message']); ?></p>
    </div>
  <?php endif; ?>

  <div class="pi-admin-layout">
    <main id="pi-admin-main" class="pi-admin-layout__main" tabindex="-1">
      <form method="get" action="">
        <input type="hidden" name="page" value="<?php echo esc_attr(AuditMenuRegistry::SLUG_PII); ?>" />
        <?php $listTable->extra_tablenav('top'); ?>
        <?php $listTable->display(); ?>
      </form>
    </main>

    <aside class="pi-admin-layout__sidebar" aria-label="<?php esc_attr_e('KPIs de PII', 'participe-ibram'); ?>">
      <div class="pi-audit-kpi-card">
        <h2><?php esc_html_e('Acessos a PII', 'participe-ibram'); ?></h2>
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
        <p class="description">
          <?php esc_html_e('Todos os registros desta página correspondem a acessos a dados pessoais sensíveis (LGPD Art. 11).', 'participe-ibram'); ?>
        </p>
      </div>
    </aside>
  </div>

  <!-- Modal de detalhe (igual ao log.php) -->
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
        <p class="pi-audit-loading" aria-live="polite"><?php esc_html_e('Carregando…', 'participe-ibram'); ?></p>
      </div>
      <div class="pi-modal__footer">
        <button type="button" class="button pi-modal__close">
          <?php esc_html_e('Fechar', 'participe-ibram'); ?>
        </button>
      </div>
    </div>
  </div>
</div>
