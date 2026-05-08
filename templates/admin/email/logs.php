<?php
/**
 * Tab Logs (todos os status com filtros).
 *
 * Vars: list_table (EmailLogsListTable), eventos (array<int,string>).
 *
 * @var array<string,mixed> $vars
 */
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}
$listTable      = $vars['list_table'] ?? null;
$eventos        = isset($vars['eventos']) && is_array($vars['eventos']) ? $vars['eventos'] : [];
$selectedEvento = isset($_GET['evento_filter']) ? sanitize_key((string) wp_unslash($_GET['evento_filter'])) : '';
$selectedStatus = isset($_GET['status_filter']) ? sanitize_key((string) wp_unslash($_GET['status_filter'])) : '';
?>
<h2><?= esc_html__('Logs de e-mail', 'participe-ibram') ?></h2>

<form method="get" action="" style="margin-bottom:12px;" aria-label="<?= esc_attr__('Filtros de logs', 'participe-ibram') ?>">
    <input type="hidden" name="page" value="pi-email">
    <input type="hidden" name="tab" value="logs">

    <label for="pi-evento-filter" class="screen-reader-text"><?= esc_html__('Evento', 'participe-ibram') ?></label>
    <select id="pi-evento-filter" name="evento_filter">
        <option value=""><?= esc_html__('-- Todos os eventos --', 'participe-ibram') ?></option>
        <?php foreach ($eventos as $ev): ?>
            <option value="<?= esc_attr($ev) ?>" <?= $selectedEvento === $ev ? 'selected' : '' ?>>
                <?= esc_html($ev) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label for="pi-status-filter" class="screen-reader-text"><?= esc_html__('Status', 'participe-ibram') ?></label>
    <select id="pi-status-filter" name="status_filter">
        <option value=""><?= esc_html__('-- Todos os status --', 'participe-ibram') ?></option>
        <?php foreach (['pendente', 'enviando', 'enviado', 'falhou'] as $s): ?>
            <option value="<?= esc_attr($s) ?>" <?= $selectedStatus === $s ? 'selected' : '' ?>>
                <?= esc_html($s) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <button type="submit" class="button"><?= esc_html__('Filtrar', 'participe-ibram') ?></button>
</form>

<?php if (is_object($listTable) && method_exists($listTable, 'prepare_items')): ?>
    <?php $listTable->prepare_items(); ?>
    <form method="get" action="">
        <input type="hidden" name="page" value="pi-email">
        <input type="hidden" name="tab" value="logs">
        <?php $listTable->display(); ?>
    </form>
<?php else: ?>
    <p><?= esc_html__('Lista nao disponivel.', 'participe-ibram') ?></p>
<?php endif; ?>
