<?php
/**
 * Template — Detalhe de registro de auditoria.
 *
 * Vars esperadas (AuditDetalheController::render()):
 *  - array<string,mixed>      $record             Registro da tabela (sem dados_antes/depois raw)
 *  - array<string,mixed>|null $dadosAntesMasked   Payload mascarado
 *  - array<string,mixed>|null $dadosDepoisMasked  Payload mascarado
 *  - string                   $backUrl            URL para voltar ao log
 *
 * W11-C: migrado para PageLayout chrome.
 *
 * @package Ibram\ParticipeIbram\Templates\Admin\Audit
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use Ibram\ParticipeIbram\Presentation\Admin\AuditMenuRegistry;
use Ibram\ParticipeIbram\Presentation\Admin\Support\PageLayout;

/** @var array<string,mixed>      $record */
/** @var array<string,mixed>|null $dadosAntesMasked */
/** @var array<string,mixed>|null $dadosDepoisMasked */
/** @var string                   $backUrl */

$record            = isset($record) && is_array($record) ? $record : [];
$dadosAntesMasked  = isset($dadosAntesMasked) && is_array($dadosAntesMasked) ? $dadosAntesMasked : null;
$dadosDepoisMasked = isset($dadosDepoisMasked) && is_array($dadosDepoisMasked) ? $dadosDepoisMasked : null;
$backUrl           = isset($backUrl) ? (string) $backUrl : AuditMenuRegistry::urlLog();

$recordId    = (int) ($record['id'] ?? 0);
$entidade    = (string) ($record['entidade'] ?? '');
$entidadeId  = $record['entidade_id'] ?? null;
$acao        = (string) ($record['acao'] ?? '');
$atorId      = $record['ator_id'] ?? null;
$ocorridoEm  = (string) ($record['ocorrido_em'] ?? '');
$ipHash      = (string) ($record['ip_hash'] ?? '');
$userAgent   = (string) ($record['user_agent'] ?? '');

// Trunca ip_hash para não exibir o hash completo (apenas 8 + reticências)
$ipHashDisplay = $ipHash !== '' ? substr($ipHash, 0, 8) . '…' : '—';

// Resolve login do ator (somente login, nunca email)
$atorLogin = null;
if ($atorId !== null && function_exists('get_userdata')) {
    $userObj = get_userdata((int) $atorId);
    $atorLogin = ($userObj !== false && isset($userObj->user_login))
        ? esc_html($userObj->user_login)
        : null;
}

$pageTitle = sprintf(
    /* translators: %d: ID do registro */
    __('Detalhe do registro de auditoria #%d', 'participe-ibram'),
    $recordId
);

PageLayout::open(
    $pageTitle,
    [
        ['label' => __('Início', 'participe-ibram'), 'url' => admin_url()],
        ['label' => __('Conformidade & LGPD', 'participe-ibram'), 'url' => admin_url('admin.php?page=' . AuditMenuRegistry::SLUG_LOG)],
        ['label' => __('Auditoria', 'participe-ibram'), 'url' => admin_url('admin.php?page=' . AuditMenuRegistry::SLUG_LOG)],
        ['label' => sprintf(__('Registro #%d', 'participe-ibram'), $recordId)],
    ]
);
?>

<a class="pi-skip-link" href="#pi-admin-main">
    <?php esc_html_e('Pular para o conteúdo', 'participe-ibram'); ?>
</a>

