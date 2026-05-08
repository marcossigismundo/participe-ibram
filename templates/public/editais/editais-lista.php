<?php
/**
 * Template: lista pública de editais.
 *
 * Shortcode [pi_editais_publicos].
 * Renderização dinâmica via fetch JS — não exibe inscrições nem PII.
 *
 * Variáveis esperadas:
 *  - $api_url    (string) ex: home_url('/wp-json/pi/v1')
 *  - $rest_nonce (string) wp_create_nonce('wp_rest')
 *
 * @package ParticipeIbram
 */

if (! defined('ABSPATH')) {
    exit;
}

$api_url    = isset($api_url) ? (string) $api_url : '';
$rest_nonce = isset($rest_nonce) ? (string) $rest_nonce : '';
?>
<div
    class="participe-ibram-scope"
    id="pi-editais-lista"
    data-api-url="<?php echo esc_attr($api_url); ?>"
    data-nonce="<?php echo esc_attr($rest_nonce); ?>"
    role="region"
    aria-label="<?php esc_attr_e('Lista de editais', 'participe-ibram'); ?>"
>
    <a class="pi-skip-link" href="#pi-editais-conteudo"><?php esc_html_e('Pular para a lista de editais', 'participe-ibram'); ?></a>

    <header class="pi-editais__header">
        <h2 id="pi-editais-titulo"><?php esc_html_e('Editais', 'participe-ibram'); ?></h2>

        <form
            class="pi-editais__filtros"
            role="search"
            aria-label="<?php esc_attr_e('Filtrar editais', 'participe-ibram'); ?>"
            id="pi-form-filtros-editais"
        >
            <fieldset>
                <legend class="sr-only"><?php esc_html_e('Filtros de busca', 'participe-ibram'); ?></legend>

                <div class="pi-campo-grupo">
                    <label for="pi-filtro-status"><?php esc_html_e('Status', 'participe-ibram'); ?></label>
                    <select id="pi-filtro-status" name="status" class="pi-select">
                        <option value=""><?php esc_html_e('Todos', 'participe-ibram'); ?></option>
                        <option value="publicado"><?php esc_html_e('Publicado', 'participe-ibram'); ?></option>
                        <option value="inscricoes_abertas"><?php esc_html_e('Inscrições Abertas', 'participe-ibram'); ?></option>
                        <option value="em_habilitacao"><?php esc_html_e('Em Habilitação', 'participe-ibram'); ?></option>
                        <option value="votacao_aberta"><?php esc_html_e('Votação Aberta', 'participe-ibram'); ?></option>
                        <option value="encerrado"><?php esc_html_e('Encerrado', 'participe-ibram'); ?></option>
                    </select>
                </div>

                <div class="pi-campo-grupo">
                    <label for="pi-filtro-abertura"><?php esc_html_e('Abertura desde', 'participe-ibram'); ?></label>
                    <input
                        type="date"
                        id="pi-filtro-abertura"
                        name="abertura_desde"
                        class="pi-input"
                        aria-describedby="pi-filtro-abertura-desc"
                    >
                    <span id="pi-filtro-abertura-desc" class="sr-only">
                        <?php esc_html_e('Filtrar editais abertos a partir desta data', 'participe-ibram'); ?>
                    </span>
                </div>

                <button type="submit" class="pi-btn pi-btn--primario">
                    <?php esc_html_e('Filtrar', 'participe-ibram'); ?>
                </button>
            </fieldset>
        </form>
    </header>

    <main id="pi-editais-conteudo" tabindex="-1" aria-live="polite" aria-atomic="false">
        <div id="pi-editais-grid" class="pi-editais__grid" role="list">
            <p class="pi-carregando" aria-live="polite">
                <?php esc_html_e('Carregando editais…', 'participe-ibram'); ?>
            </p>
        </div>

        <nav class="pi-paginacao" aria-label="<?php esc_attr_e('Paginação de editais', 'participe-ibram'); ?>" id="pi-editais-paginacao" hidden>
            <button type="button" id="pi-editais-anterior" class="pi-btn" aria-label="<?php esc_attr_e('Página anterior', 'participe-ibram'); ?>">
                <?php esc_html_e('Anterior', 'participe-ibram'); ?>
            </button>
            <span id="pi-editais-pagina-info" aria-live="polite"></span>
            <button type="button" id="pi-editais-proximo" class="pi-btn" aria-label="<?php esc_attr_e('Próxima página', 'participe-ibram'); ?>">
                <?php esc_html_e('Próxima', 'participe-ibram'); ?>
            </button>
        </nav>
    </main>

    <div aria-live="assertive" aria-atomic="true" id="pi-editais-anuncio" class="sr-only"></div>
</div>

