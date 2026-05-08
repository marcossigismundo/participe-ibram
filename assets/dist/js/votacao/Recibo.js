/**
 * Recibo.js
 *
 * Componente de exibicao do recibo de voto (hash_voto). Permite copiar para
 * area de transferencia (com fallback para navegadores sem clipboard API),
 * imprimir comprovante e abrir versao imprimivel.
 *
 * Renderiza dentro de um container <div role="region" aria-labelledby="..." hidden>
 * existente no template votacao-app.php (sem innerHTML com dados externos).
 *
 * @module votacao/Recibo
 */

function i18n(key, fallback) {
    const dict = (typeof window !== 'undefined' && window.piI18n) || {};
    return (dict && dict[key]) || fallback;
}

function safeText(value) {
    if (value === null || value === undefined) return '';
    return String(value);
}

export class Recibo {
    /**
     * @param {HTMLElement} containerEl  elemento root do recibo (escondido inicialmente)
     * @param {{
     *   liveRegion?:HTMLElement,
     *   printUrlBase?:string,
     *   auditoriaUrlBase?:string,
     * }} [options]
     */
    constructor(containerEl, options = {}) {
        if (!containerEl) {
            throw new Error('Recibo requires container element');
        }
        this.container = containerEl;
        this.liveRegion = options.liveRegion || null;
        this.printUrlBase = options.printUrlBase || '';
        this.auditoriaUrlBase = options.auditoriaUrlBase || '';
        this._buildSkeleton();
    }

    _buildSkeleton() {
        // Esvaziar e construir estrutura base
        while (this.container.firstChild) {
            this.container.removeChild(this.container.firstChild);
        }

        const titulo = document.createElement('h2');
        titulo.className = 'pi-recibo__titulo';
        titulo.id = this.container.getAttribute('aria-labelledby') || 'pi-recibo-title';
        if (!this.container.getAttribute('aria-labelledby')) {
            this.container.setAttribute('aria-labelledby', titulo.id);
        }
        titulo.textContent = i18n('reciboTitulo', 'Voto registrado com sucesso');
        titulo.setAttribute('tabindex', '-1');
        this.container.appendChild(titulo);

        const aviso = document.createElement('p');
        aviso.className = 'pi-recibo__aviso';
        aviso.textContent = i18n(
            'reciboAviso',
            'Guarde este recibo. Ele comprova que você votou e permite verificar a integridade da votação na auditoria pública.'
        );
        this.container.appendChild(aviso);

        const dl = document.createElement('dl');
        dl.className = 'pi-recibo__dados';
        this.container.appendChild(dl);
        this.elDados = dl;

        // Caixa do hash
        const wrap = document.createElement('div');
        wrap.className = 'pi-recibo__hash-wrap';
        this.container.appendChild(wrap);

        const hashLabel = document.createElement('label');
        hashLabel.className = 'pi-recibo__hash-label';
        hashLabel.htmlFor = 'pi-recibo-hash';
        hashLabel.textContent = i18n('reciboHashLabel', 'Código do recibo (hash):');
        wrap.appendChild(hashLabel);

        const hashInput = document.createElement('input');
        hashInput.type = 'text';
        hashInput.id = 'pi-recibo-hash';
        hashInput.className = 'pi-recibo__hash';
        hashInput.readOnly = true;
        hashInput.setAttribute('aria-readonly', 'true');
        hashInput.spellcheck = false;
        wrap.appendChild(hashInput);
        this.elHash = hashInput;

        // Botoes
        const btns = document.createElement('div');
        btns.className = 'pi-recibo__acoes';

        const btnCopiar = document.createElement('button');
        btnCopiar.type = 'button';
        btnCopiar.className = 'pi-btn pi-btn--secundario pi-recibo__btn-copiar';
        btnCopiar.textContent = i18n('reciboCopiar', 'Copiar recibo');
        btnCopiar.addEventListener('click', () => this.copiar());
        btns.appendChild(btnCopiar);
        this.btnCopiar = btnCopiar;

        const btnImprimir = document.createElement('button');
        btnImprimir.type = 'button';
        btnImprimir.className = 'pi-btn pi-btn--secundario pi-recibo__btn-imprimir';
        btnImprimir.textContent = i18n('reciboImprimir', 'Imprimir comprovante');
        btnImprimir.addEventListener('click', () => this.imprimir());
        btns.appendChild(btnImprimir);

        const btnPdf = document.createElement('button');
        btnPdf.type = 'button';
        btnPdf.className = 'pi-btn pi-btn--terciario pi-recibo__btn-pdf';
        btnPdf.textContent = i18n('reciboPdf', 'Baixar PDF (via impressão)');
        btnPdf.addEventListener('click', () => this.imprimir());
        btns.appendChild(btnPdf);

        // Link auditoria publica (sera atualizado em set())
        const linkAud = document.createElement('a');
        linkAud.className = 'pi-btn pi-btn--terciario pi-recibo__link-auditoria';
        linkAud.target = '_blank';
        linkAud.rel = 'noopener';
        linkAud.textContent = i18n('reciboAuditoria', 'Verificar na auditoria pública');
        linkAud.href = '#';
        linkAud.hidden = true;
        btns.appendChild(linkAud);
        this.linkAuditoria = linkAud;

        this.container.appendChild(btns);

        // Status apos copiar (live region local)
        const status = document.createElement('p');
        status.className = 'pi-recibo__status sr-only';
        status.setAttribute('role', 'status');
        status.setAttribute('aria-live', 'polite');
        this.container.appendChild(status);
        this.elStatus = status;
    }

