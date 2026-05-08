<?php
/**
 * Template: comprovante imprimível de voto.
 *
 * Acionado por ?print=1 ou via JS (window.print). HTML mínimo otimizado para
 * impressão A4 — sem nav, sem chrome, com hash em destaque.
 *
 * Variáveis esperadas:
 *  - $votacao_id           (int)
 *  - $titulo_edital        (string)
 *  - $abertura_iso         (string)
 *  - $encerramento_iso     (string)
 *  - $categoria_nome       (string)
 *  - $candidato_nome       (string)
 *  - $hash_voto            (string)
 *  - $registrado_em_iso    (string)
 *  - $auditoria_url        (string)
 *
 * @package ParticipeIbram
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

$votacao_id        = isset($votacao_id) ? (int) $votacao_id : 0;
$titulo_edital     = isset($titulo_edital) ? (string) $titulo_edital : '';
$abertura_iso      = isset($abertura_iso) ? (string) $abertura_iso : '';
$encerramento_iso  = isset($encerramento_iso) ? (string) $encerramento_iso : '';
$categoria_nome    = isset($categoria_nome) ? (string) $categoria_nome : '';
$candidato_nome    = isset($candidato_nome) ? (string) $candidato_nome : '';
$hash_voto         = isset($hash_voto) ? (string) $hash_voto : '';
$registrado_em_iso = isset($registrado_em_iso) ? (string) $registrado_em_iso : '';
$auditoria_url     = isset($auditoria_url) ? (string) $auditoria_url : '';
?>
<div class="participe-ibram-scope pi-comprovante-print" role="document">
    <header class="pi-comprovante-print__header">
        <p class="pi-comprovante-print__org">
            <?php esc_html_e('IBRAM — Instituto Brasileiro de Museus', 'participe-ibram'); ?>
        </p>
        <h1 class="pi-comprovante-print__titulo">
            <?php esc_html_e('Comprovante de voto', 'participe-ibram'); ?>
        </h1>
        <?php if ($titulo_edital !== '') : ?>
            <p class="pi-comprovante-print__edital">
                <?php echo esc_html($titulo_edital); ?>
            </p>
        <?php endif; ?>
    </header>

    <section class="pi-comprovante-print__dados" aria-label="<?php esc_attr_e('Dados da votação', 'participe-ibram'); ?>">
        <dl>
            <dt><?php esc_html_e('Votação:', 'participe-ibram'); ?></dt>
            <dd>#<?php echo esc_html((string) $votacao_id); ?></dd>

            <?php if ($abertura_iso !== '') : ?>
                <dt><?php esc_html_e('Abertura:', 'participe-ibram'); ?></dt>
                <dd>
                    <time datetime="<?php echo esc_attr($abertura_iso); ?>">
                        <?php echo esc_html($abertura_iso); ?>
                    </time>
                </dd>
            <?php endif; ?>

            <?php if ($encerramento_iso !== '') : ?>
                <dt><?php esc_html_e('Encerramento:', 'participe-ibram'); ?></dt>
                <dd>
                    <time datetime="<?php echo esc_attr($encerramento_iso); ?>">
                        <?php echo esc_html($encerramento_iso); ?>
                    </time>
                </dd>
            <?php endif; ?>

            <?php if ($categoria_nome !== '') : ?>
                <dt><?php esc_html_e('Categoria:', 'participe-ibram'); ?></dt>
                <dd><?php echo esc_html($categoria_nome); ?></dd>
            <?php endif; ?>

            <?php if ($candidato_nome !== '') : ?>
                <dt><?php esc_html_e('Candidato:', 'participe-ibram'); ?></dt>
                <dd><?php echo esc_html($candidato_nome); ?></dd>
            <?php endif; ?>

            <?php if ($registrado_em_iso !== '') : ?>
                <dt><?php esc_html_e('Data e hora do voto:', 'participe-ibram'); ?></dt>
                <dd>
                    <time datetime="<?php echo esc_attr($registrado_em_iso); ?>">
                        <?php echo esc_html($registrado_em_iso); ?>
                    </time>
                </dd>
            <?php endif; ?>
        </dl>
    </section>

    <section class="pi-comprovante-print__hash">
        <h2><?php esc_html_e('Código do recibo (hash)', 'participe-ibram'); ?></h2>
        <p class="pi-comprovante-print__hash-valor">
            <code><?php echo esc_html($hash_voto); ?></code>
        </p>
        <p class="pi-comprovante-print__hash-aviso">
            <?php esc_html_e('Este código identifica seu voto na auditoria pública sem revelar sua escolha. Guarde-o em local seguro.', 'participe-ibram'); ?>
        </p>
    </section>

    <?php if ($auditoria_url !== '') : ?>
        <section class="pi-comprovante-print__auditoria">
            <h2><?php esc_html_e('Verificação pública', 'participe-ibram'); ?></h2>
            <p>
                <?php esc_html_e('Para verificar a integridade da votação, acesse:', 'participe-ibram'); ?>
            </p>
            <p class="pi-comprovante-print__url">
                <?php echo esc_html($auditoria_url); ?>
            </p>
        </section>
    <?php endif; ?>

    <footer class="pi-comprovante-print__footer">
        <p>
            <?php
            printf(
                /* translators: %s: data atual de impressão */
                esc_html__('Documento emitido em %s.', 'participe-ibram'),
                esc_html(date_i18n('d/m/Y H:i:s'))
            );
            ?>
        </p>
    </footer>
</div>
