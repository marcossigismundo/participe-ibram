/**
 * AnonimizacaoConfirm.js
 *
 * Controla a página de confirmação final de anonimização (W8-B):
 *  - Countdown 30s visível (atualiza `aria-live`).
 *  - Botão "Confirmar" disabled durante countdown.
 *  - Disabled também após o click (spinner) — anti-double.
 *  - AbortController em `beforeunload` para não vazar pending fetch.
 *
 * @module minha-conta/AnonimizacaoConfirm
 */

import { ApiClientLgpd, ApiError } from './ApiClientLgpd.js';

export class AnonimizacaoConfirm {
    /**
     * @param {HTMLElement} root Elemento `[data-pi-anon-confirm]`.
     */
    constructor(root) {
        if (!root) throw new Error('AnonimizacaoConfirm requires a root element.');
        this.root = root;
        this.api = new ApiClientLgpd(root.dataset.restBase || '/wp-json/pi/v1', root.dataset.restNonce || '');
        this.token = root.dataset.token || '';
        this.countdownSeconds = parseInt(root.dataset.countdownSeconds || '30', 10);
        if (!Number.isFinite(this.countdownSeconds) || this.countdownSeconds < 1) {
            this.countdownSeconds = 30;
        }
        this.status = root.querySelector('#pi-anon-confirm-status');
        this.btnConfirm = root.querySelector('[data-action="confirmar-anon"]');
        this.regionCountdown = root.querySelector('[data-region="countdown"]');
        this.regionLabel = root.querySelector('[data-region="countdown-label"]');
        this.regionResult = root.querySelector('[data-region="anon-result"]');
        this.aborter = new AbortController();
        this.timer = null;
        this.confirmed = false;
    }

    init() {
        if (!this.btnConfirm) return;
        this._startCountdown();
        this.btnConfirm.addEventListener('click', (e) => {
            e.preventDefault();
            this._confirm();
        });
        // AbortController em beforeunload (previne vazamento de pending fetch).
        window.addEventListener('beforeunload', () => {
            try { this.aborter.abort(); } catch (_e) { /* noop */ }
        }, { once: true });
    }

    _announce(msg) {
        if (!this.status) return;
        this.status.textContent = '';
        setTimeout(() => { this.status.textContent = String(msg || ''); }, 30);
    }

    _startCountdown() {
        let remaining = this.countdownSeconds;
        const original = this.btnConfirm.dataset.originalLabel || 'Confirmar anonimização';

        const tick = () => {
            remaining -= 1;
            if (remaining > 0) {
                if (this.regionCountdown) {
                    this.regionCountdown.textContent = ` (${remaining}s)`;
                }
                // Anuncia a cada 5 segundos para não saturar leitor de tela.
                if (remaining === 25 || remaining === 15 || remaining === 5) {
                    this._announce(`${remaining} segundos para o botão de confirmação ficar disponível.`);
                }
            } else {
                clearInterval(this.timer);
                this.timer = null;
                if (this.regionCountdown) this.regionCountdown.textContent = '';
                if (this.regionLabel) {
                    this.regionLabel.textContent = original;
                }
                this.btnConfirm.disabled = false;
                this._announce('Botão de confirmação habilitado. Use com cuidado: a anonimização é irreversível.');
            }
        };
        this.timer = setInterval(tick, 1000);
    }

    async _confirm() {
        if (this.confirmed) return;
        if (this.btnConfirm.disabled) return;
        this.confirmed = true;
        this.btnConfirm.disabled = true;
        this.btnConfirm.setAttribute('aria-busy', 'true');
        this._announce('Executando anonimização...');

        try {
            await this.api.confirmarAnonimizacao(this.token);
            this._announce('Anonimização concluída. Você será redirecionado.');
            if (this.regionResult) {
                this.regionResult.hidden = false;
                this.regionResult.innerHTML = '<div class="pi-alert pi-alert--success" role="status">'
                    + 'Anonimização concluída. Sua sessão será encerrada.'
                    + '</div>';
            }
            // Redireciona para home após um breve delay (permite que SR anuncie).
            setTimeout(() => {
                window.location.href = '/wp-login.php?action=logout';
            }, 3000);
        } catch (e) {
            this.btnConfirm.removeAttribute('aria-busy');
            // Para anonimização não re-habilita o botão automaticamente — evita "tentativas múltiplas".
            // Apenas em alguns erros recuperáveis (rede, 429) re-habilita.
            let msg = 'Falha ao confirmar anonimização.';
            if (e instanceof ApiError) {
                if (e.status === 0 || e.status === 429) {
                    msg = 'Falha de rede ou limite de tentativas. Recarregue a página e tente novamente.';
                } else if (e.status === 401 || e.status === 403) {
                    msg = 'Sessão expirada. Faça login novamente.';
                } else if (e.code === 'pi_domain') {
                    msg = e.message || 'Token inválido ou já utilizado.';
                } else {
                    msg = e.message || msg;
                }
            }
            this._announce(msg);
            if (this.regionResult) {
                this.regionResult.hidden = false;
                this.regionResult.innerHTML = '';
                const div = document.createElement('div');
                div.className = 'pi-alert pi-alert--danger';
                div.setAttribute('role', 'alert');
                div.textContent = msg;
                this.regionResult.appendChild(div);
            }
            this.confirmed = false;
            // Re-habilita só em erros transitórios.
            if (e instanceof ApiError && (e.status === 0 || e.status === 429)) {
                this.btnConfirm.disabled = false;
            }
        }
    }
}

export function bootAnonimizacaoConfirm() {
    const root = document.querySelector('[data-pi-anon-confirm]');
    if (!root) return null;
    const ui = new AnonimizacaoConfirm(root);
    ui.init();
    return ui;
}
