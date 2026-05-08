/**
 * VotacaoApp.js
 *
 * Orquestrador da UI publica de votacao Participe Ibram. Implementa a maquina
 * de estados, anti-double-vote client-side, navegacao por teclado em radiogroups,
 * integracao com modal de confirmacao e exibicao de recibo.
 *
 * Estados:
 *   loading        — buscando elegibilidade
 *   not-eligible   — agente nao elegivel em nenhuma categoria
 *   not-voted      — pelo menos uma categoria pendente
 *   confirming     — modal de confirmacao aberto
 *   submitting     — POST /votacao/registrar em andamento
 *   voted          — todas categorias elegiveis votadas (ou voto registrado em uma)
 *   error          — erro irrecuperavel (votacao encerrada, nao logado)
 *
 * Fluxo:
 *   1. GET /pi/v1/votacao/{id}/elegibilidade
 *   2. Para cada categoria elegivel ainda nao votada -> GET candidatos
 *   3. Click em candidato -> abrir modal de confirmacao (countdown 3s)
 *   4. Confirmar -> POST /votacao/registrar (anti-double-click + AbortController)
 *   5. 201 -> mostra recibo + estado voted-na-categoria
 *      409 -> "Voto ja registrado anteriormente" + estado voted
 *      410 -> votacao encerrada, desabilita interface
 *      401/403 -> redireciona login com retorno
 *
 * Conformidade WCAG 2.1 AA:
 *   - 3.3.4 Error Prevention (Legal): modal explicito + countdown 3s
 *   - 4.1.3 Status Messages: live region polite para todos estados
 *   - 1.3.1 Info and Relationships: fieldset/legend, role=radiogroup
 *   - 2.1.1 Keyboard: Tab + Setas + Enter + Space + ESC
 *   - 2.4.7 Focus Visible: gerenciado por CSS
 *
 * @module votacao/VotacaoApp
 */

import { ApiClientVotacao, ApiError } from './ApiClientVotacao.js';
import { CandidatoCard } from './CandidatoCard.js';
import { ConfirmacaoVoto } from './ConfirmacaoVoto.js';
import { Recibo } from './Recibo.js';

const STATES = Object.freeze({
    LOADING: 'loading',
    NOT_ELIGIBLE: 'not-eligible',
    NOT_VOTED: 'not-voted',
    CONFIRMING: 'confirming',
    SUBMITTING: 'submitting',
    VOTED: 'voted',
    ERROR: 'error',
});

function i18n(key, fallback) {
    const dict = (typeof window !== 'undefined' && window.piI18n) || {};
    return (dict && dict[key]) || fallback;
}

function safeText(v) {
    return v === null || v === undefined ? '' : String(v);
}

function clearChildren(el) {
    while (el && el.firstChild) {
        el.removeChild(el.firstChild);
    }
}

export class VotacaoApp {
    /**
     * @param {HTMLElement} root
     * @param {{apiUrl:string, nonce:string, votacaoId:number, loginUrl?:string,
     *          auditoriaUrlBase?:string}} options
     */
    constructor(root, options) {
        if (!root) throw new Error('VotacaoApp requires root element');
        if (!options || !options.apiUrl) throw new Error('VotacaoApp requires options.apiUrl');
        if (!options.votacaoId) throw new Error('VotacaoApp requires options.votacaoId');

        this.root = root;
        this.opts = {
            apiUrl: options.apiUrl,
            nonce: options.nonce || '',
            votacaoId: Number(options.votacaoId),
            loginUrl: options.loginUrl || '',
            auditoriaUrlBase: options.auditoriaUrlBase || '',
        };

        this.api = new ApiClientVotacao({ apiUrl: this.opts.apiUrl, nonce: this.opts.nonce });
        this.state = STATES.LOADING;
        this.categorias = []; // [{ categoria_id, nome, elegivel, ja_votou, candidatos:[CandidatoCard] }]
        this.cardsByCategoria = new Map(); // categoria_id -> [CandidatoCard]
        this.submitController = null;
        this.submitting = false; // hard guard anti-double-submit
        this.confirmacao = null;

        this._cacheDom();
        this._initConfirmacao();
        this._initRecibo();
        this._render();

        // Cancelar submit em navigation
        this._beforeUnload = () => {
            if (this.submitController) {
                try { this.submitController.abort(); } catch (_e) { /* noop */ }
            }
        };
        window.addEventListener('beforeunload', this._beforeUnload);

        // Iniciar
        this._loadElegibilidade();
    }

