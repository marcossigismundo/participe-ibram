/**
 * StepDefinitions.js
 *
 * Estrutura dos passos por tipo de agente. Os IDs aqui DEVEM corresponder
 * aos `id` dos `<section class="pi-wizard-panel">` nos templates HTML.
 *
 * Campos: ids dos inputs do passo (validados ao avancar).
 * Opcionais: ids que existem mas nao bloqueiam avanco.
 *
 * @module wizard/StepDefinitions
 */

/**
 * @typedef {Object} StepDefinition
 * @property {string} id        ID do panel HTML (sem prefixo #)
 * @property {string} titulo    Titulo legivel pt_BR (usado em sumario, anuncios)
 * @property {string[]} campos  IDs de campos obrigatorios para validar antes de avancar
 * @property {string[]} opcionais IDs de campos opcionais
 */

/** @type {StepDefinition[]} Pessoa Fisica */
export const STEPS_PF = [
    {
        id: 'pi-passo-pf-identificacao',
        titulo: 'Identificação',
        campos: ['pi-pf-nome-completo', 'pi-pf-nome-social', 'pi-pf-cpf', 'pi-pf-data-nascimento', 'pi-pf-nacionalidade'],
        opcionais: ['pi-pf-passaporte'],
    },
    {
        id: 'pi-passo-pf-demografia',
        titulo: 'Demografia',
        campos: ['pi-pf-faixa-etaria', 'pi-pf-genero', 'pi-pf-raca-cor', 'pi-pf-grau-instrucao'],
        opcionais: ['pi-pf-orientacao-sexual', 'pi-pf-pcd', 'pi-pf-pct-grupos'],
    },
    {
        id: 'pi-passo-pf-contato',
        titulo: 'Endereço & Contato',
        campos: ['pi-pf-cep', 'pi-pf-logradouro', 'pi-pf-numero', 'pi-pf-bairro', 'pi-pf-cidade', 'pi-pf-uf', 'pi-pf-email', 'pi-pf-telefone'],
        opcionais: ['pi-pf-complemento'],
    },
    {
        id: 'pi-passo-pf-atuacao',
        titulo: 'Atuação',
        campos: ['pi-pf-ocupacao', 'pi-pf-areas-tematicas', 'pi-pf-instituicao'],
        opcionais: ['pi-pf-instancias-participacao', 'pi-pf-experiencia'],
    },
    {
        id: 'pi-passo-pf-documentos',
        titulo: 'Documentos',
        campos: ['pi-pf-doc-rg', 'pi-pf-doc-cpf', 'pi-pf-doc-carta'],
        opcionais: ['pi-pf-doc-passaporte'],
    },
    {
        id: 'pi-passo-pf-lgpd',
        titulo: 'LGPD & Submissão',
        campos: ['pi-consent-finalidade-cadastro', 'pi-consent-finalidade-comunicacao'],
        opcionais: [],
    },
];

/** @type {StepDefinition[]} Organizacao */
export const STEPS_OR = [
    {
        id: 'pi-passo-or-identificacao',
        titulo: 'Identificação da Organização',
        campos: ['pi-or-nome', 'pi-or-tem-cnpj'],
        opcionais: ['pi-or-cnpj', 'pi-or-tipo-coletivo', 'pi-or-data-fundacao'],
    },
    {
        id: 'pi-passo-or-caracterizacao',
        titulo: 'Caracterização',
        campos: ['pi-or-areas-tematicas', 'pi-or-missao'],
        opcionais: ['pi-or-historico', 'pi-or-publico-alvo'],
    },
    {
        id: 'pi-passo-or-localizacao',
        titulo: 'Localização & Abrangência',
        campos: ['pi-or-cep', 'pi-or-logradouro', 'pi-or-numero', 'pi-or-bairro', 'pi-or-cidade', 'pi-or-uf', 'pi-or-abrangencia', 'pi-or-email', 'pi-or-telefone'],
        opcionais: ['pi-or-complemento', 'pi-or-site'],
    },
    {
        id: 'pi-passo-or-representantes',
        titulo: 'Representantes',
        campos: ['pi-or-representantes'],
        opcionais: [],
    },
    {
        id: 'pi-passo-or-documentos',
        titulo: 'Documentos',
        // dinamico: depende de tem_cnpj. Validacao final no servidor.
        campos: [],
        opcionais: ['pi-or-doc-cnpj', 'pi-or-doc-estatuto', 'pi-or-doc-ata-posse', 'pi-or-doc-carta-indicacao'],
    },
    {
        id: 'pi-passo-or-lgpd',
        titulo: 'LGPD & Submissão',
        campos: ['pi-consent-finalidade-cadastro', 'pi-consent-finalidade-comunicacao'],
        opcionais: [],
    },
];

/** @type {StepDefinition[]} Sistema/Secretaria */
export const STEPS_SM = [
    {
        id: 'pi-passo-sm-orgao',
        titulo: 'Órgão',
        campos: ['pi-sm-nome-orgao', 'pi-sm-esfera', 'pi-sm-tipo', 'pi-sm-cidade', 'pi-sm-uf'],
        opcionais: ['pi-sm-site'],
    },
    {
        id: 'pi-passo-sm-marco-legal',
        titulo: 'Marco Legal',
        campos: ['pi-sm-lei-instituicao', 'pi-sm-data-lei'],
        opcionais: ['pi-sm-decreto-regulamentacao'],
    },
    {
        id: 'pi-passo-sm-representante',
        titulo: 'Representante Legal',
        campos: ['pi-sm-rep-nome', 'pi-sm-rep-cpf', 'pi-sm-rep-cargo', 'pi-sm-rep-email', 'pi-sm-rep-telefone'],
        opcionais: [],
    },
    {
        id: 'pi-passo-sm-documentos',
        titulo: 'Documentos',
        campos: ['pi-sm-doc-lei', 'pi-sm-doc-oficio'],
        opcionais: ['pi-sm-doc-rg-rep', 'pi-sm-doc-cpf-rep'],
    },
    {
        id: 'pi-passo-sm-lgpd',
        titulo: 'LGPD & Submissão',
        campos: ['pi-consent-finalidade-cadastro', 'pi-consent-finalidade-comunicacao'],
        opcionais: [],
    },
];

/**
 * @param {('PF'|'OR'|'SM')} tipo
 * @returns {StepDefinition[]}
 */
export function getStepsByTipo(tipo) {
    switch (String(tipo).toUpperCase()) {
        case 'PF':
            return STEPS_PF;
        case 'OR':
            return STEPS_OR;
        case 'SM':
            return STEPS_SM;
        default:
            throw new Error(`Tipo de agente inválido: ${tipo}`);
    }
}
