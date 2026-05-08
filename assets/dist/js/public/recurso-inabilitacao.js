/**
 * recurso-inabilitacao.js (dist) — Wave 5-C.
 *
 * Formulário público para protocolar recurso de inabilitação:
 *  - Countdown do prazo recursal.
 *  - Contador de caracteres no textarea.
 *  - Modal de confirmação ARIA antes do submit AJAX.
 *  - Live region para feedback.
 */

import { Modal } from '../wizard/Modal.js';

const FORM_SELECTOR   = '[data-pi-recurso-inabilitacao]';
const MODAL_SELECTOR  = '#pi-modal-confirmar-recurso';
const LIVE_SELECTOR   = '#pi-recurso-public-live';
const COUNTER_ID      = 'pi-char-counter';
const TEXTAREA_ID     = 'pi-fundamentacao-md';

// =========================================================================
// Countdown de prazo
// =========================================================================

function initCountdown(root) {
    const el = root.querySelector('[data-pi-prazo-countdown]');
    if (!el) return;
    const iso = el.dataset.prazoIso;
    if (!iso) return;

    const target = new Date(iso);

    function render() {
        const now  = new Date();
        const diff = target - now;
        if (diff <= 0) {
            el.textContent = '';
            return;
        }
        const totalSeconds = Math.floor(diff / 1000);
        const days    = Math.floor(totalSeconds / 86400);
        const hours   = Math.floor((totalSeconds % 86400) / 3600);
        const minutes = Math.floor((totalSeconds % 3600) / 60);
        const secs    = totalSeconds % 60;

        if (days > 0) {
            el.textContent = `(${days}d ${hours}h restantes)`;
        } else {
            el.textContent = `(${hours}h ${minutes}m ${secs}s restantes)`;
        }
    }
    render();
    setInterval(render, 1000);
}

// =========================================================================
// Contador de caracteres
// =========================================================================

function initCharCounter(root) {
    const textarea = root.querySelector('#' + TEXTAREA_ID);
    const counter  = root.querySelector('#' + COUNTER_ID);
    if (!textarea || !counter) return;

    textarea.addEventListener('input', () => {
        const len = textarea.value.replace(/<[^>]*>/g, '').trim().length;
        const msg = `${len} caracteres digitados (mínimo 50).`;
        counter.textContent = msg;
        if (len < 50) {
            textarea.setAttribute('aria-invalid', 'true');
        } else {
            textarea.removeAttribute('aria-invalid');
        }
    });
}

// =========================================================================
// Announce helper
// =========================================================================

function announce(root, message) {
    const live = root.querySelector(LIVE_SELECTOR) || document.querySelector(LIVE_SELECTOR);
    if (!live) return;
    live.textContent = '';
    setTimeout(() => { live.textContent = message; }, 50);
}

// =========================================================================
// Form com modal de confirmação + AJAX
// =========================================================================

function initForm(root) {
    const form     = root.querySelector(FORM_SELECTOR);
    const modalEl  = root.querySelector(MODAL_SELECTOR);
    if (!form || !modalEl) return;

    let modal = modalEl._piModalInstance;
    if (!modal) {
        modal = new Modal(modalEl);
        modalEl._piModalInstance = modal;
    }

    // Fechar modal
    modalEl.querySelectorAll('[data-pi-modal-close]').forEach((btn) => {
        btn.addEventListener('click', () => {
            modal.fechar();
            announce(root, 'Envio cancelado.');
        });
    });

    // Submit abre modal de confirmação (se válido)
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const textarea = form.querySelector('textarea[name="fundamentacao_md"]');
        const text     = textarea ? textarea.value.replace(/<[^>]*>/g, '').trim() : '';
        if (text.length < 50) {
            announce(root, 'A fundamentação deve ter pelo menos 50 caracteres.');
            if (textarea) textarea.focus();
            return;
        }
        const submitBtn = form.querySelector('[type="submit"]');
        modal.abrir(submitBtn);
    });

    // Confirmação → AJAX
    const confirmBtn = modalEl.querySelector('[data-pi-modal-confirm-recurso]');
    if (confirmBtn) {
        confirmBtn.addEventListener('click', () => {
            modal.fechar();
            submitForm(form, root);
        });
    }
}

function submitForm(form, root) {
    const ajaxUrl    = form.dataset.ajaxUrl || (window.piPublicData && window.piPublicData.ajaxUrl) || '';
    const inscricaoId = form.dataset.inscricaoId;
    const nonce      = (form.querySelector('[name="_wpnonce"]') || {}).value || '';
    const fundamentacao = (form.querySelector('textarea[name="fundamentacao_md"]') || {}).value || '';
    const action     = (form.querySelector('[name="action"]') || {}).value || '';

    announce(root, 'Enviando recurso…');

    const submitBtn = form.querySelector('[type="submit"]');
    if (submitBtn) {
        submitBtn.setAttribute('disabled', 'disabled');
        submitBtn.setAttribute('aria-busy', 'true');
    }

    const body = new URLSearchParams({
        action: action,
        inscricao_id: inscricaoId,
        fundamentacao_md: fundamentacao,
        _wpnonce: nonce,
    });

    fetch(ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: body.toString(),
    })
        .then((r) => r.json())
        .then((json) => {
            if (submitBtn) {
                submitBtn.removeAttribute('disabled');
                submitBtn.removeAttribute('aria-busy');
            }
            if (json && json.success) {
                announce(root, 'Recurso protocolado com sucesso!');
                form.style.display = 'none';
            } else {
                const errMsg = (json && json.data && json.data.message) || 'Falha ao enviar o recurso.';
                announce(root, errMsg);
            }
        })
        .catch(() => {
            if (submitBtn) {
                submitBtn.removeAttribute('disabled');
                submitBtn.removeAttribute('aria-busy');
            }
            announce(root, 'Erro de rede. Verifique sua conexão e tente novamente.');
        });
}

// =========================================================================
// Bootstrap
// =========================================================================

export function initRecursoInabilitacao(scope) {
    const root = scope || document;
    initCountdown(root);
    initCharCounter(root);
    initForm(root);
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => initRecursoInabilitacao());
    } else {
        initRecursoInabilitacao();
    }
}
