/**
 * audit.js — Audit Log Admin UI
 *
 * Funcionalidades:
 *  1. Modal de detalhes do registro via AJAX (fetch → renderiza JSON pretty).
 *  2. Modal de confirmação de export com escolha de formato.
 *  3. Syntax highlighting básico para JSON (sem dependência externa).
 *  4. Live region para feedback de export (ARIA).
 *  5. Trap de foco em modais (WCAG 2.1 AA, R4 §6).
 *  6. Sem JS inline; dados via elemento script type="application/json" (R5 V-05).
 *
 * @module admin/audit
 */
(function () {
    'use strict';

    /* ------------------------------------------------------------------ */
    /* Helpers                                                              */
    /* ------------------------------------------------------------------ */

    var FOCUSABLE = [
        'a[href]',
        'button:not([disabled])',
        'input:not([disabled])',
        'select:not([disabled])',
        'textarea:not([disabled])',
        '[tabindex]:not([tabindex="-1"])'
    ].join(',');

    function getRootData() {
        var node = document.getElementById('pi-audit-data');
        if (!node) {
            return {};
        }
        try {
            return JSON.parse(node.textContent || '{}');
        } catch (e) {
            return {};
        }
    }

    /**
     * Aplica syntax highlighting básico em uma string JSON.
     * Sem dependências externas — substitui tokens via regex simples.
     */
    function highlightJson(jsonStr) {
        // Escapa HTML primeiro para evitar XSS no resultado
        var escaped = jsonStr
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');

        // Coloriza: chaves (verde), strings (vermelho-escuro), números (azul),
        // booleanos/null (roxo)
        return escaped.replace(
            /("(?:[^"\\]|\\.)*")|(\b-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?\b)|(\b(?:true|false|null)\b)/g,
            function (match, str, num, kw) {
                if (str) {
                    return '<span class="pi-json-string">' + match + '</span>';
                }
                if (num) {
                    return '<span class="pi-json-number">' + match + '</span>';
                }
                if (kw) {
                    return '<span class="pi-json-keyword">' + match + '</span>';
                }
                return match;
            }
        );
    }

    /**
     * Formata objeto JS como JSON indentado com syntax highlighting.
     */
    function prettyJson(obj) {
        if (obj === null || obj === undefined) {
            return '<em class="pi-json-null">(sem dados)</em>';
        }
        try {
            var str = JSON.stringify(obj, null, 2);
            return '<code class="pi-audit-json-code">' + highlightJson(str) + '</code>';
        } catch (e) {
            return '<em>(erro ao formatar)</em>';
        }
    }

    /* ------------------------------------------------------------------ */
    /* Modal genérico (foco trap + ESC)                                    */
    /* ------------------------------------------------------------------ */

    var activeModal = null;
    var previousFocus = null;

    function openModal(modal) {
        if (!modal) {
            return;
        }
        previousFocus = document.activeElement;
        modal.hidden = false;
        modal.removeAttribute('hidden');
        document.body.classList.add('pi-modal-open');
        activeModal = modal;

        // Foca no primeiro elemento focável dentro do modal
        var focusable = modal.querySelectorAll(FOCUSABLE);
        if (focusable.length > 0) {
            focusable[0].focus();
        } else {
            modal.focus();
        }
    }

    function closeModal(modal) {
        if (!modal) {
            return;
        }
        modal.hidden = true;
        modal.setAttribute('hidden', '');
        document.body.classList.remove('pi-modal-open');
        activeModal = null;

        if (previousFocus && typeof previousFocus.focus === 'function') {
            previousFocus.focus();
        }
        previousFocus = null;
    }

    function trapFocus(modal, event) {
        var focusable = Array.prototype.slice.call(modal.querySelectorAll(FOCUSABLE));
        if (focusable.length === 0) {
            event.preventDefault();
            return;
        }
        var first = focusable[0];
        var last  = focusable[focusable.length - 1];

        if (event.shiftKey) {
            if (document.activeElement === first) {
                event.preventDefault();
                last.focus();
            }
        } else {
            if (document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }
    }

    /* ------------------------------------------------------------------ */
    /* Modal de detalhe do registro                                        */
    /* ------------------------------------------------------------------ */

    function initDetalheModal(cfg) {
        var modal = document.getElementById('pi-audit-detalhe-modal');
        var body  = document.getElementById('pi-audit-detalhe-body');
        if (!modal || !body) {
            return;
        }

        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.pi-audit-ver-detalhe');
            if (!btn) {
                return;
            }

            var id    = btn.getAttribute('data-audit-id');
            var nonce = btn.getAttribute('data-nonce');
            if (!id) {
                return;
            }

            // Limpa e abre modal com loading
            body.innerHTML = '<p class="pi-audit-loading" aria-live="polite">'
                + (cfg.i18n && cfg.i18n.loading ? cfg.i18n.loading : 'Carregando…')
                + '</p>';
            openModal(modal);

            fetchDetalhe(id, nonce, cfg, body);
        });

        // Fecha via botão ou ESC
        setupModalClose(modal);
    }

    function fetchDetalhe(id, nonce, cfg, body) {
        var url = cfg.ajaxUrl
            + '?action=pi_admin_audit_get_detalhe'
            + '&id=' + encodeURIComponent(id)
            + '&_wpnonce=' + encodeURIComponent(nonce || '');

        fetch(url, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (res) {
            if (!res.ok) {
                throw new Error('HTTP ' + res.status);
            }
            return res.json();
        })
        .then(function (data) {
            if (!data.success) {
                body.innerHTML = '<p class="pi-audit-error">'
                    + escapeHtml((data.data && data.data.message) ? data.data.message : 'Erro')
                    + '</p>';
                return;
            }
            renderDetalhe(data.data.record, body, cfg);
        })
        .catch(function (err) {
            body.innerHTML = '<p class="pi-audit-error">'
                + escapeHtml(cfg.i18n && cfg.i18n.error ? cfg.i18n.error : 'Erro ao carregar.')
                + '</p>';
            if (typeof console !== 'undefined') {
                console.error('[pi-audit] fetchDetalhe error:', err);
            }
        });
    }

    function renderDetalhe(record, container, cfg) {
        if (!record) {
            container.innerHTML = '<p>' + escapeHtml(cfg.i18n && cfg.i18n.notFound ? cfg.i18n.notFound : 'Não encontrado.') + '</p>';
            return;
        }

        var i18n = cfg.i18n || {};

        var html = '<dl class="pi-audit-dl">'
            + dlRow(i18n.id || 'ID', escapeHtml(String(record.id || '')))
            + dlRow(i18n.entidade || 'Entidade', escapeHtml(String(record.entidade || '')))
            + dlRow(i18n.entidadeId || 'ID da entidade', record.entidade_id !== null ? escapeHtml(String(record.entidade_id)) : '—')
            + dlRow(i18n.acao || 'Ação', '<code>' + escapeHtml(String(record.acao || '')) + '</code>')
            + dlRow(i18n.atorId || 'Ator', record.ator_id !== null ? escapeHtml(String(record.ator_id)) : '<em>sistema</em>')
            + dlRow(i18n.ocorridoEm || 'Ocorrido em', escapeHtml(String(record.ocorrido_em || '')))
            + dlRow(i18n.ipHash || 'IP (hash)', record.ip_hash ? '<code>' + escapeHtml(String(record.ip_hash)) + '</code>' : '—')
            + '</dl>';

        html += '<details class="pi-audit-accordion__item">'
            + '<summary class="pi-audit-accordion__summary">' + escapeHtml(i18n.dadosAntes || 'Dados antes') + '</summary>'
            + '<div class="pi-audit-accordion__body"><pre class="pi-audit-json">'
            + prettyJson(record.dados_antes)
            + '</pre></div></details>';

        html += '<details class="pi-audit-accordion__item">'
            + '<summary class="pi-audit-accordion__summary">' + escapeHtml(i18n.dadosDepois || 'Dados depois') + '</summary>'
            + '<div class="pi-audit-accordion__body"><pre class="pi-audit-json">'
            + prettyJson(record.dados_depois)
            + '</pre></div></details>';

        container.innerHTML = html;
    }

    function dlRow(label, value) {
        return '<div class="pi-audit-dl__row"><dt>' + label + '</dt><dd>' + value + '</dd></div>';
    }

    /* ------------------------------------------------------------------ */
    /* Modal de export                                                     */
    /* ------------------------------------------------------------------ */

    function initExportModal(cfg) {
        var openBtn   = document.querySelector('.pi-audit-export-open');
        var modal     = document.getElementById('pi-audit-export-modal');
        var form      = document.getElementById('pi-audit-export-form');
        var feedback  = document.getElementById('pi-audit-export-feedback');

        if (!openBtn || !modal || !form) {
            return;
        }

        openBtn.addEventListener('click', function () {
            openModal(modal);
        });

        setupModalClose(modal);

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            handleExportSubmit(form, feedback, cfg, modal);
        });
    }

    function handleExportSubmit(form, feedback, cfg, modal) {
        var formato = form.querySelector('input[name="formato"]:checked');
        var nonce   = form.querySelector('input[name="_wpnonce"]');

        if (!formato || !nonce) {
            return;
        }

        setFeedback(feedback, 'info', cfg.i18n && cfg.i18n.exporting ? cfg.i18n.exporting : 'Gerando export…');

        var body = new URLSearchParams();
        body.append('action', 'pi_admin_audit_export');
        body.append('_wpnonce', nonce.value);
        body.append('formato', formato.value);

        // Adiciona filtros ativos da URL
        var urlParams = new URLSearchParams(window.location.search);
        ['entidade', 'acao', 'data_de', 'data_ate', 'ator_id'].forEach(function (key) {
            if (urlParams.has(key)) {
                body.append(key, urlParams.get(key));
            }
        });

        fetch(cfg.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: body.toString()
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.success && data.data && data.data.url) {
                setFeedback(feedback, 'success', cfg.i18n && cfg.i18n.exportReady ? cfg.i18n.exportReady : 'Export pronto!');
                // Abre URL de download em nova aba
                window.open(data.data.url, '_blank', 'noopener,noreferrer');
                setTimeout(function () { closeModal(modal); }, 1500);
            } else {
                var msg = (data.data && data.data.message) ? data.data.message : 'Erro no export.';
                setFeedback(feedback, 'error', msg);
            }
        })
        .catch(function () {
            setFeedback(feedback, 'error', cfg.i18n && cfg.i18n.error ? cfg.i18n.error : 'Erro ao gerar export.');
        });
    }

    function setFeedback(el, type, message) {
        if (!el) {
            return;
        }
        el.className = 'pi-audit-feedback pi-audit-feedback--' + type;
        el.textContent = message;
    }

    /* ------------------------------------------------------------------ */
    /* Utilitários comuns                                                  */
    /* ------------------------------------------------------------------ */

    function setupModalClose(modal) {
        // Botões .pi-modal__close dentro do modal
        modal.addEventListener('click', function (e) {
            if (e.target.closest('.pi-modal__close') || e.target.classList.contains('pi-modal__backdrop')) {
                closeModal(modal);
            }
        });
    }

    // ESC fecha o modal ativo
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && activeModal) {
            closeModal(activeModal);
        }
        if (e.key === 'Tab' && activeModal) {
            trapFocus(activeModal, e);
        }
    });

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    /* ------------------------------------------------------------------ */
    /* Boot                                                                */
    /* ------------------------------------------------------------------ */

    function init() {
        var cfg = getRootData();
        if (!cfg.ajaxUrl) {
            return;
        }
        initDetalheModal(cfg);
        initExportModal(cfg);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

}());