    /**
     * Define os dados do recibo e exibe.
     *
     * @param {{
     *   hash_voto:string,
     *   votacao_id:number|string,
     *   categoria_id:number|string,
     *   categoria_nome?:string,
     *   candidato_nome?:string,
     *   registrado_em?:string,
     * }} dados
     */
    set(dados) {
        const d = dados || {};
        // Limpar dl
        while (this.elDados.firstChild) {
            this.elDados.removeChild(this.elDados.firstChild);
        }

        const linhas = [
            ['reciboCategoriaLabel', 'Categoria:', safeText(d.categoria_nome || d.categoria_id || '—')],
            ['reciboCandidatoLabel', 'Candidato:', safeText(d.candidato_nome || '—')],
            ['reciboDataLabel', 'Data e hora:', this._formatarData(d.registrado_em)],
            ['reciboVotacaoLabel', 'Votação:', safeText(d.votacao_id || '—')],
        ];

        linhas.forEach(([k, label, valor]) => {
            const dt = document.createElement('dt');
            dt.textContent = i18n(k, label);
            const dd = document.createElement('dd');
            dd.textContent = valor;
            this.elDados.appendChild(dt);
            this.elDados.appendChild(dd);
        });

        this.elHash.value = safeText(d.hash_voto || '');

        if (this.auditoriaUrlBase && d.votacao_id) {
            const sep = this.auditoriaUrlBase.indexOf('?') >= 0 ? '&' : '?';
            this.linkAuditoria.href = `${this.auditoriaUrlBase}${sep}hash=${encodeURIComponent(d.hash_voto || '')}`;
            this.linkAuditoria.hidden = false;
        } else {
            this.linkAuditoria.hidden = true;
        }

        this.container.hidden = false;
        // Foco no titulo para o leitor de tela anunciar
        const titulo = this.container.querySelector('.pi-recibo__titulo');
        if (titulo && typeof titulo.focus === 'function') {
            window.requestAnimationFrame(() => titulo.focus());
        }
        if (this.liveRegion) {
            this.liveRegion.textContent = i18n('reciboAnuncio', 'Voto registrado com sucesso. Recibo emitido.');
        }
    }

    /**
     * Copia o hash para a area de transferencia. Usa Clipboard API se
     * disponivel; fallback via document.execCommand('copy').
     *
     * @returns {Promise<boolean>}
     */
    async copiar() {
        const valor = this.elHash.value;
        if (!valor) {
            this._anunciar(i18n('reciboCopiarVazio', 'Nenhum recibo para copiar.'));
            return false;
        }

        // Disable button briefly to prevent double-fire
        if (this.btnCopiar) {
            this.btnCopiar.disabled = true;
            window.setTimeout(() => { if (this.btnCopiar) this.btnCopiar.disabled = false; }, 500);
        }

        // Tentativa 1: Clipboard API (precisa secure context)
        if (navigator && navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
            try {
                await navigator.clipboard.writeText(valor);
                this._anunciar(i18n('reciboCopiado', 'Recibo copiado para a área de transferência.'));
                return true;
            } catch (_e) {
                // segue fallback
            }
        }

        // Fallback: selecionar input e execCommand
        try {
            this.elHash.removeAttribute('readonly');
            this.elHash.focus();
            this.elHash.select();
            this.elHash.setSelectionRange(0, valor.length);
            const ok = document.execCommand && document.execCommand('copy');
            this.elHash.setAttribute('readonly', 'readonly');
            if (ok) {
                this._anunciar(i18n('reciboCopiado', 'Recibo copiado para a área de transferência.'));
                return true;
            }
        } catch (_e) {
            // ignore
        }
        this._anunciar(i18n('reciboCopiarFalha', 'Não foi possível copiar automaticamente. Selecione o texto manualmente.'));
        return false;
    }

    /**
     * Aciona window.print() apos garantir que conteudo do recibo esta visivel.
     * O CSS @media print aplica stylesheet especifica.
     */
    imprimir() {
        // Forcar visibilidade pre-print
        this.container.classList.add('is-printing');
        try {
            window.print();
        } finally {
            window.setTimeout(() => {
                this.container.classList.remove('is-printing');
            }, 200);
        }
    }

    _anunciar(msg) {
        if (this.elStatus) {
            this.elStatus.textContent = '';
            window.requestAnimationFrame(() => {
                this.elStatus.textContent = msg;
            });
        }
        if (this.liveRegion) {
            this.liveRegion.textContent = msg;
        }
    }

    _formatarData(iso) {
        if (!iso) return '—';
        try {
            const d = new Date(iso);
            if (Number.isNaN(d.getTime())) return String(iso);
            return d.toLocaleString('pt-BR', {
                day: '2-digit', month: '2-digit', year: 'numeric',
                hour: '2-digit', minute: '2-digit', second: '2-digit',
            });
        } catch (_e) {
            return String(iso);
        }
    }
}

export default Recibo;
