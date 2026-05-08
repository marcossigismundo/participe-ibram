/**
 * ApiClientVotacao.js
 *
 * Wrapper sobre fetch para os endpoints REST de votacao Participe Ibram (W6-A).
 * Espelha o pattern de wizard/ApiClient.js: injeta X-WP-Nonce, trata payload
 * JSON, traduz status HTTP em erros estruturados (ApiError).
 *
 * Endpoints cobertos (namespaces canonicos definidos pela W6-A):
 *  - GET  /pi/v1/votacao/{id}/elegibilidade
 *  - GET  /pi/v1/publico/votacao/{id}/categorias?categoria_id=X
 *  - POST /pi/v1/votacao/registrar
 *  - GET  /pi/v1/votacao/{id}/status
 *
 * Timeout configuravel (default 30s) via AbortController.
 *
 * @module votacao/ApiClientVotacao
 */

/**
 * Erro estruturado para o consumidor identificar status, codigo e payload.
 */
export class ApiError extends Error {
    constructor(message, { status, code, data, retryAfter } = {}) {
        super(message);
        this.name = 'ApiError';
        this.status = status || 0;
        this.code = code || '';
        this.data = data || null;
        this.retryAfter = retryAfter || 0;
    }
}

/**
 * Mensagens default em pt_BR (server pode sobrescrever via window.piI18n).
 */
const DEFAULT_MESSAGES = {
    timeout: 'O servidor demorou para responder. Tente novamente.',
    network: 'Falha de rede. Verifique sua conexão.',
    unauthorized: 'Sessão expirada. Faça login novamente para votar.',
    forbidden: 'Você não tem permissão para realizar esta ação.',
    notEligible: 'Você não está habilitado para votar nesta categoria.',
    duplicate: 'Voto já registrado anteriormente nesta categoria.',
    closed: 'Votação encerrada.',
    rateLimited: 'Muitas requisições. Aguarde alguns segundos.',
    serverError: 'Erro no servidor. Tente novamente em instantes.',
};

function i18n(key, fallback) {
    const dict = (typeof window !== 'undefined' && window.piI18n) || {};
    return (dict && dict[key]) || fallback;
}

export class ApiClientVotacao {
    /**
     * @param {{apiUrl: string, nonce: string, timeoutMs?: number}} opts
     */
    constructor(opts) {
        if (!opts || !opts.apiUrl) {
            throw new Error('ApiClientVotacao requires apiUrl');
        }
        this.apiUrl = String(opts.apiUrl).replace(/\/$/, '');
        this.nonce = opts.nonce || '';
        this.timeoutMs = typeof opts.timeoutMs === 'number' ? opts.timeoutMs : 30000;
    }

