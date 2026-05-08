/**
 * ConsentForm.js
 *
 * Componente de consentimento granular alinhado com R2 §3 + LGPD.md §3.
 * 10 finalidades: 2 obrigatorias (consentimento como base legal nao se aplica
 * a obrigacoes legais; mas para fins de UI pintamos como "necessarias") + 8
 * opcionais. Valida que obrigatorias estao marcadas antes do submit.
 *
 * Triggers o modal LGPD (id="pi-modal-help-lgpd") via "Ver termo completo".
 *
 * @module wizard/ConsentForm
 */

import { announceToLiveRegion } from './AccessibilityHelpers.js';

/**
 * @typedef {Object} Finalidade
 * @property {string} codigo
 * @property {string} rotulo
 * @property {string} descricao
 * @property {string} baseLegal  ex: "art. 7º, II - obrigacao legal"
 * @property {boolean} obrigatoria
 */

/** @type {Finalidade[]} */
export const FINALIDADES = [
    {
        codigo: 'cadastro',
        rotulo: 'Manutenção do cadastro como agente de participação',
        descricao: 'Tratamento dos dados informados para identificá-lo como agente cadastrado e permitir sua participação em editais e consultas do Ibram.',
        baseLegal: 'LGPD art. 7º, II — cumprimento de obrigação legal pelo controlador (Portaria IBRAM 3230/2024).',
        obrigatoria: true,
    },
    {
        codigo: 'comunicacao',
        rotulo: 'Comunicações oficiais sobre seu cadastro',
        descricao: 'Envio de e-mails sobre análise, deferimento, indeferimento, recursos e outras decisões sobre o seu próprio cadastro.',
        baseLegal: 'LGPD art. 7º, II — cumprimento de obrigação legal.',
        obrigatoria: true,
    },
    {
        codigo: 'editais',
        rotulo: 'Notificação sobre novos editais',
        descricao: 'Receber e-mails sobre abertura de editais aplicáveis ao seu perfil.',
        baseLegal: 'LGPD art. 7º, I — consentimento.',
        obrigatoria: false,
    },
    {
        codigo: 'votacao',
        rotulo: 'Participação em votações eletrônicas',
        descricao: 'Habilitar seu cadastro como eleitor em votações do CCDEM e outras instâncias.',
        baseLegal: 'LGPD art. 7º, I — consentimento; art. 11, II, "a" para dados sensíveis quando aplicável.',
        obrigatoria: false,
    },
    {
        codigo: 'estatistica',
        rotulo: 'Geração de estatísticas agregadas e anonimizadas',
        descricao: 'Uso dos seus dados, sem identificação pessoal, para relatórios públicos do setor museal.',
        baseLegal: 'LGPD art. 7º, IV — estudos por órgão de pesquisa.',
        obrigatoria: false,
    },
    {
        codigo: 'pesquisa',
        rotulo: 'Convites para pesquisas e consultas públicas',
        descricao: 'Convidá-lo a responder pesquisas voluntárias do Ibram.',
        baseLegal: 'LGPD art. 7º, I — consentimento.',
        obrigatoria: false,
    },
    {
        codigo: 'newsletter',
        rotulo: 'Boletim informativo do Ibram',
        descricao: 'Receber periodicamente novidades sobre museus e patrimônio.',
        baseLegal: 'LGPD art. 7º, I — consentimento.',
        obrigatoria: false,
    },
    {
        codigo: 'eventos',
        rotulo: 'Convites para eventos e fóruns',
        descricao: 'Receber convites para o Fórum Nacional de Museus e demais eventos.',
        baseLegal: 'LGPD art. 7º, I — consentimento.',
        obrigatoria: false,
    },
    {
        codigo: 'compartilhamento_orgaos',
        rotulo: 'Compartilhamento com órgãos parceiros do MinC',
        descricao: 'Compartilhamento controlado com órgãos do Ministério da Cultura, mediante convênio formal.',
        baseLegal: 'LGPD art. 7º, III — execução de políticas públicas pelo controlador.',
        obrigatoria: false,
    },
    {
        codigo: 'dados_sensiveis',
        rotulo: 'Tratamento de dados sensíveis (raça/cor, identidade de gênero, orientação sexual, PCT, PCD)',
        descricao: 'Você pode optar por informar esses dados para fins de políticas afirmativas. A recusa não impede seu cadastro.',
        baseLegal: 'LGPD art. 11, II, "a" — consentimento específico e destacado.',
        obrigatoria: false,
    },
];

const TXT = {
    obrigatoriaNaoMarcada: 'Você precisa concordar com as finalidades obrigatórias para continuar.',
    verTermo: 'Ver termo completo de consentimento',
    obrigatoria: 'Necessária',
    opcional: 'Opcional',
    bloqueio: 'Não foi possível submeter: marque as finalidades obrigatórias.',
};