<main id="pi-admin-main" class="pi-audit-detalhe" tabindex="-1">

    <!-- Card: informação principal -->
    <section class="pi-audit-card" aria-labelledby="pi-audit-info-title">
        <h2 id="pi-audit-info-title"><?php esc_html_e('Informações principais', 'participe-ibram'); ?></h2>
        <dl class="pi-audit-dl">
            <div class="pi-audit-dl__row">
                <dt><?php esc_html_e('ID', 'participe-ibram'); ?></dt>
                <dd><?php echo esc_html((string) $recordId); ?></dd>
            </div>
            <div class="pi-audit-dl__row">
                <dt><?php esc_html_e('Entidade', 'participe-ibram'); ?></dt>
                <dd><?php echo esc_html($entidade); ?></dd>
            </div>
            <div class="pi-audit-dl__row">
                <dt><?php esc_html_e('ID da entidade', 'participe-ibram'); ?></dt>
                <dd><?php echo $entidadeId !== null ? esc_html((string) $entidadeId) : '—'; ?></dd>
            </div>
            <div class="pi-audit-dl__row">
                <dt><?php esc_html_e('Ação', 'participe-ibram'); ?></dt>
                <dd><code><?php echo esc_html($acao); ?></code></dd>
            </div>
            <div class="pi-audit-dl__row">
                <dt><?php esc_html_e('Ator', 'participe-ibram'); ?></dt>
                <dd>
                    <?php if ($atorId !== null) : ?>
                        <?php echo esc_html('#' . $atorId); ?>
                        <?php if ($atorLogin !== null) : ?>
                            &nbsp;<em>(<?php echo $atorLogin; ?>)</em>
                        <?php endif; ?>
                    <?php else : ?>
                        <em><?php esc_html_e('sistema', 'participe-ibram'); ?></em>
                    <?php endif; ?>
                </dd>
            </div>
            <div class="pi-audit-dl__row">
                <dt><?php esc_html_e('Ocorrido em', 'participe-ibram'); ?></dt>
                <dd><?php echo esc_html($ocorridoEm); ?></dd>
            </div>
        </dl>
    </section>

    <!-- Card: IP e User-Agent (mascarados) -->
    <section class="pi-audit-card" aria-labelledby="pi-audit-network-title">
        <h2 id="pi-audit-network-title"><?php esc_html_e('Rede', 'participe-ibram'); ?></h2>
        <dl class="pi-audit-dl">
            <div class="pi-audit-dl__row">
                <dt><?php esc_html_e('IP (hash — 8 chars)', 'participe-ibram'); ?></dt>
                <dd><code><?php echo esc_html($ipHashDisplay); ?></code></dd>
            </div>
            <div class="pi-audit-dl__row">
                <dt><?php esc_html_e('User-Agent', 'participe-ibram'); ?></dt>
                <dd>
                    <?php if ($userAgent !== '') : ?>
                        <code><?php echo esc_html(substr($userAgent, 0, 200)); ?></code>
                    <?php else : ?>
                        —
                    <?php endif; ?>
                </dd>
            </div>
        </dl>
    </section>

    <!-- Accordion: dados_antes / dados_depois (mascarados via PiiMasker) -->
    <section class="pi-audit-card pi-audit-accordion" aria-labelledby="pi-audit-payload-title">
        <h2 id="pi-audit-payload-title"><?php esc_html_e('Payloads (PII mascarada)', 'participe-ibram'); ?></h2>

        <details class="pi-audit-accordion__item">
            <summary class="pi-audit-accordion__summary">
                <?php esc_html_e('Dados antes (dados_antes)', 'participe-ibram'); ?>
            </summary>
            <div class="pi-audit-accordion__body">
                <?php if ($dadosAntesMasked !== null) : ?>
                    <pre class="pi-audit-json"><code><?php
                        echo esc_html(
                            json_encode($dadosAntesMasked, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}'
                        );
                    ?></code></pre>
                <?php else : ?>
                    <p class="pi-audit-empty"><?php esc_html_e('(sem dados)', 'participe-ibram'); ?></p>
                <?php endif; ?>
            </div>
        </details>

        <details class="pi-audit-accordion__item">
            <summary class="pi-audit-accordion__summary">
                <?php esc_html_e('Dados depois (dados_depois)', 'participe-ibram'); ?>
            </summary>
            <div class="pi-audit-accordion__body">
                <?php if ($dadosDepoisMasked !== null) : ?>
                    <pre class="pi-audit-json"><code><?php
                        echo esc_html(
                            json_encode($dadosDepoisMasked, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}'
                        );
                    ?></code></pre>
                <?php else : ?>
                    <p class="pi-audit-empty"><?php esc_html_e('(sem dados)', 'participe-ibram'); ?></p>
                <?php endif; ?>
            </div>
        </details>
    </section>

    <p class="pi-audit-notice">
        <em><?php esc_html_e('Nota: dados pessoais sensíveis são exibidos de forma mascarada. O log original é append-only e não pode ser alterado.', 'participe-ibram'); ?></em>
    </p>

    <p>
        <a href="<?php echo esc_url($backUrl); ?>" class="button">
            &laquo; <?php esc_html_e('Voltar para o log', 'participe-ibram'); ?>
        </a>
    </p>

</main>

<?php PageLayout::close(); ?>
