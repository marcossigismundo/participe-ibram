/* eslint-disable no-console */
/**
 * VotacaoAppTest.js
 *
 * Testes unitários ES modules para VotacaoApp/ConfirmacaoVoto/ApiClientVotacao.
 * Sem dependência externa: usa node:assert + JSDOM minimalista provido inline
 * (suficiente para os comportamentos cobertos). Caso o repositório adote jest
 * no futuro, este arquivo já está estruturado em test cases independentes.
 *
 * Cobertura:
 *  1. Anti-double-click — 2 cliques rápidos só geram 1 POST registrar
 *  2. Modal cancelar não submete
 *  3. 409 (duplicado) transita corretamente para estado VOTED
 *
 * Como rodar (a partir da raiz do plugin):
 *   node --experimental-vm-modules tests/Unit/Frontend/VotacaoAppTest.js
 *
 * @module tests/Unit/Frontend/VotacaoAppTest
 */

import assert from 'node:assert/strict';

// --------------------------------------------------------------------------
// JSDOM/browser shims minimalistas suficientes para nossos módulos.
// Implementa apenas o subset usado: createElement, querySelector*,
// classList, dataset, setAttribute, addEventListener, dispatchEvent,
// document.activeElement, requestAnimationFrame, AbortController, fetch.
// --------------------------------------------------------------------------

class FakeClassList {
    constructor() { this._set = new Set(); }
    add(...names) { names.forEach((n) => this._set.add(n)); }
    remove(...names) { names.forEach((n) => this._set.delete(n)); }
    contains(name) { return this._set.has(name); }
    toggle(name, force) {
        if (force === true) { this._set.add(name); return true; }
        if (force === false) { this._set.delete(name); return false; }
        if (this._set.has(name)) { this._set.delete(name); return false; }
        this._set.add(name); return true;
    }
    toString() { return Array.from(this._set).join(' '); }
}

let __nextId = 0;

class FakeNode {
    constructor(tagName) {
        this.tagName = (tagName || '').toUpperCase();
        this.children = [];
        this.parentNode = null;
        this.attributes = {};
        this.classList = new FakeClassList();
        this.style = {};
        this._listeners = {};
        this.dataset = {};
        this.hidden = false;
        this.disabled = false;
        this.textContent = '';
        this.value = '';
        this._uid = ++__nextId;
    }
    appendChild(child) {
        if (!child) return child;
        if (child.parentNode) child.parentNode.removeChild(child);
        this.children.push(child);
        child.parentNode = this;
        return child;
    }
    insertBefore(node, ref) {
        const idx = this.children.indexOf(ref);
        if (idx < 0) return this.appendChild(node);
        this.children.splice(idx, 0, node);
        node.parentNode = this;
        return node;
    }
    removeChild(child) {
        const idx = this.children.indexOf(child);
        if (idx >= 0) {
            this.children.splice(idx, 1);
            child.parentNode = null;
        }
        return child;
    }
    remove() { if (this.parentNode) this.parentNode.removeChild(this); }
    setAttribute(name, value) {
        this.attributes[name] = String(value);
        if (name.startsWith('data-')) {
            const key = name.slice(5).replace(/-([a-z])/g, (_, c) => c.toUpperCase());
            this.dataset[key] = String(value);
        }
        if (name === 'hidden') this.hidden = true;
        if (name === 'disabled') this.disabled = true;
        if (name === 'aria-checked') this._ariaChecked = String(value);
        if (name === 'aria-disabled') this._ariaDisabled = String(value);
        if (name === 'tabindex') this._tabindex = String(value);
        if (name === 'id') this.id = String(value);
        if (name === 'class') {
            this.classList = new FakeClassList();
            String(value).split(/\s+/).filter(Boolean).forEach((c) => this.classList.add(c));
        }
    }
    getAttribute(name) {
        if (name === 'aria-labelledby') return this.attributes[name] || null;
        return this.attributes[name] !== undefined ? this.attributes[name] : null;
    }
    hasAttribute(name) { return this.attributes[name] !== undefined; }
    removeAttribute(name) {
        delete this.attributes[name];
        if (name === 'hidden') this.hidden = false;
        if (name === 'disabled') this.disabled = false;
    }
    addEventListener(type, fn) {
        if (!this._listeners[type]) this._listeners[type] = [];
        this._listeners[type].push(fn);
    }
    removeEventListener(type, fn) {
        const arr = this._listeners[type] || [];
        const i = arr.indexOf(fn);
        if (i >= 0) arr.splice(i, 1);
    }
    dispatchEvent(ev) {
        const arr = this._listeners[ev.type] || [];
        for (const fn of arr) {
            try { fn(ev); } catch (e) { console.error(e); }
            if (ev._stop) break;
        }
        return !ev.defaultPrevented;
    }
    focus() { document.activeElement = this; }
    click() {
        const ev = new FakeEvent('click');
        ev.target = this;
        this.dispatchEvent(ev);
    }
    querySelector(sel) {
        const all = this.querySelectorAll(sel);
        return all.length ? all[0] : null;
    }
    querySelectorAll(sel) {
        const out = [];
        const walk = (n) => {
            for (const c of n.children) {
                if (matchesSelector(c, sel)) out.push(c);
                walk(c);
            }
        };
        walk(this);
        return out;
    }
    set className(v) {
        this.classList = new FakeClassList();
        String(v).split(/\s+/).filter(Boolean).forEach((c) => this.classList.add(c));
        this.attributes.class = String(v);
    }
    get className() { return this.classList.toString(); }
    get firstChild() { return this.children[0] || null; }
    get offsetParent() { return this.hidden ? null : this; }
}

