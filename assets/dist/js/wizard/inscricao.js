/**
 * inscricao.js
 *
 * Bootstrap do wizard de inscrição em edital.
 * Instancia Wizard (Wave 3) com adapters específicos de inscrição.
 *
 * Uso:
 *  window.PiInscricaoWizard.init() — chamado pelo template inscricao-wizard.php.
 *
 * @module wizard/inscricao
 */

import { Wizard } from '../../../src/js/wizard/Wizard.js';
import { ApiClientInscricao } from '../../../src/js/wizard/ApiClientInscricao.js';
import { getStepsInscricao } from '../../../src/js/wizard/StepDefinitionsInscricao.js';

/**
 * Inicializa o wizard de inscrição no formulário encontrado no DOM.
 */
function init() {
    const formEl = document.querySelector('[data-pi-wizard][data-tipo="INSCRICAO"]');
    if (!formEl) return;

    const apiUrl    = formEl.dataset.apiUrl    || (window.piApiUrl || '');
    const nonce     = formEl.dataset.nonce     || (window.piRestNonce || '');
    const editalId  = parseInt(formEl.dataset.editalId,  10) || 0;
    const agenteId  = parseInt(formEl.dataset.agenteId,  10) || 0;

    if (!apiUrl || !editalId || !agenteId) {
        console.warn('[PiInscricaoWizard] Dados insuficientes para iniciar wizard.', { apiUrl, editalId, agenteId });
        return;
    }

    const api = new ApiClientInscricao({ apiUrl, restNonce: nonce });

    // Contexto compartilhado entre passos — NUNCA armazena PII.
    const context = {
        edital_id:           editalId,
        agente_id:           agenteId,
        tipo_agente:         formEl.dataset.tipoAgente || '',
        categoria_id:        null,
        categoria_nome:      '',
        portfolio_md:        '',
        inscricao_id:        null,
        documentos_enviados: [],
    };

    const steps = getStepsInscricao(context);

    const wizard = new Wizard(formEl, {
        apiUrl,
        restNonce: nonce,
        agenteId,
        tipoAgente: context.tipo_agente,
        steps,
        // Override do autosave para usar endpoint de rascunho de inscrição.
        autosaveCallback: async (dados) => {
            try {
                const res = await api.salvarRascunho({
                    edital_id:    editalId,
                    categoria_id: context.categoria_id || 0,
                    agente_id:    agenteId,
                    portfolio_md: context.portfolio_md || null,
                    inscricao_id: context.inscricao_id || null,
                    etapa_atual:  dados.etapa || 'categoria',
                });
                if (res && res.inscricao_id) {
                    context.inscricao_id = res.inscricao_id;
                }
            } catch (e) {
                // Autosave silencioso — não bloqueia o usuário.
            }
        },
        // Override do submit para usar endpoint de submissão de inscrição.
        submitCallback: async () => {
            if (!context.inscricao_id) {
                throw new Error('Inscrição não foi salva como rascunho ainda.');
            }
            const res = await api.submeterInscricao(context.inscricao_id);
            return res;
        },
    });

    wizard.init();

    // Expõe instância para debugging (apenas em WP_DEBUG).
    if (window.piDebug) {
        window.__piInscricaoWizard = wizard;
    }
}

// API pública do módulo.
window.PiInscricaoWizard = { init };

// Auto-init se DOM já carregado.
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
