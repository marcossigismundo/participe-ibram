/**
 * votacao.js (dist) — Wave 6-B.
 *
 * Bundle concatenado dos módulos ES de assets/src/js/votacao/. NÃO transpila —
 * requer browser ES2018+. Para enfileirar via WordPress prefere-se
 * `wp_enqueue_script_module()` apontando para `index.js`; este arquivo existe
 * como fallback para casos em que `<script type="module">` não está disponível
 * (não é o caso em browsers modernos suportados pelo IBRAM, mas mantemos o
 * espelho conforme convenção das ondas anteriores).
 *
 * Em browsers que suportam módulos ES, prefira:
 *   <script type="module" src=".../assets/dist/js/votacao/index.js"></script>
 *
 * @module votacao
 */

import { VotacaoApp, STATES } from './VotacaoApp.js';
import { ApiClientVotacao, ApiError } from './ApiClientVotacao.js';
import { CandidatoCard } from './CandidatoCard.js';
import { ConfirmacaoVoto } from './ConfirmacaoVoto.js';
import { Recibo } from './Recibo.js';

function bootstrap() {
    const roots = document.querySelectorAll('[data-pi-votacao]');
    if (!roots.length) return;

    const cfg = (typeof window !== 'undefined' && window.piVotacaoConfig) || {};
    const apiUrlGlobal = cfg.apiUrl ||
        (window.wpApiSettings && (window.wpApiSettings.root + 'pi/v1')) ||
        '/wp-json/pi/v1';
    const nonceGlobal = cfg.nonce ||
        (window.wpApiSettings && window.wpApiSettings.nonce) ||
        '';

    const instances = [];
    roots.forEach((root) => {
        const ds = root.dataset;
        const apiUrl = ds.apiUrl || apiUrlGlobal;
        const nonce = ds.nonce || nonceGlobal;
        const votacaoId = parseInt(ds.piVotacao || ds.votacaoId || '0', 10);
        const loginUrl = ds.loginUrl || cfg.loginUrl || '';
        const auditoriaUrlBase = ds.auditoriaUrl || cfg.auditoriaUrlBase || '';
        if (!votacaoId) {
            // eslint-disable-next-line no-console
            console.error('[Participe Ibram Votacao] votacao_id ausente em', root);
            return;
        }
        try {
            const app = new VotacaoApp(root, {
                apiUrl, nonce, votacaoId, loginUrl, auditoriaUrlBase,
            });
            instances.push(app);
        } catch (e) {
            // eslint-disable-next-line no-console
            console.error('[Participe Ibram Votacao] Falha ao iniciar:', e);
        }
    });

    const debugQS = window.location && /[\?&]pi-debug=1/.test(window.location.search);
    if (cfg.debug || debugQS) {
        window.PiVotacao = {
            instances, VotacaoApp, STATES,
            ApiClientVotacao, ApiError, CandidatoCard, ConfirmacaoVoto, Recibo,
        };
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootstrap);
} else {
    bootstrap();
}

export { VotacaoApp, STATES, ApiClientVotacao, ApiError, CandidatoCard, ConfirmacaoVoto, Recibo };
