/**
 * ApiClientInscricao.js
 *
 * Wrapper sobre ApiClient (Wave 3) com as rotas de inscrição.
 * NUNCA envia PII desnecessário (sem CPF, email, telefone, raça, gênero).
 *
 * @module wizard/ApiClientInscricao
 */

import { ApiClient } from './ApiClient.js';

export class ApiClientInscricao extends ApiClient {
    /**
     * @param {{apiUrl: string, restNonce: string}} opts
     */
    constructor(opts) {
        super(opts);
    }

    // ─── Endpoints públicos de Edital ─────────────────────────────────────────

    /**
     * GET /publico/editais — listagem paginada.
     * @param {object} filtros  Filtros: status, abertura_desde, encerramento_ate, page, per_page.
     */
    async listarEditais(filtros = {}) {
        const params = new URLSearchParams();
        Object.entries(filtros).forEach(([k, v]) => { if (v !== undefined && v !== '') params.set(k, String(v)); });
        return this.request('/publico/editais?' + params.toString());
    }

    /**
     * GET /publico/edital/{id} — detalhe.
     * @param {number} editalId
     */
    async getEdital(editalId) {
        return this.request(`/publico/edital/${encodeURIComponent(editalId)}`);
    }

    /**
     * GET /publico/edital/{id}/categorias — lista de categorias.
     * @param {number} editalId
     */
    async listarCategoriasEdital(editalId) {
        return this.request(`/publico/edital/${encodeURIComponent(editalId)}/categorias`);
    }

    /**
     * GET /publico/edital/{id}/inscritos-habilitados — lista pública de habilitados.
     * @param {number} editalId
     */
    async listarInscritosHabilitados(editalId) {
        return this.request(`/publico/edital/${encodeURIComponent(editalId)}/inscritos-habilitados`);
    }

    /**
     * Alias para detalhe de categoria com documentos_exigidos.
     * Usa o detalhe do edital e filtra a categoria específica.
     * @param {number} editalId
     * @param {number} categoriaId
     */
    async getCategoria(editalId, categoriaId) {
        const data = await this.getEdital(editalId);
        if (data && Array.isArray(data.categorias)) {
            return data.categorias.find(c => Number(c.id) === Number(categoriaId)) || null;
        }
        return null;
    }

    // ─── Endpoints autenticados de Inscrição ──────────────────────────────────

    /**
     * POST /inscricao/rascunho — salva rascunho.
     * NUNCA inclui CPF, email, ou outros PII no payload.
     *
     * @param {{edital_id: number, categoria_id: number, agente_id: number, portfolio_md?: string, inscricao_id?: number, etapa_atual?: string}} payload
     */
    async salvarRascunho(payload) {
        return this.request('/inscricao/rascunho', {
            method: 'POST',
            json: {
                edital_id:    Number(payload.edital_id),
                categoria_id: Number(payload.categoria_id),
                agente_id:    Number(payload.agente_id),
                portfolio_md: payload.portfolio_md || null,
                inscricao_id: payload.inscricao_id || null,
                etapa_atual:  payload.etapa_atual  || 'categoria',
            },
        });
    }

    /**
     * GET /inscricao/{id} — lê inscrição do dono.
     * @param {number} inscricaoId
     */
    async getInscricao(inscricaoId) {
        return this.request(`/inscricao/${encodeURIComponent(inscricaoId)}`);
    }

    /**
     * POST /inscricao/submeter — submete inscrição final.
     * @param {number} inscricaoId
     */
    async submeterInscricao(inscricaoId) {
        return this.request('/inscricao/submeter', {
            method: 'POST',
            json: { inscricao_id: Number(inscricaoId) },
        });
    }

    /**
     * POST /inscricao/{id}/upload-documento — upload multipart.
     * @param {number} inscricaoId
     * @param {number} tipoDocumentoId
     * @param {File}   arquivo
     */
    async uploadDocumento(inscricaoId, tipoDocumentoId, arquivo) {
        const fd = new FormData();
        fd.append('arquivo', arquivo);
        fd.append('tipo_documento_id', String(tipoDocumentoId));

        return this.request(`/inscricao/${encodeURIComponent(inscricaoId)}/upload-documento`, {
            method: 'POST',
            body: fd,
            // Não define Content-Type — FormData define o boundary automaticamente.
        });
    }

    /**
     * DELETE /inscricao/{id}/documento/{doc_id} — exclui documento.
     * @param {number} inscricaoId
     * @param {number} docId
     */
    async deletarDocumento(inscricaoId, docId) {
        return this.request(
            `/inscricao/${encodeURIComponent(inscricaoId)}/documento/${encodeURIComponent(docId)}`,
            { method: 'DELETE' }
        );
    }
}
