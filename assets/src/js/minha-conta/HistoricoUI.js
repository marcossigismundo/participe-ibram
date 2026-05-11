/**
 * HistoricoUI.js
 *
 * Aba "Histórico" da Minha Conta. Sub-tabs ARIA com navegação por setas,
 * paginação on-click, modal de recibo via ApiClientHistorico.
 *
 * Voto secreto (CRÍTICO):
 *  - Esta UI assume contrato de servidor: NUNCA renderiza `candidato_inscricao_id`.
 *  - A renderização do bloco "Votos" usa whitelist explícita de chaves.
 *  - O modal de recibo mostra apenas hash + timestamp + votacao_id — jamais
 *    descreve o candidato.
 *
 * Acessibilidade:
 *  - role=tablist / role=tab / role=tabpanel
 *  - setas Esq/Dir/Home/End nas tabs (padrão WAI-ARIA APG)
 *  - tabindex management (somente a aba ativa recebe tabindex=0)
 *  - aria-live polite para anúncios de carregamento e cópia
 *
 * @module minha-conta/HistoricoUI
 */

import { ApiClientHistorico } from './ApiClientHistorico.js';

const I18N_FALLBACKS = {
    loading: 'Carregando…',
    erro: 'Falha ao carregar. Tente novamente.',
    rateLimited: 'Muitas requisições. Aguarde um instante.',
    copiado: 'Recibo copiado para a área de transferência.',
    copiarFalha: 'Não foi possível copiar.',
    recibo404: 'Recibo não encontrado para esta votação.',
    paginaLabel: 'Página {n}',
};

function i18n(key, fallback) {
    const dict = (typeof window !== 'undefined' && window.piI18nHistorico) || {};
    return (dict && dict[key]) || fallback || I18N_FALLBACKS[key] || '';
}

function safeText(v) {
    if (v === null || v === undefined) return '';
    return String(v);
}

function formatDate(iso) {
    if (!iso) return '—';
    try {
        const d = new Date(iso.replace(' ', 'T') + 'Z');
        if (Number.isNaN(d.getTime())) return safeText(iso);
        return d.toLocaleString('pt-BR', {
            day: '2-digit', month: '2-digit', year: 'numeric',
            hour: '2-digit', minute: '2-digit',
        });
    } catch (_e) {
        return safeText(iso);
    }
}

function el(tag, attrs = {}, children = []) {
    const node = document.createElement(tag);
    Object.entries(attrs).forEach(([k, v]) => {
        if (v === false || v === null || v === undefined) return;
        if (k === 'class') node.className = v;
        else if (k === 'text') node.textContent = v;
        else node.setAttribute(k, String(v));
    });
    (Array.isArray(children) ? children : [children]).forEach((c) => {
        if (c == null) return;
        if (typeof c === 'string') node.appendChild(document.createTextNode(c));
        else node.appendChild(c);
    });
    return node;
}

/**
 * Whitelist defensiva — ignora qualquer chave inesperada vinda do servidor.
 * Particularmente importante para o array de votos.
 */
const WHITELIST_VOTO = ['votacao_id', 'edital_titulo', 'categoria_nome', 'votado_em', 'recibo_recuperavel'];

function sanitizarVoto(raw) {
    const out = {};
    WHITELIST_VOTO.forEach((k) => {
        if (raw && Object.prototype.hasOwnProperty.call(raw, k)) {
            out[k] = raw[k];
        }
    });
    return out;
}

export class HistoricoUI {
    /**
     * @param {HTMLElement} rootEl
     * @param {{apiUrl:string, restNonce:string}} opts
     */
    constructor(rootEl, opts) {
        if (!rootEl) throw new Error('HistoricoUI requires rootEl');
        this.root = rootEl;
        this.api = new ApiClientHistorico(opts);
        this.tabs = Array.from(rootEl.querySelectorAll('[role="tab"]'));
        this.panels = Array.from(rootEl.querySelectorAll('[role="tabpanel"]'));
        this.live = rootEl.querySelector('[data-pi-historico-live]');
        this.modal = rootEl.querySelector('[data-pi-recibo-modal]');
        this._loaded = { cadastro: false, inscricoes: false, recursos: false, votos: false, auditoria: false };
        this._page = { inscricoes: 1, auditoria: 1 };
        this._previouslyFocused = null;
        this._bindTabs();
        this._bindModal();
    }

