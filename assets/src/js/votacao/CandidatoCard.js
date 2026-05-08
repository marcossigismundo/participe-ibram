/**
 * CandidatoCard.js
 *
 * Componente de exibicao de candidato dentro de um radiogroup ARIA. NAO usa
 * innerHTML com dados externos (XSS): tudo via createElement/textContent.
 *
 * HTML gerado:
 *   <div role="radio" aria-checked="false" tabindex="-1" class="pi-candidato-card">
 *     <img alt="..." />        (opcional)
 *     <div class="pi-candidato-card__nome">...</div>
 *     <div class="pi-candidato-card__numero">Nº ...</div>
 *     <button class="pi-candidato-card__voto-btn">Votar neste candidato</button>
 *   </div>
 *
 * Whitelist de campos exibidos: nome_publico, numero_registro, foto_url, inscricao_id.
 * NUNCA exibir cpf/email/telefone (privacidade — checagem dupla mesmo se backend
 * vazasse acidentalmente).
 *
 * Navegacao por setas dentro do radiogroup e gerenciada pelo container
 * (CategoriaSection em VotacaoApp). Esta classe apenas expoe focus()/setSelected().
 *
 * @module votacao/CandidatoCard
 */

const PRIVATE_FIELDS = ['cpf', 'email', 'telefone', 'data_nascimento', 'rg'];

function safeText(value) {
    if (value === null || value === undefined) {
        return '';
    }
    return String(value);
}

function isHttpUrl(url) {
    if (typeof url !== 'string' || !url) {
        return false;
    }
    return /^https?:\/\//i.test(url);
}

export class CandidatoCard {
    /**
     * @param {{
     *   inscricaoId:number,
     *   nome_publico:string,
     *   numero_registro:string|number,
     *   foto_url?:string,
     * }} candidato
     * @param {{
     *   onVotar:(c:CandidatoCard)=>void,
     *   labelVotar:string,
     *   nameGroup:string,
     * }} options
     */
    constructor(candidato, options) {
        if (!candidato || !options || typeof options.onVotar !== 'function') {
            throw new Error('CandidatoCard requires candidato and options.onVotar');
        }

        // Defesa em profundidade: zerar campos privativos caso backend vaze
        const seguro = { ...candidato };
        PRIVATE_FIELDS.forEach((k) => { delete seguro[k]; });

        this.inscricaoId = Number(seguro.inscricaoId || seguro.inscricao_id || 0);
        this.nomePublico = safeText(seguro.nome_publico || seguro.nome || '');
        this.numeroRegistro = safeText(seguro.numero_registro || seguro.numero || '');
        this.fotoUrl = isHttpUrl(seguro.foto_url) ? String(seguro.foto_url) : '';
        this.options = options;
        this.selecionado = false;
        this.disabled = false;

        this.element = this._build();
    }

    /** @returns {HTMLElement} */
    getElement() {
        return this.element;
    }

    setSelected(selected) {
        this.selecionado = Boolean(selected);
        this.element.setAttribute('aria-checked', this.selecionado ? 'true' : 'false');
        this.element.classList.toggle('is-selected', this.selecionado);
        this.element.setAttribute('tabindex', this.selecionado ? '0' : '-1');
    }

    setTabbable(tabbable) {
        this.element.setAttribute('tabindex', tabbable ? '0' : '-1');
    }

    setDisabled(disabled) {
        this.disabled = Boolean(disabled);
        if (this.disabled) {
            this.element.setAttribute('aria-disabled', 'true');
            this.element.classList.add('is-disabled');
            const btn = this.element.querySelector('.pi-candidato-card__voto-btn');
            if (btn) {
                btn.disabled = true;
            }
        } else {
            this.element.removeAttribute('aria-disabled');
            this.element.classList.remove('is-disabled');
            const btn = this.element.querySelector('.pi-candidato-card__voto-btn');
            if (btn) {
                btn.disabled = false;
            }
        }
    }

    focus() {
        this.element.focus();
    }

    _build() {
        const root = document.createElement('div');
        root.className = 'pi-candidato-card';
        root.setAttribute('role', 'radio');
        root.setAttribute('aria-checked', 'false');
        root.setAttribute('tabindex', '-1');
        root.dataset.inscricaoId = String(this.inscricaoId);
        root.dataset.numeroRegistro = String(this.numeroRegistro);

        // Foto (opcional). Nunca usar innerHTML.
        if (this.fotoUrl) {
            const img = document.createElement('img');
            img.className = 'pi-candidato-card__foto';
            img.src = this.fotoUrl;
            img.alt = `Foto de ${this.nomePublico}`;
            img.loading = 'lazy';
            img.decoding = 'async';
            img.width = 96;
            img.height = 96;
            img.addEventListener('error', () => { img.remove(); });
            root.appendChild(img);
        }

        const corpo = document.createElement('div');
        corpo.className = 'pi-candidato-card__corpo';

        const nome = document.createElement('div');
        nome.className = 'pi-candidato-card__nome';
        nome.textContent = this.nomePublico || '—';
        corpo.appendChild(nome);

        const numero = document.createElement('div');
        numero.className = 'pi-candidato-card__numero';
        const lblNumero = document.createElement('span');
        lblNumero.className = 'pi-candidato-card__numero-label';
        lblNumero.textContent = 'Nº de registro: ';
        numero.appendChild(lblNumero);
        const valNumero = document.createElement('span');
        valNumero.className = 'pi-candidato-card__numero-valor';
        valNumero.textContent = this.numeroRegistro || '—';
        numero.appendChild(valNumero);
        corpo.appendChild(numero);

        root.appendChild(corpo);

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'pi-candidato-card__voto-btn pi-btn pi-btn--primario';
        btn.textContent = this.options.labelVotar || 'Votar neste candidato';
        btn.setAttribute('aria-label',
            `${this.options.labelVotar || 'Votar'}: ${this.nomePublico || 'candidato'}`);
        root.appendChild(btn);

        // Eventos: clique no botao OU enter/espaco no proprio role=radio
        const ativar = (ev) => {
            if (this.disabled) return;
            if (ev) {
                ev.preventDefault();
                ev.stopPropagation();
            }
            this.options.onVotar(this);
        };
        btn.addEventListener('click', ativar);
        // Click no proprio card tambem ativa (mas nao via children que nao sao o botao)
        root.addEventListener('click', (ev) => {
            if (ev.target === btn || (ev.target && ev.target.closest && ev.target.closest('.pi-candidato-card__voto-btn'))) {
                return; // ja tratado
            }
            ativar(ev);
        });
        root.addEventListener('keydown', (ev) => {
            if (this.disabled) return;
            if (ev.key === ' ' || ev.key === 'Enter') {
                ativar(ev);
            }
        });

        return root;
    }
}

export default CandidatoCard;
