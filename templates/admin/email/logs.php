<?php
/**
 * Tab Logs (todos os status com filtros).
 *
 * Vars: list_table (EmailLogsListTable), eventos (array<int,string>).
 * Incluído dentro de email/index.php (já dentro de PageLayout chrome).
 *
 * W11-C: adicionado wrapper .pi-list-table.
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
<div class="pi-list-table">
    <h2><?php esc_html_e('Logs de e-mail', 'participe-ibram'); ?></h2>

    <form method="get" action="" style="margin-bottom:12px;" aria-label="<?php esc_attr_e('Filtros de logs', 'participe-ibram'); ?>">
        <input type="hidden" name="page" value="pi-email">
        <input type="hidden" name="tab" value="logs">

        <label for="pi-evento-filter" class="screen-reader-text"><?php esc_html_e('Evento', 'participe-ibram'); ?></label>
        <select id="pi-evento-filter" name="evento_filter">
            <option value=""><?php esc_html_e('-- Todos os eventos --', 'participe-ibram'); ?></option>
            <?php foreach ($eventos as $ev) : ?>
                <option value="<?php echo esc_attr($ev); ?>" <?php echo $selectedEvento === $ev ? 'selected' : ''; ?>>
                    <?php echo esc_html($ev); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="pi-status-filter" class="screen-reader-text"><?php esc_html_e('Status', 'participe-ibram'); ?></label>
        <select id="pi-status-filter" name="status_filter">
            <option value=""><?php esc_html_e('-- Todos os status --', 'participe-ibram'); ?></option>
            <?php foreach (['pendente', 'enviando', 'enviado', 'falhou'] as $s) : ?>
                <option value="<?php echo esc_attr($s); ?>" <?php echo $selectedStatus === $s ? 'selected' : ''; ?>>
                    <?php echo esc_html($s); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="button"><?php esc_html_e('Filtrar', 'participe-ibram'); ?></button>
    </form>

    <?php if (is_object($listTable) && method_exists($listTable, 'prepare_items')) : ?>
        <?php $listTable->prepare_items(); ?>
        <form method="get" action="">
            <input type="hidden" name="page" value="pi-email">
            <input type="hidden" name="tab" value="logs">
            <?php $listTable->display(); ?>
        </form>
    <?php else : ?>
        <p><?php esc_html_e('Lista não disponível.', 'participe-ibram'); ?></p>
    <?php endif; ?>
</div><!-- .pi-list-table -->