<script>
(function() {
    'use strict';
    var container = document.getElementById('pi-editais-lista');
    if (!container) return;

    var apiUrl    = container.dataset.apiUrl || '';
    var nonce     = container.dataset.nonce  || '';
    var grid      = document.getElementById('pi-editais-grid');
    var anuncio   = document.getElementById('pi-editais-anuncio');
    var paginacao = document.getElementById('pi-editais-paginacao');
    var form      = document.getElementById('pi-form-filtros-editais');
    var currentPage = 1;
    var currentFilters = {};

    function badgeClass(status) {
        var map = {
            'publicado': 'pi-badge--info',
            'inscricoes_abertas': 'pi-badge--sucesso',
            'em_habilitacao': 'pi-badge--aviso',
            'votacao_aberta': 'pi-badge--destaque',
            'encerrado': 'pi-badge--neutro'
        };
        return map[status] || 'pi-badge--neutro';
    }

    function badgeLabel(status) {
        var map = {
            'publicado': '<?php echo esc_js(__('Publicado', 'participe-ibram')); ?>',
            'inscricoes_abertas': '<?php echo esc_js(__('Inscrições Abertas', 'participe-ibram')); ?>',
            'em_habilitacao': '<?php echo esc_js(__('Em Habilitação', 'participe-ibram')); ?>',
            'votacao_aberta': '<?php echo esc_js(__('Votação Aberta', 'participe-ibram')); ?>',
            'encerrado': '<?php echo esc_js(__('Encerrado', 'participe-ibram')); ?>'
        };
        return map[status] || status;
    }

    function escHtml(str) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(String(str)));
        return d.innerHTML;
    }

    function formatDate(iso) {
        if (!iso) return '—';
        try {
            return new Date(iso).toLocaleDateString('pt-BR', {day:'2-digit', month:'2-digit', year:'numeric'});
        } catch(e) { return iso; }
    }

    function renderCard(edital) {
        return '<article class="pi-edital-card" role="listitem" aria-labelledby="pi-edital-' + escHtml(edital.id) + '-titulo">'
            + '<header class="pi-edital-card__header">'
            + '<h3 class="pi-edital-card__titulo" id="pi-edital-' + escHtml(edital.id) + '-titulo">'
            + escHtml(edital.titulo)
            + '</h3>'
            + '<span class="pi-badge ' + badgeClass(edital.status) + '" aria-label="<?php echo esc_js(__('Status', 'participe-ibram')); ?>: ' + escHtml(badgeLabel(edital.status)) + '">'
            + escHtml(badgeLabel(edital.status))
            + '</span>'
            + '</header>'
            + '<dl class="pi-edital-card__datas">'
            + '<dt><?php echo esc_js(__('Abertura', 'participe-ibram')); ?></dt><dd>' + escHtml(formatDate(edital.abertura)) + '</dd>'
            + '<dt><?php echo esc_js(__('Encerramento inscrições', 'participe-ibram')); ?></dt><dd>' + escHtml(formatDate(edital.encerramento_inscricoes)) + '</dd>'
            + '<dt><?php echo esc_js(__('Categorias', 'participe-ibram')); ?></dt><dd>' + escHtml(edital.num_categorias) + '</dd>'
            + '</dl>'
            + '<footer class="pi-edital-card__rodape">'
            + '<a href="?pi_edital=' + encodeURIComponent(edital.id) + '" class="pi-btn pi-btn--secundario">'
            + '<?php echo esc_js(__('Ver detalhes', 'participe-ibram')); ?>'
            + '</a>'
            + '</footer>'
            + '</article>';
    }

    function announce(msg) {
        if (anuncio) { anuncio.textContent = ''; setTimeout(function() { anuncio.textContent = msg; }, 50); }
    }

    function buildQuery(page, filters) {
        var p = new URLSearchParams(filters);
        p.set('page', page);
        p.set('per_page', 12);
        return apiUrl + '/publico/editais?' + p.toString();
    }

    function load(page, filters) {
        grid.innerHTML = '<p class="pi-carregando" aria-live="polite"><?php echo esc_js(__('Carregando…', 'participe-ibram')); ?></p>';
        fetch(buildQuery(page, filters), {
            headers: { 'X-WP-Nonce': nonce, 'Accept': 'application/json' },
            credentials: 'same-origin'
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var items = data.items || [];
            if (items.length === 0) {
                grid.innerHTML = '<p class="pi-sem-resultados"><?php echo esc_js(__('Nenhum edital encontrado.', 'participe-ibram')); ?></p>';
                if (paginacao) paginacao.hidden = true;
                announce('<?php echo esc_js(__('Nenhum edital encontrado.', 'participe-ibram')); ?>');
                return;
            }
            grid.innerHTML = items.map(renderCard).join('');
            if (paginacao) {
                paginacao.hidden = false;
                var infoEl = document.getElementById('pi-editais-pagina-info');
                if (infoEl) infoEl.textContent = '<?php echo esc_js(__('Página', 'participe-ibram')); ?> ' + page;
                var total = data.total || items.length;
                var perPage = data.per_page || 12;
                document.getElementById('pi-editais-anterior').disabled = page <= 1;
                document.getElementById('pi-editais-proximo').disabled = (page * perPage) >= total;
            }
            announce('<?php echo esc_js(__('editais carregados', 'participe-ibram')); ?>');
        })
        .catch(function() {
            grid.innerHTML = '<p class="pi-erro" role="alert"><?php echo esc_js(__('Erro ao carregar editais. Tente novamente.', 'participe-ibram')); ?></p>';
        });
    }

    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            var fd = new FormData(form);
            currentFilters = {};
            fd.forEach(function(v, k) { if (v) currentFilters[k] = v; });
            currentPage = 1;
            load(currentPage, currentFilters);
        });
    }

    var btnAnterior = document.getElementById('pi-editais-anterior');
    var btnProximo  = document.getElementById('pi-editais-proximo');
    if (btnAnterior) {
        btnAnterior.addEventListener('click', function() {
            if (currentPage > 1) { currentPage--; load(currentPage, currentFilters); }
        });
    }
    if (btnProximo) {
        btnProximo.addEventListener('click', function() { currentPage++; load(currentPage, currentFilters); });
    }

    load(1, {});
}());
</script>
