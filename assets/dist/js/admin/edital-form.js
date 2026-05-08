/**
 * edital-form.js
 *
 * Admin page: criar / editar edital.
 *  - Validação cronológica de datas com aria-live (TD-11 WCAG 2.1 AA).
 *  - Mostra erros inline com aria-invalid + mensagem de erro.
 *  - Previne submit se inválido (servidor sempre revalida — defense in depth).
 *  - Sincroniza sidebar timeline ao alterar datas.
 *
 * @module admin/edital-form
 */
(function () {
    'use strict';

    var ORDER = [
        'abertura',
        'encerramento_inscricoes',
        'publicacao_habilitacao',
        'prazo_recurso_inabilitacao',
        'abertura_votacao',
        'encerramento_votacao',
        'publicacao_resultado',
    ];

    function getData() {
        var node = document.getElementById('pi-edital-form-data');
        if (!node) { return {}; }
        try { return JSON.parse(node.textContent || '{}'); } catch (e) { return {}; }
    }

    function announce(msg) {
        var live = document.getElementById('pi-date-errors');
        if (!live) { return; }
        live.textContent = '';
        setTimeout(function () { live.textContent = msg; }, 30);
    }

    /**
     * Lê os valores atuais de todos os campos data em ms epoch.
     * @returns {Object.<string, number|null>}
     */
    function readDates() {
        var out = {};
        ORDER.forEach(function (name) {
            var input = document.querySelector('[data-pi-date-order="' + name + '"]');
            if (!input) { out[name] = null; return; }
            var ts = input.value ? new Date(input.value).getTime() : null;
            out[name] = isNaN(ts) ? null : ts;
        });
        return out;
    }

    /**
     * Valida a ordem cronológica. Retorna array de mensagens de erro.
     * @returns {string[]}
     */
    function validateOrder(config) {
        var dates  = readDates();
        var errors = [];
        var prev   = null;
        var prevName = null;

        ORDER.forEach(function (name) {
            var val = dates[name];
            if (val === null) { return; }
            if (prev !== null && val <= prev) {
                errors.push(
                    (config.i18n && config.i18n.erroCronologia) ||
                    'Datas fora de ordem: ' + name
                );
            }
            prev = val;
            prevName = name;
        });
        return errors;
    }

    /**
     * Marca visualmente um campo com aria-invalid.
     */
    function markField(name, hasError, message) {
        var input = document.querySelector('[data-pi-date-order="' + name + '"]');
        if (!input) { return; }
        input.setAttribute('aria-invalid', hasError ? 'true' : 'false');
        var fieldDiv = input.closest('.pi-form-field');
        if (!fieldDiv) { return; }
        var errId = 'pi-date-inline-error-' + name;
        var errEl = fieldDiv.querySelector('#' + errId);
        if (hasError && message) {
            if (!errEl) {
                errEl = document.createElement('p');
                errEl.id = errId;
                errEl.className = 'pi-field-error';
                errEl.setAttribute('role', 'alert');
                input.parentNode.insertBefore(errEl, input.nextSibling);
            }
            errEl.textContent = message;
        } else if (errEl) {
            errEl.parentNode.removeChild(errEl);
        }
    }

    /**
     * Atualiza o sidebar timeline quando um campo data muda.
     */
    function syncTimeline() {
        ORDER.forEach(function (name) {
            var input = document.querySelector('[data-pi-date-order="' + name + '"]');
            var tl    = document.getElementById('pi-tl-' + name.replace(/_/g, '-'));
            if (!input || !tl) { return; }
            if (input.value) {
                try {
                    var d = new Date(input.value);
                    var fmt = d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR', {hour:'2-digit',minute:'2-digit'});
                    tl.textContent = fmt;
                } catch (e) {
                    tl.textContent = input.value;
                }
            } else {
                tl.textContent = '—';
            }
        });
    }

    function init() {
        var form = document.querySelector('[data-pi-edital-form] form');
        if (!form) { return; }

        var config = getData();

        // Attach change listeners to all date inputs.
        ORDER.forEach(function (name) {
            var input = document.querySelector('[data-pi-date-order="' + name + '"]');
            if (!input) { return; }
            input.addEventListener('change', function () {
                syncTimeline();
                var errs = validateOrder(config);
                ORDER.forEach(function (n) { markField(n, false, ''); });
                if (errs.length > 0) {
                    announce(errs.join(' '));
                } else {
                    announce('');
                }
            });
        });

        // Prevent submit if chronology is invalid.
        form.addEventListener('submit', function (e) {
            var errs = validateOrder(config);
            if (errs.length > 0) {
                e.preventDefault();
                announce(errs.join(' '));
                // Mark the conflicting pair.
                var dates = readDates();
                var prev = null;
                ORDER.forEach(function (name) {
                    var val = dates[name];
                    if (val === null) { return; }
                    if (prev !== null && val <= prev) {
                        markField(name, true, (config.i18n && config.i18n.erroCronologia) || 'Datas fora de ordem');
                    }
                    prev = val;
                });
                // Focus first errored field.
                var firstBad = form.querySelector('[aria-invalid="true"]');
                if (firstBad) { firstBad.focus(); }
            }
        });

        // Initial sync.
        syncTimeline();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
