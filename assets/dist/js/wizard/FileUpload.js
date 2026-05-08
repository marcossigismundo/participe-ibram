/**
 * FileUpload.js
 *
 * Uploader acessivel: drag-and-drop com fallback de input nativo, progress
 * bar com aria-live polite, erros via role="alert" + aria-invalid, lista de
 * arquivos com botao Remover. Validacao client-side; servidor revalida MIME real.
 *
 * Conforme R4 secao 7.
 *
 * @module wizard/FileUpload
 */

import { announceToLiveRegion } from './AccessibilityHelpers.js';

const DEFAULT_TXT = {
    selecionar: 'Selecionar arquivo(s)',
    arrasteAqui: 'Arraste arquivos aqui ou clique para selecionar.',
    enviando: (n, total) => `Enviando arquivo ${n} de ${total}.`,
    enviado: (nome) => `Arquivo "${nome}" enviado.`,
    erroTipo: (tipo) => `Tipo de arquivo inválido: ${tipo}. Verifique os formatos aceitos.`,
    erroTamanho: (mb) => `O arquivo excede o tamanho máximo de ${mb} MB.`,
    erroEnvio: 'Falha ao enviar o arquivo. Tente novamente.',
    remover: 'Remover',
    removido: (nome) => `Arquivo "${nome}" removido.`,
};

export class FileUpload {
    /**
     * @param {HTMLElement} containerEl  elemento raiz [data-pi-fileupload]
     * @param {object} opts
     * @param {(file: File, tipo: string, onProgress: Function) => Promise<{id, nome, tamanho}>} opts.uploadFn
     * @param {(id: string|number) => Promise<void>} opts.deleteFn
     * @param {string} opts.tipoCodigo
     * @param {string[]} [opts.mimeAceitos]
     * @param {number} [opts.tamanhoMaxBytes]
     * @param {boolean} [opts.multiplo]
     * @param {object} [opts.i18n]
     */
    constructor(containerEl, opts) {
        if (!containerEl) {
            throw new Error('FileUpload requires container element');
        }
        if (!opts || typeof opts.uploadFn !== 'function' || typeof opts.deleteFn !== 'function') {
            throw new Error('FileUpload requires uploadFn and deleteFn');
        }
        this.root = containerEl;
        this.uploadFn = opts.uploadFn;
        this.deleteFn = opts.deleteFn;
        this.tipoCodigo = opts.tipoCodigo || this.root.dataset.tipoCodigo || '';
        this.mimeAceitos = opts.mimeAceitos || (this.root.dataset.mime || '').split(',').filter(Boolean);
        this.tamanhoMax = opts.tamanhoMaxBytes || parseInt(this.root.dataset.maxBytes || '5242880', 10);
        this.multiplo = !!opts.multiplo || this.root.hasAttribute('data-multiple');
        this.i18n = Object.assign({}, DEFAULT_TXT, opts.i18n || {});
        this.arquivos = [];

        this._buildUI();
        this._bind();
    }

    _buildUI() {
        // Reusa estrutura presente no template; cria fallback se ausente
        if (this.root.querySelector('input[type="file"]')) {
            this.input = this.root.querySelector('input[type="file"]');
        } else {
            this.input = document.createElement('input');
            this.input.type = 'file';
            this.input.id = `${this.root.id || 'pi-upload'}-input`;
            this.root.appendChild(this.input);
        }
        if (this.multiplo) this.input.multiple = true;
        if (this.mimeAceitos.length) this.input.accept = this.mimeAceitos.join(',');

        this.dropzone = this.root.querySelector('.pi-upload__dropzone') || this.root;
        this.lista = this.root.querySelector('.pi-upload__lista');
        if (!this.lista) {
            this.lista = document.createElement('ul');
            this.lista.className = 'pi-upload__lista';
            this.root.appendChild(this.lista);
        }
        this.statusEl = this.root.querySelector('.pi-upload__status');
        if (!this.statusEl) {
            this.statusEl = document.createElement('p');
            this.statusEl.className = 'pi-upload__status';
            this.statusEl.setAttribute('aria-live', 'polite');
            this.statusEl.setAttribute('aria-atomic', 'true');
            this.root.appendChild(this.statusEl);
        }
        this.erroEl = this.root.querySelector('.pi-upload__erro');
        if (!this.erroEl) {
            this.erroEl = document.createElement('p');
            this.erroEl.className = 'pi-upload__erro';
            this.erroEl.setAttribute('role', 'alert');
            this.erroEl.hidden = true;
            this.root.appendChild(this.erroEl);
        }
    }

    _bind() {
        this.input.addEventListener('change', () => {
            const files = Array.from(this.input.files || []);
            this._processarArquivos(files);
            this.input.value = '';
        });
        // Drag and drop
        ['dragenter', 'dragover'].forEach((evt) => {
            this.dropzone.addEventListener(evt, (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.dropzone.classList.add('is-dragover');
            });
        });
        ['dragleave', 'drop'].forEach((evt) => {
            this.dropzone.addEventListener(evt, (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.dropzone.classList.remove('is-dragover');
            });
        });
        this.dropzone.addEventListener('drop', (e) => {
            const files = Array.from(e.dataTransfer ? e.dataTransfer.files : []);
            this._processarArquivos(files);
        });
    }

    async _processarArquivos(files) {
        this._limparErro();
        const lista = this.multiplo ? files : files.slice(0, 1);
        for (let i = 0; i < lista.length; i++) {
            const f = lista[i];
            const erro = this._validarArquivo(f);
            if (erro) {
                this._mostrarErro(erro);
                continue;
            }
            await this._enviarArquivo(f, i + 1, lista.length);
        }
    }

