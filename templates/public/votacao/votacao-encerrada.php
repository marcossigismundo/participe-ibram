<?php
/**
 * Template: votação encerrada/agendada (fora da janela ativa).
 *
 * Renderizado quando o shortcode [pi_votacao] é chamado para uma votação
 * que ainda não abriu, já encerrou, ou está em estado não-ativo.
 *
 * Variáveis esperadas:
 *  - $votacao_id          (int)
 *  - $titulo_edital       (string)
 *  - $status              (string) 'agendada'|'encerrada'|'cancelada'|'inexistente'
 *  - $abertura_iso        (string)
 *  - $encerramento_iso    (string)
 *  - $resultados_url      (string) link para resultados (vazio se ainda não publicados)
 *
 * @package ParticipeIbram
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

$votacao_id        = isset($votacao_id) ? (int) $votacao_id : 0;
$titulo_edital     = isset($titulo_edital) ? (string) $titulo_edital : '';
$status            = isset($status) ? (string) $status : 'encerrada';
$abertura_iso      = isset($abertura_iso) ? (string) $abertura_iso : '';
$encerramento_iso  = isset($encerramento_iso) ? (string) $encerramento_iso : '';
$resultados_url    = isset($resultados_url) ? (string) $resultados_url : '';

$status_textos = [
    'agendada'    => __('Esta votação ainda não foi aberta.', 'participe-ibram'),
    'encerrada'   => __('Esta votação está encerrada.', 'participe-ibram'),
    'cancelada'   => __('Esta votação foi cancelada.', 'participe-ibram'),
    'inexistente' => __('Votação não encontrada.', 'participe-ibram'),
];
$titulo_status = [
    'agendada'    => __('Votação agendada', 'participe-ibram'),
    'encerrada'   => __('Votação encerrada', 'participe-ibram'),
    'cancelada'   => __('Votação cancelada', 'participe-ibram'),
    'inexistente' => __('Votação não encontrada', 'participe-ibram'),
];

$mensagem = $status_textos[$status] ?? $status_textos['encerrada'];
$titulo   = $titulo_status[$status] ?? $titulo_status['encerrada'];
?>
<div class="participe-ibram-scope pi-votacao-app pi-votacao-app--encerrada">
    <main class="pi-votacao-app__main" tabindex="-1" id="pi-votacao-encerrada-conteudo">
        <section
            class="pi-aviso pi-aviso--aviso"
            role="region"
            aria-labelledby="pi-votacao-encerrada-titulo"
        >
            <h1 id="pi-votacao-encerrada-titulo">
                <?php echo esc_html($titulo); ?>
            </h1>
            <p><?php echo esc_html($mensagem); ?></p>

            <?php if ($titulo_edital !== '') : ?>
                <p>
                    <strong><?php esc_html_e('Edital:', 'participe-ibram'); ?></strong>
                    <?php echo esc_html($titulo_edital); ?>
                </p>
            <?php endif; ?>

            <?php if ($abertura_iso !== '' || $encerramento_iso !== '') : ?>
                <dl class="pi-votacao-app__datas">
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
                </dl>
            <?php endif; ?>

            <?php if ($status === 'encerrada' && $resultados_url !== '') : ?>
                <p class="pi-votacao-app__resultados-cta">
                    <a
                        class="pi-btn pi-btn--primario"
                        href="<?php echo esc_url($resultados_url); ?>"
                    >
                        <?php esc_html_e('Ver resultados publicados', 'participe-ibram'); ?>
                    </a>
                </p>
            <?php elseif ($status === 'encerrada') : ?>
                <p>
                    <?php esc_html_e('Os resultados serão publicados em breve.', 'participe-ibram'); ?>
                </p>
            <?php endif; ?>
        </section>
    </main>
</div>
