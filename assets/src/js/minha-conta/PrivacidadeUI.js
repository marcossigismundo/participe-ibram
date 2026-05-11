/**
 * PrivacidadeUI.js
 *
 * Controlador de UI da aba "Privacidade" (Wave 8 W8-B).
 *
 *  - Toggle revogar/reaceitar consentimento via AJAX.
 *  - Modal de nova solicitação LGPD Art. 18 (reusa wizard/Modal.js).
 *  - Modal de re-autenticação para export.
 *  - Modal de solicitação de anonimização.
 *  - Live region para a11y.
 *  - Anti-double-click em ações destrutivas (botão fica disabled durante request).
 *
 * @module minha-conta/PrivacidadeUI
 */

import { Modal } from '../wizard/Modal.js';
import { ApiClientLgpd, ApiError } from './ApiClientLgpd.js';

export class PrivacidadeUI {
    /**
     * @param {HTMLElement} container Elemento `[data-pi-mc-privacidade]`.
     */
    constructor(container) {
        if (!container) {
            throw new Error('PrivacidadeUI requires a container element.');
        }
        this.root = container;
        const restBase = this.root.dataset.restBase || '/wp-json/pi/v1';
        const nonce = this.root.dataset.restNonce || '';
        this.api = new ApiClientLgpd(restBase, nonce);
        this.status = this.root.querySelector('#pi-privacidade-status');
        this.openModal = null;
    }

    init() {
        this.root.addEventListener('click', (e) => this._onClick(e));
    }

    _announce(msg) {
        if (!this.status) return;
        this.status.textContent = '';
        // microtask ensures screen reader re-announces.
        setTimeout(() => { this.status.textContent = String(msg || ''); }, 30);
    }

    _onClick(e) {
        const btn = e.target.closest('[data-action]');
        if (!btn || !this.root.contains(btn)) return;
        const action = btn.dataset.action;
        switch (action) {
            case 'revogar':
                e.preventDefault();
                this._handleRevogar(btn);
                break;
            case 'reaceitar':
                e.preventDefault();
                this._handleReaceitar(btn);
                break;
            case 'abrir-modal-nova-solicitacao':
                e.preventDefault();
                this._openModalNovaSolicitacao();
                break;
            case 'abrir-modal-export':
                e.preventDefault();
                this._openModalExport();
                break;
            case 'abrir-modal-anon':
                e.preventDefault();
                this._openModalAnon();
                break;
            case 'imprimir-termo':
                e.preventDefault();
                window.print();
                break;
            default:
                // Botões dentro de modais são tratados pelos forms abaixo.
                break;
        }
    }

    /**
     * Bloqueia o botão durante a requisição (anti-double).
     */
    async _withGuard(btn, fn) {
        if (btn.disabled) return;
        const originalLabel = btn.textContent;
        btn.disabled = true;
        btn.setAttribute('aria-busy', 'true');
        try {
            await fn();
        } catch (e) {
            this._announce(this._messageForError(e));
        } finally {
            btn.disabled = false;
            btn.removeAttribute('aria-busy');
            // re-fix label apenas se foi alterado por loading state
            if (originalLabel && btn.textContent === '') {
                btn.textContent = originalLabel;
            }
        }
    }

    _messageForError(e) {
        if (e instanceof ApiError) {
            return e.message || 'Erro ao processar.';
        }
        return 'Erro ao processar. Tente novamente.';
    }

    async _handleRevogar(btn) {
        const finalidade = btn.dataset.finalidade || '';
        if (!finalidade) return;
        if (!window.confirm('Deseja revogar este consentimento?')) return;
        await this._withGuard(btn, async () => {
            await this.api.revogar(finalidade);
            this._announce('Consentimento revogado.');
            this._refreshConsentItem(finalidade, 'revogado');
        });
    }

    async _handleReaceitar(btn) {
        const finalidade = btn.dataset.finalidade || '';
        if (!finalidade) return;
        await this._withGuard(btn, async () => {
            await this.api.reaceitar(finalidade);
            this._announce('Consentimento aceito.');
            this._refreshConsentItem(finalidade, 'aceito');
        });
    }

    _refreshConsentItem(finalidade, novoStatus) {
        const item = this.root.querySelector(`.pi-consents-list__item[data-finalidade="${CSS.escape(finalidade)}"]`);
        if (!item) return;
        const badge = item.querySelector('[data-role="status-badge"]');
        if (badge) {
            badge.textContent = novoStatus === 'aceito' ? 'Aceito' : (novoStatus === 'revogado' ? 'Revogado' : novoStatus);
            badge.classList.remove('pi-badge--ok', 'pi-badge--muted', 'pi-badge--warn');
            badge.classList.add(novoStatus === 'aceito' ? 'pi-badge--ok' : 'pi-badge--muted');
        }
        // Toggle button revogar↔reaceitar
        const actions = item.querySelector('.pi-consents-list__actions');
        if (actions) {
            const oldBtn = actions.querySelector('button[data-action]');
            if (oldBtn) {
                if (novoStatus === 'revogado') {
                    oldBtn.dataset.action = 'reaceitar';
                    oldBtn.className = 'pi-btn pi-btn--primary';
                    oldBtn.textContent = 'Reaceitar';
                } else if (novoStatus === 'aceito') {
                    oldBtn.dataset.action = 'revogar';
                    oldBtn.className = 'pi-btn pi-btn--danger-outline';
                    oldBtn.textContent = 'Revogar';
                }
            }
        }
    }

    // ─── Modal: Nova solicitação ─────────────────────────────────────────