    init() {
        // Carrega a aba inicial (cadastro).
        this._activate('cadastro');
    }

    _announce(msg) {
        if (this.live) {
            this.live.textContent = '';
            window.requestAnimationFrame(() => {
                this.live.textContent = msg;
            });
        }
    }

    _bindTabs() {
        this.tabs.forEach((tab, idx) => {
            tab.addEventListener('click', () => {
                const key = tab.getAttribute('data-pi-tab');
                if (key) this._activate(key);
            });
            tab.addEventListener('keydown', (e) => {
                let nextIdx = null;
                if (e.key === 'ArrowRight') nextIdx = (idx + 1) % this.tabs.length;
                else if (e.key === 'ArrowLeft') nextIdx = (idx - 1 + this.tabs.length) % this.tabs.length;
                else if (e.key === 'Home') nextIdx = 0;
                else if (e.key === 'End') nextIdx = this.tabs.length - 1;

                if (nextIdx !== null) {
                    e.preventDefault();
                    const nextTab = this.tabs[nextIdx];
                    const key = nextTab.getAttribute('data-pi-tab');
                    if (key) this._activate(key, true);
                }
            });
        });
    }

    _activate(key, focus = false) {
        this.tabs.forEach((tab) => {
            const isActive = tab.getAttribute('data-pi-tab') === key;
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
            tab.setAttribute('tabindex', isActive ? '0' : '-1');
            if (isActive && focus) tab.focus();
        });
        this.panels.forEach((panel) => {
            const id = panel.getAttribute('id') || '';
            panel.hidden = id !== `pi-panel-${key}`;
        });

        if (!this._loaded[key]) {
            this._loadPanel(key);
        }
    }

    async _loadPanel(key) {
        this._announce(i18n('loading'));
        try {
            switch (key) {
                case 'cadastro': {
                    const data = await this.api.getCadastroTimeline();
                    this._renderCadastro(data && data.items ? data.items : []);
                    break;
                }
                case 'inscricoes': {
                    const data = await this.api.getInscricoes(this._page.inscricoes, 20);
                    this._renderInscricoes(data && data.items ? data.items : [], data || {});
                    break;
                }
                case 'recursos': {
                    const data = await this.api.getRecursos();
                    this._renderRecursos(data && data.items ? data.items : []);
                    break;
                }
                case 'votos': {
                    const data = await this.api.getVotos();
                    this._renderVotos(data && data.items ? data.items : []);
                    break;
                }
                case 'auditoria': {
                    const data = await this.api.getAuditTrail(this._page.auditoria, 20);
                    this._renderAuditoria(data && data.items ? data.items : [], data || {});
                    break;
                }
            }
            this._loaded[key] = true;
        } catch (e) {
            this._renderErro(key, e);
        }
    }

    _emptyList(key) {
        const list = this.root.querySelector(`[data-pi-list="${key}"]`);
        if (list) {
            while (list.firstChild) list.removeChild(list.firstChild);
        }
        return list;
    }

    _toggleEmpty(key, isEmpty) {
        const emptyEl = this.root.querySelector(`[data-pi-empty="${key}"]`);
        if (emptyEl) emptyEl.hidden = !isEmpty;
    }

    _renderErro(key, err) {
        const list = this._emptyList(key);
        if (!list) return;
        const msg = err && err.status === 429
            ? i18n('rateLimited')
            : i18n('erro');
        list.appendChild(el('li', { class: 'pi-historico__error', role: 'alert', text: msg }));
        this._toggleEmpty(key, false);
        this._announce(msg);
    }

    _renderCadastro(items) {
        const list = this._emptyList('cadastro');
        if (!list) return;
        if (!items.length) {
            this._toggleEmpty('cadastro', true);
            return;
        }
        this._toggleEmpty('cadastro', false);
        items.forEach((it) => {
            const li = el('li', { class: 'pi-historico__timeline-item' }, [
                el('time', { class: 'pi-historico__when', datetime: safeText(it.ocorrido_em), text: formatDate(it.ocorrido_em) }),
                el('div', { class: 'pi-historico__transicao' }, [
                    el('span', { class: 'pi-historico__status-antes', text: safeText(it.status_anterior) }),
                    el('span', { class: 'pi-historico__seta', 'aria-hidden': 'true', text: '→' }),
                    el('span', { class: 'pi-historico__status-novo', text: safeText(it.status_novo) }),
                ]),
                it.observacao
                    ? el('p', { class: 'pi-historico__observacao', text: safeText(it.observacao) })
                    : null,
            ]);
            list.appendChild(li);
        });
    }

