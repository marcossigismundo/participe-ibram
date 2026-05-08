/**
 * recurso-decisao.js
 *
 * Confirma decisao de recurso (retratacao ou presidencia) via modal acessivel.
 * Mostra spinner durante submit, atualiza UI apos sucesso e anuncia em live
 * region (R4 secao 6 + WCAG 4.1.3).
 *
 * Reutiliza Modal.js da Wave 3 (sem duplicar codigo).
 *
 * @module admin/recurso-decisao
 */

import { Modal } from '../wizard/Modal.js';

const FORM_SELECTOR = '[data-pi-decisao-form]';
const CONFIRM_BUTTON = '[data-pi-confirm-decisao]';
const MODAL_CONFIRM_BTN = '[data-pi-modal-confirm]';
const MODAL_CANCEL_BTN = '[data-pi-modal-cancel]';
const LIVE_REGION = '[data-pi-live]';
const MODAL_ID = 'pi-confirm-decisao';

function announce(form, message) {
    const live = form.querySelector(LIVE_REGION);
    if (!live) return;
    // Trick: limpar e re-popular para forcar leitura.
    live.textContent = '';
    setTimeout(() => { live.textContent = message; }, 50);
}

function setLoading(form, loading) {
    const btn = form.querySelector(CONFIRM_BUTTON);
    if (!btn) return;
    if (loading) {
        btn.setAttribute('disabled', 'disabled');
        btn.dataset.originalLabel = btn.textContent;
        btn.textContent = btn.dataset.loadingLabel || 'Enviando...';
        btn.setAttribute('aria-busy', 'true');
    } else {
        btn.removeAttribute('disabled');
        btn.removeAttribute('aria-busy');
        if (btn.dataset.originalLabel) {
            btn.textContent = btn.dataset.originalLabel;
        }
    }
}

/**
 * Inicializa o handler de decisao em todos os formularios marcados.
 * Idempotente.
 *
 * @param {Document|HTMLElement} [scope] escopo de busca
 */
export function initRecursoDecisao(scope) {
    const root = scope || document;
    const modalEl = root.querySelector('#' + MODAL_ID);
    if (!modalEl) return;

    let modal = modalEl._piModalInstance;
    if (!modal) {
        modal = new Modal(modalEl);
        modalEl._piModalInstance = modal;
    }

    const forms = root.querySelectorAll(FORM_SELECTOR);
    forms.forEach((form) => {
        if (form._piDecisaoBound) return;
        form._piDecisaoBound = true;

        let trigger = null;

        form.addEventListener('submit', (e) => {
            // Intercepta o submit; submete somente apos confirmacao no modal.
            if (form._piConfirmed) {
                form._piConfirmed = false;
                setLoading(form, true);
                announce(form, 'Enviando decisao...');
                return; // Permite envio normal.
            }
            e.preventDefault();

            // Valida minimo do textarea antes de abrir o modal.
            const textarea = form.querySelector('textarea[name="decisao_md"]');
            if (textarea) {
                const v = (textarea.value || '').replace(/<[^>]*>/g, '').trim();
                if (v.length < 50) {
                    announce(form, 'A fundamentacao deve ter pelo menos 50 caracteres.');
                    textarea.focus();
                    return;
                }
            }
            const radios = form.querySelectorAll('input[type="radio"]:checked');
            if (radios.length === 0) {
                announce(form, 'Selecione uma das opcoes.');
                return;
            }

            trigger = e.submitter || form.querySelector(CONFIRM_BUTTON);
            modal.abrir(trigger);
        });

        // Bind dos botoes do modal (uma unica vez globalmente; multiplos forms
        // disputam o mesmo modal mas o ultimo trigger vence — basta um modal).
        const confirmBtn = modalEl.querySelector(MODAL_CONFIRM_BTN);
        const cancelBtn = modalEl.querySelector(MODAL_CANCEL_BTN);
        if (confirmBtn && !confirmBtn._piBound) {
            confirmBtn._piBound = true;
            confirmBtn.addEventListener('click', () => {
                // Encontra o form ativo (o que abriu o modal).
                const activeForm = document.querySelector(FORM_SELECTOR);
                if (!activeForm) return;
                modal.fechar();
                activeForm._piConfirmed = true;
                if (typeof activeForm.requestSubmit === 'function') {
                    activeForm.requestSubmit();
                } else {
                    activeForm.submit();
                }
            });
        }
        if (cancelBtn && !cancelBtn._piBound) {
            cancelBtn._piBound = true;
            cancelBtn.addEventListener('click', () => {
                modal.fechar();
                announce(form, 'Decisao cancelada.');
            });
        }
    });
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => initRecursoDecisao());
    } else {
        initRecursoDecisao();
    }
}
