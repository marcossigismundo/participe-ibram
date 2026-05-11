/**
 * ApiClientHistorico.js
 *
 * Wrapper de fetch para a aba "Histórico" da Minha Conta (W8-C).
 * Injeta X-WP-Nonce e trata 401/404/429 com mensagens genéricas.
 *
 * Voto secreto: o cliente NUNCA recebe `candidato_inscricao_id` do servidor.
 * Esta camada de cliente é a última linha — qualquer chave inesperada deve ser
 * ignorada na renderização (HistoricoUI.js).
 *
 * @module minha-conta/ApiClientHistorico
 */

export class HistoricoApiError extends Error {
    constructor(message, { status, code, data, retryAfter } = {}) {
        super(message);
        this.name = 'HistoricoApiError';
        this.status = status || 0;
        this.code = code || '';
        this.data = data || null;
        this.retryAfter = retryAfter || 0;
    }
}

export class ApiClientHistorico {
    /**
     * @param {{apiUrl:string, restNonce:string}} opts
     */
    constructor(opts) {
        if (!opts || !opts.apiUrl) {
            throw new Error('ApiClientHistorico requires apiUrl');
        }
        this.apiUrl = String(opts.apiUrl).replace(/\/$/, '');
        this.restNonce = opts.restNonce || '';
    }

    async _request(path, init = {}) {
        const url = `${this.apiUrl}${path}`;
        const headers = new Headers(init.headers || {});
        if (this.restNonce) {
            headers.set('X-WP-Nonce', this.restNonce);
        }
        if (init.json !== undefined) {
            headers.set('Content-Type', 'application/json');
        }
        const opts = {
            credentials: 'same-origin',
            method: init.method || 'GET',
            headers,
        };
        if (init.json !== undefined) {
            opts.body = JSON.stringify(init.json);
        }

        let res;
        try {
            res = await fetch(url, opts);
        } catch (e) {
            throw new HistoricoApiError('Erro de rede.', { status: 0, code: 'pi_network' });
        }

        let data = null;
        try {
            data = await res.json();
        } catch (_e) {
            // resposta vazia ou nao-json
        }

        if (!res.ok) {
            const code = (data && data.code) || 'pi_error';
            const msg = (data && data.message) || 'Erro inesperado.';
            const retryAfter = parseInt(res.headers.get('Retry-After') || '0', 10) || 0;
            throw new HistoricoApiError(msg, {
                status: res.status,
                code,
                data,
                retryAfter,
            });
        }

        return data;
    }

    getCadastroTimeline() {
        return this._request('/me/historico/cadastro');
    }

    /**
     * @param {number} [page]
     * @param {number} [perPage]
     */
    getInscricoes(page = 1, perPage = 20) {
        const qs = `?page=${encodeURIComponent(page)}&per_page=${encodeURIComponent(perPage)}`;
        return this._request(`/me/historico/inscricoes${qs}`);
    }

    getRecursos() {
        return this._request('/me/historico/recursos');
    }

    getVotos() {
        return this._request('/me/historico/votos');
    }

    /**
     * @param {number} [page]
     * @param {number} [perPage]
     */
    getAuditTrail(page = 1, perPage = 20) {
        const qs = `?page=${encodeURIComponent(page)}&per_page=${encodeURIComponent(perPage)}`;
        return this._request(`/me/historico/auditoria${qs}`);
    }

    /**
     * Regenera o recibo (hash) de um voto do próprio agente.
     * O servidor NUNCA devolve `candidato_inscricao_id` aqui.
     *
     * @param {number} votacaoId
     */
    regerarRecibo(votacaoId) {
        const id = Number(votacaoId);
        if (!Number.isInteger(id) || id <= 0) {
            return Promise.reject(new HistoricoApiError('votacao_id inválido.', { status: 400 }));
        }
        return this._request(`/me/historico/votos/${id}/recibo`, {
            method: 'POST',
            json: {},
        });
    }
}

export default ApiClientHistorico;
