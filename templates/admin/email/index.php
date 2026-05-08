<?php
/**
 * Wrapper das tabs do admin de E-mail.
 *
 * Vars disponíveis (vindo de EmailController::render):
 *   $vars: array{tab:string, tabs:array<string,string>, menu:string, ...}
 *
 * @var array<string,mixed> $vars
 */
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$tab     = isset($vars['tab']) ? (string) $vars['tab'] : 'config';
$tabs    = isset($vars['tabs']) && is_array($vars['tabs']) ? $vars['tabs'] : [];
$menu    = isset($vars['menu']) ? (string) $vars['menu'] : 'pi-email';
?>
<div class="wrap">
    <h1 tabindex="-1"><?= esc_html__('Participe Ibram - E-mail', 'participe-ibram') ?></h1>

    <nav class="nav-tab-wrapper" aria-label="<?= esc_attr__('Navegacao por tabs', 'participe-ibram') ?>">
        <?php foreach ($tabs as $key => $label):
            $isActive = ($key === $tab);
            $url = add_query_arg(['page' => $menu, 'tab' => $key], admin_url('admin.php'));
            ?>
            <a href="<?= esc_url($url) ?>"
               class="nav-tab <?= $isActive ? 'nav-tab-active' : '' ?>"
               <?= $isActive ? 'aria-current="page"' : '' ?>>
                <?= esc_html($label) ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div style="margin-top:16px;">
        <?php
        $partial = PI_PLUGIN_DIR . 'templates/admin/email/' . $tab . '.php';
        if (is_file($partial)) {
            // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
            include $partial;
        } else {
            echo '<p>' . esc_html__('Conteudo da tab nao encontrado.', 'participe-ibram') . '</p>';
        }
        ?>
    </div>
</div>
