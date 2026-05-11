/**
 * MinhaContaApp.js
 *
 * Orquestrador da area autenticada do agente:
 *  - Tabs ARIA (Tab/setas/Home/End).
 *  - Deep-link via querystring `?aba=...` ja resolvido server-side; client
 *    apenas atualiza estado e fala com live region.
 *  - Carrega dashboard / cadastro via REST.
 *  - Integra EdicaoDadosForm e RevelacaoSensivel.
 *
 * @module minha-conta/MinhaContaApp
 */

import { ApiClientMinhaConta } from './ApiClientMinhaConta.js';
import { EdicaoDadosForm } from './EdicaoDadosForm.js';
import { RevelacaoSensivel } from './RevelacaoSensivel.js';

const CAMPOS_EDITAVEIS_POR_STATUS = {
    rascunho: [],
    submetido: [],
    em_analise: [],
    deferido: ['email_principal', 'telefone', 'nome_social', 'cidade_residencia', 'estado_residencia', 'bairro_residencia', 'cidade_sede', 'estado_sede', 'bairro_sede', 'apresentacao_md', 'recursos_acessibilidade'],
    deferido_em_retratacao: ['email_principal', 'telefone', 'nome_social', 'cidade_residencia', 'estado_residencia', 'bairro_residencia', 'cidade_sede', 'estado_sede', 'bairro_sede', 'apresentacao_md', 'recursos_acessibilidade'],
    deferido_em_recurso: ['email_principal', 'telefone', 'nome_social', 'cidade_residencia', 'estado_residencia', 'bairro_residencia', 'cidade_sede', 'estado_sede', 'bairro_sede', 'apresentacao_md', 'recursos_acessibilidade'],
    indeferido_aguardando_recurso: [],
    em_retratacao: [],
    em_recurso_presidencia: [],
    indeferido_final: [],
};

export class MinhaContaApp {
    /**
     * @param {HTMLElement} root  Container com [data-pi-minha-conta].
     */
    constructor(root) {
        this.root = root;
        const cfg = MinhaContaApp.readConfig(root);
        this.api = new ApiClientMinhaConta(cfg);
        this.live = root.querySelector('#pi-minha-conta-live') || document.createElement('div');
        this.abaAtual = cfg.abaAtual || 'dashboard';
        this._cadastroCache = null;

        this._initTabs();

        if (this.abaAtual === 'dashboard') {
            this._initDashboard();
        }
        if (this.abaAtual === 'dados') {
            this._initDadosTab();
        }
    }

    static readConfig(root) {
        const el = root.querySelector('#pi-minha-conta-config');
        if (!el || !el.textContent) {
            return { apiUrl: '', nonce: '', abaAtual: 'dashboard' };
        }
        try {
            return JSON.parse(el.textContent);
        } catch (_) {
            return { apiUrl: '', nonce: '', abaAtual: 'dashboard' };
        }
    }

    announce(msg) {
        if (!this.live) return;
        this.live.textContent = '';
        // Forca o anuncio mesmo se o texto for repetido.
        setTimeout(() => { this.live.textContent = String(msg || ''); }, 50);
    }

    _initTabs() {
        const tablist = this.root.querySelector('[role="tablist"]');
        if (!tablist) return;
        const tabs = Array.prototype.slice.call(tablist.querySelectorAll('[role="tab"]'));

        tablist.addEventListener('keydown', (e) => {
            const idx = tabs.indexOf(document.activeElement);
            if (idx === -1) return;
            let next = null;
            if (e.key === 'ArrowRight') next = tabs[(idx + 1) % tabs.length];
            else if (e.key === 'ArrowLeft') next = tabs[(idx - 1 + tabs.length) % tabs.length];
            else if (e.key === 'Home') next = tabs[0];
            else if (e.key === 'End') next = tabs[tabs.length - 1];
            if (next) {
                e.preventDefault();
                next.focus();
            }
        });
    }

    async _initDashboard() {
        try {
            const d = await this.api.getDashboard();
            this._renderDashboard(d);
            this.announce('Dashboard carregado.');
        } catch (err) {
            this.announce('Erro ao carregar o dashboard.');
        }
    }

