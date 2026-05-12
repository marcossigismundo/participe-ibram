<?php
/**
 * Tab Fila pendente.
 *
 * Vars: list_table (EmailLogsListTable).
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
$listTable = $vars['list_table'] ?? null;
?>
<div class="pi-list-table">
    <h2><?php esc_html_e('Fila pendente', 'participe-ibram'); ?></h2>
    <p><?php esc_html_e('Mensagens aguardando envio. O worker processa a fila a cada 5 minutos.', 'participe-ibram'); ?></p>

    <?php if (is_object($listTable) && method_exists($listTable, 'prepare_items')) : ?>
        <?php $listTable->prepare_items(); ?>
        <form method="get" action="">
            <input type="hidden" name="page" value="pi-email">
            <input type="hidden" name="tab" value="fila">
            <?php $listTable->display(); ?>
        </form>
    <?php else : ?>
        <p><?php esc_html_e('Lista não disponível.', 'participe-ibram'); ?></p>
    <?php endif; ?>
</div><!-- .pi-list-table -->
