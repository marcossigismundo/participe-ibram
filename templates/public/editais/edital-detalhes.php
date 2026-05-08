<?php
/**
 * Template: detalhe público de edital.
 *
 * Shortcode [pi_edital_detalhes id="..."].
 * Não exibe NENHUMA informação de inscritos (privacidade).
 *
 * Variáveis esperadas:
 *  - $edital_id  (int)    ID do edital.
 *  - $api_url    (string) URL base da API REST.
 *  - $rest_nonce (string) Nonce WP REST.
 *  - $agente_id  (int)    ID do agente logado (0 se não logado).
 *  - $is_logado  (bool)   Usuário está logado.
 *
 * @package ParticipeIbram
 */

if (! defined('ABSPATH')) {
    exit;
}

$edital_id  = isset($edital_id)  ? (int) $edital_id  : 0;
$api_url    = isset($api_url)    ? (string) $api_url   : '';
$rest_nonce = isset($rest_nonce) ? (string) $rest_nonce : '';
$agente_id  = isset($agente_id)  ? (int) $agente_id   : 0;
$is_logado  = isset($is_logado)  ? (bool) $is_logado   : false;

if ($edital_id <= 0) {
    return;
}
?>
<div
    class="participe-ibram-scope"
    id="pi-edital-detalhe"
    data-api-url="<?php echo esc_attr($api_url); ?>"
    data-nonce="<?php echo esc_attr($rest_nonce); ?>"
    data-edital-id="<?php echo esc_attr((string) $edital_id); ?>"
    data-agente-id="<?php echo esc_attr((string) $agente_id); ?>"
    data-is-logado="<?php echo esc_attr($is_logado ? '1' : '0'); ?>"
    role="region"
    aria-label="<?php esc_attr_e('Detalhes do edital', 'participe-ibram'); ?>"
>
    <a class="pi-skip-link" href="#pi-edital-conteudo"><?php esc_html_e('Pular para o conteúdo do edital', 'participe-ibram'); ?></a>

    <main id="pi-edital-conteudo" tabindex="-1">
        <div id="pi-edital-carregando" class="pi-carregando" aria-live="polite">
            <?php esc_html_e('Carregando edital…', 'participe-ibram'); ?>
        </div>

        <article id="pi-edital-artigo" class="pi-edital-detalhe" hidden>
            <header class="pi-edital-detalhe__header">
                <h1 id="pi-edital-titulo" class="pi-edital-detalhe__titulo"></h1>
                <span id="pi-edital-badge-status" class="pi-badge" aria-live="polite"></span>
            </header>

            <section class="pi-edital-detalhe__descricao" aria-labelledby="pi-edital-desc-titulo">
                <h2 id="pi-edital-desc-titulo"><?php esc_html_e('Sobre o Edital', 'participe-ibram'); ?></h2>
                <div id="pi-edital-descricao-md" class="pi-markdown"></div>
            </section>

            <section class="pi-edital-detalhe__timeline" aria-labelledby="pi-edital-timeline-titulo">
                <h2 id="pi-edital-timeline-titulo"><?php esc_html_e('Cronograma', 'participe-ibram'); ?></h2>
                <ol id="pi-edital-timeline" class="pi-timeline" aria-label="<?php esc_attr_e('Datas do edital', 'participe-ibram'); ?>"></ol>
            </section>

            <section class="pi-edital-detalhe__categorias" aria-labelledby="pi-edital-cat-titulo">
                <h2 id="pi-edital-cat-titulo"><?php esc_html_e('Categorias', 'participe-ibram'); ?></h2>
                <div id="pi-edital-categorias-lista" role="list"></div>
            </section>

            <div id="pi-inscricao-cta" class="pi-edital-detalhe__cta" hidden>
                <a
                    id="pi-btn-inscrever"
                    href="#"
                    class="pi-btn pi-btn--primario pi-btn--lg"
                    aria-describedby="pi-inscricao-aviso"
                >
                    <?php esc_html_e('Inscrever-se', 'participe-ibram'); ?>
                </a>
                <p id="pi-inscricao-aviso" class="pi-aviso" hidden>
                    <?php esc_html_e('Você precisa estar logado e com cadastro deferido para se inscrever.', 'participe-ibram'); ?>
                </p>
            </div>
        </article>

        <div id="pi-edital-erro" class="pi-erro" role="alert" hidden></div>
    </main>

    <div aria-live="assertive" aria-atomic="true" id="pi-edital-anuncio" class="sr-only"></div>
</div>