function matchesSelector(node, sel) {
    sel = sel.trim();
    // very small selector engine
    if (sel.startsWith('.')) return node.classList.contains(sel.slice(1));
    if (sel.startsWith('#')) return node.id === sel.slice(1);
    if (sel.startsWith('[') && sel.endsWith(']')) {
        const body = sel.slice(1, -1);
        const eq = body.indexOf('=');
        if (eq < 0) return node.hasAttribute(body);
        const name = body.slice(0, eq);
        let val = body.slice(eq + 1);
        if ((val.startsWith('"') && val.endsWith('"')) || (val.startsWith("'") && val.endsWith("'"))) {
            val = val.slice(1, -1);
        }
        return node.getAttribute(name) === val;
    }
    return node.tagName === sel.toUpperCase();
}

class FakeEvent {
    constructor(type) {
        this.type = type;
        this.defaultPrevented = false;
        this._stop = false;
        this.target = null;
        this.currentTarget = null;
        this.shiftKey = false;
    }
    preventDefault() { this.defaultPrevented = true; }
    stopPropagation() { this._stop = true; }
}

class FakeKeyboardEvent extends FakeEvent {
    constructor(type, init = {}) {
        super(type);
        this.key = init.key || '';
        this.shiftKey = !!init.shiftKey;
    }
}

class FakeCustomEvent extends FakeEvent {
    constructor(type, init = {}) { super(type); this.detail = init.detail; this.bubbles = !!init.bubbles; }
}

const document = {
    activeElement: null,
    body: new FakeNode('body'),
    readyState: 'complete',
    createElement(tag) { return new FakeNode(tag); },
    createTextNode(text) {
        const n = new FakeNode('#text');
        n.textContent = String(text || '');
        return n;
    },
    addEventListener() { /* noop */ },
    getElementById(id) {
        const walk = (n) => {
            if (n.id === id) return n;
            for (const c of n.children) {
                const r = walk(c); if (r) return r;
            }
            return null;
        };
        return walk(document.body);
    },
    querySelector(sel) { return document.body.querySelector(sel); },
    querySelectorAll(sel) { return document.body.querySelectorAll(sel); },
    dispatchEvent() { /* noop */ },
    execCommand() { return true; },
};

const window = {
    location: { href: 'http://localhost/votar/', search: '' },
    requestAnimationFrame: (fn) => setTimeout(fn, 0),
    setTimeout: (fn, ms) => setTimeout(fn, ms),
    setInterval: (fn, ms) => setInterval(fn, ms),
    clearInterval: (id) => clearInterval(id),
    clearTimeout: (id) => clearTimeout(id),
    addEventListener() {},
    removeEventListener() {},
    print() {},
    piI18n: {},
    piVotacaoConfig: {},
};