    _validarArquivo(file) {
        if (this.mimeAceitos.length && !this.mimeAceitos.includes(file.type)) {
            return this.i18n.erroTipo(file.type || 'desconhecido');
        }
        if (file.size > this.tamanhoMax) {
            const mb = (this.tamanhoMax / 1024 / 1024).toFixed(0);
            return this.i18n.erroTamanho(mb);
        }
        return null;
    }

    async _enviarArquivo(file, n, total) {
        const itemEl = this._criarItemLista(file);
        this.lista.appendChild(itemEl);
        const progressBar = itemEl.querySelector('.pi-upload__progress-bar');
        const progressTxt = itemEl.querySelector('.pi-upload__progress-text');
        announceToLiveRegion(this.i18n.enviando(n, total));
        try {
            const result = await this.uploadFn(file, this.tipoCodigo, (pct) => {
                progressBar.style.width = `${pct}%`;
                progressBar.setAttribute('aria-valuenow', String(pct));
                if (progressTxt) progressTxt.textContent = `${pct}%`;
            });
            const id = (result && (result.id || result.documento_id)) || null;
            itemEl.dataset.docId = id || '';
            itemEl.classList.add('is-complete');
            progressBar.style.width = '100%';
            progressBar.setAttribute('aria-valuenow', '100');
            if (progressTxt) progressTxt.textContent = '100%';
            this.arquivos.push({ id, nome: file.name, tamanho: file.size, el: itemEl });
            this.statusEl.textContent = this.i18n.enviado(file.name);
            announceToLiveRegion(this.i18n.enviado(file.name));
            this.root.dispatchEvent(new CustomEvent('pi:upload:complete', { bubbles: true, detail: { id, nome: file.name } }));
        } catch (err) {
            itemEl.remove();
            const msg = (err && err.message) || this.i18n.erroEnvio;
            this._mostrarErro(msg);
        }
    }

    _criarItemLista(file) {
        const li = document.createElement('li');
        li.className = 'pi-upload__item';

        const nome = document.createElement('span');
        nome.className = 'pi-upload__item-nome';
        nome.textContent = file.name; // textContent: imune a XSS

        const tamanho = document.createElement('span');
        tamanho.className = 'pi-upload__item-tamanho';
        tamanho.textContent = `${(file.size / 1024).toFixed(0)} KB`;

        const progressWrap = document.createElement('div');
        progressWrap.className = 'pi-upload__progress';
        progressWrap.setAttribute('role', 'progressbar');
        progressWrap.setAttribute('aria-valuemin', '0');
        progressWrap.setAttribute('aria-valuemax', '100');
        progressWrap.setAttribute('aria-valuenow', '0');
        progressWrap.setAttribute('aria-label', `Progresso do envio de ${file.name}`);
        const bar = document.createElement('div');
        bar.className = 'pi-upload__progress-bar';
        bar.style.width = '0%';
        progressWrap.appendChild(bar);
        const txt = document.createElement('span');
        txt.className = 'pi-upload__progress-text';
        txt.textContent = '0%';

        const btnRemover = document.createElement('button');
        btnRemover.type = 'button';
        btnRemover.className = 'pi-upload__remover';
        btnRemover.textContent = this.i18n.remover;
        btnRemover.setAttribute('aria-label', `${this.i18n.remover} ${file.name}`);
        btnRemover.addEventListener('click', () => this._removerItem(li, file.name));

        li.appendChild(nome);
        li.appendChild(tamanho);
        li.appendChild(progressWrap);
        li.appendChild(txt);
        li.appendChild(btnRemover);
        return li;
    }

    async _removerItem(li, nome) {
        const id = li.dataset.docId;
        try {
            if (id) {
                await this.deleteFn(id);
            }
            li.remove();
            this.arquivos = this.arquivos.filter((a) => a.el !== li);
            this.statusEl.textContent = this.i18n.removido(nome);
            announceToLiveRegion(this.i18n.removido(nome));
        } catch (err) {
            this._mostrarErro((err && err.message) || this.i18n.erroEnvio);
        }
    }

    _mostrarErro(msg) {
        this.erroEl.textContent = msg;
        this.erroEl.hidden = false;
        this.input.setAttribute('aria-invalid', 'true');
        announceToLiveRegion(msg, 'assertive');
    }

    _limparErro() {
        this.erroEl.textContent = '';
        this.erroEl.hidden = true;
        this.input.removeAttribute('aria-invalid');
    }

    /**
     * @returns {{id, nome, tamanho}[]}
     */
    getArquivos() {
        return this.arquivos.map((a) => ({ id: a.id, nome: a.nome, tamanho: a.tamanho }));
    }
}

/**
 * Auto-instancia FileUpload em todos elementos com [data-pi-fileupload].
 * @param {ParentNode} scope
 * @param {{uploadFn: Function, deleteFn: Function}} apis
 */
export function initFileUploads(scope, apis) {
    const instances = [];
    scope.querySelectorAll('[data-pi-fileupload]').forEach((el) => {
        if (el._piFileUpload) {
            instances.push(el._piFileUpload);
            return;
        }
        const inst = new FileUpload(el, {
            uploadFn: apis.uploadFn,
            deleteFn: apis.deleteFn,
        });
        el._piFileUpload = inst;
        instances.push(inst);
    });
    return instances;
}
