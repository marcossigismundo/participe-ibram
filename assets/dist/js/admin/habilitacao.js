/**
 * habilitacao.js (dist) — Wave 5-C.
 *
 * Modais de confirmação ARIA para habilitar/inabilitar inscrição.
 * Tabs ARIA para a tela de detalhes da inscrição.
 * Live region para feedback de ações AJAX.
 *
 * Reutiliza Modal.js (Wave 3).
 */

import { Modal } from '../wizard/Modal.js';

const SCOPE_SELECTOR  = '[data-pi-inscricao-detalhes]';
const TABS_SELECTOR   = '[data-pi-tabs]';
const LIVE_SELECTOR   = '#pi-inscricao-live';
const DATA_ID         = 'pi-inscricao-detalhes-data';

// =========================================================================
// Helpers
// =========================================================================

function announce(root, message) {
    const live = root.querySelector(LIVE_SELECTOR) || document.querySelector(LIVE_SELECTOR);
    if (!live) return;
    live.textContent = '';
    setTimeout(() => { live.textContent = message; }, 50);
}

function readConfig(root) {
    const el = root.querySelector('#' + DATA_ID) || document.getElementById(DATA_ID);
    if (!el) return null;
    try {
        return JSON.parse(el.textContent || '{}');
    } catch (_) {
        return null;
    }
}

// =========================================================================
// ARIA Tabs
// =========================================================================

function initTabs(container) {
    const tablist = container.querySelector('[role="tablist"]');
    if (!tablist) return;
    const tabs   = Array.from(tablist.querySelectorAll('[role="tab"]'));
    const panels = tabs.map((t) => document.getElementById(t.getAttribute('aria-controls') || ''));

    function activateTab(tab) {
        tabs.forEach((t, i) => {
            const selected = t === tab;
            t.setAttribute('aria-selected', selected ? 'true' : 'false');
            t.tabIndex = selected ? 0 : -1;
            const panel = panels[i];
            if (panel) {
                if (selected) {
                    panel.removeAttribute('hidden');
                } else {
                    panel.setAttribute('hidden', '');
                }
            }
        });
        tab.focus();
    }

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => activateTab(tab));
        tab.addEventListener('keydown', (e) => {
            const idx = tabs.indexOf(tab);
            if (e.key === 'ArrowRight') {
                e.preventDefault();
                activateTab(tabs[(idx + 1) % tabs.length]);
            } else if (e.key === 'ArrowLeft') {
                e.preventDefault();
                activateTab(tabs[(idx - 1 + tabs.length) % tabs.length]);
            } else if (e.key === 'Home') {
                e.preventDefault();
                activateTab(tabs[0]);
            } else if (e.key === 'End') {
                e.preventDefault();
                activateTab(tabs[tabs.length - 1]);
            }
        });
    });
}

// =========================================================================
// Modal actions (habilitar / inabilitar)
// =========================================================================

