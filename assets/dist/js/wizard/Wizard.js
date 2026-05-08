/**
 * Wizard.js
 *
 * Classe principal do wizard PF/OR/SM. State machine de passos, validacao
 * inline, autosave, sumario de erros acessivel, anuncios via live region,
 * aria-current="step" (NAO role="tablist"), foco no h2 ao mudar de passo,
 * fallback localStorage.
 *
 * Eventos custom emitidos no formulario:
 *  - pi:wizard:step-changed { detail: { from, to, total } }
 *  - pi:wizard:saved        { detail: { local: bool, remote: bool } }
 *  - pi:wizard:submitted    { detail: { agente_id, numero_protocolo } }
 *
 * @module wizard/Wizard
 */

import { announceToLiveRegion, ensureLiveRegion, getFieldLabel, setFocusOnHeading, cssEscape } from './AccessibilityHelpers.js';
import {
    validateRequired,
    validateEmail,
    validateCpf,
    validateCnpj,
    validatePhone,
    validateCep,
    applyMask,
} from './FieldValidators.js';
import { Autosave } from './Autosave.js';
import { ApiClient } from './ApiClient.js';
import { getStepsByTipo } from './StepDefinitions.js';
import { ConsentForm } from './ConsentForm.js';
import { initHelpModals } from './HelpModals.js';
import { initFileUploads } from './FileUpload.js';

const VALIDATOR_BY_TYPE = {
    email: validateEmail,
    cpf: validateCpf,
    cnpj: validateCnpj,
    tel: validatePhone,
    phone: validatePhone,
    cep: validateCep,
};

const i18n = (key, fallback) => {
    const dict = (typeof window !== 'undefined' && window.piI18n) || {};
    return dict[key] || fallback;
};

export class Wizard {
    /**
     * @param {HTMLFormElement} formElement
     * @param {object} options
     * @param {string} options.apiUrl
     * @param {string} options.restNonce
     * @param {string|number} [options.agenteId]
     * @param {('PF'|'OR'|'SM')} options.tipoAgente
     * @param {string} [options.nonce]  WP ajax nonce (legado)
     */
    constructor(formElement, options = {}) {
        if (!formElement) {
            throw new Error('Wizard requires a form element');
        }
        this.form = formElement;
        this.tipoAgente = String(options.tipoAgente || formElement.dataset.tipo || 'PF').toUpperCase();
        this.agenteId = options.agenteId || formElement.dataset.agenteId || '';
        this.api = new ApiClient({
            apiUrl: options.apiUrl,
            restNonce: options.restNonce || options.nonce || '',
        });
        this.steps = getStepsByTipo(this.tipoAgente);
        this.indice = 0;
        this.consent = null;
        this.uploads = [];

        ensureLiveRegion();
        this._initStepperPanels();
        this._initConsent();
        this._initHelpModals();
        this._initFileUploads();
        this._initAutosave();
        this._bindEvents();
        this._tryRestoreDraft();
        this._mostrarPasso(0, /* announce */ false);
    }

    /* ============= Inicializacao ============= */

    _initStepperPanels() {
        // Esconde todos os panels exceto o primeiro
        const panels = this._allPanels();
        panels.forEach((p, i) => {
            p.hidden = i !== 0;
            const heading = p.querySelector('h2');
            if (heading && !heading.hasAttribute('tabindex')) {
                heading.setAttribute('tabindex', '-1');
            }
        });
        this._atualizarStepper();
    }

    _initConsent() {
        const el = this.form.querySelector('[data-pi-consent]');
        if (el) {
            this.consent = new ConsentForm(el);
        }
    }

    _initHelpModals() {
        // Modais podem viver fora do form (no fim do template)
        initHelpModals(this.form.ownerDocument);
    }

    _initFileUploads() {
        this.uploads = initFileUploads(this.form, {
            uploadFn: (file, tipo, onProgress) => this.api.uploadDocumento(file, tipo, onProgress),
            deleteFn: (id) => this.api.removerDocumento(id),
        });
    }

