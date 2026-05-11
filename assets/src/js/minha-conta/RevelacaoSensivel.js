/**
 * RevelacaoSensivel.js
 *
 * Toggle "Mostrar/Ocultar" para campos sensiveis (CPF, RG, Passaporte, CNPJ).
 *
 *  - Click "Mostrar" -> AJAX reveal endpoint + atualiza DOM + aria-expanded=true.
 *  - Click "Ocultar" -> volta para mascarado SEM novo request.
 *  - Cooldown 2s entre toggles (anti-misclick).
 *  - Audit do reveal e registrado server-side (AccessTracker), nao precisa
 *    instrumentar nada aqui — apenas chamar a API.
 *
 * Layout esperado em cada linha:
 *   <dt>...</dt>
 *   <dd>
 *     <code data-pi-mc-campo-valor>XXX.XXX.999-XX</code>
 *     <button type="button" data-pi-mc-reveal data-campo="cpf"
 *             aria-expanded="false" aria-controls="...">Mostrar</button>
 *   </dd>
 *
 * @module minha-conta/RevelacaoSensivel
 */

const COOLDOWN_MS = 2000;

export class RevelacaoSensivel {
    /**
     * @param {HTMLElement} root  Container raiz (ex.: .pi-mc-dados).
     * @param {import('./ApiClientMinhaConta.js').ApiClientMinhaConta} api
     * @param {(msg: string) => void} liveAnnounce  Helper para anunciar mudancas em SR.
     */
    constructor(root, api, liveAnnounce) {
        this.root = root;
        this.api = api;
        this.announce = liveAnnounce || (() => {});
        this._lastToggleAt = 0;
        this._mascarado = {}; // campo -> texto mascarado atual (para ocultar de volta)
        this._onClick = this._onClick.bind(this);
        this.root.addEventListener('click', this._onClick);
    }

    destroy() {
        this.root.removeEventListener('click', this._onClick);
    }

    /**
     * Quando o cadastro e (re)carregado, sincroniza o cache de valores mascarados.
     * @param {Record<string,{value: string|null, masked: boolean}>} valoresMascarados
     */
    sincronizarMascaras(valoresMascarados) {
        for (const campo in valoresMascarados) {
            if (Object.prototype.hasOwnProperty.call(valoresMascarados, campo)) {
                const v = valoresMascarados[campo];
                if (v && v.masked && v.value !== null) {
                    this._mascarado[campo] = String(v.value);
                }
            }
        }
    }

    /**
     * @param {MouseEvent} e
     */
    async _onClick(e) {
        const t = e.target instanceof Element ? e.target.closest('[data-pi-mc-reveal]') : null;
        if (!t) return;
        e.preventDefault();

        const agora = Date.now();
        if (agora - this._lastToggleAt < COOLDOWN_MS) {
            // Cooldown ativo — apenas ignora silenciosamente (sem network).
            return;
        }
        this._lastToggleAt = agora;

        const campo = t.getAttribute('data-campo') || '';
        if (!campo) return;
        const expanded = t.getAttribute('aria-expanded') === 'true';
        const cell = t.closest('dd') || t.parentElement;
        const code = cell ? cell.querySelector('[data-pi-mc-campo-valor]') : null;

        if (expanded) {
            // Ocultar — sem request.
            if (code && this._mascarado[campo]) {
                code.textContent = this._mascarado[campo];
            }
            t.setAttribute('aria-expanded', 'false');
            t.textContent = t.dataset.labelMostrar || 'Mostrar';
            this.announce('Valor ocultado.');
            return;
        }

        // Revelar — request.
        t.setAttribute('disabled', '');
        t.setAttribute('aria-busy', 'true');
        try {
            const cadastro = await this.api.revelarCampo(campo);
            const dados = cadastro && cadastro[campo] ? cadastro[campo] : null;
            if (!dados || dados.value === null) {
                this.announce('Nao foi possivel revelar o valor.');
                return;
            }
            if (code) {
                code.textContent = String(dados.value);
            }
            t.setAttribute('aria-expanded', 'true');
            t.textContent = t.dataset.labelOcultar || 'Ocultar';
            this.announce('Valor revelado. Esta visualizacao foi registrada em auditoria.');
        } catch (err) {
            this.announce('Erro ao revelar o campo.');
        } finally {
            t.removeAttribute('disabled');
            t.removeAttribute('aria-busy');
        }
    }
}
