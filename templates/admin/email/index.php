<?php
/**
 * Wrapper das tabs do admin de E-mail.
 *
 * Vars disponíveis (vindo de EmailController::render):
 *   $vars: array{tab:string, tabs:array<string,string>, menu:string, ...}
 *
 * W11-C: migrado para PageLayout chrome + breadcrumbs re-parented sob participe-ibram.
 *
 * @var array<string,mixed> $vars
 */
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use Ibram\ParticipeIbram\Presentation\Admin\Support\PageLayout;

$tab     = isset($vars['tab']) ? (string) $vars['tab'] : 'config';
$tabs    = isset($vars['tabs']) && is_array($vars['tabs']) ? $vars['tabs'] : [];
$menu    = isset($vars['menu']) ? (string) $vars['menu'] : 'pi-email';

// Resolve the active tab label for the breadcrumb.
$tabLabels = [
    'config'    => __('Configuração SMTP', 'participe-ibram'),
    'fila'      => __('Fila pendente', 'participe-ibram'),
    'logs'      => __('Logs', 'participe-ibram'),
    'templates' => __('Preview de templates', 'participe-ibram'),
];
$activeTabLabel = $tabLabels[$tab] ?? ucfirst($tab);

PageLayout::open(
    __('E-mail', 'participe-ibram'),
    [
        ['label' => __('Início', 'participe-ibram'), 'url' => admin_url()],
        ['label' => __('Ferramentas', 'participe-ibram'), 'url' => admin_url('admin.php?page=participe-ibram')],
        ['label' => __('E-mail', 'participe-ibram'), 'url' => admin_url('admin.php?page=' . $menu)],
        ['label' => $activeTabLabel],
    ]
);
?>

<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e('Navegação por abas', 'participe-ibram'); ?>">
    <?php foreach ($tabs as $key => $label) :
        $isActive = ($key === $tab);
        $url = add_query_arg(['page' => $menu, 'tab' => $key], admin_url('admin.php'));
        ?>
        <a href="<?php echo esc_url($url); ?>"
           class="nav-tab <?php echo $isActive ? 'nav-tab-active' : ''; ?>"
           <?php echo $isActive ? 'aria-current="page"' : ''; ?>>
            <?php echo esc_html($label); ?>
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
        echo '<p>' . esc_html__('Conteúdo da aba não encontrado.', 'participe-ibram') . '</p>';
    }
    ?>
</div>

<?php PageLayout::close(); ?>
