/**
 * ConfirmacaoVoto.js
 *
 * Modal de confirmacao de voto. Estende o pattern de wizard/Modal.js (mesma
 * mecanica de focus trap, ESC, restauracao de foco), com adicoes especificas
 * de "warning":
 *
 *  - Aviso destacado de IRREVERSIBILIDADE
 *  - Foco default no botao "Cancelar" (R4 §6 — never default focus on destructive)
 *  - Botao "Confirmar" exibido com countdown de 3s antes de habilitar
 *    (anti-confirmacao-apressada — WCAG 2.1 AA 3.3.4 Error Prevention Legal)
 *  - Countdown anunciado via aria-live polite ("Botão liberado em 3...2...1")
 *  - Apenas Enter explicito no botao Confirmar dispara — Space e bloqueado
 *    durante o countdown e logo apos (debounce de 200ms para evitar
 *    Tab+Space rapido inadvertido).
 *
 * Reutiliza window.PiModal? — para evitar dependencia circular preferimos
 * import direto da Wave 3.
 *
 * @module votacao/ConfirmacaoVoto
 */

import { Modal } from '../wizard/Modal.js';

const COUNTDOWN_SECONDS = 3;
const POST_ENABLE_DEBOUNCE_MS = 200;

function i18n(key, fallback) {
    const dict = (typeof window !== 'undefined' && window.piI18n) || {};
    return (dict && dict[key]) || fallback;
}

/**
 * Wrapper sobre Modal com fluxo de confirmacao para voto.
 */
export class ConfirmacaoVoto {
    /**
     * @param {HTMLElement} modalEl elemento root do modal estatico no DOM
     */
    constructor(modalEl) {
        if (!modalEl) {
            throw new Error('ConfirmacaoVoto requires modal element');
        }
        this.modalEl = modalEl;
        this.modal = modalEl._piModalInstance || new Modal(modalEl);
        modalEl._piModalInstance = this.modal;

        this._countdownInterval = null;
        this._enabledAt = 0;
        this._currentResolver = null;

        this._cacheDom();
        this._wireEvents();
    }

    _cacheDom() {
        const root = this.modalEl;
        this.elNomeCandidato = root.querySelector('[data-pi-confirm-candidato]');
        this.elNumeroCandidato = root.querySelector('[data-pi-confirm-numero]');
        this.elCategoria = root.querySelector('[data-pi-confirm-categoria]');
        this.btnCancelar = root.querySelector('[data-pi-confirm-cancelar]');
        this.btnConfirmar = root.querySelector('[data-pi-confirm-ok]');
        this.elCountdown = root.querySelector('[data-pi-confirm-countdown]');
        this.elCountdownLive = root.querySelector('[data-pi-confirm-countdown-live]');
    }

    _wireEvents() {
        if (this.btnCancelar) {
            this.btnCancelar.addEventListener('click', (e) => {
                e.preventDefault();
                this._resolveAndClose(false);
            });
        }
        if (this.btnConfirmar) {
            this.btnConfirmar.addEventListener('click', (e) => {
                e.preventDefault();
                this._handleConfirmClick(e);
            });
            // Bloqueia Space durante countdown e logo apos (anti-confirmacao
            // apressada via Tab+Space). Enter sempre permitido apos habilitar.
            this.btnConfirmar.addEventListener('keydown', (e) => {
                if (e.key === ' ') {
                    if (this._countdownInterval || (Date.now() - this._enabledAt) < POST_ENABLE_DEBOUNCE_MS) {
                        e.preventDefault();
                    }
                }
            });
        }

        // Quando o modal fecha por ESC ou click no overlay, considerar como cancelar
        this.modalEl.addEventListener('pi:modal:closed', () => {
            this._stopCountdown();
            this._resolvePending(false);
        });
    }