    // --- DOM caching --------------------------------------------------------

    _cacheDom() {
        this.elLoading = this.root.querySelector('[data-pi-votacao-loading]');
        this.elError = this.root.querySelector('[data-pi-votacao-error]');
        this.elContainerCategorias = this.root.querySelector('[data-pi-votacao-categorias]');
        this.elNotEligible = this.root.querySelector('[data-pi-votacao-not-eligible]');
        this.elClosed = this.root.querySelector('[data-pi-votacao-closed]');
        this.elLive = this.root.querySelector('[data-pi-votacao-live]') || this._ensureLive();
        this.elReciboContainer = this.root.querySelector('[data-pi-recibo]');
        this.elModal = this.root.querySelector('[data-pi-confirmacao-modal]');
    }

    _ensureLive() {
        const el = document.createElement('div');
        el.setAttribute('data-pi-votacao-live', '');
        el.id = 'pi-votacao-live';
        el.className = 'sr-only';
        el.setAttribute('role', 'status');
        el.setAttribute('aria-live', 'polite');
        el.setAttribute('aria-atomic', 'true');
        this.root.appendChild(el);
        return el;
    }

    _initConfirmacao() {
        if (this.elModal) {
            this.confirmacao = new ConfirmacaoVoto(this.elModal);
        }
    }

    _initRecibo() {
        if (this.elReciboContainer) {
            this.recibo = new Recibo(this.elReciboContainer, {
                liveRegion: this.elLive,
                auditoriaUrlBase: this.opts.auditoriaUrlBase,
            });
        }
    }

    // --- Live region --------------------------------------------------------

    announce(msg) {
        if (!this.elLive) return;
        this.elLive.textContent = '';
        window.requestAnimationFrame(() => {
            this.elLive.textContent = String(msg || '');
        });
    }

    // --- State machine ------------------------------------------------------

    setState(newState, payload = null) {
        this.state = newState;
        this._render(payload);
    }

    /**
     * Renderiza UI baseado em this.state. Cada transicao limpa apenas o
     * que precisa, sem destruir o resto do DOM.
     */
    _render(payload) {
        const show = (el, on) => { if (el) el.hidden = !on; };

        show(this.elLoading, this.state === STATES.LOADING);
        show(this.elError, this.state === STATES.ERROR);
        show(this.elNotEligible, this.state === STATES.NOT_ELIGIBLE);
        show(this.elContainerCategorias,
            this.state === STATES.NOT_VOTED ||
            this.state === STATES.CONFIRMING ||
            this.state === STATES.SUBMITTING ||
            this.state === STATES.VOTED);

        if (this.state === STATES.ERROR && payload && payload.message && this.elError) {
            this.elError.textContent = String(payload.message);
        }

        // Bloqueia interacao durante submit
        if (this.state === STATES.SUBMITTING) {
            this._setCardsBusy(true);
        } else {
            this._setCardsBusy(false);
        }
    }

    _setCardsBusy(busy) {
        this.cardsByCategoria.forEach((cards) => {
            cards.forEach((c) => c.setDisabled(busy || c.options.__categoriaJaVotada === true));
        });
    }

    // --- Carregamento inicial -----------------------------------------------

    async _loadElegibilidade() {
        this.announce(i18n('carregando', 'Carregando informações da votação…'));
        try {
            const data = await this.api.getElegibilidade(this.opts.votacaoId);
            const cats = (data && Array.isArray(data.categorias)) ? data.categorias : [];
            const elegiveis = cats.filter((c) => c.elegivel);
            this.categorias = elegiveis.map((c) => ({
                categoria_id: Number(c.categoria_id),
                nome: safeText(c.nome),
                elegivel: true,
                ja_votou: Boolean(c.ja_votou),
                motivo_inelegibilidade: safeText(c.motivo_inelegibilidade || ''),
            }));

            if (this.categorias.length === 0) {
                this.setState(STATES.NOT_ELIGIBLE, {
                    message: i18n('semCategorias',
                        'Você não está elegível para votar em nenhuma categoria desta votação.'),
                });
                this.announce(i18n('semCategorias',
                    'Você não está elegível para votar em nenhuma categoria desta votação.'));
                return;
            }

            this.setState(STATES.NOT_VOTED);
            this._renderCategorias();
            await this._loadCandidatosPendentes();
        } catch (err) {
            this._handleApiError(err, true);
        }
    }

