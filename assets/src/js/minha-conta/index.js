/**
 * Entry point para Minha Conta. Detecta [data-pi-minha-conta] e instancia MinhaContaApp.
 *
 * @module minha-conta/index
 */

import { MinhaContaApp } from './MinhaContaApp.js';
import { initModals } from '../wizard/Modal.js';

function boot() {
    const roots = document.querySelectorAll('[data-pi-minha-conta]');
    if (roots.length === 0) return;
    initModals(document);
    roots.forEach((root) => {
        if (root._piMcInstance) return;
        root._piMcInstance = new MinhaContaApp(root);
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}
