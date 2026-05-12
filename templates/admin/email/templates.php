<?php
/**
 * Tab Preview de templates.
 *
 * Vars: eventos (array<int,string>), template_selected (string), preview (?array).
 * Incluído dentro de email/index.php (já dentro de PageLayout chrome).
 *
 * W11-C: adicionado wrapper .pi-form.
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
<div class="pi-form">
    <h2><?php esc_html_e('Preview de templates', 'participe-ibram'); ?></h2>
    <p><?php esc_html_e('Visualize os templates renderizados com vars de exemplo (sem PII real).', 'participe-ibram'); ?></p>

    <form method="get" action="" style="margin-bottom:16px;" aria-label="<?php esc_attr_e('Seleção de template', 'participe-ibram'); ?>">
        <input type="hidden" name="page" value="pi-email">
        <input type="hidden" name="tab" value="templates">

        <label for="pi-template-select"><?php esc_html_e('Template', 'participe-ibram'); ?>:</label>
        <select id="pi-template-select" name="template" onchange="this.form.submit()">
            <option value=""><?php esc_html_e('-- Selecione --', 'participe-ibram'); ?></option>
            <?php foreach ($eventos as $ev) : ?>
                <option value="<?php echo esc_attr($ev); ?>" <?php echo $templateSelected === $ev ? 'selected' : ''; ?>>
                    <?php echo esc_html($ev); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <noscript><button type="submit" class="button"><?php esc_html_e('Carregar', 'participe-ibram'); ?></button></noscript>
    </form>

    <?php if (is_array($preview)) : ?>
        <h3><?php esc_html_e('Assunto', 'participe-ibram'); ?></h3>
        <pre style="padding:12px;background:#f5f5f5;border:1px solid #ddd;"><?php echo esc_html((string) $preview['assunto']); ?></pre>

        <h3><?php esc_html_e('HTML renderizado', 'participe-ibram'); ?></h3>
        <p><em><?php esc_html_e('O HTML abaixo é exibido em um iframe sandbox para garantir isolamento.', 'participe-ibram'); ?></em></p>
        <iframe sandbox="allow-same-origin"
                style="width:100%;height:600px;border:1px solid #ccc;"
                srcdoc="<?php echo esc_attr((string) $preview['html']); ?>"
                title="<?php esc_attr_e('Preview HTML', 'participe-ibram'); ?>"></iframe>

        <h3><?php esc_html_e('Versão texto', 'participe-ibram'); ?></h3>
        <pre style="padding:12px;background:#f5f5f5;border:1px solid #ddd;white-space:pre-wrap;"><?php echo esc_html((string) $preview['text']); ?></pre>
    <?php elseif ($templateSelected !== '') : ?>
        <p style="color:#a80521;" role="alert">
            <?php esc_html_e('Falha ao renderizar o template (verifique se os arquivos existem em templates/emails/).', 'participe-ibram'); ?>
        </p>
    <?php endif; ?>
</div><!-- .pi-form -->