    /**
     * Request generico com timeout via AbortController.
     *
     * @param {string} path  ex: '/votacao/123/elegibilidade'
     * @param {RequestInit & {json?: any, signal?: AbortSignal}} [init]
     * @returns {Promise<any>}
     */
    async request(path, init = {}) {
        const url = path.startsWith('http') ? path : `${this.apiUrl}${path}`;
        const headers = new Headers();
        headers.set('Accept', 'application/json');
        if (this.nonce) {
            headers.set('X-WP-Nonce', this.nonce);
        }
        if (init.headers) {
            new Headers(init.headers).forEach((v, k) => headers.set(k, v));
        }

        let body = init.body;
        if (init.json !== undefined) {
            headers.set('Content-Type', 'application/json');
            body = JSON.stringify(init.json);
        }

        // Combine external signal with timeout signal
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), this.timeoutMs);
        let externalAbortHandler = null;
        if (init.signal) {
            if (init.signal.aborted) {
                controller.abort();
            } else {
                externalAbortHandler = () => controller.abort();
                init.signal.addEventListener('abort', externalAbortHandler);
            }
        }

        const fetchOpts = {
            credentials: 'same-origin',
            ...init,
            headers,
            body,
            signal: controller.signal,
        };
        delete fetchOpts.json;

        let res;
        try {
            res = await fetch(url, fetchOpts);
        } catch (err) {
            clearTimeout(timeoutId);
            if (externalAbortHandler && init.signal) {
                init.signal.removeEventListener('abort', externalAbortHandler);
            }
            if (err && err.name === 'AbortError') {
                // Distinguir abort por timeout do abort externo
                if (init.signal && init.signal.aborted) {
                    throw new ApiError('Cancelado.', { status: 0, code: 'aborted' });
                }
                throw new ApiError(i18n('timeout', DEFAULT_MESSAGES.timeout), { status: 0, code: 'timeout' });
            }
            throw new ApiError(i18n('network', DEFAULT_MESSAGES.network), { status: 0, code: 'network' });
        }
        clearTimeout(timeoutId);
        if (externalAbortHandler && init.signal) {
            init.signal.removeEventListener('abort', externalAbortHandler);
        }

        if (res.status === 204) {
            return null;
        }

        const ct = res.headers.get('Content-Type') || '';
        const payload = ct.includes('application/json')
            ? await res.json().catch(() => null)
            : await res.text();

        if (!res.ok) {
            const retryAfterRaw = res.headers.get('Retry-After');
            const retryAfter = retryAfterRaw ? (parseInt(retryAfterRaw, 10) || 0) : 0;
            const code = (payload && payload.code) || '';
            let msg = (payload && payload.message) || `Erro ${res.status}`;
            // Mensagens amigaveis para status conhecidos
            switch (res.status) {
                case 401:
                    msg = i18n('unauthorized', DEFAULT_MESSAGES.unauthorized);
                    break;
                case 403:
                    msg = (payload && payload.message) || i18n('forbidden', DEFAULT_MESSAGES.forbidden);
                    break;
                case 409:
                    msg = i18n('duplicate', DEFAULT_MESSAGES.duplicate);
                    break;
                case 410:
                    msg = i18n('closed', DEFAULT_MESSAGES.closed);
                    break;
                case 422:
                    msg = (payload && payload.message) || i18n('notEligible', DEFAULT_MESSAGES.notEligible);
                    break;
                case 429:
                    msg = i18n('rateLimited', DEFAULT_MESSAGES.rateLimited);
                    break;
                default:
                    if (res.status >= 500) {
                        msg = i18n('serverError', DEFAULT_MESSAGES.serverError);
                    }
            }
            throw new ApiError(msg, {
                status: res.status,
                code,
                data: payload,
                retryAfter,
            });
        }

        return payload;
    }

    /**
     * GET /pi/v1/votacao/{id}/elegibilidade
     *
     * Retorna { votacao_id, agente_id, categorias: [{ categoria_id, nome,
     * elegivel, ja_votou, motivo_inelegibilidade? }] }.
     *
     * @param {number|string} votacaoId
     * @param {AbortSignal} [signal]
     */
    getElegibilidade(votacaoId, signal) {
        const id = encodeURIComponent(String(votacaoId));
        return this.request(`/votacao/${id}/elegibilidade`, { method: 'GET', signal });
    }

    /**
     * GET /pi/v1/publico/votacao/{id}/categorias?categoria_id=X
     *
     * Lista candidatos de uma categoria (whitelist: nome_publico, numero_registro,
     * inscricao_id, foto opcional). NAO retorna CPF/email/telefone.
     *
     * @param {number|string} votacaoId
     * @param {number|string} categoriaId
     * @param {AbortSignal} [signal]
     */
    getCandidatos(votacaoId, categoriaId, signal) {
        const id = encodeURIComponent(String(votacaoId));
        const cat = encodeURIComponent(String(categoriaId));
        return this.request(`/publico/votacao/${id}/categorias?categoria_id=${cat}`, {
            method: 'GET',
            signal,
        });
    }

    /**
     * POST /pi/v1/votacao/registrar
     *
     * Body: { votacao_id, categoria_id, candidato_inscricao_id }.
     * Retorna 201 { hash_voto, registrado_em, votacao_id, categoria_id }.
     * Pode retornar 409 (duplicado), 410 (encerrada), 422 (inelegivel).
     *
     * @param {{votacaoId:number, categoriaId:number, candidatoInscricaoId:number}} dados
     * @param {AbortSignal} [signal]
     */
    registrarVoto(dados, signal) {
        const json = {
            votacao_id: Number(dados.votacaoId),
            categoria_id: Number(dados.categoriaId),
            candidato_inscricao_id: Number(dados.candidatoInscricaoId),
        };
        return this.request('/votacao/registrar', { method: 'POST', json, signal });
    }

    /**
     * GET /pi/v1/votacao/{id}/status
     *
     * Retorna { votacao_id, status: 'aberta'|'encerrada'|'agendada',
     * abertura, encerramento, agora_servidor }.
     *
     * @param {number|string} votacaoId
     * @param {AbortSignal} [signal]
     */
    getStatusVotacao(votacaoId, signal) {
        const id = encodeURIComponent(String(votacaoId));
        return this.request(`/votacao/${id}/status`, { method: 'GET', signal });
    }
}

export default ApiClientVotacao;
