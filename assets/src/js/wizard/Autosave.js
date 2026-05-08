/**
 * Autosave.js
 *
 * Auto-save com debounce 2s, retry exponencial em falha, fallback offline
 * via localStorage. Indica status via live region (R4 erro #16: nunca silencioso).
 *
 * @module wizard/Autosave
 */

import { announceToLiveRegion } from './AccessibilityHelpers.js';

const DEFAULT_DEBOUNCE_MS = 2000;
const MAX_RETRY = 4;
const BACKOFF_BASE_MS = 1000;
const TXT = {
    saving: 'Salvando rascunho.',
    saved: 'Rascunho salvo.',
    offline: 'Sem conexão. Rascunho salvo localmente.',
    error: 'Falha ao salvar rascunho. Tentando novamente.',
    failed: 'Não foi possível salvar o rascunho. Tente novamente mais tarde.',
};

export class Autosave {
    /**
     * @param {object} opts
     * @param {string} opts.storageKey  ex: 'pi_wizard_PF_42'
     * @param {(payload: object) => Promise<any>} opts.saveFn  callback de envio
     * @param {() => object} opts.getPayload  obtem snapshot atual do form
     * @param {number} [opts.debounceMs]
     * @param {HTMLElement} [opts.statusEl]  elemento visual opcional para "Salvando.../Salvo"
     */
    constructor(opts) {
        if (!opts || typeof opts.saveFn !== 'function' || typeof opts.getPayload !== 'function') {
            throw new Error('Autosave requires saveFn and getPayload');
        }
        this.storageKey = opts.storageKey || 'pi_wizard_default';
        this.saveFn = opts.saveFn;
        this.getPayload = opts.getPayload;
        this.debounceMs = typeof opts.debounceMs === 'number' ? opts.debounceMs : DEFAULT_DEBOUNCE_MS;
        this.statusEl = opts.statusEl || null;
        this._timer = null;
        this._inFlight = false;
        this._pending = false;
        this._lastSavedAt = null;
    }

    /**
     * Agenda autosave (debounced). Chame em cada mudanca relevante.
     */
    schedule() {
        if (this._timer) {
            clearTimeout(this._timer);
        }
        this._timer = setTimeout(() => {
            this._timer = null;
            this.saveNow();
        }, this.debounceMs);
    }

    /**
     * Executa salvamento imediato (skip debounce).
     */
    async saveNow() {
        if (this._inFlight) {
            this._pending = true;
            return;
        }
        const payload = this.getPayload();
        // Persistencia local SEMPRE (mesmo se network depois falhar)
        this._writeLocal(payload);
        this._setStatus(TXT.saving);
        announceToLiveRegion(TXT.saving);
        this._inFlight = true;

        try {
            await this._sendWithRetry(payload);
            this._lastSavedAt = new Date();
            this._setStatus(TXT.saved);
            announceToLiveRegion(TXT.saved);
        } catch (err) {
            // Mantem em localStorage; comunica falha
            const msg = navigator.onLine ? TXT.failed : TXT.offline;
            this._setStatus(msg);
            announceToLiveRegion(msg, 'assertive');
        } finally {
            this._inFlight = false;
            if (this._pending) {
                this._pending = false;
                this.schedule();
            }
        }
    }

    /**
     * Forca flush imediato sem debounce. Util antes de unload.
     */
    flush() {
        if (this._timer) {
            clearTimeout(this._timer);
            this._timer = null;
        }
        return this.saveNow();
    }

    /**
     * Le snapshot localStorage (recuperacao apos refresh ou fallback).
     * @returns {object|null}
     */
    readLocal() {
        try {
            const raw = window.localStorage.getItem(this.storageKey);
            if (!raw) return null;
            const obj = JSON.parse(raw);
            return obj && obj.dados ? obj.dados : obj;
        } catch (_e) {
            return null;
        }
    }

    /**
     * Limpa rascunho local.
     */
    clearLocal() {
        try {
            window.localStorage.removeItem(this.storageKey);
        } catch (_e) {
            /* noop */
        }
    }

    /* ---------------- internos ---------------- */

    _writeLocal(payload) {
        try {
            const wrap = { dados: payload, savedAt: new Date().toISOString() };
            window.localStorage.setItem(this.storageKey, JSON.stringify(wrap));
        } catch (_e) {
            // Quota exceeded ou modo privado: ignora silenciosamente
        }
    }

    _setStatus(msg) {
        if (this.statusEl) {
            this.statusEl.textContent = msg;
        }
    }

    async _sendWithRetry(payload, attempt = 0) {
        try {
            return await this.saveFn(payload);
        } catch (err) {
            if (attempt >= MAX_RETRY) {
                throw err;
            }
            // 4xx (exceto 429) nao retentar
            if (err && err.status >= 400 && err.status < 500 && err.status !== 429) {
                throw err;
            }
            const wait = err && err.retryAfter
                ? err.retryAfter * 1000
                : BACKOFF_BASE_MS * Math.pow(2, attempt);
            await new Promise((r) => setTimeout(r, wait));
            return this._sendWithRetry(payload, attempt + 1);
        }
    }
}