globalThis.document = document;
globalThis.window = window;
globalThis.Headers = class Headers {
    constructor(init) { this._m = new Map(); if (init && init.forEach) init.forEach((v, k) => this.set(k, v)); else if (init) Object.keys(init).forEach((k) => this.set(k, init[k])); }
    set(k, v) { this._m.set(String(k).toLowerCase(), String(v)); }
    get(k) { return this._m.has(String(k).toLowerCase()) ? this._m.get(String(k).toLowerCase()) : null; }
    has(k) { return this._m.has(String(k).toLowerCase()); }
    forEach(cb) { this._m.forEach((v, k) => cb(v, k)); }
};
globalThis.AbortController = globalThis.AbortController || class { constructor() { this.signal = { aborted: false, addEventListener() {}, removeEventListener() {} }; } abort() { this.signal.aborted = true; } };
globalThis.CustomEvent = FakeCustomEvent;
globalThis.KeyboardEvent = FakeKeyboardEvent;
globalThis.HTMLElement = FakeNode;

// --------------------------------------------------------------------------
// Mocks de fetch
// --------------------------------------------------------------------------

let fetchCalls = [];
let fetchRouter = null;

function makeJsonResponse(body, status = 200, headers = {}) {
    const h = new Headers({ 'Content-Type': 'application/json', ...headers });
    return Promise.resolve({
        ok: status >= 200 && status < 300,
        status,
        headers: h,
        json: async () => body,
        text: async () => JSON.stringify(body),
    });
}

globalThis.fetch = function (url, init) {
    fetchCalls.push({ url, init });
    if (fetchRouter) return fetchRouter(url, init);
    return makeJsonResponse({}, 200);
};

// --------------------------------------------------------------------------
// Test runner mínimo
// --------------------------------------------------------------------------

const tests = [];
function test(name, fn) { tests.push({ name, fn }); }

async function runAll() {
    let passed = 0; let failed = 0;
    for (const t of tests) {
        // reset DOM
        document.body = new FakeNode('body');
        document.activeElement = null;
        fetchCalls = [];
        fetchRouter = null;
        try {
            await t.fn();
            console.log('  ok  ' + t.name);
            passed += 1;
        } catch (e) {
            console.log('  FAIL ' + t.name);
            console.log(e && e.stack ? e.stack : e);
            failed += 1;
        }
    }
    console.log(`\n${passed} passed, ${failed} failed (${tests.length} total)`);
    if (failed > 0) process.exitCode = 1;
}

// --------------------------------------------------------------------------
// Helpers para montar DOM da app
// --------------------------------------------------------------------------

