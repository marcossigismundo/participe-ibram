/**
 * transparencia.js
 *
 * Public page: transparência da votação.
 *  - Carrega via REST `/pi/v1/publico/votacao/{id}/transparencia`.
 *  - Copy-to-clipboard do hash de pré-apuração.
 *  - Download do log público de auditoria como CSV (gera blob no browser
 *    a partir das páginas do endpoint `/audit-public`).
 *  - Sem PII: o JS APENAS exibe os campos da whitelist.
 *
 * @module public/transparencia
 */
(function () {
    'use strict';

    function getData() {
        var node = document.getElementById('pi-transparencia-data');
        if (!node) { return null; }
        try { return JSON.parse(node.textContent || '{}'); } catch (e) { return null; }
    }

    function announce(msg) {
        var live = document.getElementById('pi-transparencia-live');
        if (!live) { return; }
        live.textContent = '';
        setTimeout(function () { live.textContent = msg; }, 30);
    }

    /**
     * Whitelist defensiva no client — só renderiza estes campos.
     */
    var TRANSP_FIELDS = [
        'edital_titulo', 'status', 'abertura', 'encerramento',
        'total_votos', 'apurado_em', 'publicado_em',
        'hash_pre_apuracao', 'algoritmo'
    ];

    function fmtDate(iso) {
        if (!iso) { return '—'; }
        try {
            var d = new Date(iso);
            return d.toLocaleString();
        } catch (e) {
            return iso;
        }
    }

    function renderTransparencia(payload) {
        TRANSP_FIELDS.forEach(function (key) {
            var el = document.querySelector('[data-pi-field="' + key + '"]');
            if (!el) { return; }
            var v = payload[key];
            if (v === null || v === undefined || v === '') {
                el.textContent = '—';
                return;
            }
            if (key === 'abertura' || key === 'encerramento'
                || key === 'apurado_em' || key === 'publicado_em') {
                el.textContent = fmtDate(v);
            } else {
                el.textContent = String(v);
            }
        });
    }

    function loadTransparencia(cfg) {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', cfg.transpUrl, true);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) { return; }
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    var data = JSON.parse(xhr.responseText);
                    renderTransparencia(data || {});
                } catch (e) {
                    announce((cfg.i18n && cfg.i18n.erroCarregar) || 'Erro.');
                }
            } else {
                announce((cfg.i18n && cfg.i18n.erroCarregar) || 'Erro.');
            }
        };
        xhr.send();
    }

    function initCopy(cfg) {
        var btn = document.querySelector('[data-pi-copy-hash]');
        if (!btn) { return; }
        btn.addEventListener('click', function () {
            var el = document.querySelector('[data-pi-field="hash_pre_apuracao"]');
            var txt = el ? el.textContent.trim() : '';
            if (!txt || txt === '—') { return; }
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(txt).then(function () {
                    announce((cfg.i18n && cfg.i18n.hashCopiado) || 'Hash copiado.');
                });
            } else {
                var ta = document.createElement('textarea');
                ta.value = txt;
                document.body.appendChild(ta);
                ta.select();
                try { document.execCommand('copy'); } catch (e) { /* ignore */ }
                document.body.removeChild(ta);
                announce((cfg.i18n && cfg.i18n.hashCopiado) || 'Hash copiado.');
            }
        });
    }

    /* =========== Download de auditoria pública (CSV) =========== */

    function csvEscape(value) {
        var s = String(value === null || value === undefined ? '' : value);
        if (/[",\n\r]/.test(s)) {
            return '"' + s.replace(/"/g, '""') + '"';
        }
        return s;
    }

    function fetchAuditPage(url, callback) {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', url, true);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) { return; }
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    callback(null, JSON.parse(xhr.responseText));
                } catch (e) {
                    callback(e, null);
                }
            } else {
                callback(new Error('http ' + xhr.status), null);
            }
        };
        xhr.send();
    }

    function downloadAudit(cfg) {
        announce((cfg.i18n && cfg.i18n.baixandoAudit) || 'Preparando download…');

        var perPage = 500;
        var page    = 1;
        var rows    = [];

        function loadNext() {
            var url = cfg.auditUrl + '?page=' + page + '&per_page=' + perPage;
            fetchAuditPage(url, function (err, data) {
                if (err || !data || !Array.isArray(data.items)) {
                    announce((cfg.i18n && cfg.i18n.erroCarregar) || 'Erro.');
                    return;
                }
                data.items.forEach(function (item) {
                    rows.push([
                        item.ocorrido_em || '',
                        item.categoria_id != null ? item.categoria_id : '',
                        item.eleitor_hash || '',
                        item.candidato_inscricao_id != null ? item.candidato_inscricao_id : '',
                        item.ip_hash || ''
                    ]);
                });
                var total = data.total || 0;
                if (page * perPage < total) {
                    page++;
                    loadNext();
                    return;
                }
                emitCsv(rows, cfg);
            });
        }

        loadNext();
    }

    function emitCsv(rows, cfg) {
        var header = ['ocorrido_em', 'categoria_id', 'eleitor_hash', 'candidato_inscricao_id', 'ip_hash'];
        var lines  = [header.map(csvEscape).join(',')];
        rows.forEach(function (r) { lines.push(r.map(csvEscape).join(',')); });
        var csv  = '﻿' + lines.join('\n');
        var blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
        var url  = URL.createObjectURL(blob);
        var a    = document.createElement('a');
        a.href     = url;
        a.download = 'votacao-' + cfg.votacaoId + '-audit-public.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
        announce((cfg.i18n && cfg.i18n.auditPronto) || 'Pronto.');
    }

    function initDownload(cfg) {
        var btn = document.querySelector('[data-pi-baixar-audit]');
        if (!btn) { return; }
        btn.addEventListener('click', function () { downloadAudit(cfg); });
    }

    function init() {
        var root = document.querySelector('[data-pi-transparencia]');
        if (!root) { return; }
        var cfg = getData();
        if (!cfg) { return; }

        loadTransparencia(cfg);
        initCopy(cfg);
        initDownload(cfg);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