    _renderCategorias() {
        if (!this.elContainerCategorias) return;
        clearChildren(this.elContainerCategorias);

        this.categorias.forEach((cat) => {
            const fieldset = document.createElement('fieldset');
            fieldset.className = 'pi-votacao-categoria';
            fieldset.dataset.categoriaId = String(cat.categoria_id);

            const legend = document.createElement('legend');
            legend.className = 'pi-votacao-categoria__legenda';
            legend.textContent = i18n('categoriaPrefixo', 'Categoria: ') + cat.nome;
            fieldset.appendChild(legend);

            const status = document.createElement('p');
            status.className = 'pi-votacao-categoria__status';
            status.dataset.piCategoriaStatus = '1';
            status.setAttribute('aria-live', 'polite');
            if (cat.ja_votou) {
                status.classList.add('pi-votacao-categoria__status--votado');
                status.textContent = i18n('jaVotou',
                    'Voto já registrado nesta categoria.');
            } else {
                status.textContent = i18n('selecioneCandidato',
                    'Selecione um candidato e confirme seu voto.');
            }
            fieldset.appendChild(status);

            const radiogroup = document.createElement('div');
            radiogroup.setAttribute('role', 'radiogroup');
            radiogroup.setAttribute('aria-label', cat.nome);
            radiogroup.dataset.piRadiogroup = '1';
            radiogroup.dataset.categoriaId = String(cat.categoria_id);
            fieldset.appendChild(radiogroup);

            this.elContainerCategorias.appendChild(fieldset);
        });
    }

    async _loadCandidatosPendentes() {
        const promessas = this.categorias
            .filter((c) => !c.ja_votou)
            .map((c) => this._loadCandidatosCategoria(c));

        if (promessas.length === 0) {
            // Todas ja votadas
            this.setState(STATES.VOTED);
            this.announce(i18n('todasVotadas', 'Todas as suas categorias já tiveram voto registrado.'));
            return;
        }
        await Promise.allSettled(promessas);
    }

    async _loadCandidatosCategoria(categoria) {
        const fs = this.elContainerCategorias.querySelector(
            `fieldset[data-categoria-id="${categoria.categoria_id}"]`
        );
        if (!fs) return;
        const radiogroup = fs.querySelector('[data-pi-radiogroup]');
        if (!radiogroup) return;

        try {
            const resp = await this.api.getCandidatos(this.opts.votacaoId, categoria.categoria_id);
            const lista = (resp && Array.isArray(resp.candidatos)) ? resp.candidatos : (Array.isArray(resp) ? resp : []);

            clearChildren(radiogroup);
            const cards = [];
            lista.forEach((cand, idx) => {
                const card = new CandidatoCard(cand, {
                    nameGroup: `categoria-${categoria.categoria_id}`,
                    labelVotar: i18n('votarNeste', 'Votar neste candidato'),
                    onVotar: (c) => this._onCandidatoVotar(categoria, c),
                });
                card.options.__categoriaJaVotada = categoria.ja_votou;
                if (categoria.ja_votou) {
                    card.setDisabled(true);
                }
                if (idx === 0) {
                    card.setTabbable(true);
                }
                radiogroup.appendChild(card.getElement());
                cards.push(card);
            });
            this.cardsByCategoria.set(categoria.categoria_id, cards);
            this._wireRadiogroupKeys(radiogroup, cards);
        } catch (err) {
            const aviso = document.createElement('p');
            aviso.className = 'pi-aviso pi-aviso--erro';
            aviso.setAttribute('role', 'alert');
            aviso.textContent = (err && err.message) || i18n('erroCandidatos', 'Erro ao carregar candidatos.');
            radiogroup.appendChild(aviso);
        }
    }

