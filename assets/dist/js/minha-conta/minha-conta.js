/*!
 * Participe Ibram — Minha Conta bundle
 *
 * Concatenacao manual (IIFE) dos modulos:
 *  - ApiClientMinhaConta
 *  - RevelacaoSensivel
 *  - EdicaoDadosForm
 *  - MinhaContaApp
 *  - bootstrap (index)
 *
 * Cliente: namespace global `PIMinhaConta` apenas para introspeccao em testes.
 */
(function () {
    'use strict';

    /* ============================================================
     * ApiClientMinhaConta
     * ============================================================ */
    function ApiClientMinhaConta(config) {
        if (!config || !config.apiUrl) throw new Error('ApiClientMinhaConta: apiUrl is required');
        this.baseUrl = String(config.apiUrl).replace(/\/+$/, '');
        this.nonce = String(config.nonce || '');
    }
    ApiClientMinhaConta.prototype.getCadastro = function (reveal) {
        var qs = reveal && reveal.length > 0 ? '?reveal=' + encodeURIComponent(reveal.join(',')) : '';
        return this._request(this.baseUrl + '/me/cadastro' + qs, { method: 'GET' });
    };
    ApiClientMinhaConta.prototype.getDashboard = function () {
        return this._request(this.baseUrl + '/me/dashboard', { method: 'GET' });
    };
    ApiClientMinhaConta.prototype.patchCadastro = function (dados) {
        return this._request(this.baseUrl + '/me/cadastro', {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(dados || {}),
        });
    };
    ApiClientMinhaConta.prototype.revelarCampo = function (campo) {
        return this.getCadastro([campo]);
    };
    ApiClientMinhaConta.prototype._request = function (url, init) {
        var headers = Object.assign(
            { Accept: 'application/json' },
            init.headers || {},
            this.nonce ? { 'X-WP-Nonce': this.nonce } : {}
        );
        return fetch(url, Object.assign({ credentials: 'same-origin' }, init, { headers })).then(function (response) {
            var ct = response.headers.get('Content-Type') || '';
            var parse = ct.indexOf('application/json') !== -1
                ? response.json().catch(function () { return null; })
                : response.text().catch(function () { return null; });
            return parse.then(function (body) {
                if (!response.ok) {
                    var err = new Error((body && body.code) ? body.code : 'http_' + response.status);
                    err.status = response.status;
                    err.body = body;
                    throw err;
                }
                return body;
            });
        }, function (e) {
            var err = new Error('network_error');
            err.cause = e;
            err.status = 0;
            throw err;
        });
    };

    /* ============================================================
     * RevelacaoSensivel
     * ============================================================ */
    var COOLDOWN_MS = 2000;

    function RevelacaoSensivel(root, api, liveAnnounce) {
        this.root = root;
        this.api = api;
        this.announce = liveAnnounce || function () { };
        this._lastToggleAt = 0;
        this._mascarado = {};
        this._onClick = this._onClick.bind(this);
        this.root.addEventListener('click', this._onClick);
    }
    RevelacaoSensivel.prototype.destroy = function () {
        this.root.removeEventListener('click', this._onClick);
    };
    RevelacaoSensivel.prototype.sincronizarMascaras = function (valoresMascarados) {
        for (var campo in valoresMascarados) {
            if (Object.prototype.hasOwnProperty.call(valoresMascarados, campo)) {
                var v = valoresMascarados[campo];
                if (v && v.masked && v.value !== null) this._mascarado[campo] = String(v.value);
            }
        }
    };
    RevelacaoSensivel.prototype._onClick = function (e) {
        var t = e.target instanceof Element ? e.target.closest('[data-pi-mc-reveal]') : null;
        if (!t) return;
        e.preventDefault();
        var agora = Date.now();
        if (agora - this._lastToggleAt < COOLDOWN_MS) return;
        this._lastToggleAt = agora;

        var campo = t.getAttribute('data-campo') || '';
        if (!campo) return;
        var expanded = t.getAttribute('aria-expanded') === 'true';
        var cell = t.closest('dd') || t.parentElement;
        var code = cell ? cell.querySelector('[data-pi-mc-campo-valor]') : null;

        var self = this;
        if (expanded) {
            if (code && this._mascarado[campo]) code.textContent = this._mascarado[campo];
            t.setAttribute('aria-expanded', 'false');
            t.textContent = t.dataset.labelMostrar || 'Mostrar';
            this.announce('Valor ocultado.');
            return;
        }
        t.setAttribute('disabled', '');
        t.setAttribute('aria-busy', 'true');
        this.api.revelarCampo(campo).then(function (cadastro) {
            var dados = cadastro && cadastro[campo] ? cadastro[campo] : null;
            if (!dados || dados.value === null) { self.announce('Nao foi possivel revelar o valor.'); return; }
            if (code) code.textContent = String(dados.value);
            t.setAttribute('aria-expanded', 'true');
            t.textContent = t.dataset.labelOcultar || 'Ocultar';
            self.announce('Valor revelado. Esta visualizacao foi registrada em auditoria.');
        }, function () {
            self.announce('Erro ao revelar o campo.');
        }).then(function () {
            t.removeAttribute('disabled');
            t.removeAttribute('aria-busy');
        });
    };

    /* ============================================================
     * EdicaoDadosForm
     * ============================================================ */
    var REGEX_EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    var REGEX_UF = /^[A-Z]{2}$/;

    function EdicaoDadosForm(form, fields, submitBtn, errors, api, announce, onSaved) {
        this.form = form;
        this.fields = fields;
        this.submitBtn = submitBtn;
        this.errors = errors;
        this.api = api;
        this.announce = announce || function () { };
        this.onSaved = onSaved || function () { };
        this._busy = false;
        this._snapshot = {};
        var self = this;
        this.form.addEventListener('submit', function (e) { self._handleSubmit(e); });
    }
    EdicaoDadosForm.prototype.render = function (cadastro, editaveis) {
        this._snapshot = Object.assign({}, cadastro);
        this.fields.textContent = '';
        this.errors.textContent = '';
        if (!editaveis || editaveis.length === 0) {
            var p = document.createElement('p');
            p.className = 'pi-alert pi-alert--info';
            p.textContent = 'Nenhum campo pode ser editado no estado atual.';
            this.fields.appendChild(p);
            this.submitBtn.disabled = true;
            return;
        }
        this.submitBtn.disabled = false;
        var self = this;
        editaveis.forEach(function (campo) { self.fields.appendChild(self._makeField(campo, cadastro[campo])); });
    };
    EdicaoDadosForm.prototype._labelFor = function (campo) {
        var map = {
            email_principal: 'E-mail principal', telefone: 'Telefone', nome_social: 'Nome social',
            cidade_residencia: 'Cidade', estado_residencia: 'UF', bairro_residencia: 'Bairro',
            cidade_sede: 'Cidade (sede)', estado_sede: 'UF (sede)', bairro_sede: 'Bairro (sede)',
            apresentacao_md: 'Apresentacao publica', recursos_acessibilidade: 'Recursos de acessibilidade',
        };
        return map[campo] || campo;
    };
    EdicaoDadosForm.prototype._makeField = function (campo, valor) {
        var w = document.createElement('div'); w.className = 'pi-form-group';
        var id = 'pi-mc-field-' + campo;
        var lbl = document.createElement('label'); lbl.htmlFor = id; lbl.textContent = this._labelFor(campo);
        var isTextarea = campo === 'apresentacao_md' || campo === 'recursos_acessibilidade';
        var isEmail = campo === 'email_principal';
        var isUF = campo === 'estado_residencia' || campo === 'estado_sede';
        var input = isTextarea ? document.createElement('textarea') : document.createElement('input');
        if (!isTextarea) input.type = isEmail ? 'email' : 'text'; else input.rows = 5;
        input.id = id; input.name = campo;
        if (typeof valor === 'string' || typeof valor === 'number') input.value = String(valor);
        if (isUF) { input.maxLength = 2; input.pattern = '[A-Za-z]{2}'; input.style.textTransform = 'uppercase'; }
        input.setAttribute('aria-describedby', id + '-erro');
        var err = document.createElement('p'); err.id = id + '-erro'; err.className = 'pi-form-error'; err.setAttribute('role', 'alert');
        w.appendChild(lbl); w.appendChild(input); w.appendChild(err);
        return w;
    };
    EdicaoDadosForm.prototype._collect = function () {
        var out = {}; var self = this;
        var inputs = this.fields.querySelectorAll('input[name], textarea[name]');
        Array.prototype.forEach.call(inputs, function (el) {
            var name = el.getAttribute('name'); if (!name) return;
            var val = el.value;
            if (typeof val === 'string') {
                val = val.trim();
                if (name === 'estado_residencia' || name === 'estado_sede') val = val.toUpperCase();
            }
            var snap = self._snapshot[name] == null ? '' : String(self._snapshot[name]);
            if (val !== snap) out[name] = val;
        });
        return out;
    };
    EdicaoDadosForm.prototype._validate = function (dados) {
        var erros = {};
        if ('email_principal' in dados && !REGEX_EMAIL.test(String(dados.email_principal))) erros.email_principal = 'E-mail invalido.';
        if ('estado_residencia' in dados && dados.estado_residencia !== '' && !REGEX_UF.test(String(dados.estado_residencia))) erros.estado_residencia = 'UF deve ter 2 letras (ex.: SP).';
        if ('estado_sede' in dados && dados.estado_sede !== '' && !REGEX_UF.test(String(dados.estado_sede))) erros.estado_sede = 'UF deve ter 2 letras (ex.: SP).';
        return erros;
    };
    EdicaoDadosForm.prototype._showErros = function (erros) {
        this.errors.textContent = '';
        var prev = this.fields.querySelectorAll('[aria-invalid]');
        Array.prototype.forEach.call(prev, function (el) { el.removeAttribute('aria-invalid'); });
        var keys = Object.keys(erros);
        if (keys.length === 0) return false;
        var self = this;
        keys.forEach(function (campo) {
            var el = self.fields.querySelector('[name="' + campo + '"]');
            var errEl = self.fields.querySelector('#pi-mc-field-' + campo + '-erro');
            if (el) el.setAttribute('aria-invalid', 'true');
            if (errEl) errEl.textContent = erros[campo];
        });
        this.errors.textContent = 'Verifique os campos com erro.';
        return true;
    };
    EdicaoDadosForm.prototype._handleSubmit = function (e) {
        e.preventDefault();
        if (this._busy) return;
        var dados = this._collect();
        if (Object.keys(dados).length === 0) { this.errors.textContent = 'Nenhuma mudanca para salvar.'; return; }
        var erros = this._validate(dados);
        if (this._showErros(erros)) return;
        if ('email_principal' in dados) {
            if (!window.confirm('Voce esta alterando o e-mail principal — futuras notificacoes serao enviadas para o novo endereco. Confirmar?')) return;
        }
        this._busy = true; this.submitBtn.disabled = true; this.submitBtn.setAttribute('aria-busy', 'true');
        this.announce('Salvando…');
        var self = this;
        this.api.patchCadastro(dados).then(function (resp) {
            self.announce('Dados salvos.');
            self.onSaved(resp);
        }, function (err) {
            if (err && err.status === 423) { self.errors.textContent = (err.body && err.body.message) || 'Edicao bloqueada pelo estado atual.'; self.announce('Edicao bloqueada.'); }
            else if (err && err.status === 400) { self.errors.textContent = (err.body && err.body.message) || 'Erro de validacao.'; self.announce('Erro de validacao.'); }
            else if (err && err.status === 403) { self.errors.textContent = 'Permissao negada.'; self.announce('Permissao negada.'); }
            else if (err && err.status === 429) { self.errors.textContent = 'Muitas requisicoes. Aguarde alguns segundos.'; self.announce('Limite de requisicoes atingido.'); }
            else { self.errors.textContent = 'Erro ao salvar. Tente novamente.'; self.announce('Erro ao salvar.'); }
        }).then(function () {
            self._busy = false; self.submitBtn.disabled = false; self.submitBtn.removeAttribute('aria-busy');
        });
    };

    /* ============================================================
     * MinhaContaApp
     * ============================================================ */
    var CAMPOS_EDITAVEIS_POR_STATUS = {
        rascunho: [], submetido: [], em_analise: [],
        deferido: ['email_principal', 'telefone', 'nome_social', 'cidade_residencia', 'estado_residencia', 'bairro_residencia', 'cidade_sede', 'estado_sede', 'bairro_sede', 'apresentacao_md', 'recursos_acessibilidade'],
        deferido_em_retratacao: ['email_principal', 'telefone', 'nome_social', 'cidade_residencia', 'estado_residencia', 'bairro_residencia', 'cidade_sede', 'estado_sede', 'bairro_sede', 'apresentacao_md', 'recursos_acessibilidade'],
        deferido_em_recurso: ['email_principal', 'telefone', 'nome_social', 'cidade_residencia', 'estado_residencia', 'bairro_residencia', 'cidade_sede', 'estado_sede', 'bairro_sede', 'apresentacao_md', 'recursos_acessibilidade'],
        indeferido_aguardando_recurso: [], em_retratacao: [], em_recurso_presidencia: [], indeferido_final: [],
    };

    function MinhaContaApp(root) {
        this.root = root;
        var cfg = MinhaContaApp.readConfig(root);
        this.api = new ApiClientMinhaConta(cfg);
        this.live = root.querySelector('#pi-minha-conta-live') || document.createElement('div');
        this.abaAtual = cfg.abaAtual || 'dashboard';
        this._cadastroCache = null;
        this._initTabs();
        if (this.abaAtual === 'dashboard') this._initDashboard();
        if (this.abaAtual === 'dados') this._initDadosTab();
    }
    MinhaContaApp.readConfig = function (root) {
        var el = root.querySelector('#pi-minha-conta-config');
        if (!el || !el.textContent) return { apiUrl: '', nonce: '', abaAtual: 'dashboard' };
        try { return JSON.parse(el.textContent); } catch (_) { return { apiUrl: '', nonce: '', abaAtual: 'dashboard' }; }
    };
    MinhaContaApp.prototype.announce = function (msg) {
        if (!this.live) return;
        this.live.textContent = '';
        var self = this;
        setTimeout(function () { self.live.textContent = String(msg || ''); }, 50);
    };
    MinhaContaApp.prototype._initTabs = function () {
        var tablist = this.root.querySelector('[role="tablist"]');
        if (!tablist) return;
        var tabs = Array.prototype.slice.call(tablist.querySelectorAll('[role="tab"]'));
        tablist.addEventListener('keydown', function (e) {
            var idx = tabs.indexOf(document.activeElement);
            if (idx === -1) return;
            var next = null;
            if (e.key === 'ArrowRight') next = tabs[(idx + 1) % tabs.length];
            else if (e.key === 'ArrowLeft') next = tabs[(idx - 1 + tabs.length) % tabs.length];
            else if (e.key === 'Home') next = tabs[0];
            else if (e.key === 'End') next = tabs[tabs.length - 1];
            if (next) { e.preventDefault(); next.focus(); }
        });
    };
    MinhaContaApp.prototype._initDashboard = function () {
        var self = this;
        this.api.getDashboard().then(function (d) {
            self._renderDashboard(d);
            self.announce('Dashboard carregado.');
        }, function () { self.announce('Erro ao carregar o dashboard.'); });
    };
    MinhaContaApp.prototype._renderDashboard = function (d) {
        if (!d || d.has_cadastro === false) return;
        var status = this.root.querySelector('[data-pi-mc-status]');
        var map = {
            rascunho: 'Rascunho', submetido: 'Submetido', em_analise: 'Em analise',
            deferido: 'Deferido', deferido_em_retratacao: 'Deferido (retratacao)', deferido_em_recurso: 'Deferido (recurso)',
            indeferido_aguardando_recurso: 'Indeferido — recurso disponivel',
            em_retratacao: 'Em retratacao', em_recurso_presidencia: 'Em recurso de presidencia', indeferido_final: 'Indeferido (final)'
        };
        if (status) {
            status.textContent = map[d.status_cadastro] || d.status_cadastro || '—';
            status.setAttribute('data-status', d.status_cadastro || '');
        }
        var wrap = this.root.querySelector('[data-pi-mc-numero-registro]');
        if (wrap) {
            if (d.numero_registro) {
                wrap.hidden = false;
                var val = wrap.querySelector('[data-pi-mc-numero-registro-value]');
                if (val) val.textContent = d.numero_registro;
                var copy = wrap.querySelector('[data-pi-mc-copy]');
                var self = this;
                if (copy && !copy._bound) {
                    copy._bound = true;
                    copy.addEventListener('click', function () { self._copy(d.numero_registro); });
                }
            } else { wrap.hidden = true; }
        }
        var prox = this.root.querySelector('[data-pi-mc-proximos]');
        if (prox) {
            prox.textContent = '';
            (d.proximos_passos || []).forEach(function (p) {
                var li = document.createElement('li'); li.className = p.concluido ? 'is-concluido' : '';
                var t = document.createElement('strong'); t.textContent = p.titulo;
                var desc = document.createElement('p'); desc.textContent = p.descricao;
                li.appendChild(t); li.appendChild(desc); prox.appendChild(li);
            });
            if (!prox.firstChild) { var li2 = document.createElement('li'); li2.textContent = 'Nenhum passo pendente.'; prox.appendChild(li2); }
        }
        var pend = this.root.querySelector('[data-pi-mc-pendencias]');
        if (pend) {
            pend.textContent = '';
            var items = d.pendencias || [];
            if (items.length === 0) { var li = document.createElement('li'); li.textContent = 'Nenhuma pendencia.'; pend.appendChild(li); }
            else { items.forEach(function (it) { var li = document.createElement('li'); li.textContent = it.mensagem; pend.appendChild(li); }); }
        }
        var tl = this.root.querySelector('[data-pi-mc-timeline]');
        if (tl) {
            tl.textContent = '';
            var eventos = [
                d.submetido_em ? { quando: d.submetido_em, label: 'Cadastro submetido' } : null,
                d.deferido_em ? { quando: d.deferido_em, label: 'Cadastro deferido' } : null,
                d.publicado_em ? { quando: d.publicado_em, label: 'Publicado no site' } : null,
            ].filter(function (x) { return x; });
            if (eventos.length === 0) { var li = document.createElement('li'); li.textContent = 'Sem eventos registrados.'; tl.appendChild(li); }
            else {
                var self2 = this;
                eventos.forEach(function (ev) {
                    var li = document.createElement('li');
                    li.textContent = ev.label + ' — ' + self2._formatDate(ev.quando);
                    tl.appendChild(li);
                });
            }
        }
    };
    MinhaContaApp.prototype._copy = function (text) {
        var self = this;
        try {
            navigator.clipboard.writeText(String(text)).then(function () { self.announce('Numero copiado.'); }, function () { self.announce('Nao foi possivel copiar.'); });
        } catch (_) { self.announce('Nao foi possivel copiar.'); }
    };
    MinhaContaApp.prototype._formatDate = function (iso) {
        try { return new Date(iso).toLocaleString('pt-BR'); } catch (_) { return String(iso); }
    };
    MinhaContaApp.prototype._initDadosTab = function () {
        var self = this;
        this.api.getCadastro().then(function (cadastro) {
            self._cadastroCache = cadastro;
            self._renderDados(cadastro);
            self._setupEditFormSeAplicavel(cadastro);
            self._setupRevelacao();
            self.announce('Dados carregados.');
        }, function () { self.announce('Erro ao carregar os dados.'); });
    };
    MinhaContaApp.prototype._renderDados = function (cadastro) {
        var self = this;
        var dlBas = this.root.querySelector('[data-pi-mc-secao="basicos"]');
        if (dlBas) {
            this._fillDd(dlBas, 'email_principal', cadastro.email_principal);
            this._fillDd(dlBas, 'telefone', cadastro.telefone);
            this._fillDd(dlBas, 'tipo', cadastro.tipo);
        }
        var dlSens = this.root.querySelector('[data-pi-mc-secao="sensiveis"]');
        if (dlSens) {
            dlSens.textContent = '';
            ['cpf', 'rg', 'passaporte', 'cnpj', 'representante_cpf'].forEach(function (c) {
                if (cadastro[c]) dlSens.appendChild(self._makeSensitiveRow(c, cadastro[c]));
            });
        }
        var dlEnd = this.root.querySelector('[data-pi-mc-secao="endereco"]');
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
        var dlPerf = this.root.querySelector('[data-pi-mc-secao="perfil"]');
        if (dlPerf) {
            dlPerf.textContent = '';
            if (cadastro.tipo === 'PF') this._appendDl(dlPerf, 'Nome social', cadastro.nome_social);
            this._appendDl(dlPerf, 'Apresentacao', cadastro.apresentacao_md);
        }
    };
    MinhaContaApp.prototype._fillDd = function (dl, campo, valor) {
        var dd = dl.querySelector('dd[data-campo="' + campo + '"]');
        if (dd) dd.textContent = (valor === null || valor === undefined || valor === '') ? '—' : String(valor);
    };
    MinhaContaApp.prototype._appendDl = function (dl, label, valor) {
        var row = document.createElement('div'); row.className = 'pi-mc-dl__row';
        var dt = document.createElement('dt'); dt.textContent = label;
        var dd = document.createElement('dd'); dd.textContent = (valor === null || valor === undefined || valor === '') ? '—' : String(valor);
        row.appendChild(dt); row.appendChild(dd); dl.appendChild(row);
    };
    MinhaContaApp.prototype._makeSensitiveRow = function (campo, info) {
        var labelMap = { cpf: 'CPF', rg: 'RG', passaporte: 'Passaporte', cnpj: 'CNPJ', representante_cpf: 'CPF do representante' };
        var row = document.createElement('div'); row.className = 'pi-mc-dl__row';
        var dt = document.createElement('dt'); dt.textContent = labelMap[campo] || campo;
        var dd = document.createElement('dd');
        var code = document.createElement('code');
        code.setAttribute('data-pi-mc-campo-valor', '');
        code.textContent = info.value !== null && info.value !== undefined ? String(info.value) : '—';
        var btn = document.createElement('button');
        btn.type = 'button'; btn.className = 'pi-btn pi-btn--ghost pi-btn--sm';
        btn.setAttribute('data-pi-mc-reveal', ''); btn.setAttribute('data-campo', campo);
        btn.setAttribute('aria-expanded', info.masked ? 'false' : 'true');
        btn.dataset.labelMostrar = 'Mostrar'; btn.dataset.labelOcultar = 'Ocultar';
        btn.textContent = info.masked ? 'Mostrar' : 'Ocultar';
        dd.appendChild(code); dd.appendChild(document.createTextNode(' ')); dd.appendChild(btn);
        row.appendChild(dt); row.appendChild(dd);
        return row;
    };
    MinhaContaApp.prototype._setupEditFormSeAplicavel = function (cadastro) {
        var editaveis = CAMPOS_EDITAVEIS_POR_STATUS[cadastro.status_cadastro] || [];
        var tipo = cadastro.tipo;
        var filtrados = editaveis.filter(function (c) {
            if (c === 'nome_social' && tipo !== 'PF') return false;
            if ((c === 'cidade_residencia' || c === 'estado_residencia' || c === 'bairro_residencia') && tipo !== 'PF') return false;
            if ((c === 'cidade_sede' || c === 'estado_sede' || c === 'bairro_sede') && tipo === 'PF') return false;
            return true;
        });
        var btnEdit = this.root.querySelector('[data-pi-mc-editar]');
        var estadoMsg = this.root.querySelector('[data-pi-mc-estado-msg]');
        if (filtrados.length === 0) {
            if (btnEdit) btnEdit.hidden = true;
            if (estadoMsg) estadoMsg.textContent = 'Edicao bloqueada no estado atual do cadastro.';
            return;
        }
        if (btnEdit) btnEdit.hidden = false;
        if (estadoMsg) estadoMsg.textContent = '';
        var form = this.root.querySelector('#pi-mc-form-editar');
        var fields = this.root.querySelector('[data-pi-mc-form-fields]');
        var submitBtn = this.root.querySelector('[data-pi-mc-submit]');
        var errors = this.root.querySelector('[data-pi-mc-form-errors]');
        if (!form || !fields || !submitBtn || !errors) return;
        var self = this;
        var onSaved = function () {
            var modal = document.getElementById('pi-modal-mc-editar');
            if (modal && modal._piModalInstance) modal._piModalInstance.fechar();
            self._initDadosTab();
        };
        this._editForm = new EdicaoDadosForm(form, fields, submitBtn, errors, this.api, function (m) { self.announce(m); }, onSaved);
        this._editForm.render(cadastro, filtrados);
    };
    MinhaContaApp.prototype._setupRevelacao = function () {
        var root = this.root.querySelector('[data-pi-mc-dados]');
        if (!root) return;
        if (this._reveal) this._reveal.destroy();
        var self = this;
        this._reveal = new RevelacaoSensivel(root, this.api, function (m) { self.announce(m); });
        var mascaradas = {};
        var cadastro = this._cadastroCache || {};
        ['cpf', 'rg', 'passaporte', 'cnpj', 'representante_cpf'].forEach(function (c) {
            if (cadastro[c]) mascaradas[c] = cadastro[c];
        });
        this._reveal.sincronizarMascaras(mascaradas);
    };

    /* ============================================================
     * Bootstrap
     * ============================================================ */
    function boot() {
        var roots = document.querySelectorAll('[data-pi-minha-conta]');
        if (roots.length === 0) return;
        Array.prototype.forEach.call(roots, function (root) {
            if (root._piMcInstance) return;
            root._piMcInstance = new MinhaContaApp(root);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    // Exporta utilitarios para inspecao em testes (window.PIMinhaConta).
    window.PIMinhaConta = {
        ApiClientMinhaConta: ApiClientMinhaConta,
        RevelacaoSensivel: RevelacaoSensivel,
        EdicaoDadosForm: EdicaoDadosForm,
        MinhaContaApp: MinhaContaApp,
    };
})();
