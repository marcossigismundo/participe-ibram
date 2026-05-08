/**
 * HelpModals.js
 *
 * Ativa os modais explicativos (TD-10): "Por que pedimos CPF?", "Como obter
 * ata de posse?", "O que e numero de registro?", "Como funciona a votacao?".
 * O HTML dos modais vive nos templates; este modulo apenas instancia.
 *
 * @module wizard/HelpModals
 */

import { initModals } from './Modal.js';

/**
 * Lista canonica de modais de ajuda esperados nos templates. Caso algum nao
 * exista no DOM, o trigger correspondente apenas nao tera comportamento (sem
 * crash). Isso permite reusar o mesmo bundle JS em PF/OR/SM.
 */
export const HELP_MODAL_IDS = [
    'pi-modal-help-cpf',
    'pi-modal-help-rg',
    'pi-modal-help-ata-posse',
    'pi-modal-help-numero-registro',
    'pi-modal-help-votacao',
    'pi-modal-help-cnpj',
    'pi-modal-help-coletivo',
    'pi-modal-help-pct',
    'pi-modal-help-pcd',
    'pi-modal-help-lgpd',
];

/**
 * Inicializa todos os help modals presentes em scope. Os triggers usam
 * [data-pi-modal-open="<id>"]. Os botoes "?" devem ter aria-label
 * e aria-haspopup="dialog" e aria-controls="<id>" no template.
 *
 * @param {ParentNode} [scope]
 */
export function initHelpModals(scope = document) {
    return initModals(scope);
}