    _renderInscricoes(items, meta) {
        const list = this._emptyList('inscricoes');
        if (!list) return;
        if (!items.length) {
            this._toggleEmpty('inscricoes', true);
            return;
        }
        this._toggleEmpty('inscricoes', false);
        items.forEach((it) => {
            const card = el('li', { class: 'pi-historico__card pi-historico__card--inscricao' }, [
                el('header', { class: 'pi-historico__card-header' }, [
                    el('h4', { class: 'pi-historico__card-title', text: safeText(it.edital_titulo) || `#${it.edital_id}` }),
                    el('span', {
                        class: `pi-historico__badge pi-historico__badge--${safeText(it.status).replace(/_/g, '-')}`,
                        text: safeText(it.status),
                    }),
                ]),
                el('p', { class: 'pi-historico__card-meta', text: safeText(it.categoria_nome) }),
                el('dl', { class: 'pi-historico__card-dl' }, [
                    el('dt', { text: 'Inscrito em:' }),
                    el('dd', { text: formatDate(it.inscrito_em) }),
                    el('dt', { text: 'Habilitado em:' }),
                    el('dd', { text: formatDate(it.habilitado_em) }),
                    el('dt', { text: 'Inabilitado em:' }),
                    el('dd', { text: formatDate(it.inabilitado_em) }),
                ]),
                it.motivo_inabilitacao_md
                    ? el('details', { class: 'pi-historico__motivo' }, [
                        el('summary', { text: 'Motivo da inabilitação' }),
                        el('p', { text: safeText(it.motivo_inabilitacao_md) }),
                    ])
                    : null,
            ]);
            list.appendChild(card);
        });

        this._bindPaginacao('inscricoes', meta);
    }

    _renderRecursos(items) {
        const list = this._emptyList('recursos');
        if (!list) return;
        if (!items.length) {
            this._toggleEmpty('recursos', true);
            return;
        }
        this._toggleEmpty('recursos', false);
        items.forEach((it) => {
            const tipoLabel = it.tipo === 'inabilitacao' ? 'Recurso de inabilitação' : 'Recurso de cadastro';
            const card = el('li', { class: 'pi-historico__card pi-historico__card--recurso' }, [
                el('header', { class: 'pi-historico__card-header' }, [
                    el('h4', { class: 'pi-historico__card-title', text: tipoLabel }),
                    it.fase && it.fase !== 'unica'
                        ? el('span', { class: 'pi-historico__chip', text: `Fase: ${safeText(it.fase)}` })
                        : null,
                ]),
                el('dl', { class: 'pi-historico__card-dl' }, [
                    el('dt', { text: 'Protocolado em:' }),
                    el('dd', { text: formatDate(it.protocolado_em) }),
                    it.prazo_fim ? el('dt', { text: 'Prazo até:' }) : null,
                    it.prazo_fim ? el('dd', { text: formatDate(it.prazo_fim) }) : null,
                    el('dt', { text: 'Decisão:' }),
                    el('dd', { text: safeText(it.decisao) || '—' }),
                    it.decidido_em ? el('dt', { text: 'Decidido em:' }) : null,
                    it.decidido_em ? el('dd', { text: formatDate(it.decidido_em) }) : null,
                ]),
                it.decisao_md
                    ? el('details', { class: 'pi-historico__motivo' }, [
                        el('summary', { text: 'Fundamentação da decisão' }),
                        el('p', { text: safeText(it.decisao_md) }),
                    ])
                    : null,
            ]);
            list.appendChild(card);
        });
    }