    /**
     * Implementa navegacao por setas dentro de um role=radiogroup
     * (WCAG 2.1.1 + ARIA Authoring Practices).
     */
    _wireRadiogroupKeys(radiogroup, cards) {
        if (!radiogroup || !cards.length) return;

        const focusIdx = (idx) => {
            const i = ((idx % cards.length) + cards.length) % cards.length;
            cards.forEach((c, j) => c.setTabbable(j === i));
            cards[i].focus();
        };

        radiogroup.addEventListener('keydown', (ev) => {
            const target = ev.target;
            const idx = cards.findIndex((c) => c.getElement() === target);
            if (idx < 0) return;
            switch (ev.key) {
                case 'ArrowDown':
                case 'ArrowRight':
                    ev.preventDefault();
                    focusIdx(idx + 1);
                    break;
                case 'ArrowUp':
                case 'ArrowLeft':
                    ev.preventDefault();
                    focusIdx(idx - 1);
                    break;
                case 'Home':
                    ev.preventDefault();
                    focusIdx(0);
                    break;
                case 'End':
                    ev.preventDefault();
                    focusIdx(cards.length - 1);
                    break;
                default:
                    break;
            }
        });
    }

    // --- Voto ---------------------------------------------------------------

    async _onCandidatoVotar(categoria, card) {
        if (this.submitting) {
            // Anti-double-click hard guard
            return;
        }
        if (categoria.ja_votou) {
            this.announce(i18n('jaVotou', 'Voto já registrado nesta categoria.'));
            return;
        }
        if (!this.confirmacao) {
            // Sem modal — evita votar sem confirmacao explicita
            this._showError(i18n('semModal', 'Erro de configuração da página. Recarregue.'));
            return;
        }

        this.setState(STATES.CONFIRMING);

        const triggerEl = card.getElement();
        const confirmado = await this.confirmacao.abrir({
            nomeCandidato: card.nomePublico,
            numeroRegistro: card.numeroRegistro,
            nomeCategoria: categoria.nome,
            trigger: triggerEl,
        });

        if (!confirmado) {
            this.setState(STATES.NOT_VOTED);
            this.announce(i18n('cancelado', 'Voto cancelado.'));
            return;
        }

        await this._submitVoto(categoria, card);
    }

    async _submitVoto(categoria, card) {
        if (this.submitting) return; // duplo guard
        this.submitting = true;
        this.setState(STATES.SUBMITTING);
        this.announce(i18n('registrando', 'Registrando voto…'));

        if (this.submitController) {
            try { this.submitController.abort(); } catch (_e) { /* noop */ }
        }
        this.submitController = new AbortController();

        try {
            const resp = await this.api.registrarVoto({
                votacaoId: this.opts.votacaoId,
                categoriaId: categoria.categoria_id,
                candidatoInscricaoId: card.inscricaoId,
            }, this.submitController.signal);

            // 201 esperado
            categoria.ja_votou = true;
            this._marcarCategoriaVotada(categoria);
            this.submitting = false;
            this.submitController = null;

            const reciboData = {
                hash_voto: (resp && resp.hash_voto) || '',
                votacao_id: this.opts.votacaoId,
                categoria_id: categoria.categoria_id,
                categoria_nome: categoria.nome,
                candidato_nome: card.nomePublico,
                registrado_em: (resp && resp.registrado_em) || new Date().toISOString(),
            };
            if (this.recibo) {
                this.recibo.set(reciboData);
            }

            this.announce(i18n('votoRegistrado', 'Voto registrado com sucesso. Recibo emitido.'));
            this.setState(this._calcularEstadoPosVoto());
        } catch (err) {
            this.submitting = false;
            this.submitController = null;
            this._handleVotoError(err, categoria, card);
        }
    }

    _calcularEstadoPosVoto() {
        const todasVotadas = this.categorias.every((c) => c.ja_votou);
        return todasVotadas ? STATES.VOTED : STATES.NOT_VOTED;
    }

    _marcarCategoriaVotada(categoria) {
        const fs = this.elContainerCategorias.querySelector(
            `fieldset[data-categoria-id="${categoria.categoria_id}"]`
        );
        if (!fs) return;
        fs.classList.add('is-voted');
        const status = fs.querySelector('[data-pi-categoria-status]');
        if (status) {
            status.classList.add('pi-votacao-categoria__status--votado');
            status.textContent = i18n('jaVotou', 'Voto já registrado nesta categoria.');
        }
        const cards = this.cardsByCategoria.get(categoria.categoria_id) || [];
        cards.forEach((c) => {
            c.options.__categoriaJaVotada = true;
            c.setDisabled(true);
        });
    }

