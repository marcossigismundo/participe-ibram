/**
 * AccessibilityHelpers.js
 *
 * Utilitarios de acessibilidade: live region, foco gerenciado, skip links.
 * Conforme R4 secoes 2-4 (eMAG/WCAG 2.1 AA).
 *
 * @module wizard/AccessibilityHelpers
 */

const LIVE_REGION_ID = 'pi-wizard-live';

/**
 * Garante a existencia de uma live region polite no DOM.
 * @returns {HTMLElement}
 */
export function ensureLiveRegion() {
    let region = document.getElementById(LIVE_REGION_ID);
    if (region) {
        return region;
    }
    region = document.createElement('div');
    region.id = LIVE_REGION_ID;
    region.className = 'sr-only';
    region.setAttribute('role', 'status');
    region.setAttribute('aria-live', 'polite');
    region.setAttribute('aria-atomic', 'true');
    document.body.appendChild(region);
    return region;
}

/**
 * Anuncia um texto via live region. Reaplica o texto para forcar leitor a relê-lo.
 *
 * @param {string} text texto em pt_BR ja escapado.
 * @param {('polite'|'assertive')} [politeness='polite']
 */
export function announceToLiveRegion(text, politeness = 'polite') {
    const region = ensureLiveRegion();
    region.setAttribute('aria-live', politeness);
    // Limpa primeiro para garantir releitura caso mensagem seja igual
    region.textContent = '';
    // requestAnimationFrame garante repaint entre os dois sets
    window.requestAnimationFrame(() => {
        region.textContent = String(text);
    });
}

/**
 * Move o foco para o sumario de erros de um passo.
 * @param {HTMLElement} formOrPanel
 * @returns {HTMLElement|null}
 */
export function focusFirstError(formOrPanel) {
    if (!formOrPanel) {
        return null;
    }
    const sumario = formOrPanel.querySelector('.pi-erros-sumario:not([hidden])');
    if (sumario) {
        sumario.setAttribute('tabindex', '-1');
        sumario.focus({ preventScroll: false });
        return sumario;
    }
    const invalido = formOrPanel.querySelector('[aria-invalid="true"]');
    if (invalido && typeof invalido.focus === 'function') {
        invalido.focus();
        return invalido;
    }
    return null;
}

/**
 * Foca o titulo H2 do passo (R4 erro comum #12: focar no h2 nao no input).
 *
 * @param {HTMLElement} stepEl elemento do panel do passo
 */
export function setFocusOnHeading(stepEl) {
    if (!stepEl) {
        return;
    }
    const heading = stepEl.querySelector('h2[tabindex="-1"], h2');
    if (heading) {
        if (!heading.hasAttribute('tabindex')) {
            heading.setAttribute('tabindex', '-1');
        }
        heading.focus({ preventScroll: false });
    }
}

/**
 * Garante presenca de skip link como primeiro elemento focavel.
 * @param {string} targetId
 */
export function setupSkipLinks(targetId = 'pi-conteudo-principal') {
    if (document.querySelector('.pi-skip-link')) {
        return;
    }
    const link = document.createElement('a');
    link.className = 'pi-skip-link';
    link.href = `#${targetId}`;
    link.textContent = 'Pular para o conteúdo principal';
    document.body.insertBefore(link, document.body.firstChild);
}

/**
 * Extrai texto do label associado a um campo.
 * @param {HTMLElement} field
 * @param {HTMLElement} [scope]
 * @returns {string}
 */
export function getFieldLabel(field, scope) {
    if (!field) {
        return '';
    }
    const root = scope || field.ownerDocument || document;
    if (field.id) {
        const label = root.querySelector(`label[for="${cssEscape(field.id)}"]`);
        if (label) {
            return label.textContent.replace(/\*/g, '').replace(/\s+/g, ' ').trim();
        }
    }
    if (field.getAttribute('aria-label')) {
        return field.getAttribute('aria-label').trim();
    }
    return field.name || '';
}

/**
 * Polyfill simples para CSS.escape em browsers muito antigos.
 * @param {string} value
 * @returns {string}
 */
export function cssEscape(value) {
    if (window.CSS && typeof window.CSS.escape === 'function') {
        return window.CSS.escape(value);
    }
    return String(value).replace(/([!"#$%&'()*+,./:;<=>?@[\\\]^`{|}~])/g, '\\$1');
}