    _renderVotos(items) {
        const list = this._emptyList('votos');
        if (!list) return;
        if (!items.length) {
            this._toggleEmpty('votos', true);
            return;
        }
        this._toggleEmpty('votos', false);

        // CRÍTICO: passa por sanitizar — qualquer chave fora da whitelist é descartada.
        items.map(sanitizarVoto).forEach((it) => {
            const card = el('li', { class: 'pi-historico__card pi-historico__card--voto' }, [
                el('header', { class: 'pi-historico__card-header' }, [
                    el('h4', {
                        class: 'pi-historico__card-title',
                        text: safeText(it.edital_titulo) || `Votação #${it.votacao_id}`,
                    }),
                ]),
                el('p', { class: 'pi-historico__card-meta', text: safeText(it.categoria_nome) }),
                el('p', { class: 'pi-historico__card-meta' }, [
                    el('time', { datetime: safeText(it.votado_em), text: formatDate(it.votado_em) }),
                ]),
            ]);

            if (it.recibo_recuperavel) {
                const btn = el('button', {
                    type: 'button',
                    class: 'pi-btn pi-btn--terciario pi-historico__btn-recibo',
                    'aria-label': `Regerar recibo da votação ${safeText(it.votacao_id)}`,
                    text: 'Ver recibo',
                });
                btn.addEventListener('click', () => this._abrirRecibo(it.votacao_id));
                card.appendChild(btn);
            }

            list.appendChild(card);
        });
    }

    _renderAuditoria(items, meta) {
        const list = this._emptyList('auditoria');
        if (!list) return;
        if (!items.length) {
            this._toggleEmpty('auditoria', true);
            return;
        }
        this._toggleEmpty('auditoria', false);

        // Whitelist defensiva — descarta qualquer chave inesperada.
        items.forEach((raw) => {
            const it = {
                entidade: raw.entidade,
                acao: raw.acao,
                ocorrido_em: raw.ocorrido_em,
                descricao_amigavel: raw.descricao_amigavel,
            };
            const li = el('li', { class: 'pi-historico__timeline-item pi-historico__timeline-item--audit' }, [
                el('time', { class: 'pi-historico__when', datetime: safeText(it.ocorrido_em), text: formatDate(it.ocorrido_em) }),
                el('p', { class: 'pi-historico__audit-desc', text: safeText(it.descricao_amigavel) }),
            ]);
            list.appendChild(li);
        });

        this._bindPaginacao('auditoria', meta);
    }

    _bindPaginacao(key, meta) {
        const nav = this.root.querySelector(`[data-pi-paginacao="${key}"]`);
        if (!nav) return;
        const total = Number(meta.total || 0);
        const perPage = Number(meta.per_page || 20);
        const page = Number(meta.page || this._page[key] || 1);
        const totalPages = Math.max(1, Math.ceil(total / perPage));
        const showPag = total > perPage || page > 1;
        nav.hidden = !showPag;
        if (!showPag) return;

        const label = nav.querySelector('[data-pi-page-label]');
        if (label) {
            label.textContent = i18n('paginaLabel').replace('{n}', page) + ` / ${totalPages}`;
        }
        const prev = nav.querySelector('[data-pi-page-prev]');
        const next = nav.querySelector('[data-pi-page-next]');
        if (prev) {
            prev.disabled = page <= 1;
            prev.onclick = () => {
                this._page[key] = Math.max(1, page - 1);
                this._loaded[key] = false;
                this._loadPanel(key);
            };
        }
        if (next) {
            next.disabled = page >= totalPages;
            next.onclick = () => {
                this._page[key] = Math.min(totalPages, page + 1);
                this._loaded[key] = false;
                this._loadPanel(key);
            };
        }
    }

    // ------------------------------------------------------------------
    // Modal de recibo
    // ------------------------------------------------------------------

    _bindModal() {
        if (!this.modal) return;
        this.modal.addEventListener('click', (e) => {
            const t = e.target;
            if (t && t instanceof Element && t.matches('[data-pi-modal-close]')) {
                this._fecharModal();
            }
        });
        document.addEventListener('keydown', (e) => {
            if (!this.modal || this.modal.hidden) return;
            if (e.key === 'Escape') {
                e.preventDefault();
                this._fecharModal();
            }
            if (e.key === 'Tab') {
                this._trapTab(e);
            }
        });
        const btnCopiar = this.modal.querySelector('[data-pi-recibo-copiar]');
        if (btnCopiar) {
            btnCopiar.addEventListener('click', () => this._copiarHash());
        }
    }