    /**
     * Abre modal de confirmacao e retorna Promise que resolve com:
     *  - true  -> usuario confirmou
     *  - false -> usuario cancelou (ESC, click cancelar, click overlay)
     *
     * @param {{
     *   nomeCandidato:string,
     *   numeroRegistro:string,
     *   nomeCategoria:string,
     *   trigger?:HTMLElement,
     * }} dados
     * @returns {Promise<boolean>}
     */
    abrir(dados) {
        // Resolve Promise pendente anterior (defesa contra duplo-abrir)
        this._resolvePending(false);

        if (this.elNomeCandidato) {
            this.elNomeCandidato.textContent = String(dados.nomeCandidato || '');
        }
        if (this.elNumeroCandidato) {
            this.elNumeroCandidato.textContent = String(dados.numeroRegistro || '');
        }
        if (this.elCategoria) {
            this.elCategoria.textContent = String(dados.nomeCategoria || '');
        }

        // Estado inicial: confirmar desabilitado, countdown a iniciar
        if (this.btnConfirmar) {
            this.btnConfirmar.disabled = true;
            this.btnConfirmar.setAttribute('aria-disabled', 'true');
        }

        const promise = new Promise((resolve) => {
            this._currentResolver = resolve;
        });

        this.modal.abrir(dados.trigger);
        // Foco default em "Cancelar" (R4 §6: nunca em ação destrutiva por default)
        if (this.btnCancelar) {
            // Aguardar 1 frame para o modal aparecer e Modal.js focar o primeiro
            // focusable; entao mover para Cancelar explicitamente.
            window.requestAnimationFrame(() => {
                if (this.btnCancelar) {
                    this.btnCancelar.focus();
                }
            });
        }
        this._startCountdown();

        return promise;
    }

    fechar() {
        this._stopCountdown();
        this.modal.fechar();
    }

    _startCountdown() {
        let remaining = COUNTDOWN_SECONDS;
        this._renderCountdown(remaining);
        this._announceCountdown(remaining);

        this._stopCountdown();
        this._countdownInterval = window.setInterval(() => {
            remaining -= 1;
            if (remaining <= 0) {
                this._stopCountdown();
                this._enableConfirm();
                return;
            }
            this._renderCountdown(remaining);
            this._announceCountdown(remaining);
        }, 1000);
    }

    _stopCountdown() {
        if (this._countdownInterval) {
            window.clearInterval(this._countdownInterval);
            this._countdownInterval = null;
        }
    }

    _renderCountdown(seconds) {
        if (!this.elCountdown) return;
        const tpl = i18n('confirmCountdown', 'Botão liberado em {n}s');
        this.elCountdown.textContent = tpl.replace('{n}', String(seconds));
    }

    _announceCountdown(seconds) {
        if (!this.elCountdownLive) return;
        // Para leitor de tela: anuncia apenas marcos (3 e 1)
        if (seconds === COUNTDOWN_SECONDS) {
            this.elCountdownLive.textContent = i18n(
                'confirmCountdownAnnounce',
                'Aguarde 3 segundos antes de confirmar o voto'
            );
        } else if (seconds === 1) {
            this.elCountdownLive.textContent = i18n(
                'confirmCountdownReady',
                'Botão Confirmar voto será habilitado'
            );
        }
    }

    _enableConfirm() {
        if (this.elCountdown) {
            this.elCountdown.textContent = '';
        }
        if (this.elCountdownLive) {
            this.elCountdownLive.textContent = i18n(
                'confirmEnabled',
                'Botão Confirmar voto habilitado. Pressione Enter para confirmar.'
            );
        }
        if (this.btnConfirmar) {
            this.btnConfirmar.disabled = false;
            this.btnConfirmar.removeAttribute('aria-disabled');
        }
        this._enabledAt = Date.now();
    }

    _handleConfirmClick(_event) {
        if (!this.btnConfirmar) return;
        if (this.btnConfirmar.disabled) return;
        // Anti-double-click — desabilitar e resolver. O caller usara o resultado
        // para iniciar o submit; se houver erro, abrir nova confirmacao.
        this.btnConfirmar.disabled = true;
        this._resolveAndClose(true);
    }

    _resolveAndClose(confirmed) {
        this._stopCountdown();
        const resolver = this._currentResolver;
        this._currentResolver = null;
        this.modal.fechar();
        if (resolver) {
            resolver(Boolean(confirmed));
        }
    }

    _resolvePending(value) {
        const resolver = this._currentResolver;
        this._currentResolver = null;
        if (resolver) {
            resolver(Boolean(value));
        }
    }
}

export default ConfirmacaoVoto;
