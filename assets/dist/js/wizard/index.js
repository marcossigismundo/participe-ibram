/**
 * Build de distribuicao do wizard Participe Ibram (sem transpilacao - ES2018+).
 *
 * Esta versao e identica aos modulos em assets/src/js/wizard/, prontos para
 * carregar via <script type="module"> em browsers modernos. Sem build pipeline.
 *
 * Para enfileirar via WordPress:
 *   wp_enqueue_script_module(
 *     'pi-wizard',
 *     plugins_url('assets/dist/js/wizard/index.js', __FILE__),
 *     [],
 *     PARTICIPE_IBRAM_VERSION
 *   );
 *
 * @module wizard
 */

import { Wizard } from './Wizard.js';
import { setupSkipLinks } from './AccessibilityHelpers.js';

function bootstrap() {
    setupSkipLinks();
    const forms = document.querySelectorAll('[data-pi-wizard]');
    if (!forms.length) return;
    const cfg = window.piWizardConfig || {};
    const apiUrl = cfg.apiUrl || (window.wpApiSettings && (window.wpApiSettings.root + 'pi/v1')) || '/wp-json/pi/v1';
    const restNonce = cfg.restNonce || (window.wpApiSettings && window.wpApiSettings.nonce) || '';

    const instances = [];
    forms.forEach((form) => {
        const tipo = form.dataset.tipo || 'PF';
        const agenteId = form.dataset.agenteId || '';
        try {
            const w = new Wizard(form, { apiUrl, restNonce, tipoAgente: tipo, agenteId });
            instances.push(w);
        } catch (e) {
            // eslint-disable-next-line no-console
            console.error('[Participe Ibram] Falha ao iniciar wizard:', e);
        }
    });

    if (cfg.debug || (window.location && /[\?&]pi-debug=1/.test(window.location.search))) {
        window.PiWizard = { instances, Wizard };
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootstrap);
} else {
    bootstrap();
}

export { Wizard };
