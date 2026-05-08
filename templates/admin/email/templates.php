<?php
/**
 * Tab Preview de templates.
 *
 * Vars: eventos (array<int,string>), template_selected (string), preview (?array).
 *
 * @var array<string,mixed> $vars
 */
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$eventos          = isset($vars['eventos']) && is_array($vars['eventos']) ? $vars['eventos'] : [];
$templateSelected = isset($vars['template_selected']) ? (string) $vars['template_selected'] : '';
$preview          = $vars['preview'] ?? null;
?>
<h2><?= esc_html__('Preview de templates', 'participe-ibram') ?></h2>
<p><?= esc_html__('Visualize os templates renderizados com vars de exemplo (sem PII real).', 'participe-ibram') ?></p>

<form method="get" action="" style="margin-bottom:16px;" aria-label="<?= esc_attr__('Selecao de template', 'participe-ibram') ?>">
    <input type="hidden" name="page" value="pi-email">
    <input type="hidden" name="tab" value="templates">

    <label for="pi-template-select"><?= esc_html__('Template', 'participe-ibram') ?>:</label>
    <select id="pi-template-select" name="template" onchange="this.form.submit()">
        <option value=""><?= esc_html__('-- Selecione --', 'participe-ibram') ?></option>
        <?php foreach ($eventos as $ev): ?>
            <option value="<?= esc_attr($ev) ?>" <?= $templateSelected === $ev ? 'selected' : '' ?>>
                <?= esc_html($ev) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <noscript><button type="submit" class="button"><?= esc_html__('Carregar', 'participe-ibram') ?></button></noscript>
</form>

<?php if (is_array($preview)): ?>
    <h3><?= esc_html__('Assunto', 'participe-ibram') ?></h3>
    <pre style="padding:12px;background:#f5f5f5;border:1px solid #ddd;"><?= esc_html((string) $preview['assunto']) ?></pre>

    <h3><?= esc_html__('HTML renderizado', 'participe-ibram') ?></h3>
    <p><em><?= esc_html__('O HTML abaixo e exibido em um iframe sandbox para garantir isolamento.', 'participe-ibram') ?></em></p>
    <iframe sandbox="allow-same-origin"
            style="width:100%;height:600px;border:1px solid #ccc;"
            srcdoc="<?= esc_attr((string) $preview['html']) ?>"
            title="<?= esc_attr__('Preview HTML', 'participe-ibram') ?>"></iframe>

    <h3><?= esc_html__('Versao texto', 'participe-ibram') ?></h3>
    <pre style="padding:12px;background:#f5f5f5;border:1px solid #ddd;white-space:pre-wrap;"><?= esc_html((string) $preview['text']) ?></pre>
<?php elseif ($templateSelected !== ''): ?>
    <p style="color:#a80521;" role="alert">
        <?= esc_html__('Falha ao renderizar o template (verifique se os arquivos existem em templates/emails/).', 'participe-ibram') ?>
    </p>
<?php endif; ?>