function buildAppRoot(votacaoId = 1) {
    const root = document.createElement('div');
    root.setAttribute('data-pi-votacao', String(votacaoId));
    root.id = `pi-votacao-${votacaoId}`;

    const loading = document.createElement('div');
    loading.setAttribute('data-pi-votacao-loading', '');
    root.appendChild(loading);

    const error = document.createElement('div');
    error.setAttribute('data-pi-votacao-error', '');
    error.hidden = true;
    root.appendChild(error);

    const notElig = document.createElement('div');
    notElig.setAttribute('data-pi-votacao-not-eligible', '');
    notElig.hidden = true;
    root.appendChild(notElig);

    const closed = document.createElement('div');
    closed.setAttribute('data-pi-votacao-closed', '');
    closed.hidden = true;
    const closedMsg = document.createElement('p');
    closedMsg.setAttribute('data-pi-closed-msg', '');
    closed.appendChild(closedMsg);
    root.appendChild(closed);

    const cats = document.createElement('div');
    cats.setAttribute('data-pi-votacao-categorias', '');
    root.appendChild(cats);

    const recibo = document.createElement('div');
    recibo.setAttribute('data-pi-recibo', '');
    recibo.hidden = true;
    root.appendChild(recibo);

    const live = document.createElement('div');
    live.setAttribute('data-pi-votacao-live', '');
    root.appendChild(live);

    // Modal estático
    const modal = document.createElement('div');
    modal.setAttribute('data-pi-modal', '');
    modal.setAttribute('data-pi-confirmacao-modal', '');
    modal.id = `pi-confirmacao-voto-${votacaoId}`;
    modal.hidden = true;

    const dialog = document.createElement('div');
    dialog.className = 'pi-modal__dialog';
    modal.appendChild(dialog);

    const cat = document.createElement('span');
    cat.setAttribute('data-pi-confirm-categoria', '');
    dialog.appendChild(cat);

    const cand = document.createElement('strong');
    cand.setAttribute('data-pi-confirm-candidato', '');
    dialog.appendChild(cand);

    const num = document.createElement('span');
    num.setAttribute('data-pi-confirm-numero', '');
    dialog.appendChild(num);

    const cd = document.createElement('p');
    cd.setAttribute('data-pi-confirm-countdown', '');
    dialog.appendChild(cd);

    const cdLive = document.createElement('span');
    cdLive.setAttribute('data-pi-confirm-countdown-live', '');
    dialog.appendChild(cdLive);

    const btnCancelar = document.createElement('button');
    btnCancelar.setAttribute('data-pi-confirm-cancelar', '');
    btnCancelar.setAttribute('data-pi-modal-close', '');
    dialog.appendChild(btnCancelar);

    const btnOk = document.createElement('button');
    btnOk.setAttribute('data-pi-confirm-ok', '');
    btnOk.disabled = true;
    btnOk.setAttribute('aria-disabled', 'true');
    dialog.appendChild(btnOk);

    root.appendChild(modal);
    document.body.appendChild(root);
    return root;
}

// --------------------------------------------------------------------------
// Rotas mock para as APIs
// --------------------------------------------------------------------------

function mockRoutes({ duplicate = false, latencyMs = 0, registrarStatus = 201 } = {}) {
    let registrarCount = 0;
    fetchRouter = (url, init) => {
        const u = String(url);
        const method = (init && init.method) || 'GET';
        if (/\/elegibilidade$/.test(u)) {
            return makeJsonResponse({
                votacao_id: 1,
                agente_id: 100,
                categorias: [
                    { categoria_id: 10, nome: 'Categoria A', elegivel: true, ja_votou: false },
                ],
            });
        }
        if (/\/categorias\?categoria_id=10$/.test(u) || /\/categorias\?categoria_id=10/.test(u)) {
            return makeJsonResponse({
                candidatos: [
                    { inscricao_id: 501, nome_publico: 'Maria Silva', numero_registro: 'A-001' },
                    { inscricao_id: 502, nome_publico: 'João Souza', numero_registro: 'A-002' },
                ],
            });
        }
        if (method === 'POST' && /\/votacao\/registrar$/.test(u)) {
            registrarCount += 1;
            globalThis.__registrarCount = registrarCount;
            if (duplicate) {
                return makeJsonResponse(
                    { code: 'pi_duplicate', message: 'Já votou' },
                    409
                );
            }
            const resp = {
                hash_voto: 'abc123def456',
                votacao_id: 1,
                categoria_id: 10,
                registrado_em: new Date().toISOString(),
            };
            if (latencyMs > 0) {
                return new Promise((res) => setTimeout(() => res({
                    ok: true, status: registrarStatus,
                    headers: new Headers({ 'Content-Type': 'application/json' }),
                    json: async () => resp, text: async () => JSON.stringify(resp),
                }), latencyMs));
            }
            return makeJsonResponse(resp, registrarStatus);
        }
        return makeJsonResponse({}, 200);
    };
}

async function waitFor(predicate, timeoutMs = 1000) {
    const start = Date.now();
    while (!predicate()) {
        if (Date.now() - start > timeoutMs) throw new Error('Timeout esperando condição');
        await new Promise((r) => setTimeout(r, 10));
    }
}

function getCard(root, idx = 0) {
    const cards = root.querySelectorAll('.pi-candidato-card');
    return cards[idx];
}