    _renderDashboard(d) {
        if (!d || d.has_cadastro === false) return;

        // Status badge.
        const status = this.root.querySelector('[data-pi-mc-status]');
        if (status) {
            const map = {
                rascunho: 'Rascunho',
                submetido: 'Submetido',
                em_analise: 'Em analise',
                deferido: 'Deferido',
                deferido_em_retratacao: 'Deferido (retratacao)',
                deferido_em_recurso: 'Deferido (recurso)',
                indeferido_aguardando_recurso: 'Indeferido — recurso disponivel',
                em_retratacao: 'Em retratacao',
                em_recurso_presidencia: 'Em recurso de presidencia',
                indeferido_final: 'Indeferido (final)',
            };
            status.textContent = map[d.status_cadastro] || d.status_cadastro || '—';
            status.setAttribute('data-status', d.status_cadastro || '');
        }

        // Numero de registro.
        const wrap = this.root.querySelector('[data-pi-mc-numero-registro]');
        if (wrap) {
            if (d.numero_registro) {
                wrap.hidden = false;
                const val = wrap.querySelector('[data-pi-mc-numero-registro-value]');
                if (val) val.textContent = d.numero_registro;
                const copy = wrap.querySelector('[data-pi-mc-copy]');
                if (copy && !copy._bound) {
                    copy._bound = true;
                    copy.addEventListener('click', () => this._copy(d.numero_registro));
                }
            } else {
                wrap.hidden = true;
            }
        }

        // Proximos passos.
        const proximos = this.root.querySelector('[data-pi-mc-proximos]');
        if (proximos) {
            proximos.textContent = '';
            (d.proximos_passos || []).forEach((p) => {
                const li = document.createElement('li');
                li.className = p.concluido ? 'is-concluido' : '';
                const t = document.createElement('strong');
                t.textContent = p.titulo;
                const desc = document.createElement('p');
                desc.textContent = p.descricao;
                li.appendChild(t);
                li.appendChild(desc);
                proximos.appendChild(li);
            });
            if (!proximos.firstChild) {
                const li = document.createElement('li');
                li.textContent = 'Nenhum passo pendente.';
                proximos.appendChild(li);
            }
        }

        // Pendencias.
        const pend = this.root.querySelector('[data-pi-mc-pendencias]');
        if (pend) {
            pend.textContent = '';
            const items = d.pendencias || [];
            if (items.length === 0) {
                const li = document.createElement('li');
                li.textContent = 'Nenhuma pendencia.';
                pend.appendChild(li);
            } else {
                items.forEach((it) => {
                    const li = document.createElement('li');
                    li.textContent = it.mensagem;
                    pend.appendChild(li);
                });
            }
        }

        // Timeline (mini): submetido -> deferido.
        const tl = this.root.querySelector('[data-pi-mc-timeline]');
        if (tl) {
            tl.textContent = '';
            const eventos = [
                d.submetido_em ? { quando: d.submetido_em, label: 'Cadastro submetido' } : null,
                d.deferido_em ? { quando: d.deferido_em, label: 'Cadastro deferido' } : null,
                d.publicado_em ? { quando: d.publicado_em, label: 'Publicado no site' } : null,
            ].filter(Boolean);
            if (eventos.length === 0) {
                const li = document.createElement('li');
                li.textContent = 'Sem eventos registrados.';
                tl.appendChild(li);
            } else {
                eventos.forEach((e) => {
                    const li = document.createElement('li');
                    li.textContent = `${e.label} — ${this._formatDate(e.quando)}`;
                    tl.appendChild(li);
                });
            }
        }
    }

    async _copy(text) {
        try {
            await navigator.clipboard.writeText(String(text));
            this.announce('Numero copiado.');
        } catch (_) {
            this.announce('Nao foi possivel copiar.');
        }
    }

    _formatDate(iso) {
        try {
            const d = new Date(iso);
            return d.toLocaleString('pt-BR');
        } catch (_) {
            return String(iso);
        }
    }

    async _initDadosTab() {
        try {
            const cadastro = await this.api.getCadastro();
            this._cadastroCache = cadastro;
            this._renderDados(cadastro);
            this._setupEditFormSeAplicavel(cadastro);
            this._setupRevelacao();
            this.announce('Dados carregados.');
        } catch (err) {
            this.announce('Erro ao carregar os dados.');
        }
    }

    _renderDados(cadastro) {
        // Basicos.
        const dlBas = this.root.querySelector('[data-pi-mc-secao="basicos"]');
        if (dlBas) {
            this._fillDd(dlBas, 'email_principal', cadastro.email_principal);
            this._fillDd(dlBas, 'telefone', cadastro.telefone);
            this._fillDd(dlBas, 'tipo', cadastro.tipo);
        }
        // Sensiveis (linhas dinamicas).
        const dlSens = this.root.querySelector('[data-pi-mc-secao="sensiveis"]');
        if (dlSens) {
            dlSens.textContent = '';
            ['cpf', 'rg', 'passaporte', 'cnpj', 'representante_cpf'].forEach((campo) => {
                if (cadastro[campo]) {
                    dlSens.appendChild(this._makeSensitiveRow(campo, cadastro[campo]));
                }
            });
        }
        // Endereco.
        const dlEnd = this.root.querySelector('[data-pi-mc-secao="endereco"]');
        if (dlEnd) {
            dlEnd.textContent = '';
            if (cadastro.tipo === 'PF') {
                this._appendDl(dlEnd, 'Cidade', cadastro.cidade_residencia);
                this._appendDl(dlEnd, 'UF', cadastro.estado_residencia);
                this._appendDl(dlEnd, 'Bairro', cadastro.bairro_residencia);
            } else {
                this._appendDl(dlEnd, 'Cidade (sede)', cadastro.cidade_sede || cadastro.municipio);
                this._appendDl(dlEnd, 'UF (sede)', cadastro.estado_sede || cadastro.uf);
                this._appendDl(dlEnd, 'Bairro (sede)', cadastro.bairro_sede);
            }
        }
        // Perfil.
        const dlPerf = this.root.querySelector('[data-pi-mc-secao="perfil"]');
        if (dlPerf) {
            dlPerf.textContent = '';
            if (cadastro.tipo === 'PF') {
                this._appendDl(dlPerf, 'Nome social', cadastro.nome_social);
            }
            this._appendDl(dlPerf, 'Apresentacao', cadastro.apresentacao_md);
        }
    }