export class ConsentForm {
    /**
     * @param {HTMLElement} containerEl  elemento [data-pi-consent]
     * @param {object} [opts]
     * @param {string} [opts.versaoTermo]
     * @param {string} [opts.modalLgpdId]
     */
    constructor(containerEl, opts = {}) {
        if (!containerEl) {
            throw new Error('ConsentForm requires container');
        }
        this.root = containerEl;
        this.versaoTermo = opts.versaoTermo || this.root.dataset.versaoTermo || '1.0';
        this.modalLgpdId = opts.modalLgpdId || 'pi-modal-help-lgpd';
        this._render();
    }

    _render() {
        // Limpa apenas conteudo dinamico, preserva legend se existir
        const dyn = this.root.querySelector('.pi-consent__lista');
        if (dyn) dyn.remove();

        const lista = document.createElement('div');
        lista.className = 'pi-consent__lista';

        FINALIDADES.forEach((f) => {
            lista.appendChild(this._renderFinalidade(f));
        });
        this.root.appendChild(lista);

        // Botao "Ver termo completo"
        if (!this.root.querySelector('.pi-consent__ver-termo')) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'pi-consent__ver-termo';
            btn.textContent = TXT.verTermo;
            btn.setAttribute('aria-haspopup', 'dialog');
            btn.setAttribute('aria-controls', this.modalLgpdId);
            btn.setAttribute('data-pi-modal-open', this.modalLgpdId);
            this.root.appendChild(btn);
        }

        // Hidden input com versao do termo
        if (!this.root.querySelector('input[name="termo_versao"]')) {
            const v = document.createElement('input');
            v.type = 'hidden';
            v.name = 'termo_versao';
            v.value = this.versaoTermo;
            this.root.appendChild(v);
        }
    }

    /**
     * @param {Finalidade} f
     * @returns {HTMLElement}
     */
    _renderFinalidade(f) {
        const wrap = document.createElement('div');
        wrap.className = 'pi-consent__item';
        if (f.obrigatoria) wrap.classList.add('is-obrigatoria');

        const checkboxId = `pi-consent-finalidade-${f.codigo}`;
        const descId = `${checkboxId}-desc`;
        const baseId = `${checkboxId}-base`;

        const cb = document.createElement('input');
        cb.type = 'checkbox';
        cb.id = checkboxId;
        cb.name = `consentimento[${f.codigo}]`;
        cb.value = '1';
        cb.setAttribute('aria-describedby', `${descId} ${baseId}`);
        cb.dataset.finalidade = f.codigo;
        if (f.obrigatoria) {
            cb.checked = true;
            cb.required = true;
            cb.setAttribute('aria-required', 'true');
        }

        const label = document.createElement('label');
        label.setAttribute('for', checkboxId);
        // textContent garante seguranca contra XSS
        const tag = document.createElement('span');
        tag.className = 'pi-consent__tag';
        tag.textContent = f.obrigatoria ? TXT.obrigatoria : TXT.opcional;
        label.appendChild(tag);
        const rot = document.createElement('span');
        rot.className = 'pi-consent__rotulo';
        rot.textContent = ' ' + f.rotulo;
        label.appendChild(rot);

        const desc = document.createElement('p');
        desc.className = 'pi-consent__descricao';
        desc.id = descId;
        desc.textContent = f.descricao;

        const base = document.createElement('p');
        base.className = 'pi-consent__base-legal';
        base.id = baseId;
        base.textContent = `Base legal: ${f.baseLegal}`;

        wrap.appendChild(cb);
        wrap.appendChild(label);
        wrap.appendChild(desc);
        wrap.appendChild(base);
        return wrap;
    }

    /**
     * Valida que finalidades obrigatorias estao marcadas.
     * @returns {{ok: boolean, message: string}}
     */
    validar() {
        const obrigatorias = FINALIDADES.filter((f) => f.obrigatoria);
        for (const f of obrigatorias) {
            const cb = this.root.querySelector(`#pi-consent-finalidade-${f.codigo}`);
            if (!cb || !cb.checked) {
                announceToLiveRegion(TXT.bloqueio, 'assertive');
                if (cb) cb.focus();
                return { ok: false, message: TXT.obrigatoriaNaoMarcada };
            }
        }
        return { ok: true, message: '' };
    }

    /**
     * Snapshot dos consentimentos atuais para envio ao backend.
     * @returns {{versao: string, finalidades: Object<string, boolean>, dataHora: string}}
     */
    snapshot() {
        const finalidades = {};
        FINALIDADES.forEach((f) => {
            const cb = this.root.querySelector(`#pi-consent-finalidade-${f.codigo}`);
            finalidades[f.codigo] = !!(cb && cb.checked);
        });
        return {
            versao: this.versaoTermo,
            finalidades,
            dataHora: new Date().toISOString(),
        };
    }
}
