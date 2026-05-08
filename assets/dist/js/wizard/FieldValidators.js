/**
 * FieldValidators.js
 *
 * Validadores client-side em pt_BR. O servidor permanece como fonte da verdade
 * (R5 - code review). Estes validadores reduzem round-trips e melhoram UX,
 * mas nao podem ser confiados para autorizar dados.
 *
 * Cada funcao retorna { ok: boolean, message: string }.
 *
 * @module wizard/FieldValidators
 */

const MSG = {
    obrigatorio: 'Este campo é obrigatório.',
    cpfFormato: 'Informe um CPF no formato 000.000.000-00.',
    cpfInvalido: 'CPF inválido. Verifique os números informados.',
    cnpjFormato: 'Informe um CNPJ no formato 00.000.000/0000-00.',
    cnpjInvalido: 'CNPJ inválido. Verifique os números informados.',
    emailFormato: 'Informe um endereço de e-mail válido (ex.: nome@dominio.gov.br).',
    telefoneFormato: 'Informe um telefone com DDD, no formato (00) 00000-0000.',
    cepFormato: 'Informe um CEP no formato 00000-000.',
    minimo: (n) => `Mínimo de ${n} caracteres.`,
    maximo: (n) => `Máximo de ${n} caracteres.`,
};

/**
 * @param {*} value
 * @returns {{ok: boolean, message: string}}
 */
export function validateRequired(value) {
    if (value === null || value === undefined) {
        return { ok: false, message: MSG.obrigatorio };
    }
    if (typeof value === 'string' && value.trim() === '') {
        return { ok: false, message: MSG.obrigatorio };
    }
    if (Array.isArray(value) && value.length === 0) {
        return { ok: false, message: MSG.obrigatorio };
    }
    return { ok: true, message: '' };
}

/**
 * @param {string} value
 * @param {{min?: number, max?: number}} [opts]
 */
export function validateLength(value, opts = {}) {
    const v = String(value || '');
    if (typeof opts.min === 'number' && v.length < opts.min) {
        return { ok: false, message: MSG.minimo(opts.min) };
    }
    if (typeof opts.max === 'number' && v.length > opts.max) {
        return { ok: false, message: MSG.maximo(opts.max) };
    }
    return { ok: true, message: '' };
}

/**
 * @param {string} value
 */
export function validateEmail(value) {
    const v = String(value || '').trim();
    // RFC 5322 simplificada (suficiente para entrada manual)
    const re = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
    if (!re.test(v)) {
        return { ok: false, message: MSG.emailFormato };
    }
    return { ok: true, message: '' };
}

/**
 * Valida telefone fixo ou celular brasileiro com DDD (10 ou 11 digitos).
 * @param {string} value
 */
export function validatePhone(value) {
    const digits = String(value || '').replace(/\D/g, '');
    if (digits.length !== 10 && digits.length !== 11) {
        return { ok: false, message: MSG.telefoneFormato };
    }
    // DDD valido (11..99)
    const ddd = parseInt(digits.slice(0, 2), 10);
    if (ddd < 11 || ddd > 99) {
        return { ok: false, message: MSG.telefoneFormato };
    }
    return { ok: true, message: '' };
}

/**
 * @param {string} value
 */
export function validateCep(value) {
    const digits = String(value || '').replace(/\D/g, '');
    if (digits.length !== 8) {
        return { ok: false, message: MSG.cepFormato };
    }
    return { ok: true, message: '' };
}

/**
 * Valida CPF: 11 digitos + DV (algoritmo oficial Receita Federal).
 * @param {string} value
 */
export function validateCpf(value) {
    const digits = String(value || '').replace(/\D/g, '');
    if (digits.length !== 11) {
        return { ok: false, message: MSG.cpfFormato };
    }
    // Rejeita sequencias repetidas (000..., 111...)
    if (/^(\d)\1{10}$/.test(digits)) {
        return { ok: false, message: MSG.cpfInvalido };
    }
    const calcDv = (slice) => {
        let sum = 0;
        for (let i = 0; i < slice.length; i++) {
            sum += parseInt(slice[i], 10) * (slice.length + 1 - i);
        }
        const r = (sum * 10) % 11;
        return r === 10 ? 0 : r;
    };
    const dv1 = calcDv(digits.slice(0, 9));
    const dv2 = calcDv(digits.slice(0, 10));
    if (dv1 !== parseInt(digits[9], 10) || dv2 !== parseInt(digits[10], 10)) {
        return { ok: false, message: MSG.cpfInvalido };
    }
    return { ok: true, message: '' };
}

/**
 * Valida CNPJ: 14 digitos + DV (algoritmo oficial Receita Federal).
 * @param {string} value
 */
export function validateCnpj(value) {
    const digits = String(value || '').replace(/\D/g, '');
    if (digits.length !== 14) {
        return { ok: false, message: MSG.cnpjFormato };
    }
    if (/^(\d)\1{13}$/.test(digits)) {
        return { ok: false, message: MSG.cnpjInvalido };
    }
    const weights1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
    const weights2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
    const calc = (slice, weights) => {
        let sum = 0;
        for (let i = 0; i < weights.length; i++) {
            sum += parseInt(slice[i], 10) * weights[i];
        }
        const r = sum % 11;
        return r < 2 ? 0 : 11 - r;
    };
    const dv1 = calc(digits.slice(0, 12), weights1);
    const dv2 = calc(digits.slice(0, 13), weights2);
    if (dv1 !== parseInt(digits[12], 10) || dv2 !== parseInt(digits[13], 10)) {
        return { ok: false, message: MSG.cnpjInvalido };
    }
    return { ok: true, message: '' };
}

/**
 * Aplica mascara visual em campo (pt_BR).
 * @param {string} kind
 * @param {string} value
 */
export function applyMask(kind, value) {
    const d = String(value || '').replace(/\D/g, '');
    switch (kind) {
        case 'cpf':
            return d
                .slice(0, 11)
                .replace(/(\d{3})(\d)/, '$1.$2')
                .replace(/(\d{3})(\d)/, '$1.$2')
                .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
        case 'cnpj':
            return d
                .slice(0, 14)
                .replace(/(\d{2})(\d)/, '$1.$2')
                .replace(/(\d{3})(\d)/, '$1.$2')
                .replace(/(\d{3})(\d)/, '$1/$2')
                .replace(/(\d{4})(\d{1,2})$/, '$1-$2');
        case 'cep':
            return d.slice(0, 8).replace(/(\d{5})(\d)/, '$1-$2');
        case 'phone':
            if (d.length <= 10) {
                return d
                    .slice(0, 10)
                    .replace(/(\d{2})(\d)/, '($1) $2')
                    .replace(/(\d{4})(\d)/, '$1-$2');
            }
            return d
                .slice(0, 11)
                .replace(/(\d{2})(\d)/, '($1) $2')
                .replace(/(\d{5})(\d)/, '$1-$2');
        default:
            return value;
    }
}

export const VALIDATOR_MESSAGES = MSG;