    _fillDd(dl, campo, valor) {
        const dd = dl.querySelector(`dd[data-campo="${campo}"]`);
        if (dd) dd.textContent = (valor === null || valor === undefined || valor === '') ? '—' : String(valor);
    }

    _appendDl(dl, label, valor) {
        const row = document.createElement('div');
        row.className = 'pi-mc-dl__row';
        const dt = document.createElement('dt');
        dt.textContent = label;
        const dd = document.createElement('dd');
        dd.textContent = (valor === null || valor === undefined || valor === '') ? '—' : String(valor);
        row.appendChild(dt);
        row.appendChild(dd);
        dl.appendChild(row);
    }

    _makeSensitiveRow(campo, info) {
        const labelMap = {
            cpf: 'CPF',
            rg: 'RG',
            passaporte: 'Passaporte',
            cnpj: 'CNPJ',
            representante_cpf: 'CPF do representante',
        };
        const row = document.createElement('div');
        row.className = 'pi-mc-dl__row';
        const dt = document.createElement('dt');
        dt.textContent = labelMap[campo] || campo;
        const dd = document.createElement('dd');
        const code = document.createElement('code');
        code.setAttribute('data-pi-mc-campo-valor', '');
        code.textContent = info.value !== null && info.value !== undefined ? String(info.value) : '—';
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'pi-btn pi-btn--ghost pi-btn--sm';
        btn.setAttribute('data-pi-mc-reveal', '');
        btn.setAttribute('data-campo', campo);
        btn.setAttribute('aria-expanded', info.masked ? 'false' : 'true');
        btn.dataset.labelMostrar = 'Mostrar';
        btn.dataset.labelOcultar = 'Ocultar';
        btn.textContent = info.masked ? 'Mostrar' : 'Ocultar';
        dd.appendChild(code);
        dd.appendChild(document.createTextNode(' '));
        dd.appendChild(btn);
        row.appendChild(dt);
        row.appendChild(dd);
        return row;
    }

    _setupEditFormSeAplicavel(cadastro) {
        const editaveis = CAMPOS_EDITAVEIS_POR_STATUS[cadastro.status_cadastro] || [];
        // Ajusta whitelist por tipo (sem nome_social fora de PF; sem sede em PF; sem residencia fora de PF).
        const tipo = cadastro.tipo;
        const filtrados = editaveis.filter((c) => {
            if (c === 'nome_social' && tipo !== 'PF') return false;
            if ((c === 'cidade_residencia' || c === 'estado_residencia' || c === 'bairro_residencia') && tipo !== 'PF') return false;
            if ((c === 'cidade_sede' || c === 'estado_sede' || c === 'bairro_sede') && tipo === 'PF') return false;
            return true;
        });

        const btnEdit = this.root.querySelector('[data-pi-mc-editar]');
        const estadoMsg = this.root.querySelector('[data-pi-mc-estado-msg]');

        if (filtrados.length === 0) {
            if (btnEdit) btnEdit.hidden = true;
            if (estadoMsg) estadoMsg.textContent = 'Edicao bloqueada no estado atual do cadastro.';
            return;
        }
        if (btnEdit) btnEdit.hidden = false;
        if (estadoMsg) estadoMsg.textContent = '';

        const form = this.root.querySelector('#pi-mc-form-editar');
        const fieldsContainer = this.root.querySelector('[data-pi-mc-form-fields]');
        const submitBtn = this.root.querySelector('[data-pi-mc-submit]');
        const errorsContainer = this.root.querySelector('[data-pi-mc-form-errors]');
        if (!form || !fieldsContainer || !submitBtn || !errorsContainer) return;

        const onSaved = () => {
            const modal = document.getElementById('pi-modal-mc-editar');
            if (modal && modal._piModalInstance) {
                modal._piModalInstance.fechar();
            }
            // Recarrega cadastro.
            this._initDadosTab();
        };

        this._editForm = new EdicaoDadosForm(
            form,
            fieldsContainer,
            submitBtn,
            errorsContainer,
            this.api,
            (m) => this.announce(m),
            onSaved
        );
        this._editForm.render(cadastro, filtrados);
    }

    _setupRevelacao() {
        const root = this.root.querySelector('[data-pi-mc-dados]');
        if (!root) return;
        if (this._reveal) {
            this._reveal.destroy();
        }
        this._reveal = new RevelacaoSensivel(root, this.api, (m) => this.announce(m));
        const mascaradas = {};
        const cadastro = this._cadastroCache || {};
        ['cpf', 'rg', 'passaporte', 'cnpj', 'representante_cpf'].forEach((c) => {
            if (cadastro[c]) mascaradas[c] = cadastro[c];
        });
        this._reveal.sincronizarMascaras(mascaradas);
    }
}
