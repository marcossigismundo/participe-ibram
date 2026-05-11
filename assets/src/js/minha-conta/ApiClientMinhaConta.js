/**
 * ApiClientMinhaConta.js
 *
 * Thin wrapper over fetch() with X-WP-Nonce. Single responsibility: REST calls
 * for Minha Conta. Never carries `agente_id` in URL/body — server resolves
 * ownership via OwnershipResolver from the WP session.
 *
 * @module minha-conta/ApiClientMinhaConta
 */

export class ApiClientMinhaConta {
    /**
     * @param {{apiUrl: string, nonce: string}} config
     */
    constructor(config) {
        if (!config || !config.apiUrl) {
            throw new Error('ApiClientMinhaConta: apiUrl is required');
        }
        this.baseUrl = String(config.apiUrl).replace(/\/+$/, '');
        this.nonce = String(config.nonce || '');
    }

    /**
     * @param {string[]=} reveal lista de campos sensiveis a revelar (cpf, rg, ...)
     */
    async getCadastro(reveal) {
        const qs = reveal && reveal.length > 0
            ? `?reveal=${encodeURIComponent(reveal.join(','))}`
            : '';
        return this._request(`${this.baseUrl}/me/cadastro${qs}`, { method: 'GET' });
    }

    async getDashboard() {
        return this._request(`${this.baseUrl}/me/dashboard`, { method: 'GET' });
    }

    /**
     * @param {Record<string,unknown>} dados Campos a atualizar (whitelist server-side).
     */
    async patchCadastro(dados) {
        return this._request(`${this.baseUrl}/me/cadastro`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(dados || {}),
        });
    }

    /**
     * Convenience: revela um único campo. Reaproveita getCadastro.
     * @param {string} campo
     */
    async revelarCampo(campo) {
        return this.getCadastro([campo]);
    }

    /**
     * @param {string} url
     * @param {RequestInit} init
     */
    async _request(url, init) {
        const headers = Object.assign(
            { Accept: 'application/json' },
            init.headers || {},
            this.nonce ? { 'X-WP-Nonce': this.nonce } : {},
        );
        let response;
        try {
            response = await fetch(url, Object.assign({ credentials: 'same-origin' }, init, { headers }));
        } catch (e) {
            // Network error.
            const err = new Error('network_error');
            err.cause = e;
            err.status = 0;
            throw err;
        }
        const ct = response.headers.get('Content-Type') || '';
        let body = null;
        if (ct.indexOf('application/json') !== -1) {
            try { body = await response.json(); } catch (_) { body = null; }
        } else {
            try { body = await response.text(); } catch (_) { body = null; }
        }
        if (!response.ok) {
            const err = new Error((body && body.code) ? body.code : `http_${response.status}`);
            err.status = response.status;
            err.body = body;
            throw err;
        }
        return body;
    }
}
