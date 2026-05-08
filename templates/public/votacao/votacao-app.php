<?php
/**
 * Template: aplicação pública de votação.
 *
 * Shortcode [pi_votacao id="..."]. Renderiza:
 *  - Skip link
 *  - <main> com cabeçalho da votação + countdown
 *  - Container de categorias (preenchido via JS)
 *  - Modal estático de confirmação (controlado por ConfirmacaoVoto.js)
 *  - Container de recibo (escondido até voto registrado)
 *  - Live region polite para anúncios de status
 *
 * Variáveis esperadas:
 *  - $votacao_id          (int)    ID da votação
 *  - $titulo_edital       (string) Título do edital associado
 *  - $abertura_iso        (string) Data/hora ISO de abertura
 *  - $encerramento_iso    (string) Data/hora ISO de encerramento
 *  - $api_url             (string) URL base /wp-json/pi/v1
 *  - $rest_nonce          (string) Nonce REST
 *  - $login_url           (string) URL de login com retorno
 *  - $auditoria_url_base  (string) URL pública para verificação de hash
 *
 * @package ParticipeIbram
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

$votacao_id          = isset($votacao_id) ? (int) $votacao_id : 0;
$titulo_edital       = isset($titulo_edital) ? (string) $titulo_edital : '';
$abertura_iso        = isset($abertura_iso) ? (string) $abertura_iso : '';
$encerramento_iso    = isset($encerramento_iso) ? (string) $encerramento_iso : '';
$api_url             = isset($api_url) ? (string) $api_url : '';
$rest_nonce          = isset($rest_nonce) ? (string) $rest_nonce : '';
$login_url           = isset($login_url) ? (string) $login_url : '';
$auditoria_url_base  = isset($auditoria_url_base) ? (string) $auditoria_url_base : '';

if ($votacao_id <= 0) {
    return;
}
?>
<div
    class="participe-ibram-scope pi-votacao-app"
    id="pi-votacao-<?php echo esc_attr((string) $votacao_id); ?>"
    data-pi-votacao="<?php echo esc_attr((string) $votacao_id); ?>"
    data-votacao-id="<?php echo esc_attr((string) $votacao_id); ?>"
    data-api-url="<?php echo esc_attr($api_url); ?>"
    data-nonce="<?php echo esc_attr($rest_nonce); ?>"
    data-login-url="<?php echo esc_attr($login_url); ?>"
    data-auditoria-url="<?php echo esc_attr($auditoria_url_base); ?>"
>
    <a class="pi-skip-link" href="#pi-votacao-conteudo">
        <?php esc_html_e('Pular para a votação', 'participe-ibram'); ?>
    </a>

    <main id="pi-votacao-conteudo" class="pi-votacao-app__main" tabindex="-1">

        <header class="pi-votacao-app__header" role="region" aria-labelledby="pi-votacao-titulo">
            <h1 id="pi-votacao-titulo" class="pi-votacao-app__titulo">
                <?php echo esc_html($titulo_edital !== '' ? $titulo_edital : __('Votação', 'participe-ibram')); ?>
            </h1>

            <section
                class="pi-votacao-app__info"
                role="region"
                aria-labelledby="pi-info-votacao-title"
            >
                <h2 id="pi-info-votacao-title" class="sr-only">
                    <?php esc_html_e('Informações da votação', 'participe-ibram'); ?>
                </h2>
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
                            <time
                                datetime="<?php echo esc_attr($encerramento_iso); ?>"
                                data-pi-votacao-encerramento="<?php echo esc_attr($encerramento_iso); ?>"
                            >
                                <?php echo esc_html($encerramento_iso); ?>
                            </time>
                        </dd>
                    <?php endif; ?>
                </dl>
                <p
                    class="pi-votacao-app__countdown"
                    data-pi-votacao-countdown
                    aria-live="polite"
                ></p>
            </section>

            <p class="pi-votacao-app__aviso pi-aviso pi-aviso--importante" role="note">
                <strong><?php esc_html_e('Atenção:', 'participe-ibram'); ?></strong>
                <?php esc_html_e('o voto é IRREVERSÍVEL. Após confirmar, não será possível alterar.', 'participe-ibram'); ?>
            </p>
        </header>

        <div
            class="pi-votacao-app__loading"
            data-pi-votacao-loading
            role="status"
            aria-live="polite"
        >
            <span class="pi-spinner" aria-hidden="true"></span>
            <span><?php esc_html_e('Carregando informações da votação…', 'participe-ibram'); ?></span>
        </div>

        <div
            class="pi-aviso pi-aviso--erro"
            data-pi-votacao-error
            role="alert"
            hidden
        ></div>

        <section
            class="pi-votacao-app__not-eligible pi-aviso pi-aviso--info"
            data-pi-votacao-not-eligible
            role="region"
            aria-labelledby="pi-not-eligible-title"
            hidden
        >
            <h2 id="pi-not-eligible-title">
                <?php esc_html_e('Você não está elegível para esta votação', 'participe-ibram'); ?>
            </h2>
            <p>
                <?php esc_html_e('Sua inscrição não foi habilitada em nenhuma categoria desta votação. Em caso de dúvida, entre em contato com a equipe organizadora.', 'participe-ibram'); ?>
            </p>
        </section>

        <section
            class="pi-votacao-app__closed pi-aviso pi-aviso--aviso"
            data-pi-votacao-closed
            role="region"
            aria-labelledby="pi-closed-title"
            hidden
        >
            <h2 id="pi-closed-title">
                <?php esc_html_e('Votação encerrada', 'participe-ibram'); ?>
            </h2>
            <p data-pi-closed-msg>
                <?php esc_html_e('Esta votação não está mais aceitando votos.', 'participe-ibram'); ?>
            </p>
        </section>

        <section
            class="pi-votacao-app__categorias"
            role="region"
            aria-labelledby="pi-categorias-title"
        >
            <h2 id="pi-categorias-title" class="sr-only">
                <?php esc_html_e('Categorias para votação', 'participe-ibram'); ?>
            </h2>
            <div data-pi-votacao-categorias></div>
        </section>

        <section
            class="pi-recibo"
            data-pi-recibo
            role="region"
            aria-labelledby="pi-recibo-title"
            hidden
        ></section>

        <div
            id="pi-votacao-live"
            data-pi-votacao-live
            class="sr-only"
            role="status"
            aria-live="polite"
            aria-atomic="true"
        ></div>
    </main>

    <!-- Modal de confirmação de voto (controlado por ConfirmacaoVoto.js) -->
    <div
        class="pi-modal pi-confirmacao-modal pi-confirmacao-modal--warning"
        id="pi-confirmacao-voto-<?php echo esc_attr((string) $votacao_id); ?>"
        data-pi-modal
        data-pi-confirmacao-modal
        role="dialog"
        aria-modal="true"
        aria-labelledby="pi-confirmacao-voto-title-<?php echo esc_attr((string) $votacao_id); ?>"
        aria-describedby="pi-confirmacao-voto-desc-<?php echo esc_attr((string) $votacao_id); ?>"
        hidden
    >
        <div class="pi-modal__overlay" aria-hidden="true"></div>
        <div class="pi-modal__dialog" role="document">
            <header class="pi-modal__header">
                <h2
                    id="pi-confirmacao-voto-title-<?php echo esc_attr((string) $votacao_id); ?>"
                    class="pi-modal__title"
                >
                    <?php esc_html_e('Confirmar voto', 'participe-ibram'); ?>
                </h2>
                <button
                    type="button"
                    class="pi-modal__close"
                    data-pi-modal-close
                    aria-label="<?php esc_attr_e('Fechar sem votar', 'participe-ibram'); ?>"
                >&times;</button>
            </header>
            <div class="pi-modal__body" id="pi-confirmacao-voto-desc-<?php echo esc_attr((string) $votacao_id); ?>">
                <p class="pi-confirmacao-voto__pergunta">
                    <?php esc_html_e('Você está prestes a votar em:', 'participe-ibram'); ?>
                </p>
                <dl class="pi-confirmacao-voto__detalhes">
                    <dt><?php esc_html_e('Categoria:', 'participe-ibram'); ?></dt>
                    <dd data-pi-confirm-categoria>—</dd>
                    <dt><?php esc_html_e('Candidato:', 'participe-ibram'); ?></dt>
                    <dd>
                        <strong data-pi-confirm-candidato>—</strong>
                        <span class="pi-confirmacao-voto__numero">
                            <?php esc_html_e('Nº de registro:', 'participe-ibram'); ?>
                            <span data-pi-confirm-numero>—</span>
                        </span>
                    </dd>
                </dl>

                <div class="pi-confirmacao-voto__alerta" role="alert">
                    <strong><?php esc_html_e('Atenção:', 'participe-ibram'); ?></strong>
                    <?php esc_html_e('o voto é IRREVERSÍVEL. Após confirmar, não será possível alterar nem cancelar este voto.', 'participe-ibram'); ?>
                </div>

                <p
                    class="pi-confirmacao-voto__countdown"
                    data-pi-confirm-countdown
                    aria-hidden="false"
                ></p>
                <span
                    class="sr-only"
                    data-pi-confirm-countdown-live
                    role="status"
                    aria-live="polite"
                    aria-atomic="true"
                ></span>
            </div>
            <footer class="pi-modal__footer">
                <button
                    type="button"
                    class="pi-btn pi-btn--secundario"
                    data-pi-confirm-cancelar
                    data-pi-modal-close
                    autofocus
                >
                    <?php esc_html_e('Cancelar', 'participe-ibram'); ?>
                </button>
                <button
                    type="button"
                    class="pi-btn pi-btn--perigo"
                    data-pi-confirm-ok
                    disabled
                    aria-disabled="true"
                >
                    <?php esc_html_e('Confirmar voto', 'participe-ibram'); ?>
                </button>
            </footer>
        </div>
    </div>
</div>

<?php
// i18n strings disponibilizadas para o JS via window.piI18n (via wp_localize_script
// no enqueue). Aqui apenas uma fallback inline para garantir que mensagens
// chave estejam disponiveis caso o enqueue ainda nao tenha rodado.
$pi_votacao_i18n = [
    'carregando'             => __('Carregando informações da votação…', 'participe-ibram'),
    'registrando'            => __('Registrando voto…', 'participe-ibram'),
    'votoRegistrado'         => __('Voto registrado com sucesso. Recibo emitido.', 'participe-ibram'),
    'duplicado'              => __('Voto já registrado anteriormente nesta categoria.', 'participe-ibram'),
    'jaVotou'                => __('Voto já registrado nesta categoria.', 'participe-ibram'),
    'cancelado'              => __('Voto cancelado.', 'participe-ibram'),
    'encerrada'              => __('Votação encerrada.', 'participe-ibram'),
    'sessaoExpirada'         => __('Sessão expirada. Redirecionando para login…', 'participe-ibram'),
    'inelegivel'             => __('Você não está habilitado para esta categoria.', 'participe-ibram'),
    'erroGenerico'           => __('Não foi possível registrar o voto. Tente novamente.', 'participe-ibram'),
    'erroCarregar'           => __('Não foi possível carregar a votação. Tente novamente.', 'participe-ibram'),
    'erroCandidatos'         => __('Erro ao carregar candidatos.', 'participe-ibram'),
    'selecioneCandidato'     => __('Selecione um candidato e confirme seu voto.', 'participe-ibram'),
    'votarNeste'             => __('Votar neste candidato', 'participe-ibram'),
    'categoriaPrefixo'       => __('Categoria: ', 'participe-ibram'),
    'semCategorias'          => __('Você não está elegível para votar em nenhuma categoria desta votação.', 'participe-ibram'),
    'todasVotadas'           => __('Todas as suas categorias já tiveram voto registrado.', 'participe-ibram'),
    'confirmCountdown'       => __('Botão liberado em {n}s', 'participe-ibram'),
    'confirmCountdownAnnounce' => __('Aguarde 3 segundos antes de confirmar o voto', 'participe-ibram'),
    'confirmCountdownReady'  => __('Botão Confirmar voto será habilitado', 'participe-ibram'),
    'confirmEnabled'         => __('Botão Confirmar voto habilitado. Pressione Enter para confirmar.', 'participe-ibram'),
    'reciboTitulo'           => __('Voto registrado com sucesso', 'participe-ibram'),
    'reciboAviso'            => __('Guarde este recibo. Ele comprova que você votou e permite verificar a integridade da votação na auditoria pública.', 'participe-ibram'),
    'reciboHashLabel'        => __('Código do recibo (hash):', 'participe-ibram'),
    'reciboCopiar'           => __('Copiar recibo', 'participe-ibram'),
    'reciboImprimir'         => __('Imprimir comprovante', 'participe-ibram'),
    'reciboPdf'              => __('Baixar PDF (via impressão)', 'participe-ibram'),
    'reciboAuditoria'        => __('Verificar na auditoria pública', 'participe-ibram'),
    'reciboCopiado'          => __('Recibo copiado para a área de transferência.', 'participe-ibram'),
    'reciboCopiarFalha'      => __('Não foi possível copiar automaticamente. Selecione o texto manualmente.', 'participe-ibram'),
    'reciboCopiarVazio'      => __('Nenhum recibo para copiar.', 'participe-ibram'),
    'reciboCategoriaLabel'   => __('Categoria:', 'participe-ibram'),
    'reciboCandidatoLabel'   => __('Candidato:', 'participe-ibram'),
    'reciboDataLabel'        => __('Data e hora:', 'participe-ibram'),
    'reciboVotacaoLabel'     => __('Votação:', 'participe-ibram'),
    'reciboAnuncio'          => __('Voto registrado com sucesso. Recibo emitido.', 'participe-ibram'),
    'timeout'                => __('O servidor demorou para responder. Tente novamente.', 'participe-ibram'),
    'network'                => __('Falha de rede. Verifique sua conexão.', 'participe-ibram'),
    'unauthorized'           => __('Sessão expirada. Faça login novamente para votar.', 'participe-ibram'),
    'forbidden'              => __('Você não tem permissão para realizar esta ação.', 'participe-ibram'),
    'notEligible'            => __('Você não está habilitado para votar nesta categoria.', 'participe-ibram'),
    'duplicate'              => __('Voto já registrado anteriormente nesta categoria.', 'participe-ibram'),
    'closed'                 => __('Votação encerrada.', 'participe-ibram'),
    'rateLimited'            => __('Muitas requisições. Aguarde alguns segundos.', 'participe-ibram'),
    'serverError'            => __('Erro no servidor. Tente novamente em instantes.', 'participe-ibram'),
];
$pi_votacao_i18n_json = wp_json_encode($pi_votacao_i18n);
?>
<script>
(function () {
    'use strict';
    window.piI18n = window.piI18n || {};
    try {
        var dict = <?php echo $pi_votacao_i18n_json !== false ? $pi_votacao_i18n_json : '{}'; ?>;
        for (var k in dict) {
            if (Object.prototype.hasOwnProperty.call(dict, k) && !window.piI18n[k]) {
                window.piI18n[k] = dict[k];
            }
        }
    } catch (_e) { /* noop */ }
})();
</script>
