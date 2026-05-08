/**
 * list-table-actions.js
 *
 * Intercepts clicks on `.pi-action-confirm` links inside the cadastros list
 * tables and shows a confirmation dialog before navigating. Falls back to a
 * native confirm() when no inline modal is present (the list table page has
 * no modal of its own — modals live on the agente-detalhes page).
 *
 * @module admin/list-table-actions
 */
(function () {
    'use strict';

    function readMessage(el) {
        var msg = el.getAttribute('data-pi-confirm');
        if (msg && msg.trim() !== '') {
            return msg;
        }
        return 'Confirmar esta ação?';
    }

    function onClick(event) {
        var target = event.target;
        while (target && target !== document.body) {
            if (target.classList && target.classList.contains('pi-action-confirm')) {
                break;
            }
            target = target.parentNode;
        }
        if (!target || target === document.body) {
            return;
        }
        var ok = window.confirm(readMessage(target));
        if (!ok) {
            event.preventDefault();
        }
    }

    function init() {
        document.addEventListener('click', onClick, true);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
