/**
 * StepDefinitionsInscricao.js
 *
 * Define os 5 passos do wizard de inscrição em edital.
 * Reutiliza validadores de FieldValidators.js (Wave 3).
 *
 * @module wizard/StepDefinitionsInscricao
 */

import { validateRequired } from './FieldValidators.js';

/**
 * Definição dos passos do wizard de inscrição.
 *
 * @param {object} context  Contexto com edital_id, agente_id, categoria_id (preenchido em runtime).
 * @returns {Array<StepDefinition>}
 */
export function getStepsInscricao(context = {}) {
    return [
        // Passo 1: Seleção de Categoria.
        {
            id: 'pi-passo-inscricao-categoria',
            titulo: 'Categoria',
            numero: 1,
            /**
             * Valida que uma categoria foi selecionada.
             * @param {HTMLElement} stepEl
             * @returns {string[]} Lista de mensagens de erro (vazia = válido).
             */
            validate(stepEl) {
                const select = stepEl.querySelector('#pi-inscricao-categoria');
                if (!select || !select.value) {
                    return ['Selecione uma categoria para continuar.'];
                }
                return [];
            },
            /**
             * Preenche o select de categorias com dados filtrados por tipo de agente.
             * @param {HTMLElement} stepEl
             * @param {ApiClientInscricao} api
             */
            async onEnter(stepEl, api) {
                const select = stepEl.querySelector('#pi-inscricao-categoria');
                if (!select || !context.edital_id) return;

                select.innerHTML = '<option value="">Carregando…</option>';
                select.disabled = true;

                try {
                    const data = await api.listarCategoriasEdital(context.edital_id);
                    const categorias = (data && data.items) ? data.items : [];

                    // Filtra categorias elegíveis pelo tipo do agente.
                    const tipoAgente = context.tipo_agente || '';
                    const elegiveis = tipoAgente
                        ? categorias.filter(c => {
                            const tipos = (c.tipos_agente_elegivel || '').split(',').map(t => t.trim().toUpperCase());
                            return tipos.includes(tipoAgente.toUpperCase());
                        })
                        : categorias;

                    select.innerHTML = '<option value="">Selecione uma categoria…</option>';
                    elegiveis.forEach(cat => {
                        const opt = document.createElement('option');
                        opt.value = String(cat.id);
                        opt.textContent = cat.nome;
                        opt.dataset.descricaoMd = cat.descricao_md || '';
                        opt.dataset.numVagas = cat.num_vagas || 0;
                        select.appendChild(opt);
                    });

                    if (elegiveis.length === 0) {
                        select.innerHTML = '<option value="">Nenhuma categoria elegível para o seu tipo de agente.</option>';
                    }
                } catch (e) {
                    select.innerHTML = '<option value="">Erro ao carregar categorias.</option>';
                } finally {
                    select.disabled = false;
                }

                // Mostra descrição da categoria selecionada.
                select.addEventListener('change', () => {
                    const opt = select.selectedOptions[0];
                    const descEl = stepEl.querySelector('#pi-cat-desc');
                    if (descEl) {
                        descEl.textContent = (opt && opt.dataset.descricaoMd) ? opt.dataset.descricaoMd : '';
                    }
                    context.categoria_id = select.value ? parseInt(select.value, 10) : null;
                });
            },
        },

        // Passo 2: Portfólio.
        {
            id: 'pi-passo-inscricao-portfolio',
            titulo: 'Portfólio',
            numero: 2,
            validate(stepEl) {
                // Portfólio é opcional — sem erro de validação obrigatória.
                const ta = stepEl.querySelector('#pi-inscricao-portfolio');
                if (ta && ta.value.length > 5000) {
                    return ['O portfólio não pode exceder 5.000 caracteres.'];
                }
                return [];
            },
            onEnter(stepEl) {
                const ta      = stepEl.querySelector('#pi-inscricao-portfolio');
                const counter = stepEl.querySelector('#pi-portfolio-chars');
                if (ta && counter) {
                    const update = () => { counter.textContent = String(ta.value.length); };
                    update();
                    ta.addEventListener('input', update);
                }
            },
            onLeave(stepEl) {
                const ta = stepEl.querySelector('#pi-inscricao-portfolio');
                context.portfolio_md = ta ? ta.value : '';
            },
        },

        // Passo 3: Documentos.
        {
            id: 'pi-passo-inscricao-documentos',
            titulo: 'Documentos',
            numero: 3,
            /**
             * Valida que todos os documentos obrigatórios foram enviados.
             */
            validate(stepEl) {
                const lista = stepEl.querySelector('#pi-inscricao-documentos-lista');
                if (!lista) return [];

                // Verifica inputs de arquivo pendentes (não enviados ainda).
                const inputs = lista.querySelectorAll('input[type="file"][data-obrigatorio="true"]');
                const faltando = [];
                inputs.forEach(input => {
                    const enviado = input.closest('.pi-upload-item')
                        ?.querySelector('.pi-upload-status--ok');
                    if (!enviado) {
                        faltando.push(input.getAttribute('aria-label') || 'Documento');
                    }
                });
                if (faltando.length > 0) {
                    return ['Documentos obrigatórios faltando: ' + faltando.join(', ')];
                }
                return [];
            },
            async onEnter(stepEl, api) {
                if (!context.categoria_id || !context.inscricao_id) return;

                const lista = stepEl.querySelector('#pi-inscricao-documentos-lista');
                if (!lista) return;

                lista.innerHTML = '<p class="pi-carregando">Carregando documentos exigidos…</p>';

                try {
                    const catData = await api.getCategoria(context.edital_id, context.categoria_id);
                    const documentosExigidos = (catData && catData.documentos_exigidos) ? catData.documentos_exigidos : [];

                    if (documentosExigidos.length === 0) {
                        lista.innerHTML = '<p>Nenhum documento obrigatório para esta categoria.</p>';
                        return;
                    }

                    lista.innerHTML = documentosExigidos.map(doc => `
                        <div class="pi-upload-item" data-tipo-doc-id="${doc.id || ''}">
                            <label class="pi-label">
                                ${doc.nome || doc}
                                <span aria-hidden="true" class="pi-obrigatorio">*</span>
                            </label>
                            <input
                                type="file"
                                class="pi-file-input"
                                accept=".pdf,.jpg,.jpeg,.png"
                                data-obrigatorio="true"
                                data-inscricao-id="${context.inscricao_id}"
                                data-tipo-documento-id="${doc.id || ''}"
                                aria-label="${doc.nome || doc}"
                                aria-required="true"
                            >
                            <div class="pi-upload-progress" aria-live="polite"></div>
                        </div>
                    `).join('');

                    // Inicializa FileUpload.js (Wave 3) nos inputs.
                    if (typeof window.piInitFileUploads === 'function') {
                        window.piInitFileUploads(lista, {
                            apiUrl: api.apiUrl,
                            nonce:  api.nonce,
                            endpoint: (inscricaoId, tipoDocId) =>
                                `/inscricao/${inscricaoId}/upload-documento`,
                        });
                    }
                } catch (e) {
                    lista.innerHTML = '<p class="pi-erro" role="alert">Erro ao carregar documentos exigidos.</p>';
                }
            },
        },

        // Passo 4: Revisão.
        {
            id: 'pi-passo-inscricao-revisao',
            titulo: 'Revisão',
            numero: 4,
            validate() {
                return [];
            },
            onEnter(stepEl) {
                const resumoEdital    = stepEl.querySelector('#pi-resumo-edital');
                const resumoCategoria = stepEl.querySelector('#pi-resumo-categoria');
                const resumoPortfolio = stepEl.querySelector('#pi-resumo-portfolio-chars');
                const resumoDocs      = stepEl.querySelector('#pi-resumo-documentos');

                if (resumoEdital)    resumoEdital.textContent    = String(context.edital_id || '');
                if (resumoCategoria) resumoCategoria.textContent = String(context.categoria_nome || context.categoria_id || '');
                if (resumoPortfolio) resumoPortfolio.textContent = `${(context.portfolio_md || '').length} caracteres`;
                if (resumoDocs) {
                    const docs = context.documentos_enviados || [];
                    resumoDocs.textContent = docs.length > 0
                        ? `${docs.length} documento(s) enviado(s)`
                        : 'Nenhum documento enviado.';
                }
            },
        },

        // Passo 5: Confirmação + LGPD.
        {
            id: 'pi-passo-inscricao-confirmacao',
            titulo: 'Confirmação & LGPD',
            numero: 5,
            validate(stepEl) {
                const erros = [];
                const checkCandidatura = stepEl.querySelector('#pi-lgpd-candidatura');
                const checkPublicidade = stepEl.querySelector('#pi-lgpd-publicidade');
                if (!checkCandidatura || !checkCandidatura.checked) {
                    erros.push('É necessário autorizar o tratamento dos dados para prosseguir.');
                }
                if (!checkPublicidade || !checkPublicidade.checked) {
                    erros.push('É necessário declarar ciência da publicidade do nome e registro.');
                }
                return erros;
            },
            isFinal: true,
        },
    ];
}