    _handleVotoError(err, categoria, card) {
        if (err && err.code === 'aborted') {
            // navegacao — silencioso
            return;
        }
        if (err instanceof ApiError) {
            switch (err.status) {
                case 401:
                case 403:
                    this._redirectToLogin();
                    return;
                case 409: {
                    // Voto ja registrado - tratar como sucesso silencioso de estado
                    categoria.ja_votou = true;
                    this._marcarCategoriaVotada(categoria);
                    this.announce(i18n('duplicado', 'Voto já registrado anteriormente nesta categoria.'));
                    this.setState(this._calcularEstadoPosVoto());
                    return;
                }
                case 410: {
                    // Votacao encerrada — desabilita interface
                    this._showClosed(err.message);
                    return;
                }
                case 422: {
                    this.announce(err.message || i18n('inelegivel', 'Você não está habilitado para esta categoria.'));
                    this._showAvisoCategoria(categoria, err.message);
                    this.setState(STATES.NOT_VOTED);
                    return;
                }
                default:
                    break;
            }
        }
        const msg = (err && err.message) || i18n('erroGenerico', 'Não foi possível registrar o voto. Tente novamente.');
        this.announce(msg);
        this._showAvisoCategoria(categoria, msg);
        this.setState(STATES.NOT_VOTED);
    }

    _showAvisoCategoria(categoria, msg) {
        const fs = this.elContainerCategorias.querySelector(
            `fieldset[data-categoria-id="${categoria.categoria_id}"]`
        );
        if (!fs) return;
        let aviso = fs.querySelector('.pi-aviso--erro');
        if (!aviso) {
            aviso = document.createElement('p');
            aviso.className = 'pi-aviso pi-aviso--erro';
            aviso.setAttribute('role', 'alert');
            fs.insertBefore(aviso, fs.firstChild.nextSibling);
        }
        aviso.textContent = String(msg || '');
    }

    _showClosed(msg) {
        if (this.elClosed) {
            this.elClosed.hidden = false;
            const m = this.elClosed.querySelector('[data-pi-closed-msg]');
            if (m) m.textContent = String(msg || i18n('encerrada', 'Votação encerrada.'));
        }
        if (this.elContainerCategorias) {
            this.elContainerCategorias.setAttribute('aria-disabled', 'true');
        }
        // Desabilita todos os cards
        this.cardsByCategoria.forEach((cards) => cards.forEach((c) => c.setDisabled(true)));
        this.setState(STATES.ERROR, { message: msg || i18n('encerrada', 'Votação encerrada.') });
        this.announce(msg || i18n('encerrada', 'Votação encerrada.'));
    }

    _showError(msg) {
        this.setState(STATES.ERROR, { message: msg });
        this.announce(msg);
    }

    _redirectToLogin() {
        const back = window.location.href;
        const base = this.opts.loginUrl ||
            (typeof window !== 'undefined' && window.piVotacaoConfig && window.piVotacaoConfig.loginUrl) ||
            '/wp-login.php';
        const sep = base.indexOf('?') >= 0 ? '&' : '?';
        const url = `${base}${sep}redirect_to=${encodeURIComponent(back)}`;
        this.announce(i18n('sessaoExpirada', 'Sessão expirada. Redirecionando para login…'));
        window.location.assign(url);
    }

    _handleApiError(err, isInitial) {
        if (err && err.code === 'aborted') return;
        if (err instanceof ApiError) {
            if (err.status === 401 || err.status === 403) {
                this._redirectToLogin();
                return;
            }
            if (err.status === 410) {
                this._showClosed(err.message);
                return;
            }
        }
        const msg = (err && err.message) || i18n('erroCarregar',
            'Não foi possível carregar a votação. Tente novamente.');
        if (isInitial) {
            this._showError(msg);
        } else {
            this.announce(msg);
        }
    }

    destroy() {
        if (this.submitController) {
            try { this.submitController.abort(); } catch (_e) { /* noop */ }
        }
        if (this._beforeUnload) {
            window.removeEventListener('beforeunload', this._beforeUnload);
        }
    }
}

VotacaoApp.STATES = STATES;

export { STATES };
export default VotacaoApp;