<script>
(function() {
    'use strict';
    var container = document.getElementById('pi-edital-detalhe');
    if (!container) return;

    var apiUrl    = container.dataset.apiUrl   || '';
    var nonce     = container.dataset.nonce    || '';
    var editalId  = container.dataset.editalId || '';
    var agenteId  = container.dataset.agenteId || '0';
    var isLogado  = container.dataset.isLogado === '1';

    function escHtml(str) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(String(str || '')));
        return d.innerHTML;
    }

    function formatDate(iso) {
        if (!iso) return '—';
        try { return new Date(iso).toLocaleDateString('pt-BR', {day:'2-digit',month:'2-digit',year:'numeric'}); }
        catch(e) { return iso; }
    }

    function badgeClass(status) {
        var map = {'publicado':'pi-badge--info','inscricoes_abertas':'pi-badge--sucesso','em_habilitacao':'pi-badge--aviso','votacao_aberta':'pi-badge--destaque','encerrado':'pi-badge--neutro'};
        return map[status] || 'pi-badge--neutro';
    }

    function badgeLabel(status) {
        var map = {'publicado':'<?php echo esc_js(__('Publicado','participe-ibram')); ?>','inscricoes_abertas':'<?php echo esc_js(__('Inscrições Abertas','participe-ibram')); ?>','em_habilitacao':'<?php echo esc_js(__('Em Habilitação','participe-ibram')); ?>','votacao_aberta':'<?php echo esc_js(__('Votação Aberta','participe-ibram')); ?>','encerrado':'<?php echo esc_js(__('Encerrado','participe-ibram')); ?>'};
        return map[status] || status;
    }

    function renderTimeline(edital) {
        var datas = [
            {label:'<?php echo esc_js(__('Abertura','participe-ibram')); ?>', val: edital.abertura},
            {label:'<?php echo esc_js(__('Encerramento de Inscrições','participe-ibram')); ?>', val: edital.encerramento_inscricoes},
            {label:'<?php echo esc_js(__('Abertura da Votação','participe-ibram')); ?>', val: edital.abertura_votacao},
            {label:'<?php echo esc_js(__('Encerramento da Votação','participe-ibram')); ?>', val: edital.encerramento_votacao}
        ];
        return datas.filter(function(d){return !!d.val;}).map(function(d) {
            return '<li class="pi-timeline__item"><span class="pi-timeline__label">' + escHtml(d.label) + '</span>'
                + '<time datetime="' + escHtml(d.val) + '" class="pi-timeline__data">' + escHtml(formatDate(d.val)) + '</time></li>';
        }).join('');
    }

    function renderCategorias(categorias) {
        if (!categorias || !categorias.length) return '<p><?php echo esc_js(__('Nenhuma categoria encontrada.','participe-ibram')); ?></p>';
        return categorias.map(function(cat) {
            return '<div class="pi-categoria-card" role="listitem">'
                + '<h3 class="pi-categoria-card__nome">' + escHtml(cat.nome) + '</h3>'
                + '<dl class="pi-categoria-card__info">'
                + '<dt><?php echo esc_js(__('Vagas','participe-ibram')); ?></dt><dd>' + escHtml(cat.num_vagas) + '</dd>'
                + '<dt><?php echo esc_js(__('Suplentes','participe-ibram')); ?></dt><dd>' + escHtml(cat.num_suplentes) + '</dd>'
                + '<dt><?php echo esc_js(__('Tipos elegíveis','participe-ibram')); ?></dt><dd>' + escHtml(cat.tipos_agente_elegivel) + '</dd>'
                + '</dl>'
                + (cat.criterios_md ? '<div class="pi-categoria-card__criterios pi-markdown">' + escHtml(cat.criterios_md) + '</div>' : '')
                + '</div>';
        }).join('');
    }

    fetch(apiUrl + '/publico/edital/' + encodeURIComponent(editalId), {
        headers: {'Accept':'application/json'},
        credentials: 'same-origin'
    })
    .then(function(r) {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
    })
    .then(function(edital) {
        document.getElementById('pi-edital-carregando').hidden = true;

        document.getElementById('pi-edital-titulo').textContent = edital.titulo || '';

        var badge = document.getElementById('pi-edital-badge-status');
        badge.textContent = badgeLabel(edital.status);
        badge.className = 'pi-badge ' + badgeClass(edital.status);

        // Descrição — markdown rendido como texto simples (sem XSS).
        var descEl = document.getElementById('pi-edital-descricao-md');
        if (descEl) descEl.textContent = edital.descricao_md || '';

        var timeline = document.getElementById('pi-edital-timeline');
        if (timeline) timeline.innerHTML = renderTimeline(edital);

        var catEl = document.getElementById('pi-edital-categorias-lista');
        if (catEl) catEl.innerHTML = renderCategorias(edital.categorias || []);

        // Botão inscrever: visível apenas se inscricoes_abertas + logado.
        if (edital.status === 'inscricoes_abertas') {
            var cta = document.getElementById('pi-inscricao-cta');
            if (cta) {
                cta.hidden = false;
                if (!isLogado || agenteId === '0') {
                    var aviso = document.getElementById('pi-inscricao-aviso');
                    if (aviso) aviso.hidden = false;
                    var btn = document.getElementById('pi-btn-inscrever');
                    if (btn) { btn.setAttribute('aria-disabled','true'); btn.setAttribute('tabindex','-1'); }
                } else {
                    var btn2 = document.getElementById('pi-btn-inscrever');
                    if (btn2) btn2.href = '?pi_inscricao_edital=' + encodeURIComponent(editalId);
                }
            }
        }

        var artigo = document.getElementById('pi-edital-artigo');
        if (artigo) artigo.hidden = false;

        var anuncio = document.getElementById('pi-edital-anuncio');
        if (anuncio) anuncio.textContent = '<?php echo esc_js(__('Edital carregado','participe-ibram')); ?>: ' + (edital.titulo || '');
    })
    .catch(function() {
        document.getElementById('pi-edital-carregando').hidden = true;
        var errEl = document.getElementById('pi-edital-erro');
        if (errEl) { errEl.textContent = '<?php echo esc_js(__('Erro ao carregar o edital. Tente novamente.','participe-ibram')); ?>'; errEl.hidden = false; }
    });
}());
</script>
