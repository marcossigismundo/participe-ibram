/**
 * edital-detalhes.js
 *
 * Admin page: detalhes do edital.
 *  - Tabs ARIA com navegação por setas (WCAG 2.1 AA, R4 §6).
 *  - Modais de confirmação (focus trap + ESC + restore focus + inert).
 *  - Handlers AJAX para "Publicar Edital" e "Abrir Inscrições".
 *
 * @module admin/edital-detalhes
 */
(function () {
    'use strict';

    var FOCUSABLE_SELECTORS = [
        'a[href]', 'button:not([disabled])', 'input:not([disabled])',
        'select:not([disabled])', 'textarea:not([disabled])',
        '[tabindex]:not([tabindex="-1"])'
    ].join(',');

    /* ===================== Data ===================== */

    function getData() {
        var node = document.getElementById('pi-edital-detalhes-data');
        if (!node) { return null; }
        try { return JSON.parse(node.textContent || '{}'); } catch (e) { return null; }
    }

    function announce(msg) {
        var live = document.getElementById('pi-admin-detalhes-live');
        if (!live) { return; }
        live.textContent = '';
        setTimeout(function () { live.textContent = msg; }, 30);
    }

    /* ===================== Tabs ===================== */

    function initTabs(root) {
        var tabsRoot = root.querySelector('[data-pi-tabs]');
        if (!tabsRoot) { return; }

        var tabs   = Array.prototype.slice.call(tabsRoot.querySelectorAll('[role="tab"]'));
        var panels = Array.prototype.slice.call(tabsRoot.querySelectorAll('[role="tabpanel"]'));

        function activate(idx) {
            tabs.forEach(function (tab, i) {
                var active = i === idx;
                tab.setAttribute('aria-selected', active ? 'true' : 'false');
                tab.setAttribute('tabindex', active ? '0' : '-1');
                if (active) { tab.focus(); }
            });
            panels.forEach(function (panel, i) {
                if (i === idx) {
                    panel.removeAttribute('hidden');
                } else {
                    panel.setAttribute('hidden', '');
                }
            });
        }

        tabs.forEach(function (tab, i) {
            tab.addEventListener('click', function () { activate(i); });
            tab.addEventListener('keydown', function (e) {
                if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
                    e.preventDefault();
                    activate((i + 1) % tabs.length);
                } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
                    e.preventDefault();
                    activate((i - 1 + tabs.length) % tabs.length);
                } else if (e.key === 'Home') {
                    e.preventDefault();
                    activate(0);
                } else if (e.key === 'End') {
                    e.preventDefault();
                    activate(tabs.length - 1);
                }
            });
        });
    }

    /* ===================== Modal ===================== */

    function openModal(modal) {
        if (!modal) { return; }
        modal.removeAttribute('hidden');
        // inert on siblings (R4 §6).
        Array.prototype.slice.call(document.body.children).forEach(function (el) {
            if (el !== modal && el.id !== 'wpadminbar') {
                el.setAttribute('inert', '');
            }
        });
        // Focus first focusable inside modal.
        var focusable = modal.querySelectorAll(FOCUSABLE_SELECTORS);
        if (focusable.length > 0) { focusable[0].focus(); }
        // Focus trap.
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
        if (modal._triggerEl)   { modal._triggerEl.focus(); }
    }

    /* ===================== AJAX helpers ===================== */

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

    /* ===================== Init ===================== */

    function init() {
        var root = document.querySelector('[data-pi-detalhes]');
        if (!root) { return; }

        var config = getData();
        if (!config) { return; }

        initTabs(root);

        /* ---- Modal open buttons ---- */
        root.querySelectorAll('[data-pi-action]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var action  = btn.getAttribute('data-pi-action');
                var modalId = btn.getAttribute('aria-controls');
                var modal   = document.getElementById(modalId);
                if (modal) {
                    modal._triggerEl = btn;
                    openModal(modal);
                }
            });
        });

        /* ---- Modal close buttons ---- */
        root.querySelectorAll('[data-pi-modal-close]').forEach(function (el) {
            el.addEventListener('click', function () {
                var modal = el.closest('[role="dialog"]');
                closeModal(modal);
            });
        });

        /* ---- Modal confirm buttons ---- */
        root.querySelectorAll('[data-pi-modal-confirm]').forEach(function (confirmBtn) {
            confirmBtn.addEventListener('click', function () {
                var action = confirmBtn.getAttribute('data-pi-modal-confirm');
                var modal  = confirmBtn.closest('[role="dialog"]');
                closeModal(modal);
                if (action === 'publicar') {
                    doPublicar(config);
                } else if (action === 'abrir') {
                    doAbrirInscricoes(config);
                }
            });
        });
    }

    /* ===================== AJAX actions ===================== */

    function doPublicar(config) {
        announce((config.i18n && config.i18n.confirmarPublicar) || 'Publicando edital...');
        ajaxPost(config.ajaxUrl, {
            action:     'pi_admin_publicar_edital',
            edital_id:  config.editalId,
            _wpnonce:   config.nonces.publicar,
        }, function (status, resp) {
            if (resp && resp.success) {
                announce((config.i18n && config.i18n.sucessoPublicar) || 'Edital publicado.');
                window.location.reload();
            } else {
                var msg = (resp && resp.data && resp.data.message) || (config.i18n && config.i18n.erroGenerico) || 'Erro.';
                announce(msg);
                alert(msg);
            }
        });
    }

    function doAbrirInscricoes(config) {
        announce((config.i18n && config.i18n.confirmarAbrir) || 'Abrindo inscrições...');
        ajaxPost(config.ajaxUrl, {
            action:    'pi_admin_abrir_inscricoes',
            edital_id: config.editalId,
            _wpnonce:  config.nonces.abrir,
        }, function (status, resp) {
            if (resp && resp.success) {
                announce((config.i18n && config.i18n.sucessoAbrir) || 'Inscrições abertas.');
                window.location.reload();
            } else {
                var msg = (resp && resp.data && resp.data.message) || (config.i18n && config.i18n.erroGenerico) || 'Erro.';
                announce(msg);
                alert(msg);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
