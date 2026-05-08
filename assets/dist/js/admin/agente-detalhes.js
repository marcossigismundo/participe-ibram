/**
 * agente-detalhes.js
 *
 * Drives the admin "Detalhes do agente" page:
 *  - ARIA-correct tab switching with arrow-key navigation (R4 §6).
 *  - Modal open/close with focus trap + ESC + restore focus.
 *  - Confirm-and-submit handlers for assumir/iniciar/deferir/indeferir.
 *  - "Reveal sensitive" flow that calls AJAX, audits server-side and replaces
 *    masked placeholders inline. Updates aria-live region for SR feedback.
 *
 * @module admin/agente-detalhes
 */
(function () {
    'use strict';

    /* ---------------- helpers ---------------- */

    var FOCUSABLE = [
        'a[href]', 'button:not([disabled])', 'input:not([disabled])',
        'select:not([disabled])', 'textarea:not([disabled])',
        '[tabindex]:not([tabindex="-1"])'
    ].join(',');

    function getRootData() {
        var node = document.getElementById('pi-admin-detalhes-data');
        if (!node) {
            return null;
        }
        try {
            return JSON.parse(node.textContent || '{}');
        } catch (e) {
            return null;
        }
    }

    function announce(msg) {
        var live = document.getElementById('pi-admin-detalhes-live');
        if (live) {
            live.textContent = '';
            // Force a tick so SR re-reads even when text is unchanged.
            setTimeout(function () { live.textContent = msg; }, 30);
        }
    }

    /* ---------------- Tabs (ARIA) ---------------- */

    function initTabs(root) {
        var tabsRoot = root.querySelector('[data-pi-tabs]');
        if (!tabsRoot) return;

        var tabs   = Array.prototype.slice.call(tabsRoot.querySelectorAll('[role="tab"]'));
        var panels = Array.prototype.slice.call(tabsRoot.querySelectorAll('[role="tabpanel"]'));

        function activate(idx) {
            tabs.forEach(function (tab, i) {
                var active = i === idx;
                tab.setAttribute('aria-selected', active ? 'true' : 'false');
                tab.setAttribute('tabindex', active ? '0' : '-1');
                if (active) {
                    tab.focus();
                }
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

    /* ---------------- Modals ---------------- */

    var openStack = [];

    function trapTab(modal, e) {
        var focusables = Array.prototype.slice.call(modal.querySelectorAll(FOCUSABLE))
            .filter(function (el) { return el.offsetParent !== null; });
        if (focusables.length === 0) return;
        var first = focusables[0];
        var last  = focusables[focusables.length - 1];
        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
        }
    }

    function setBackgroundInert(modal, inert) {
        Array.prototype.forEach.call(document.body.children, function (child) {
            if (child === modal || child.contains(modal)) return;
            if (inert) {
                child.setAttribute('inert', '');
                child.setAttribute('aria-hidden', 'true');
            } else if (openStack.length === 0) {
                child.removeAttribute('inert');
                child.removeAttribute('aria-hidden');
            }
        });
    }

    function openModal(modal, trigger) {
        if (!modal) return;
        modal._trigger = trigger || document.activeElement;
        modal.removeAttribute('hidden');
        setBackgroundInert(modal, true);
        openStack.push(modal);
        var firstFocusable = modal.querySelector(FOCUSABLE);
        if (firstFocusable) firstFocusable.focus();
    }

    function closeModal(modal) {
        if (!modal) return;
        modal.setAttribute('hidden', '');
        var idx = openStack.indexOf(modal);
        if (idx !== -1) openStack.splice(idx, 1);
        setBackgroundInert(modal, false);
        if (modal._trigger && typeof modal._trigger.focus === 'function') {
            modal._trigger.focus();
        }
    }

    function installModalKeyboardHandler() {
        document.addEventListener('keydown', function (e) {
            var top = openStack[openStack.length - 1];
            if (!top) return;
            if (e.key === 'Escape') {
                e.preventDefault();
                closeModal(top);
            } else if (e.key === 'Tab') {
                trapTab(top, e);
            }
        });
    }

    function bindModalCloseButtons(root) {
        Array.prototype.forEach.call(root.querySelectorAll('[data-pi-modal-close]'), function (btn) {
            btn.addEventListener('click', function () {
                var modal = btn.closest('.pi-modal');
                closeModal(modal);
            });
        });
        // Click backdrop to close
        Array.prototype.forEach.call(root.querySelectorAll('.pi-modal'), function (modal) {
            modal.addEventListener('click', function (e) {
                if (e.target === modal) {
                    closeModal(modal);
                }
            });
        });
    }

    /* ---------------- AJAX ---------------- */

    function postJson(url, body, nonce) {
        var headers = {
            'Content-Type': 'application/json; charset=utf-8',
            'Accept': 'application/json'
        };
        // Nonce vai como _wpnonce na URL (compatível com check_ajax_referer).
        var qs = nonce ? (url.indexOf('?') >= 0 ? '&' : '?') + '_wpnonce=' + encodeURIComponent(nonce) : '';
        return fetch(url + qs, {
            method: 'POST',
            credentials: 'same-origin',
            headers: headers,
            body: JSON.stringify(body || {})
        }).then(function (res) {
            return res.json().then(function (json) {
                return { ok: res.ok, status: res.status, body: json };
            });
        });
    }

    function buildAjaxUrl(data, action) {
        return data.ajaxUrl + '?action=' + encodeURIComponent(action);
    }

    /* ---------------- Action handlers ---------------- */

    function bindActions(root, data) {
        // Open buttons
        var assumirBtn   = root.querySelector('[data-pi-action="assumir"]');
        var deferirBtn   = root.querySelector('[data-pi-action="abrir-deferir"]');
        var indeferirBtn = root.querySelector('[data-pi-action="abrir-indeferir"]');
        var revelarBtn   = root.querySelector('[data-pi-action="revelar-sensivel"]');

        if (assumirBtn) {
            assumirBtn.addEventListener('click', function () {
                openModal(document.getElementById('pi-modal-confirm-assumir'), assumirBtn);
            });
        }
        if (deferirBtn) {
            deferirBtn.addEventListener('click', function () {
                openModal(document.getElementById('pi-modal-deferir'), deferirBtn);
            });
        }
        if (indeferirBtn) {
            indeferirBtn.addEventListener('click', function () {
                openModal(document.getElementById('pi-modal-indeferir'), indeferirBtn);
            });
        }
        if (revelarBtn) {
            revelarBtn.addEventListener('click', function () {
                openModal(document.getElementById('pi-modal-revelar'), revelarBtn);
            });
        }

        // Confirm buttons
        var confirms = root.querySelectorAll('[data-pi-confirm]');
        Array.prototype.forEach.call(confirms, function (btn) {
            btn.addEventListener('click', function () {
                var which = btn.getAttribute('data-pi-confirm');
                if (which === 'assumir') {
                    handleAssumir(root, data, btn);
                } else if (which === 'deferir') {
                    handleDeferir(root, data, btn);
                } else if (which === 'indeferir') {
                    handleIndeferir(root, data, btn);
                } else if (which === 'revelar') {
                    handleRevelar(root, data, btn);
                }
            });
        });
    }

    function handleAssumir(root, data, btn) {
        btn.disabled = true;
        postJson(buildAjaxUrl(data, 'pi_admin_assumir_analise'),
                 { agente_id: data.agenteId },
                 data.nonces.assumir)
            .then(function (res) {
                btn.disabled = false;
                if (res.ok && res.body && res.body.success) {
                    announce(data.i18n.sucessoAssumir);
                    setTimeout(function () { window.location.reload(); }, 600);
                } else {
                    var msg = (res.body && res.body.data && res.body.data.message) || data.i18n.erroGenerico;
                    announce(msg);
                }
                closeModal(document.getElementById('pi-modal-confirm-assumir'));
            }).catch(function () {
                btn.disabled = false;
                announce(data.i18n.erroGenerico);
            });
    }

    function handleDeferir(root, data, btn) {
        var parecer = (root.querySelector('#pi-deferir-parecer') || {}).value || '';
        if (!parecer.trim()) {
            announce(data.i18n.erroGenerico);
            return;
        }
        btn.disabled = true;
        postJson(buildAjaxUrl(data, 'pi_admin_deferir_cadastro'),
                 { agente_id: data.agenteId, parecer_md: parecer },
                 data.nonces.deferir)
            .then(function (res) {
                btn.disabled = false;
                closeModal(document.getElementById('pi-modal-deferir'));
                if (res.ok && res.body && res.body.success) {
                    announce(data.i18n.sucessoDeferir);
                    setTimeout(function () { window.location.reload(); }, 800);
                } else {
                    var msg = (res.body && res.body.data && res.body.data.message) || data.i18n.erroGenerico;
                    announce(msg);
                }
            }).catch(function () {
                btn.disabled = false;
                announce(data.i18n.erroGenerico);
            });
    }

    function handleIndeferir(root, data, btn) {
        var parecer = (root.querySelector('#pi-indeferir-parecer') || {}).value || '';
        var fund    = (root.querySelector('#pi-indeferir-fundamentacao') || {}).value || '';
        if (!parecer.trim() || !fund.trim()) {
            announce(data.i18n.erroGenerico);
            return;
        }
        btn.disabled = true;
        postJson(buildAjaxUrl(data, 'pi_admin_indeferir_cadastro'),
                 { agente_id: data.agenteId, parecer_md: parecer, fundamentacao_md: fund },
                 data.nonces.indeferir)
            .then(function (res) {
                btn.disabled = false;
                closeModal(document.getElementById('pi-modal-indeferir'));
                if (res.ok && res.body && res.body.success) {
                    announce(data.i18n.sucessoIndeferir);
                    setTimeout(function () { window.location.reload(); }, 800);
                } else {
                    var msg = (res.body && res.body.data && res.body.data.message) || data.i18n.erroGenerico;
                    announce(msg);
                }
            }).catch(function () {
                btn.disabled = false;
                announce(data.i18n.erroGenerico);
            });
    }

    function handleRevelar(root, data, btn) {
        // Discover the masked sensitive spans in the document panel.
        var spans = Array.prototype.slice.call(root.querySelectorAll('[data-pi-sensitive]'));
        var fields = spans.map(function (el) { return el.getAttribute('data-pi-sensitive'); });
        if (fields.length === 0) {
            closeModal(document.getElementById('pi-modal-revelar'));
            return;
        }
        btn.disabled = true;
        postJson(buildAjaxUrl(data, 'pi_admin_revelar_sensivel'),
                 { agente_id: data.agenteId, campos: fields },
                 data.nonces.revelar)
            .then(function (res) {
                btn.disabled = false;
                closeModal(document.getElementById('pi-modal-revelar'));
                if (res.ok && res.body && res.body.success && res.body.data && res.body.data.campos) {
                    var revealed = res.body.data.campos;
                    spans.forEach(function (el) {
                        var key = el.getAttribute('data-pi-sensitive');
                        if (Object.prototype.hasOwnProperty.call(revealed, key) && revealed[key] !== null) {
                            el.textContent = revealed[key];
                            el.setAttribute('data-pi-revealed', 'true');
                        }
                    });
                    var revealBtn = root.querySelector('[data-pi-action="revelar-sensivel"]');
                    if (revealBtn) revealBtn.setAttribute('aria-pressed', 'true');
                    announce('Dados sensíveis revelados.');
                } else {
                    var msg = (res.body && res.body.data && res.body.data.message) || data.i18n.erroGenerico;
                    announce(msg);
                }
            }).catch(function () {
                btn.disabled = false;
                announce(data.i18n.erroGenerico);
            });
    }

    /* ---------------- Init ---------------- */

    function init() {
        var root = document.querySelector('[data-pi-detalhes]');
        if (!root) return;
        var data = getRootData();
        if (!data) return;

        installModalKeyboardHandler();
        initTabs(root);
        bindModalCloseButtons(root);
        bindActions(root, data);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
