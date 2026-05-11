/**
 * EdicaoDadosForm.js
 *
 * Formulario de edicao do cadastro (Minha conta -> Meus dados).
 *
 *  - Renderiza apenas campos editaveis para o estado/tipo (lista fornecida pelo backend).
 *  - Validacao inline + aria-invalid (basico — usa FieldValidators do wizard
 *    quando disponivel, fallback regex local).
 *  - Confirmacao modal antes de submeter mudancas em campos sensiveis (email).
 *  - Anti-double-click: desabilita botao + spinner enquanto aguarda.
 *  - Live region: "Salvando..." -> "Salvo." / "Erro".
 *
 * @module minha-conta/EdicaoDadosForm
 */

const REGEX_EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const REGEX_UF = /^[A-Z]{2}$/;

export class EdicaoDadosForm {
    /**
     * @param {HTMLFormElement} form
     * @param {HTMLElement} fieldsContainer
     * @param {HTMLButtonElement} submitBtn
     * @param {HTMLElement} errorsContainer
     * @param {import('./ApiClientMinhaConta.js').ApiClientMinhaConta} api
     * @param {(msg: string) => void} liveAnnounce
     * @param {() => void} onSavedCloseModal
     */
    constructor(form, fieldsContainer, submitBtn, errorsContainer, api, liveAnnounce, onSavedCloseModal) {
        this.form = form;
        this.fields = fieldsContainer;
        this.submitBtn = submitBtn;
        this.errors = errorsContainer;
        this.api = api;
        this.announce = liveAnnounce || (() => {});
        this.onSaved = onSavedCloseModal || (() => {});
        this._busy = false;
        this._snapshot = {};
        this.form.addEventListener('submit', (e) => this._handleSubmit(e));
    }

    /**
     * Renderiza os campos com base no cadastro carregado.
     * @param {Record<string, unknown>} cadastro
     * @param {string[]} editaveis  Lista de campos editaveis no estado atual.
     */
    render(cadastro, editaveis) {
        this._snapshot = Object.assign({}, cadastro);
        this.fields.textContent = '';
        this.errors.textContent = '';

        if (!editaveis || editaveis.length === 0) {
            const p = document.createElement('p');
            p.className = 'pi-alert pi-alert--info';
            p.textContent = 'Nenhum campo pode ser editado no estado atual.';
            this.fields.appendChild(p);
            this.submitBtn.disabled = true;
            return;
        }
        this.submitBtn.disabled = false;

        editaveis.forEach((campo) => {
            this.fields.appendChild(this._makeField(campo, cadastro[campo]));
        });
    }

    _makeField(campo, valor) {
        const wrapper = document.createElement('div');
        wrapper.className = 'pi-form-group';

        const labelText = this._labelFor(campo);
        const id = `pi-mc-field-${campo}`;

        const label = document.createElement('label');
        label.htmlFor = id;
        label.textContent = labelText;

        const isTextarea = campo === 'apresentacao_md' || campo === 'recursos_acessibilidade';
        const isEmail = campo === 'email_principal';
        const isUF = campo === 'estado_residencia' || campo === 'estado_sede';

        const input = isTextarea ? document.createElement('textarea') : document.createElement('input');
        if (!isTextarea) {
            input.type = isEmail ? 'email' : 'text';
        } else {
            input.rows = 5;
        }
        input.id = id;
        input.name = campo;
        if (typeof valor === 'string' || typeof valor === 'number') {
            input.value = String(valor);
        }
        if (isUF) {
            input.maxLength = 2;
            input.pattern = '[A-Za-z]{2}';
            input.style.textTransform = 'uppercase';
        }
        input.setAttribute('aria-describedby', `${id}-erro`);

        const err = document.createElement('p');
        err.id = `${id}-erro`;
        err.className = 'pi-form-error';
        err.setAttribute('role', 'alert');

        wrapper.appendChild(label);
        wrapper.appendChild(input);
        wrapper.appendChild(err);
        return wrapper;
    }

    _labelFor(campo) {
        const map = {
            email_principal: 'E-mail principal',
            telefone: 'Telefone',
            nome_social: 'Nome social',
            cidade_residencia: 'Cidade',
            estado_residencia: 'UF',
            bairro_residencia: 'Bairro',
            cidade_sede: 'Cidade (sede)',
            estado_sede: 'UF (sede)',
            bairro_sede: 'Bairro (sede)',
            apresentacao_md: 'Apresentacao publica',
            recursos_acessibilidade: 'Recursos de acessibilidade',
        };
        return map[campo] || campo;
    }

