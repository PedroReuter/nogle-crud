import { ref } from 'vue'

import { lookupPostalCode, PostalCodeError, type PostalCodeAddress } from '@/services/postalCode'
import { onlyDigits } from '@/support/masks'

/**
 * Consulta automática de CEP para o formulário.
 *
 * Responsável por quando consultar, não por como: dispara assim que o CEP
 * fica completo, cancela a consulta anterior se outro CEP for digitado, e
 * evita repetir a busca do mesmo CEP.
 *
 * O cancelamento é o ponto central do requisito "evitar que respostas
 * antigas sobrescrevam informações de um CEP digitado posteriormente".
 * Digitar 01001000, apagar e digitar 20040002 deixa duas consultas em voo;
 * sem cancelamento, a que chegar por último vence — e pode ser a do CEP
 * antigo, preenchendo São Paulo num formulário cujo CEP é do Rio.
 */
export function usePostalCodeLookup() {
  const loading = ref(false)
  const error = ref<string | null>(null)

  let inFlight: AbortController | null = null

  /** Último CEP consultado com sucesso, para não repetir a busca. */
  let lastLookup: string | null = null

  /**
   * Consulta o CEP, se ele estiver completo.
   *
   * @returns o endereço encontrado, ou `null` quando não houve consulta ou
   *          ela não produziu resultado utilizável.
   */
  async function lookup(postalCode: string): Promise<PostalCodeAddress | null> {
    const digits = onlyDigits(postalCode)

    // CEP incompleto não é erro: o usuário ainda está digitando. Mostrar
    // "CEP inválido" a cada tecla seria ruído.
    if (digits.length !== 8) {
      error.value = null
      inFlight?.abort()
      inFlight = null
      loading.value = false

      return null
    }

    if (digits === lastLookup) {
      return null
    }

    inFlight?.abort()
    inFlight = new AbortController()
    const controller = inFlight

    loading.value = true
    error.value = null

    try {
      const address = await lookupPostalCode(digits, controller.signal)

      // Só a consulta mais recente escreve no formulário.
      if (controller !== inFlight) return null

      lastLookup = digits

      return address
    } catch (caught) {
      if (controller !== inFlight) return null

      if (caught instanceof PostalCodeError) {
        // Cancelamento não vira mensagem: foi a própria aplicação que
        // cancelou, ao iniciar uma consulta mais nova.
        error.value = caught.kind === 'cancelled' ? null : caught.message
      } else {
        error.value = 'Não foi possível consultar o CEP. Preencha o endereço manualmente.'
      }

      return null
    } finally {
      if (controller === inFlight) {
        loading.value = false
      }
    }
  }

  /**
   * Esquece o último CEP consultado.
   *
   * Chamado ao abrir o formulário: sem isso, editar um cliente cujo CEP é
   * igual ao do cliente editado antes não dispararia a consulta.
   */
  function reset(): void {
    inFlight?.abort()
    inFlight = null
    lastLookup = null
    loading.value = false
    error.value = null
  }

  return { loading, error, lookup, reset }
}
