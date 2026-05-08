/**
 * ApiClient.js
 *
 * Wrapper de fetch que injeta X-WP-Nonce automaticamente. Trata 401/422/429
 * conforme contrato dos endpoints REST de W3-B.
 *
 * @module wizard/ApiClient
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

export class ApiClient {
    /**
     * @param {{apiUrl: string, restNonce: string, baseHeaders?: object}} opts
     */
    constructor(opts) {
        if (!opts || !opts.apiUrl) {
            throw new Error('ApiClient requires apiUrl');
        }
        this.apiUrl = opts.apiUrl.replace(/\/$/, '');
        this.restNonce = opts.restNonce || '';
        this.baseHeaders = opts.baseHeaders || {};
    }

    /**
     * @param {string} path  ex: '/wizard/rascunho'
     * @param {RequestInit & {json?: any}} [init]
     * @returns {Promise<any>}
     */
    async request(path, init = {}) {
        const url = path.startsWith('http') ? path : `${this.apiUrl}${path}`;
        const headers = new Headers(this.baseHeaders);
        if (this.restNonce) {
            headers.set('X-WP-Nonce', this.restNonce);
        }
        if (init.headers) {
            new Headers(init.headers).forEach((v, k) => headers.set(k, v));
        }

        let body = init.body;
        if (init.json !== undefined) {
            headers.set('Content-Type', 'application/json');
            body = JSON.stringify(init.json);
        }

        const opts = {
            credentials: 'same-origin',
            ...init,
            headers,
            body,
        };
        delete opts.json;

        const res = await fetch(url, opts);

        if (res.status === 204) {
            return null;
        }

        const ct = res.headers.get('Content-Type') || '';
        const payload = ct.includes('application/json') ? await res.json().catch(() => null) : await res.text();

        if (!res.ok) {
            const retryAfterRaw = res.headers.get('Retry-After');
            const retryAfter = retryAfterRaw ? parseInt(retryAfterRaw, 10) || 0 : 0;
            throw new ApiError(
                (payload && payload.message) || `Erro ${res.status}`,
                {
                    status: res.status,
                    code: (payload && payload.code) || '',
                    data: payload,
                    retryAfter,
                },
            );
        }

        return payload;
    }

    /**
     * Salva rascunho (POST /wizard/rascunho).
     * @param {object} dados
     */
    salvarRascunho(dados) {
        return this.request('/wizard/rascunho', { method: 'POST', json: dados });
    }

    /**
     * Recupera rascunho por ID.
     * @param {number|string} id
     */
    getRascunho(id) {
        return this.request(`/wizard/rascunho/${encodeURIComponent(id)}`, { method: 'GET' });
    }

    /**
     * Submete cadastro (POST /wizard/submeter).
     * @param {object} dados
     */
    submeter(dados) {
        return this.request('/wizard/submeter', { method: 'POST', json: dados });
    }

    /**
     * Upload multipart de documento.
     * @param {File} file
     * @param {string} tipoCodigo
     * @param {(percent: number) => void} [onProgress]
     */
    uploadDocumento(file, tipoCodigo, onProgress) {
        const url = `${this.apiUrl}/wizard/upload-documento`;
        const fd = new FormData();
        fd.append('arquivo', file);
        fd.append('tipo_codigo', tipoCodigo);

        if (typeof onProgress !== 'function') {
            return this.request('/wizard/upload-documento', { method: 'POST', body: fd });
        }
        // XHR para progress events (fetch ainda nao tem upload progress estavel)
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', url, true);
            xhr.withCredentials = true;
            if (this.restNonce) {
                xhr.setRequestHeader('X-WP-Nonce', this.restNonce);
            }
            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                    onProgress(Math.round((e.loaded / e.total) * 100));
                }
            });
            xhr.addEventListener('load', () => {
                let payload = null;
                try {
                    payload = JSON.parse(xhr.responseText);
                } catch (_e) {
                    payload = xhr.responseText;
                }
                if (xhr.status >= 200 && xhr.status < 300) {
                    resolve(payload);
                } else {
                    reject(
                        new ApiError(
                            (payload && payload.message) || `Erro ${xhr.status}`,
                            {
                                status: xhr.status,
                                code: (payload && payload.code) || '',
                                data: payload,
                            },
                        ),
                    );
                }
            });
            xhr.addEventListener('error', () => reject(new ApiError('Falha de rede ao enviar arquivo.', { status: 0 })));
            xhr.addEventListener('abort', () => reject(new ApiError('Envio cancelado.', { status: 0 })));
            xhr.send(fd);
        });
    }

    /**
     * @param {number|string} id
     */
    removerDocumento(id) {
        return this.request(`/wizard/documento/${encodeURIComponent(id)}`, { method: 'DELETE' });
    }

    /**
     * @param {string} tipo  ex: 'tipos_coletivo', 'racas_cor'
     */
    getVocabulario(tipo) {
        return this.request(`/wizard/vocabulario/${encodeURIComponent(tipo)}`, { method: 'GET' });
    }
}