    _collect() {
        /** @type {Record<string,unknown>} */
        const out = {};
        const inputs = this.fields.querySelectorAll('input[name], textarea[name]');
        inputs.forEach((el) => {
            const name = el.getAttribute('name');
            if (!name) return;
            let val = el.value;
            if (typeof val === 'string') {
                val = val.trim();
                if (name === 'estado_residencia' || name === 'estado_sede') {
                    val = val.toUpperCase();
                }
            }
            // Envia somente campos efetivamente alterados (diff minimo).
            if (val !== (this._snapshot[name] == null ? '' : String(this._snapshot[name]))) {
                out[name] = val;
            }
        });
        return out;
    }

    _validate(dados) {
        const erros = {};
        if ('email_principal' in dados) {
            if (!REGEX_EMAIL.test(String(dados.email_principal))) {
                erros.email_principal = 'E-mail invalido.';
            }
        }
        if ('estado_residencia' in dados && dados.estado_residencia !== '') {
            if (!REGEX_UF.test(String(dados.estado_residencia))) {
                erros.estado_residencia = 'UF deve ter 2 letras (ex.: SP).';
            }
        }
        if ('estado_sede' in dados && dados.estado_sede !== '') {
            if (!REGEX_UF.test(String(dados.estado_sede))) {
                erros.estado_sede = 'UF deve ter 2 letras (ex.: SP).';
            }
        }
        return erros;
    }

    _showErros(erros) {
        this.errors.textContent = '';
        const inputs = this.fields.querySelectorAll('[aria-invalid]');
        inputs.forEach((el) => el.removeAttribute('aria-invalid'));

        const keys = Object.keys(erros);
        if (keys.length === 0) return false;
        keys.forEach((campo) => {
            const el = this.fields.querySelector(`[name="${campo}"]`);
            const errEl = this.fields.querySelector(`#pi-mc-field-${campo}-erro`);
            if (el) el.setAttribute('aria-invalid', 'true');
            if (errEl) errEl.textContent = erros[campo];
        });
        this.errors.textContent = 'Verifique os campos com erro.';
        return true;
    }

    async _handleSubmit(e) {
        e.preventDefault();
        if (this._busy) return;

        const dados = this._collect();
        if (Object.keys(dados).length === 0) {
            this.errors.textContent = 'Nenhuma mudanca para salvar.';
            return;
        }
        const erros = this._validate(dados);
        if (this._showErros(erros)) return;

        // Confirmacao de campos sensiveis: email muda credencial de notificacao.
        if ('email_principal' in dados) {
            const ok = window.confirm(
                'Voce esta alterando o e-mail principal — futuras notificacoes serao enviadas para o novo endereco. Confirmar?'
            );
            if (!ok) return;
        }

        this._busy = true;
        this.submitBtn.disabled = true;
        this.submitBtn.setAttribute('aria-busy', 'true');
        this.announce('Salvando…');

        try {
            const resp = await this.api.patchCadastro(dados);
            this.announce('Dados salvos.');
            this.onSaved(resp);
        } catch (err) {
            if (err && err.status === 423) {
                this.errors.textContent = (err.body && err.body.message) || 'Edicao bloqueada pelo estado atual.';
                this.announce('Edicao bloqueada.');
            } else if (err && err.status === 400) {
                this.errors.textContent = (err.body && err.body.message) || 'Erro de validacao.';
                this.announce('Erro de validacao.');
            } else if (err && err.status === 403) {
                this.errors.textContent = 'Permissao negada.';
                this.announce('Permissao negada.');
            } else if (err && err.status === 429) {
                this.errors.textContent = 'Muitas requisicoes. Aguarde alguns segundos.';
                this.announce('Limite de requisicoes atingido.');
            } else {
                this.errors.textContent = 'Erro ao salvar. Tente novamente.';
                this.announce('Erro ao salvar.');
            }
        } finally {
            this._busy = false;
            this.submitBtn.disabled = false;
            this.submitBtn.removeAttribute('aria-busy');
        }
    }
}
