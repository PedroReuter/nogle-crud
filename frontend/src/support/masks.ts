/**
 * Máscaras de exibição e normalização para envio.
 *
 * O backend recebe e devolve apenas dígitos; a máscara existe só na
 * interface. Cada função tem o par inverso, para que o valor digitado possa
 * ser mascarado ao exibir e desmascarado ao enviar sem perda.
 */

/** Remove tudo que não é dígito. Espelha `App\Support\Digits::only`. */
export function onlyDigits(value: string | null | undefined): string {
  return (value ?? '').replace(/\D/g, '')
}

/**
 * Aplica a máscara 000.000.000-00 progressivamente.
 *
 * Progressivo e não tudo-ou-nada: a máscara é aplicada enquanto o usuário
 * digita, então precisa produzir um resultado coerente para qualquer
 * quantidade parcial de dígitos.
 */
export function maskCpf(value: string | null | undefined): string {
  const digits = onlyDigits(value).slice(0, 11)

  if (digits.length <= 3) return digits
  if (digits.length <= 6) return `${digits.slice(0, 3)}.${digits.slice(3)}`
  if (digits.length <= 9) return `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6)}`

  return `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6, 9)}-${digits.slice(9)}`
}

/** Aplica a máscara 00000-000 ao CEP. */
export function maskPostalCode(value: string | null | undefined): string {
  const digits = onlyDigits(value).slice(0, 8)

  return digits.length <= 5 ? digits : `${digits.slice(0, 5)}-${digits.slice(5)}`
}

/**
 * Aplica a máscara de telefone, escolhendo o formato pela quantidade de
 * dígitos: (00) 0000-0000 para fixo e (00) 00000-0000 para celular.
 *
 * A decisão só é possível com 11 dígitos, por isso o décimo primeiro dígito
 * reposiciona o hífen — comportamento esperado em formulários brasileiros.
 */
export function maskPhone(value: string | null | undefined): string {
  const digits = onlyDigits(value).slice(0, 11)

  if (digits.length <= 2) return digits
  if (digits.length <= 6) return `(${digits.slice(0, 2)}) ${digits.slice(2)}`

  if (digits.length <= 10) {
    return `(${digits.slice(0, 2)}) ${digits.slice(2, 6)}-${digits.slice(6)}`
  }

  return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`
}
