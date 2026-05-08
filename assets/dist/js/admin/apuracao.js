/**
 * apuracao.js
 *
 * Admin page: apuração de votação.
 *  - Modal acessível (focus trap + ESC + restore focus + inert) para
 *    confirmação de "Apurar" e "Publicar".
 *  - Botão "Recalcular hash" via AJAX W6-A `pi_admin_votacao_recalcular_hash`
 *    com feedback visual e live region.
 *  - Copy-to-clipboard do hash de pré-apuração.
 *  - Exportar relatório de apuração (recebe URL do ZIP gerado).
 *
 * @module admin/apuracao
 */
(function () {
    'use strict';

    var FOCUSABLE_SELECTORS = [
        'a[href]', 'button:not([disabled])', 'input:not([disabled])',
        'select:not([disabled])', 'textarea:not([disabled])',
        '[tabindex]:not([tabindex="-1"])'
    ].join(',');

    function getData() {
        var node = document.getElementById('pi-apuracao-data');
        if (!node) { return null; }
        try { return JSON.parse(node.textContent || '{}'); } catch (e) { return null; }
    }

    function announce(msg) {
        var live = document.getElementById('pi-apuracao-live');
        if (!live) { return; }
        live.textContent = '';
        setTimeout(function () { live.textContent = msg; }, 30);
    }

    /* ===================== Modal ===================== */

    function openModal(modal, trigger) {
        if (!modal) { return; }
        modal.removeAttribute('hidden');
        modal._trigger = trigger || null;
        Array.prototype.slice.call(document.body.children).forEach(function (el) {
            if (el !== modal && el.id !== 'wpadminbar') { el.setAttribute('inert', ''); }
        });
        var focusable = modal.querySelectorAll(FOCUSABLE_SELECTORS);
        if (focusable.length > 0) { focusable[0].focus(); }
        modal._trapHandler = function (e) {
            if (e.key !== 'Tab') { return; }
            var focusables = Array.prototype.slice.call(modal.querySelectorAll(FOCUSABLE_SELECTORS));
            if (focusables.length === 0) { return; }
            var first = focusables[0];
            var last  = focusables[focusables.length - 1];
            if (e.shiftKey) {
                if (document.activeElement === first) { e.preventDefault(); last.focus(); }
            } else {
                if (document.activeElement === last)  { e.preventDefault(); first.focus(); }
            }
        };
        modal._escHandler = function (e) {
            if (e.key === 'Escape') { closeModal(modal); }
        };
        document.addEventListener('keydown', modal._trapHandler);
        document.addEventListener('keydown', modal._escHandler);
    }

    function closeModal(modal) {
        if (!modal) { return; }
        modal.setAttribute('hidden', '');
        Array.prototype.slice.call(document.body.children).forEach(function (el) {
            el.removeAttribute('inert');
        });
        if (modal._trapHandler) { document.removeEventListener('keydown', modal._trapHandler); }
        if (modal._escHandler)  { document.removeEventListener('keydown', modal._escHandler); }
        if (modal._trigger)     { modal._trigger.focus(); }
    }

    /* ===================== AJAX ===================== */

    function ajaxPost(url, data, callback) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', url, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4) {
                var resp = null;
                try { resp = JSON.parse(xhr.responseText); } catch (e) { /* ignore */ }
                callback(xhr.status, resp);
            }
        };
        var parts = [];
        Object.keys(data).forEach(function (k) {
            parts.push(encodeURIComponent(k) + '=' + encodeURIComponent(data[k]));
        });
        xhr.send(parts.join('&'));
    }

    function defaultErr(cfg, resp) {
        return (resp && resp.data && resp.data.message)
            || (cfg.i18n && cfg.i18n.erroGenerico)
            || 'Erro.';
    }

    /* ===================== Copy hash ===================== */

    function initCopy(cfg) {
        document.querySelectorAll('[data-pi-copy]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var txt = btn.getAttribute('data-pi-copy') || '';
                if (!txt) { return; }
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
        });
    }

    /* ===================== Recalcular hash ===================== */

    function initRecalcular(cfg) {
        var btn = document.querySelector('[data-pi-recalcular]');
        if (!btn) { return; }
        var resultEl = document.getElementById('pi-hash-result');

        btn.addEventListener('click', function () {
            btn.disabled = true;
            announce('Recalculando…');
            if (resultEl) { resultEl.textContent = '…'; }
            ajaxPost(cfg.ajaxUrl, {
                action:     'pi_admin_votacao_recalcular_hash',
                votacao_id: cfg.votacaoId,
                _wpnonce:   cfg.nonces.recalcular
            }, function (status, resp) {
                btn.disabled = false;
                if (resp && resp.success && resp.data) {
                    var atual    = (resp.data.hash_atual || '').slice(0, 16) + '…';
                    var registro = (resp.data.hash_registrado || '').slice(0, 16) + '…';
                    if (resp.data.match === true) {
                        announce((cfg.i18n && cfg.i18n.hashOk) || 'Integridade OK.');
                        if (resultEl) {
                            resultEl.textContent = '✓ ' + ((cfg.i18n && cfg.i18n.hashOk) || 'Integridade OK.')
                                + ' (' + atual + ' = ' + registro + ')';
                            resultEl.className = 'pi-hash-result pi-hash-result--ok';
                        }
                    } else {
                        announce((cfg.i18n && cfg.i18n.hashDiverge) || 'Hashes divergem.');
                        if (resultEl) {
                            resultEl.textContent = '⚠ ' + ((cfg.i18n && cfg.i18n.hashDiverge) || 'Hashes divergem.');
                            resultEl.className = 'pi-hash-result pi-hash-result--erro';
                        }
                    }
                } else {
                    var msg = defaultErr(cfg, resp);
                    announce(msg);
                    if (resultEl) {
                        resultEl.textContent = msg;
                        resultEl.className = 'pi-hash-result pi-hash-result--erro';
                    }
                }
            });
        });
    }

    /* ===================== Ações com modal ===================== */

    function doApurar(cfg) {
        announce('Apurando…');
        ajaxPost(cfg.ajaxUrl, {
            action:     'pi_admin_apurar_votacao',
            votacao_id: cfg.votacaoId,
            _wpnonce:   cfg.nonces.apurar
        }, function (status, resp) {
            if (resp && resp.success) {
                announce((cfg.i18n && cfg.i18n.sucessoApurar) || 'Apurado.');
                window.location.reload();
            } else {
                var msg = defaultErr(cfg, resp);
                announce(msg);
                window.alert(msg);
            }
        });
    }

    function doPublicar(cfg) {
        announce('Publicando…');
        ajaxPost(cfg.ajaxUrl, {
            action:     'pi_admin_publicar_resultado',
            votacao_id: cfg.votacaoId,
            _wpnonce:   cfg.nonces.publicar
        }, function (status, resp) {
            if (resp && resp.success) {
                announce((cfg.i18n && cfg.i18n.sucessoPublicar) || 'Publicado.');
                window.location.reload();
            } else {
                var msg = defaultErr(cfg, resp);
                announce(msg);
                window.alert(msg);
            }
        });
    }

    function doExportar(cfg) {
        announce('Gerando relatório…');
        ajaxPost(cfg.ajaxUrl, {
            action:     'pi_admin_exportar_apuracao',
            votacao_id: cfg.votacaoId,
            _wpnonce:   cfg.nonces.exportar
        }, function (status, resp) {
            if (resp && resp.success && resp.data && resp.data.url) {
                announce((cfg.i18n && cfg.i18n.sucessoExportar) || 'Relatório pronto.');
                window.location.href = resp.data.url;
            } else {
                var msg = defaultErr(cfg, resp);
                announce(msg);
                window.alert(msg);
            }
        });
    }

    /* ===================== Init ===================== */

    function init() {
        var root = document.querySelector('[data-pi-apuracao]');
        if (!root) { return; }

        var cfg = getData();
        if (!cfg) { return; }

        initCopy(cfg);
        initRecalcular(cfg);

        // Open modals.
        root.querySelectorAll('[data-pi-action]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var action = btn.getAttribute('data-pi-action');
                if (action === 'apurar') {
                    openModal(document.getElementById('pi-modal-apurar'), btn);
                } else if (action === 'publicar') {
                    openModal(document.getElementById('pi-modal-publicar'), btn);
                } else if (action === 'exportar') {
                    doExportar(cfg);
                }
            });
        });

        // Close.
        root.querySelectorAll('[data-pi-modal-close]').forEach(function (el) {
            el.addEventListener('click', function () {
                var modal = el.closest('[role="dialog"]');
                closeModal(modal);
            });
        });

        // Confirm.
        root.querySelectorAll('[data-pi-modal-confirm]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var action = btn.getAttribute('data-pi-modal-confirm');
                var modal  = btn.closest('[role="dialog"]');
                closeModal(modal);
                if (action === 'apurar')   { doApurar(cfg); }
                if (action === 'publicar') { doPublicar(cfg); }
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
