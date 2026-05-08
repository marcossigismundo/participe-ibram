<?php
/**
 * Tab Fila pendente.
 *
 * Vars: list_table (EmailLogsListTable).
 *
 * @var array<string,mixed> $vars
 */
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}
$listTable = $vars['list_table'] ?? null;
?>
<h2><?= esc_html__('Fila pendente', 'participe-ibram') ?></h2>
<p><?= esc_html__('Mensagens aguardando envio. O worker processa a fila a cada 5 minutos.', 'participe-ibram') ?></p>

<?php if (is_object($listTable) && method_exists($listTable, 'prepare_items')): ?>
    <?php $listTable->prepare_items(); ?>
    <form method="get" action="">
        <input type="hidden" name="page" value="pi-email">
        <input type="hidden" name="tab" value="fila">
        <?php $listTable->display(); ?>
    </form>
<?php else: ?>
    <p><?= esc_html__('Lista nao disponivel.', 'participe-ibram') ?></p>
<?php endif; ?>