// --------------------------------------------------------------------------
// Test cases
// --------------------------------------------------------------------------

const APP_MODULE_URL = new URL('../../../assets/src/js/votacao/VotacaoApp.js', import.meta.url);

test('Anti-double-click: 2 cliques rápidos geram apenas 1 POST registrar', async () => {
    const { VotacaoApp, STATES } = await import(APP_MODULE_URL.href);
    mockRoutes({ latencyMs: 80 });
    globalThis.__registrarCount = 0;

    const root = buildAppRoot(1);
    const app = new VotacaoApp(root, { apiUrl: '/wp-json/pi/v1', nonce: 'n', votacaoId: 1 });

    await waitFor(() => root.querySelectorAll('.pi-candidato-card').length === 2, 2000);

    // Disparar dois cliques rápidos no primeiro card
    const card = getCard(root, 0);
    card.click();
    card.click(); // segundo click — deve ser ignorado pois já está em CONFIRMING

    // Aguardar countdown completar (3s) — encurtamos via fast-forward não disponível
    // Em vez disso, aceleramos confirmando direto pela API publica do app
    // (simulando passagem dos 3s):
    // Liberar botão e clicar
    const btnOk = root.querySelector('[data-pi-confirm-ok]');
    await waitFor(() => btnOk.disabled === false, 5000);
    // Clicar duas vezes rapidamente em "Confirmar"
    btnOk.click();
    btnOk.click(); // segundo click — anti-double-click via this.submitting

    // Aguardar resolução
    await waitFor(() => app.state === STATES.VOTED || app.state === STATES.NOT_VOTED && app.categorias[0].ja_votou, 5000);

    assert.equal(globalThis.__registrarCount, 1,
        'POST /votacao/registrar deve ter sido chamado exatamente 1 vez');
    app.destroy();
});

test('Modal cancelar não submete voto', async () => {
    const { VotacaoApp, STATES } = await import(APP_MODULE_URL.href);
    mockRoutes();
    globalThis.__registrarCount = 0;

    const root = buildAppRoot(1);
    const app = new VotacaoApp(root, { apiUrl: '/wp-json/pi/v1', nonce: 'n', votacaoId: 1 });
    await waitFor(() => root.querySelectorAll('.pi-candidato-card').length === 2, 2000);

    const card = getCard(root, 0);
    card.click();
    // Aguarda o modal abrir
    const modal = root.querySelector('[data-pi-confirmacao-modal]');
    await waitFor(() => modal.hidden === false, 2000);

    // Cancelar
    const btnCancel = root.querySelector('[data-pi-confirm-cancelar]');
    btnCancel.click();

    await waitFor(() => app.state === STATES.NOT_VOTED, 2000);
    assert.equal(globalThis.__registrarCount, 0, 'Cancelar NÃO deve disparar POST registrar');
    assert.equal(app.categorias[0].ja_votou, false, 'Categoria deve permanecer não-votada');
    app.destroy();
});

test('409 (duplicado) transita corretamente para estado VOTED na categoria', async () => {
    const { VotacaoApp, STATES } = await import(APP_MODULE_URL.href);
    mockRoutes({ duplicate: true });
    globalThis.__registrarCount = 0;

    const root = buildAppRoot(1);
    const app = new VotacaoApp(root, { apiUrl: '/wp-json/pi/v1', nonce: 'n', votacaoId: 1 });
    await waitFor(() => root.querySelectorAll('.pi-candidato-card').length === 2, 2000);

    const card = getCard(root, 0);
    card.click();

    const btnOk = root.querySelector('[data-pi-confirm-ok]');
    await waitFor(() => btnOk.disabled === false, 5000);
    btnOk.click();

    await waitFor(() => app.categorias[0].ja_votou === true, 3000);
    // Como há apenas 1 categoria, estado final deve ser VOTED
    assert.equal(app.state, STATES.VOTED, 'Estado deve ser VOTED após duplicado em única categoria');
    assert.equal(app.categorias[0].ja_votou, true, 'Categoria deve estar marcada como votada');
    app.destroy();
});

runAll();