    _initAutosave() {
        const key = `pi_wizard_${this.tipoAgente}_${this.agenteId || 'novo'}`;
        const status = this.form.querySelector('.pi-wizard__autosave-status');
        this.autosave = new Autosave({
            storageKey: key,
            statusEl: status,
            getPayload: () => this.coletarDados(),
            saveFn: async (dados) => {
                const res = await this.api.salvarRascunho({
                    agente_id: this.agenteId || null,
                    tipo_agente: this.tipoAgente,
                    passo_atual: this.indice,
                    dados,
                });
                if (res && res.agente_id && !this.agenteId) {
                    this.agenteId = res.agente_id;
                    this.form.dataset.agenteId = String(res.agente_id);
                }
                this.form.dispatchEvent(new CustomEvent('pi:wizard:saved', {
                    bubbles: true,
                    detail: { local: true, remote: true, agenteId: this.agenteId },
                }));
                return res;
            },
        });
    }

    _bindEvents() {
        this.form.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-acao]');
            if (!btn || !this.form.contains(btn)) return;
            const acao = btn.getAttribute('data-acao');
            if (acao === 'avancar') {
                e.preventDefault();
                this.avancar();
            } else if (acao === 'voltar') {
                e.preventDefault();
                this.voltar();
            } else if (acao === 'salvar') {
                e.preventDefault();
                this.autosave.flush();
            } else if (acao === 'submeter') {
                e.preventDefault();
                this.submeter();
            } else if (acao === 'ir-para') {
                const idx = parseInt(btn.getAttribute('data-passo') || '-1', 10);
                if (idx >= 0 && idx < this.indice) {
                    e.preventDefault();
                    this.irParaPasso(idx);
                }
            }
        });

        // Validar em blur (R4 §5: aria-invalid so apos blur)
        this.form.addEventListener('blur', (e) => {
            const t = e.target;
            if (t && t.matches && t.matches('input, select, textarea')) {
                this.validarCampo(t);
                this.autosave.schedule();
            }
        }, true);

        // Mascaras
        this.form.addEventListener('input', (e) => {
            const t = e.target;
            if (!t.matches) return;
            if (t.matches('input, select, textarea')) {
                const mask = t.dataset.mask;
                if (mask && VALIDATOR_BY_TYPE[mask]) {
                    const masked = applyMask(mask, t.value);
                    if (masked !== t.value) t.value = masked;
                }
                // Limpa estado de erro durante digitacao (mas nao valida ainda)
                if (t.getAttribute('aria-invalid') === 'true' && t.value) {
                    t.removeAttribute('aria-invalid');
                    const erroEl = this._erroElDe(t);
                    if (erroEl) {
                        erroEl.hidden = true;
                        erroEl.textContent = '';
                    }
                }
                this.autosave.schedule();
            }
        });

        // Tenta flush antes de unload
        window.addEventListener('beforeunload', () => {
            try { this.autosave.flush(); } catch (_e) { /* noop */ }
        });
    }

    /* ============= Restauracao de rascunho ============= */

    _tryRestoreDraft() {
        const local = this.autosave.readLocal();
        if (local && typeof local === 'object') {
            this._aplicarDados(local);
            announceToLiveRegion(i18n('rascunhoRestaurado', 'Rascunho local restaurado.'));
        }
    }

    _aplicarDados(dados) {
        if (!dados) return;
        Object.keys(dados).forEach((name) => {
            const el = this.form.elements.namedItem(name);
            if (!el) return;
            const value = dados[name];
            if (el.type === 'checkbox' || el.type === 'radio') {
                if (Array.isArray(value)) {
                    Array.from(this.form.querySelectorAll(`[name="${cssEscape(name)}"]`))
                        .forEach((c) => { c.checked = value.includes(c.value); });
                } else {
                    el.checked = !!value;
                }
            } else if (el instanceof HTMLSelectElement && el.multiple && Array.isArray(value)) {
                Array.from(el.options).forEach((o) => { o.selected = value.includes(o.value); });
            } else {
                el.value = value;
            }
        });
    }

    /* ============= Validacao ============= */

    /**
     * Valida campo individual; aplica aria-invalid e mostra erro inline.
     * @param {HTMLElement} campo
     * @returns {boolean}
     */
    validarCampo(campo) {
        const erroEl = this._erroElDe(campo);
        const label = getFieldLabel(campo, this.form);
        let resultado = { ok: true, message: '' };

        if (campo.required || campo.getAttribute('aria-required') === 'true') {
            const v = (campo.type === 'checkbox') ? campo.checked : campo.value;
            const r = validateRequired(v);
            if (!r.ok) {
                resultado = { ok: false, message: r.message };
            }
        }
        if (resultado.ok && campo.value && campo.dataset.validate) {
            const fn = VALIDATOR_BY_TYPE[campo.dataset.validate];
            if (fn) {
                const r = fn(campo.value);
                if (!r.ok) {
                    resultado = r;
                }
            }
        }
        if (resultado.ok && campo.type === 'email' && campo.value) {
            const r = validateEmail(campo.value);
            if (!r.ok) resultado = r;
        }

        if (!resultado.ok) {
            campo.setAttribute('aria-invalid', 'true');
            if (erroEl) {
                erroEl.textContent = `${label ? label + ': ' : ''}${resultado.message}`;
                erroEl.hidden = false;
            }
            return false;
        }
        campo.removeAttribute('aria-invalid');
        if (erroEl) {
            erroEl.textContent = '';
            erroEl.hidden = true;
        }
        return true;
    }

    /**
     * @param {number} idx
     * @returns {{ok: boolean, invalidos: HTMLElement[]}}
     */
    validarPasso(idx) {
        const def = this.steps[idx];
        const panel = this._panelOf(idx);
        if (!def || !panel) return { ok: true, invalidos: [] };

        const invalidos = [];
        // Valida campos listados em definicao
        def.campos.forEach((id) => {
            const el = panel.querySelector(`#${cssEscape(id)}`);
            if (!el) return;
            // Para checkbox-group / radio-group (representado por container)
            if (el.matches('fieldset, [data-pi-checkbox-group], [data-pi-radio-group]')) {
                const inputs = el.querySelectorAll('input[type="checkbox"], input[type="radio"]');
                const algum = Array.from(inputs).some((i) => i.checked);
                if (!algum) {
                    el.setAttribute('aria-invalid', 'true');
                    invalidos.push(el);
                } else {
                    el.removeAttribute('aria-invalid');
                }
                return;
            }
            if (!this.validarCampo(el)) invalidos.push(el);
        });
        // Valida tambem todos os campos required no panel (defesa em profundidade)
        panel.querySelectorAll('[required], [aria-required="true"]').forEach((el) => {
            if (!def.campos.includes(el.id)) {
                if (!this.validarCampo(el) && !invalidos.includes(el)) {
                    invalidos.push(el);
                }
            }
        });

        // Consent (so no ultimo passo)
        if (def.id.endsWith('-lgpd') && this.consent) {
            const c = this.consent.validar();
            if (!c.ok) {
                invalidos.push(this.consent.root);
            }
        }
        return { ok: invalidos.length === 0, invalidos };
    }

    /* ============= Navegacao ============= */

    avancar() {
        const { ok, invalidos } = this.validarPasso(this.indice);
        if (!ok) {
            this._renderSumarioErros(this.indice, invalidos);
            return;
        }
        this._limparSumarioErros(this.indice);
        if (this.indice >= this.steps.length - 1) {
            // Em ultimo passo, "Avancar" significa Submeter
            return this.submeter();
        }
        // Salva antes de avancar
        this.autosave.flush();
        this._mostrarPasso(this.indice + 1, true);
    }

    voltar() {
        if (this.indice === 0) return;
        this._mostrarPasso(this.indice - 1, true);
    }

    /**
     * @param {number} idx
     */
    irParaPasso(idx) {
        if (idx < 0 || idx >= this.steps.length) return;
        // Permite ir para qualquer passo ja completado (idx <= indice)
        if (idx > this.indice) {
            const { ok, invalidos } = this.validarPasso(this.indice);
            if (!ok) {
                this._renderSumarioErros(this.indice, invalidos);
                return;
            }
        }
        this._mostrarPasso(idx, true);
    }

    _mostrarPasso(idx, announce) {
        const from = this.indice;
        const panels = this._allPanels();
        panels.forEach((p, i) => {
            p.hidden = i !== idx;
        });
        this.indice = idx;
        this._atualizarStepper();
        const panel = panels[idx];
        if (panel) {
            setFocusOnHeading(panel);
        }
        if (announce) {
            const def = this.steps[idx];
            announceToLiveRegion(`Passo ${idx + 1} de ${this.steps.length}: ${def.titulo}.`);
        }
        this.form.dispatchEvent(new CustomEvent('pi:wizard:step-changed', {
            bubbles: true,
            detail: { from, to: idx, total: this.steps.length },
        }));
    }

    _atualizarStepper() {
        const nav = this.form.querySelector('.pi-wizard__nav');
        if (!nav) return;
        const items = nav.querySelectorAll('.pi-wizard__step');
        items.forEach((li, i) => {
            li.classList.remove('is-current', 'is-complete');
            li.removeAttribute('aria-current');
            if (i < this.indice) li.classList.add('is-complete');
            if (i === this.indice) {
                li.classList.add('is-current');
                li.setAttribute('aria-current', 'step');
            }
        });
    }

    /* ============= Submissao ============= */

    async submeter() {
        const { ok, invalidos } = this.validarPasso(this.indice);
        if (!ok) {
            this._renderSumarioErros(this.indice, invalidos);
            return;
        }
        // Valida todos os passos antes de submeter
        for (let i = 0; i < this.steps.length; i++) {
            const r = this.validarPasso(i);
            if (!r.ok) {
                this._mostrarPasso(i, true);
                this._renderSumarioErros(i, r.invalidos);
                return;
            }
        }
        try {
            await this.autosave.flush();
            const payload = {
                agente_id: this.agenteId || null,
                tipo_agente: this.tipoAgente,
                dados: this.coletarDados(),
                consentimento: this.consent ? this.consent.snapshot() : null,
                documentos: this.uploads.flatMap((u) => u.getArquivos()),
            };
            const res = await this.api.submeter(payload);
            this.autosave.clearLocal();
            announceToLiveRegion(i18n('cadastroSubmetido', 'Cadastro submetido com sucesso.'));
            this.form.dispatchEvent(new CustomEvent('pi:wizard:submitted', {
                bubbles: true,
                detail: res || {},
            }));
            const sucessoUrl = this.form.dataset.sucessoUrl;
            if (sucessoUrl) {
                window.location.href = sucessoUrl;
            }
        } catch (err) {
            const msg = (err && err.message) || i18n('erroSubmissao', 'Não foi possível submeter o cadastro.');
            announceToLiveRegion(msg, 'assertive');
            // Se 422, tenta destacar campos por nome
            if (err && err.status === 422 && err.data && err.data.errors) {
                this._aplicarErrosServidor(err.data.errors);
            }
        }
    }

    _aplicarErrosServidor(errors) {
        // errors: { "campo_id": "mensagem" }
        const invalidos = [];
        Object.keys(errors).forEach((id) => {
            const el = this.form.querySelector(`#${cssEscape(id)}`);
            if (!el) return;
            el.setAttribute('aria-invalid', 'true');
            const erroEl = this._erroElDe(el);
            if (erroEl) {
                erroEl.textContent = errors[id];
                erroEl.hidden = false;
            }
            invalidos.push(el);
        });
        if (invalidos.length) {
            // Vai ao primeiro passo que tem erro
            for (let i = 0; i < this.steps.length; i++) {
                const panel = this._panelOf(i);
                if (panel && invalidos.some((el) => panel.contains(el))) {
                    this._mostrarPasso(i, true);
                    this._renderSumarioErros(i, invalidos.filter((el) => panel.contains(el)));
                    break;
                }
            }
        }
    }

    /* ============= Sumario de erros ============= */

    _renderSumarioErros(idx, invalidos) {
        const panel = this._panelOf(idx);
        if (!panel) return;
        let sumario = panel.querySelector('.pi-erros-sumario');
        if (!sumario) {
            sumario = document.createElement('div');
            sumario.className = 'pi-erros-sumario';
            sumario.setAttribute('role', 'alert');
            sumario.setAttribute('aria-live', 'assertive');
            sumario.setAttribute('tabindex', '-1');
            const h3 = document.createElement('h3');
            h3.textContent = 'Existem erros neste passo';
            sumario.appendChild(h3);
            const ul = document.createElement('ul');
            sumario.appendChild(ul);
            panel.insertBefore(sumario, panel.firstChild.nextSibling);
        }
        const ul = sumario.querySelector('ul');
        // textContent only — sem innerHTML com dados externos
        ul.textContent = '';
        invalidos.forEach((el) => {
            const li = document.createElement('li');
            const a = document.createElement('a');
            a.href = `#${el.id}`;
            const label = getFieldLabel(el, this.form) || el.id;
            const erroEl = this._erroElDe(el);
            const msg = (erroEl && erroEl.textContent) ? erroEl.textContent : '';
            a.textContent = msg ? `${label}: ${msg}` : label;
            a.addEventListener('click', (e) => {
                e.preventDefault();
                if (el.focus) el.focus();
            });
            li.appendChild(a);
            ul.appendChild(li);
        });
        sumario.hidden = false;
        sumario.focus();
        announceToLiveRegion(`${invalidos.length} erro${invalidos.length > 1 ? 's' : ''} encontrado${invalidos.length > 1 ? 's' : ''} neste passo.`, 'assertive');
    }

    _limparSumarioErros(idx) {
        const panel = this._panelOf(idx);
        if (!panel) return;
        const sumario = panel.querySelector('.pi-erros-sumario');
        if (sumario) {
            sumario.hidden = true;
            const ul = sumario.querySelector('ul');
            if (ul) ul.textContent = '';
        }
    }

    /* ============= Coleta de dados ============= */

    /**
     * @returns {object} dados serializados do form (exceto arquivos)
     */
    coletarDados() {
        const fd = new FormData(this.form);
        const out = {};
        for (const [k, v] of fd.entries()) {
            if (v instanceof File) continue;
            if (out[k] === undefined) {
                out[k] = v;
            } else if (Array.isArray(out[k])) {
                out[k].push(v);
            } else {
                out[k] = [out[k], v];
            }
        }
        return out;
    }

    /* ============= Helpers DOM ============= */

    _allPanels() {
        return Array.from(this.form.querySelectorAll('.pi-wizard-panel'));
    }

    _panelOf(idx) {
        const def = this.steps[idx];
        if (!def) return null;
        return this.form.querySelector(`#${cssEscape(def.id)}`);
    }

    _erroElDe(campo) {
        const ids = (campo.getAttribute('aria-describedby') || '').split(' ').filter(Boolean);
        for (const id of ids) {
            const el = this.form.ownerDocument.getElementById(id);
            if (el && el.classList.contains('pi-campo__erro')) {
                return el;
            }
        }
        // fallback por convencao: <id>-erro
        if (campo.id) {
            return this.form.ownerDocument.getElementById(`${campo.id}-erro`);
        }
        return null;
    }
}
