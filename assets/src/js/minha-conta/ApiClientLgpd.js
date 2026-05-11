/**
 * ApiClientLgpd.js
 *
 * Wrapper de fetch para endpoints `/me/lgpd/*` (LGPD self-service, Wave 8 W8-B).
 *
 * Convenções:
 *   - Sempre envia `X-WP-Nonce` (lê de `data-rest-nonce` no container).
 *   - Sempre envia `credentials: 'same-origin'` (cookies WP).
 *   - Trata 401/403/422/423/429 com mensagens específicas para o usuário.
 *   - Em sucesso devolve `data`. Em erro lança ApiError com `{ status, code, message, details }`.
 *
 * @module minha-conta/ApiClientLgpd
 */

export class ApiError extends Error {
    /**
     * @param {string} message
     * @param {number} status
     * @param {string} code
     * @param {Object} [details]
     */
    constructor(message, status, code, details = {}) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.code = code;
        this.details = details;
    }
}

export class ApiClientLgpd {
    /**
     * @param {string} restBase Ex.: '/wp-json/pi/v1'
     * @param {string} nonce    Wp REST nonce.
     */
    constructor(restBase, nonce) {
        this.restBase = String(restBase || '').replace(/\/$/, '');
        this.nonce = String(nonce || '');
    }

    /**
     * @param {string} path
     * @param {Object} [opts]
     * @param {string} [opts.method='GET']
     * @param {Object} [opts.body]
     * @returns {Promise<any>}
     */
    async request(path, opts = {}) {
        const method = opts.method || 'GET';
        const init = {
            method,
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-WP-Nonce': this.nonce,
            },
        };
        if (opts.body !== undefined && method !== 'GET') {
            init.headers['Content-Type'] = 'application/json';
            init.body = JSON.stringify(opts.body);
        }
        if (opts.signal) {
            init.signal = opts.signal;
        }

        let response;
        try {
            response = await fetch(this.restBase + path, init);
        } catch (e) {
            throw new ApiError('Falha de rede. Verifique sua conexão.', 0, 'pi_network', { cause: String(e && e.message || e) });
        }

        let payload = null;
        try {
            payload = await response.json();
        } catch (_e) {
            payload = null;
        }

        if (!response.ok) {
            const code = payload && payload.code ? String(payload.code) : 'pi_error';
            const msg = payload && payload.message
                ? String(payload.message)
                : this._defaultMessage(response.status);
            throw new ApiError(msg, response.status, code, payload && payload.data ? payload.data : {});
        }

        return payload;
    }

    _defaultMessage(status) {
        switch (status) {
            case 401: return 'Sessão expirada. Faça login novamente.';
            case 403: return 'Permissão negada.';
            case 404: return 'Recurso não encontrado.';
            case 422: return 'Validação falhou.';
            case 423: return 'Conta bloqueada para esta operação.';
            case 429: return 'Muitas tentativas. Tente novamente em instantes.';
            default:  return 'Erro inesperado. Tente novamente.';
        }
    }

    // ─── Consentimentos ──────────────────────────────────────────────────

    listarConsentimentos() {
        return this.request('/me/consentimentos');
    }

    listarHistorico() {
        return this.request('/me/consentimentos/historico');
    }

    revogar(finalidade) {
        return this.request(
            `/me/consentimentos/${encodeURIComponent(finalidade)}/revogar`,
            { method: 'POST' }
        );
    }

    reaceitar(finalidade) {
        return this.request(
            `/me/consentimentos/${encodeURIComponent(finalidade)}/reaceitar`,
            { method: 'POST' }
        );
    }

    // ─── Solicitações Art. 18 ────────────────────────────────────────────

    listarSolicitacoes() {
        return this.request('/me/solicitacoes');
    }

    criarSolicitacao(tipo, detalhes) {
        return this.request('/me/solicitacoes', {
            method: 'POST',
            body: { tipo, detalhes_md: detalhes || '' },
        });
    }

    detalheSolicitacao(id) {
        const safeId = Number(id) >>> 0;
        return this.request(`/me/solicitacoes/${safeId}`);
    }

    // ─── Export / Anonimização ───────────────────────────────────────────

    solicitarExport(senha) {
        return this.request('/me/exportar-dados', {
            method: 'POST',
            body: { confirmacao_senha: senha },
        });
    }

    solicitarAnonimizacao(senha, motivo) {
        return this.request('/me/solicitar-anonimizacao', {
            method: 'POST',
            body: { confirmacao_senha: senha, motivo: motivo || null },
        });
    }

    confirmarAnonimizacao(token) {
        return this.request('/me/anonimizacao-confirmar', {
            method: 'POST',
            body: { token },
        });
    }
}
