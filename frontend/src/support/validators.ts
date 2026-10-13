import { onlyDigits } from './masks'

/**
 * Validações do formulário.
 *
 * O requisito pede validação no frontend E no backend. A do frontend existe
 * para dar retorno imediato sem ida ao servidor; a do backend é a que vale,
 * porque é a única que um cliente não pode burlar. Esta duplicação é
 * deliberada, e a regra a seguir é a única que realmente duplica lógica:
 * manter as duas consistentes é mais barato do que fazer cada tecla digitada
 * ir até o servidor.
 */

/**
 * Valida os dígitos verificadores do CPF.
 *
 * Reimplementa `App\Support\Cpf::isValid`. Mesma ordem de reprovação:
 * tamanho, sequência de dígito repetido, e os dois dígitos verificadores.
 */
export function isValidCpf(value: string | null | undefined): boolean {
  const cpf = onlyDigits(value)

  if (cpf.length !== 11) return false

  // Sequências como 111.111.111-11 satisfazem o cálculo, mas não são CPFs
  // atribuíveis.
  if (/^(\d)\1{10}$/.test(cpf)) return false

  return checkDigit(cpf, 9) === Number(cpf[9]) && checkDigit(cpf, 10) === Number(cpf[10])
}

/** Peso decrescente a partir de `position + 1`: 10..2 e depois 11..2. */
function checkDigit(cpf: string, position: number): number {
  let sum = 0

  for (let i = 0; i < position; i++) {
    sum += Number(cpf[i]) * (position + 1 - i)
  }

  const remainder = sum % 11

  return remainder < 2 ? 0 : 11 - remainder
}

/** Telefone brasileiro: 10 dígitos para fixo, 11 para celular, com DDD. */
export function isValidPhone(value: string | null | undefined): boolean {
  const digits = onlyDigits(value)

  return digits.length === 10 || digits.length === 11
}

/** CEP: exatamente oito dígitos. */
export function isValidPostalCode(value: string | null | undefined): boolean {
  return onlyDigits(value).length === 8
}

/**
 * Regras no formato que o Vuetify espera: `true` quando válido, ou a
 * mensagem de erro.
 *
 * Expostas como funções que devolvem regras, e não como regras prontas, para
 * que a mensagem possa nomear o campo — "Informe o nome" é mais útil do que
 * "Campo obrigatório" quando há dez campos na tela.
 */
export type ValidationRule = (value: unknown) => true | string

export function required(message = 'Campo obrigatório'): ValidationRule {
  return (value) => {
    // `trim` porque uma string só de espaços preenche o campo visualmente
    // mas não é um valor — o backend também a rejeita.
    if (value === null || value === undefined) return message
    if (typeof value === 'string' && value.trim() === '') return message

    return true
  }
}

export function maxLength(limit: number, message?: string): ValidationRule {
  return (value) => {
    if (typeof value !== 'string' || value.length <= limit) return true

    return message ?? `Máximo de ${limit} caracteres`
  }
}

export function minLength(limit: number, message?: string): ValidationRule {
  return (value) => {
    if (typeof value !== 'string') return true
    if (value.trim() === '') return true // deixa a obrigatoriedade para `required`
    if (value.trim().length >= limit) return true

    return message ?? `Mínimo de ${limit} caracteres`
  }
}

export function cpfRule(message = 'CPF inválido'): ValidationRule {
  return (value) => {
    if (typeof value !== 'string' || value.trim() === '') return true

    return isValidCpf(value) || message
  }
}

export function phoneRule(message = 'Telefone inválido. Informe DDD e número'): ValidationRule {
  return (value) => {
    if (typeof value !== 'string' || value.trim() === '') return true

    return isValidPhone(value) || message
  }
}

export function postalCodeRule(message = 'CEP deve ter 8 dígitos'): ValidationRule {
  return (value) => {
    if (typeof value !== 'string' || value.trim() === '') return true

    return isValidPostalCode(value) || message
  }
}