function initHabilitacaoModals(root, config) {
    const modalHabilitarEl  = root.querySelector('#pi-modal-habilitar');
    const modalInabilitarEl = root.querySelector('#pi-modal-inabilitar');

    let modalHabilitar  = null;
    let modalInabilitar = null;

    if (modalHabilitarEl) {
        modalHabilitar = modalHabilitarEl._piModalInstance || new Modal(modalHabilitarEl);
        modalHabilitarEl._piModalInstance = modalHabilitar;
    }
    if (modalInabilitarEl) {
        modalInabilitar = modalInabilitarEl._piModalInstance || new Modal(modalInabilitarEl);
        modalInabilitarEl._piModalInstance = modalInabilitar;
    }

    // Abrir modais
    root.querySelectorAll('[data-pi-action="abrir-habilitar"]').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (modalHabilitar) modalHabilitar.abrir(btn);
        });
    });
    root.querySelectorAll('[data-pi-action="abrir-inabilitar"]').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (modalInabilitar) modalInabilitar.abrir(btn);
        });
    });

    // Fechar modais
    [modalHabilitarEl, modalInabilitarEl].filter(Boolean).forEach((el) => {
        el.querySelectorAll('[data-pi-modal-close]').forEach((closeBtn) => {
            closeBtn.addEventListener('click', () => {
                const inst = el._piModalInstance;
                if (inst) inst.fechar();
            });
        });
    });

    // Confirmar habilitar
    if (modalHabilitarEl) {
        const confirmBtn = modalHabilitarEl.querySelector('[data-pi-confirm="habilitar"]');
        if (confirmBtn) {
            confirmBtn.addEventListener('click', () => {
                const inscricaoId = confirmBtn.dataset.inscricaoId;
                const nonce       = confirmBtn.dataset.nonce;
                if (modalHabilitar) modalHabilitar.fechar();
                sendDecisao('habilitar', inscricaoId, nonce, null, root, config);
            });
        }
    }

    // Confirmar inabilitar
    if (modalInabilitarEl) {
        const confirmBtn = modalInabilitarEl.querySelector('[data-pi-confirm="inabilitar"]');
        if (confirmBtn) {
            confirmBtn.addEventListener('click', () => {
                const textarea    = modalInabilitarEl.querySelector('textarea[name="motivo_inabilitacao_md"]');
                const motivoText  = textarea ? (textarea.value || '').replace(/<[^>]*>/g, '').trim() : '';
                if (motivoText.length < 50) {
                    announce(root, config && config.i18n ? config.i18n.motivoMinChars : 'O motivo deve ter pelo menos 50 caracteres.');
                    if (textarea) textarea.focus();
                    return;
                }
                const inscricaoId = confirmBtn.dataset.inscricaoId;
                const nonce       = confirmBtn.dataset.nonce;
                if (modalInabilitar) modalInabilitar.fechar();
                sendDecisao('inabilitar', inscricaoId, nonce, motivoText, root, config);
            });
        }
    }
}

function sendDecisao(decisao, inscricaoId, nonce, motivoMd, root, config) {
    if (!config || !config.ajaxUrl) return;
    const action = decisao === 'habilitar'
        ? 'pi_admin_habilitar_inscricao'
        : 'pi_admin_inabilitar_inscricao';

    announce(root, config.i18n && config.i18n[decisao === 'habilitar' ? 'habilitandoMsg' : 'inabilitandoMsg']
        ? config.i18n[decisao === 'habilitar' ? 'habilitandoMsg' : 'inabilitandoMsg']
        : 'Enviando…');

    const body = new URLSearchParams({
        action: action,
        inscricao_id: inscricaoId,
        _wpnonce: nonce,
    });
    if (motivoMd !== null) {
        body.append('motivo_inabilitacao_md', motivoMd);
    }

    fetch(config.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: body.toString(),
    })
        .then((r) => r.json())
        .then((json) => {
            if (json && json.success) {
                const msg = decisao === 'habilitar'
                    ? (config.i18n && config.i18n.sucessoHabilitar) || 'Inscrição habilitada com sucesso.'
                    : (config.i18n && config.i18n.sucessoInabilitar) || 'Inscrição inabilitada com sucesso.';
                announce(root, msg);
                // Recarrega para refletir o novo status.
                setTimeout(() => { window.location.reload(); }, 800);
            } else {
                const errMsg = (json && json.data && json.data.message)
                    || (config.i18n && config.i18n.erroGenerico)
                    || 'Falha ao processar a requisição.';
                announce(root, errMsg);
            }
        })
        .catch(() => {
            announce(root, (config && config.i18n && config.i18n.erroGenerico) || 'Erro de rede.');
        });
}

// =========================================================================
// Bootstrap
// =========================================================================

export function initHabilitacao(scope) {
    const roots = scope
        ? [scope]
        : Array.from(document.querySelectorAll(SCOPE_SELECTOR));

    roots.forEach((root) => {
        if (root._piHabilitacaoBound) return;
        root._piHabilitacaoBound = true;

        const config = readConfig(root);

        // Tabs
        root.querySelectorAll(TABS_SELECTOR).forEach(initTabs);

        // Modais
        initHabilitacaoModals(root, config);
    });
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => initHabilitacao());
    } else {
        initHabilitacao();
    }
}
