<?php
/**
 * Template: resultados públicos do edital (inscritos habilitados + eleitos).
 *
 * Shortcode [pi_edital_resultados id="..."].
 * Exibe APENAS: numero_registro, nome_publico, categoria (whitelist PII-free).
 * NUNCA exibe CPF, email, telefone, raça, gênero, deficiência.
 *
 * Variáveis esperadas:
 *  - $edital_id  (int)    ID do edital.
 *  - $api_url    (string) URL base da API REST.
 *  - $rest_nonce (string) Nonce WP REST.
 *
 * @package ParticipeIbram
 */

if (! defined('ABSPATH')) {
    exit;
}

$edital_id  = isset($edital_id)  ? (int) $edital_id  : 0;
$api_url    = isset($api_url)    ? (string) $api_url   : '';
$rest_nonce = isset($rest_nonce) ? (string) $rest_nonce : '';

if ($edital_id <= 0) {
    return;
}
?>
<div
    class="participe-ibram-scope"
    id="pi-edital-resultados"
    data-api-url="<?php echo esc_attr($api_url); ?>"
    data-nonce="<?php echo esc_attr($rest_nonce); ?>"
    data-edital-id="<?php echo esc_attr((string) $edital_id); ?>"
    role="region"
    aria-label="<?php esc_attr_e('Resultados do edital', 'participe-ibram'); ?>"
>
    <a class="pi-skip-link" href="#pi-resultados-conteudo"><?php esc_html_e('Pular para os resultados', 'participe-ibram'); ?></a>

    <main id="pi-resultados-conteudo" tabindex="-1">
        <h2><?php esc_html_e('Inscritos Habilitados', 'participe-ibram'); ?></h2>

        <div id="pi-resultados-carregando" class="pi-carregando" aria-live="polite">
            <?php esc_html_e('Carregando resultados…', 'participe-ibram'); ?>
        </div>

        <div id="pi-resultados-conteudo-interno" hidden>
            <p class="pi-aviso pi-aviso--lgpd">
                <?php esc_html_e('Lista de candidatos habilitados conforme publicação oficial. Apenas nome público e número de registro são exibidos.', 'participe-ibram'); ?>
            </p>

            <table
                id="pi-resultados-tabela"
                class="pi-tabela pi-tabela--resultados"
                aria-label="<?php esc_attr_e('Lista de inscritos habilitados', 'participe-ibram'); ?>"
            >
                <caption class="sr-only"><?php esc_html_e('Candidatos habilitados por categoria', 'participe-ibram'); ?></caption>
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e('N.º Registro', 'participe-ibram'); ?></th>
                        <th scope="col"><?php esc_html_e('Nome Público', 'participe-ibram'); ?></th>
                        <th scope="col"><?php esc_html_e('Categoria', 'participe-ibram'); ?></th>
                    </tr>
                </thead>
                <tbody id="pi-resultados-tbody"></tbody>
            </table>

            <p id="pi-resultados-vazio" class="pi-sem-resultados" hidden>
                <?php esc_html_e('Nenhum candidato habilitado encontrado.', 'participe-ibram'); ?>
            </p>
        </div>

        <div id="pi-resultados-erro" class="pi-erro" role="alert" hidden></div>
    </main>

    <div aria-live="assertive" aria-atomic="true" id="pi-resultados-anuncio" class="sr-only"></div>
</div>

<script>
(function() {
    'use strict';
    var container = document.getElementById('pi-edital-resultados');
    if (!container) return;

    var apiUrl   = container.dataset.apiUrl   || '';
    var nonce    = container.dataset.nonce    || '';
    var editalId = container.dataset.editalId || '';

    function escHtml(str) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(String(str || '')));
        return d.innerHTML;
    }

    fetch(apiUrl + '/publico/edital/' + encodeURIComponent(editalId) + '/inscritos-habilitados', {
        headers: {'Accept': 'application/json'},
        credentials: 'same-origin'
    })
    .then(function(r) {
        if (!r.ok) {
            if (r.status === 400) {
                // Edital ainda não na fase de votação.
                return r.json().then(function(data) {
                    document.getElementById('pi-resultados-carregando').hidden = true;
                    var errEl = document.getElementById('pi-resultados-erro');
                    if (errEl) {
                        errEl.textContent = (data && data.message)
                            ? data.message
                            : '<?php echo esc_js(__('Resultados ainda não disponíveis.','participe-ibram')); ?>';
                        errEl.hidden = false;
                    }
                    throw new Error('nao_votacao');
                });
            }
            throw new Error('HTTP ' + r.status);
        }
        return r.json();
    })
    .then(function(data) {
        document.getElementById('pi-resultados-carregando').hidden = true;
        document.getElementById('pi-resultados-conteudo-interno').hidden = false;

        var items = (data && data.items) ? data.items : [];
        var tbody = document.getElementById('pi-resultados-tbody');
        var vazio = document.getElementById('pi-resultados-vazio');
        var tabela = document.getElementById('pi-resultados-tabela');

        if (items.length === 0) {
            if (tabela) tabela.hidden = true;
            if (vazio) vazio.hidden = false;
        } else {
            if (vazio) vazio.hidden = true;
            // Whitelist: renderiza apenas numero_registro, nome_publico, categoria_id.
            // NUNCA acessa item.cpf, item.email, etc.
            var html = '';
            items.forEach(function(item) {
                html += '<tr>'
                    + '<td>' + escHtml(item.numero_registro) + '</td>'
                    + '<td>' + escHtml(item.nome_publico) + '</td>'
                    + '<td>' + escHtml(item.categoria_id) + '</td>'
                    + '</tr>';
            });
            if (tbody) tbody.innerHTML = html;
        }

        var anuncio = document.getElementById('pi-resultados-anuncio');
        if (anuncio) {
            anuncio.textContent = items.length > 0
                ? items.length + ' <?php echo esc_js(__('candidatos habilitados carregados','participe-ibram')); ?>'
                : '<?php echo esc_js(__('Nenhum candidato habilitado encontrado','participe-ibram')); ?>';
        }
    })
    .catch(function(e) {
        if (e && e.message === 'nao_votacao') return;
        document.getElementById('pi-resultados-carregando').hidden = true;
        var errEl = document.getElementById('pi-resultados-erro');
        if (errEl) {
            errEl.textContent = '<?php echo esc_js(__('Erro ao carregar resultados. Tente novamente.','participe-ibram')); ?>';
            errEl.hidden = false;
        }
    });
}());
</script>