    _openModalNovaSolicitacao() {
        const tpl = this.root.querySelector('#pi-modal-nova-solicitacao');
        if (!tpl) return;
        const node = tpl.content.firstElementChild.cloneNode(true);
        const modal = new Modal(node, { onClose: () => { this.openModal = null; } });
        this.openModal = modal;
        modal.abrir();

        const form = node.querySelector('form[data-form="nova-solicitacao"]');
        if (form) {
            form.addEventListener('submit', async (ev) => {
                ev.preventDefault();
                const submitBtn = form.querySelector('button[data-action="enviar-solicitacao"]');
                const tipo = form.elements.namedItem('tipo')?.value || '';
                const detalhes = form.elements.namedItem('detalhes_md')?.value || '';
                if (!tipo) {
                    this._announce('Selecione um tipo de solicitação.');
                    return;
                }
                await this._withGuard(submitBtn, async () => {
                    await this.api.criarSolicitacao(tipo, detalhes);
                    this._announce('Solicitação registrada. Você receberá um email de confirmação.');
                    modal.fechar();
                    // Recarrega para refletir lista atualizada
                    window.location.reload();
                });
            });
        }
        // Fechar via data-action
        node.querySelectorAll('[data-action="fechar-modal"]').forEach((b) => {
            b.addEventListener('click', (ev) => { ev.preventDefault(); modal.fechar(); });
        });
    }

    // ─── Modal: Export (re-autenticação) ─────────────────────────────────

    _openModalExport() {
        const tpl = this.root.querySelector('#pi-modal-export');
        if (!tpl) return;
        const node = tpl.content.firstElementChild.cloneNode(true);
        const modal = new Modal(node, { onClose: () => { this.openModal = null; } });
        this.openModal = modal;
        modal.abrir();

        const form = node.querySelector('form[data-form="export-reauth"]');
        const resultBox = node.querySelector('[data-region="export-result"]');
        if (form) {
            form.addEventListener('submit', async (ev) => {
                ev.preventDefault();
                const submitBtn = form.querySelector('button[data-action="enviar-export"]');
                const senha = form.elements.namedItem('confirmacao_senha')?.value || '';
                if (!senha) {
                    this._announce('Senha obrigatória.');
                    return;
                }
                await this._withGuard(submitBtn, async () => {
                    try {
                        const resp = await this.api.solicitarExport(senha);
                        form.hidden = true;
                        if (resultBox) {
                            resultBox.hidden = false;
                            const url = resp && resp.download_url ? resp.download_url : '';
                            resultBox.innerHTML = '';
                            const p = document.createElement('p');
                            p.textContent = 'Seu pacote está pronto. O link expira em 24h.';
                            const a = document.createElement('a');
                            a.href = url;
                            a.className = 'pi-btn pi-btn--primary';
                            a.rel = 'noopener';
                            a.textContent = 'Baixar pacote';
                            resultBox.appendChild(p);
                            resultBox.appendChild(a);
                            a.focus();
                        }
                        this._announce('Pacote de dados pronto para download.');
                    } catch (e) {
                        if (e instanceof ApiError && e.status === 429) {
                            this._announce('Você já gerou um export hoje. Tente novamente em 24h.');
                        } else if (e instanceof ApiError && e.status === 401) {
                            this._announce('Senha incorreta.');
                        } else {
                            throw e;
                        }
                    }
                });
            });
        }
        node.querySelectorAll('[data-action="fechar-modal"]').forEach((b) => {
            b.addEventListener('click', (ev) => { ev.preventDefault(); modal.fechar(); });
        });
    }

    // ─── Modal: Anonimização ─────────────────────────────────────────────

    _openModalAnon() {
        const tpl = this.root.querySelector('#pi-modal-anon');
        if (!tpl) return;
        const node = tpl.content.firstElementChild.cloneNode(true);
        const modal = new Modal(node, { onClose: () => { this.openModal = null; } });
        this.openModal = modal;
        modal.abrir();

        const form = node.querySelector('form[data-form="anon-reauth"]');
        if (form) {
            form.addEventListener('submit', async (ev) => {
                ev.preventDefault();
                const submitBtn = form.querySelector('button[data-action="enviar-anon"]');
                const senha = form.elements.namedItem('confirmacao_senha')?.value || '';
                const motivo = form.elements.namedItem('motivo')?.value || '';
                if (!senha) {
                    this._announce('Senha obrigatória.');
                    return;
                }
                await this._withGuard(submitBtn, async () => {
                    try {
                        await this.api.solicitarAnonimizacao(senha, motivo);
                        this._announce('Enviamos um link de confirmação para seu email. Verifique a caixa de entrada.');
                        modal.fechar();
                        window.alert(
                            'Enviamos um link de confirmação para seu email.\n\n'
                            + 'Clique no link em até 24 horas para concluir a anonimização. '
                            + 'A página de confirmação tem um aviso final de 30 segundos antes do botão ficar ativo.'
                        );
                    } catch (e) {
                        if (e instanceof ApiError && e.status === 401) {
                            this._announce('Senha incorreta.');
                        } else if (e instanceof ApiError && e.status === 429) {
                            this._announce('Já existe um pedido em andamento. Verifique seu email ou aguarde 24h.');
                        } else {
                            throw e;
                        }
                    }
                });
            });
        }
        node.querySelectorAll('[data-action="fechar-modal"]').forEach((b) => {
            b.addEventListener('click', (ev) => { ev.preventDefault(); modal.fechar(); });
        });
    }
}

/**
 * Auto-bootstrap se a aba estiver no DOM.
 */
export function bootPrivacidade() {
    const root = document.querySelector('[data-pi-mc-privacidade]');
    if (!root) return null;
    const ui = new PrivacidadeUI(root);
    ui.init();
    return ui;
}