    _trapTab(e) {
        const focusable = this.modal.querySelectorAll(
            'a[href],button:not([disabled]),input:not([disabled]),[tabindex]:not([tabindex="-1"])'
        );
        if (focusable.length === 0) return;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
        }
    }

    async _abrirRecibo(votacaoId) {
        if (!this.modal) return;
        const id = Number(votacaoId);
        if (!Number.isInteger(id) || id <= 0) return;

        // Sanitiza campos do modal antes da chamada.
        this._setReciboModal({ votacao_id: id, votado_em: '—', hash_voto: '' });
        this._announce(i18n('loading'));

        try {
            const data = await this.api.regerarRecibo(id);
            // Whitelist no cliente — apenas hash_voto e votado_em.
            this._setReciboModal({
                votacao_id: id,
                votado_em: data && data.votado_em ? data.votado_em : '—',
                hash_voto: data && data.hash_voto ? data.hash_voto : '',
            });
            this._mostrarModal();
        } catch (e) {
            const msg = e && e.status === 404
                ? i18n('recibo404')
                : (e && e.status === 429 ? i18n('rateLimited') : i18n('erro'));
            this._announce(msg);
            // Ainda assim mostra modal com mensagem de erro (acessível).
            this._setReciboModal({ votacao_id: id, votado_em: '—', hash_voto: '', erro: msg });
            this._mostrarModal();
        }
    }

    _setReciboModal({ votacao_id, votado_em, hash_voto, erro }) {
        if (!this.modal) return;
        const votEl = this.modal.querySelector('[data-pi-recibo-votacao]');
        const dataEl = this.modal.querySelector('[data-pi-recibo-votado-em]');
        const hashEl = this.modal.querySelector('[data-pi-recibo-hash]');
        const statusEl = this.modal.querySelector('[data-pi-recibo-status]');
        if (votEl) votEl.textContent = safeText(votacao_id);
        if (dataEl) dataEl.textContent = votado_em && votado_em !== '—' ? formatDate(votado_em) : '—';
        if (hashEl) hashEl.value = safeText(hash_voto);
        if (statusEl) statusEl.textContent = erro ? safeText(erro) : '';
    }

    _mostrarModal() {
        if (!this.modal) return;
        this._previouslyFocused = document.activeElement;
        this.modal.hidden = false;
        const title = this.modal.querySelector('#pi-historico-recibo-title');
        if (title && typeof title.focus === 'function') {
            window.requestAnimationFrame(() => title.focus());
        }
    }

    _fecharModal() {
        if (!this.modal) return;
        this.modal.hidden = true;
        if (this._previouslyFocused && typeof this._previouslyFocused.focus === 'function') {
            this._previouslyFocused.focus();
        }
        this._previouslyFocused = null;
    }

    async _copiarHash() {
        if (!this.modal) return;
        const hashEl = this.modal.querySelector('[data-pi-recibo-hash]');
        const statusEl = this.modal.querySelector('[data-pi-recibo-status]');
        if (!hashEl || !hashEl.value) return;

        const value = hashEl.value;
        let ok = false;
        if (navigator && navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
            try {
                await navigator.clipboard.writeText(value);
                ok = true;
            } catch (_e) {
                ok = false;
            }
        }
        if (!ok) {
            try {
                hashEl.removeAttribute('readonly');
                hashEl.focus();
                hashEl.select();
                ok = !!(document.execCommand && document.execCommand('copy'));
                hashEl.setAttribute('readonly', 'readonly');
            } catch (_e) {
                ok = false;
            }
        }
        const msg = ok ? i18n('copiado') : i18n('copiarFalha');
        if (statusEl) {
            statusEl.textContent = '';
            window.requestAnimationFrame(() => { statusEl.textContent = msg; });
        }
        this._announce(msg);
    }
}

/**
 * Bootstrap automático quando há um root no DOM e config global expõe URL/nonce.
 */
export function bootstrapHistoricoUI() {
    if (typeof window === 'undefined' || typeof document === 'undefined') return null;
    const root = document.querySelector('[data-pi-historico-root]');
    if (!root) return null;
    const cfg = (window.piHistoricoConfig || {});
    if (!cfg.apiUrl) return null;
    const ui = new HistoricoUI(root, {
        apiUrl: cfg.apiUrl,
        restNonce: cfg.restNonce || '',
    });
    ui.init();
    return ui;
}

export default HistoricoUI;
