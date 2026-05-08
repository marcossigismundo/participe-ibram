/**
 * Modal.js
 *
 * Modal acessivel com focus trap, inert no fundo, ESC, restauracao de foco e
 * suporte a stack (modais empilhados). Baseado em R4 secao 6.
 *
 * @module wizard/Modal
 */

const FOCUSABLE_SELECTOR = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
].join(',');

/** Stack global de modais abertos (suporta empilhamento). */
const stack = [];

let keyHandlerInstalled = false;

function installGlobalKeyHandler() {
    if (keyHandlerInstalled) return;
    keyHandlerInstalled = true;
    document.addEventListener('keydown', (e) => {
        const top = stack[stack.length - 1];
        if (!top) return;
        if (e.key === 'Escape') {
            e.preventDefault();
            top.fechar();
            return;
        }
        if (e.key === 'Tab') {
            top._trapTab(e);
        }
    });
}

function applyInert(modalEl, inert) {
    // Aplica inert/aria-hidden em irmaos top-level do <body>
    const root = modalEl.ownerDocument.body;
    Array.prototype.forEach.call(root.children, (child) => {
        if (child === modalEl || child.contains(modalEl)) return;
        if (inert) {
            child.setAttribute('inert', '');
            child.setAttribute('aria-hidden', 'true');
        } else {
            // So remove se nao houver outro modal acima exigindo inertness
            if (stack.length === 0) {
                child.removeAttribute('inert');
                child.removeAttribute('aria-hidden');
            }
        }
    });
}

export class Modal {
    /**
     * @param {HTMLElement} modalEl  elemento com role="dialog" aria-modal="true"
     */
    constructor(modalEl) {
        if (!modalEl) {
            throw new Error('Modal requires an element');
        }
        this.modal = modalEl;
        this.dialog = modalEl.querySelector('.pi-modal__dialog') || modalEl;
        this.triggerAnterior = null;
        this._onClick = this._onClick.bind(this);
        this.modal.addEventListener('click', this._onClick);
        installGlobalKeyHandler();
    }

    /**
     * @param {HTMLElement} [trigger] elemento que abriu (para devolver foco)
     */
    abrir(trigger) {
        if (stack.includes(this)) return;
        this.triggerAnterior = trigger || document.activeElement;
        this.modal.hidden = false;
        this.modal.removeAttribute('aria-hidden');
        this.modal.classList.add('is-open');

        applyInert(this.modal, true);
        stack.push(this);
        document.body.classList.add('pi-modal-open');

        // Foco no primeiro focavel ou no dialog
        const first = this._focusables()[0];
        if (first) {
            first.focus();
        } else {
            this.dialog.setAttribute('tabindex', '-1');
            this.dialog.focus();
        }
        this.modal.dispatchEvent(new CustomEvent('pi:modal:opened', { bubbles: true }));
    }

    fechar() {
        const idx = stack.indexOf(this);
        if (idx === -1) return;
        stack.splice(idx, 1);

        this.modal.hidden = true;
        this.modal.classList.remove('is-open');
        this.modal.setAttribute('aria-hidden', 'true');

        if (stack.length === 0) {
            applyInert(this.modal, false);
            document.body.classList.remove('pi-modal-open');
        }

        if (this.triggerAnterior && typeof this.triggerAnterior.focus === 'function') {
            this.triggerAnterior.focus();
        }
        this.modal.dispatchEvent(new CustomEvent('pi:modal:closed', { bubbles: true }));
    }

    destroy() {
        this.modal.removeEventListener('click', this._onClick);
        if (stack.includes(this)) {
            this.fechar();
        }
    }

    _onClick(e) {
        const target = e.target;
        if (target.closest('[data-pi-modal-close]')) {
            e.preventDefault();
            this.fechar();
            return;
        }
        // Click em overlay (fora do dialog) fecha
        if (target === this.modal || target.classList.contains('pi-modal__overlay')) {
            this.fechar();
        }
    }

    _focusables() {
        return Array.prototype.filter.call(
            this.modal.querySelectorAll(FOCUSABLE_SELECTOR),
            (el) => !el.hasAttribute('disabled') && el.offsetParent !== null,
        );
    }

    _trapTab(e) {
        const focusables = this._focusables();
        if (focusables.length === 0) {
            e.preventDefault();
            return;
        }
        const first = focusables[0];
        const last = focusables[focusables.length - 1];
        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
        }
    }
}

/**
 * Inicializa todos os modais marcados com [data-pi-modal] e registra triggers
 * com [data-pi-modal-open="modal-id"]. Idempotente.
 */
export function initModals(scope = document) {
    const modals = new Map();
    scope.querySelectorAll('[data-pi-modal]').forEach((el) => {
        if (el._piModalInstance) {
            modals.set(el.id, el._piModalInstance);
            return;
        }
        const m = new Modal(el);
        el._piModalInstance = m;
        modals.set(el.id, m);
    });
    scope.querySelectorAll('[data-pi-modal-open]').forEach((btn) => {
        if (btn._piModalBound) return;
        btn._piModalBound = true;
        btn.addEventListener('click', (e) => {
            const id = btn.getAttribute('data-pi-modal-open');
            const inst = modals.get(id);
            if (inst) {
                e.preventDefault();
                inst.abrir(btn);
            }
        });
    });
    return modals;
}
