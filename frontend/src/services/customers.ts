import type {
  Customer,
  CustomerAudit,
  CustomerPayload,
  CustomerQuery,
  PaginatedResponse,
} from '@/types/customer'

import { http, toApiError } from './http'
import { onlyDigits } from '@/support/masks'

/**
 * Chamadas à API de clientes.
 *
 * É a fronteira entre a interface e o HTTP, exigida pelo requisito de
 * "separação entre UI e chamadas HTTP". Nenhum componente monta URL, escolhe
 * verbo ou interpreta resposta: tudo isso acontece aqui, e o componente
 * recebe tipos do domínio.
 *
 * Também é o ponto em que a máscara da interface é desfeita: o backend
 * aceita e devolve apenas dígitos.
 */

/** Lista clientes, com filtros, ordenação e paginação. */
export async function listCustomers(
  query: CustomerQuery,
  signal?: AbortSignal,
): Promise<PaginatedResponse<Customer>> {
  try {
    const { data } = await http.get<PaginatedResponse<Customer>>('/customers', {
      params: pruneEmpty({
        ...query,
        // O backend normaliza o CPF do filtro, mas enviar já normalizado
        // mantém a URL legível e o cache de navegação coerente.
        cpf: query.cpf ? onlyDigits(query.cpf) : undefined,
      }),
      signal,
    })

    return data
  } catch (error) {
    throw toApiError(error)
  }
}

export async function createCustomer(payload: CustomerPayload): Promise<Customer> {
  try {
    const { data } = await http.post<{ data: Customer }>('/customers', normalize(payload))

    return data.data
  } catch (error) {
    throw toApiError(error)
  }
}

export async function updateCustomer(id: number, payload: CustomerPayload): Promise<Customer> {
  try {
    const { data } = await http.put<{ data: Customer }>(`/customers/${id}`, normalize(payload))

    return data.data
  } catch (error) {
    throw toApiError(error)
  }
}

export async function deactivateCustomer(id: number): Promise<Customer> {
  try {
    const { data } = await http.patch<{ data: Customer }>(`/customers/${id}/deactivate`)

    return data.data
  } catch (error) {
    throw toApiError(error)
  }
}

export async function reactivateCustomer(id: number): Promise<Customer> {
  try {
    const { data } = await http.patch<{ data: Customer }>(`/customers/${id}/reactivate`)

    return data.data
  } catch (error) {
    throw toApiError(error)
  }
}

/**
 * Elimina definitivamente o cadastro.
 *
 * Operação distinta da inativação, e com propósito distinto: atende ao
 * direito de eliminação da LGPD. O backend só a permite para cliente já
 * inativo, de modo que eliminar é sempre um segundo passo deliberado.
 */
export async function deleteCustomer(id: number): Promise<void> {
  try {
    await http.delete(`/customers/${id}`)
  } catch (error) {
    throw toApiError(error)
  }
}

/** Histórico de alterações do cliente. */
export async function fetchCustomerAudits(id: number): Promise<PaginatedResponse<CustomerAudit>> {
  try {
    const { data } = await http.get<PaginatedResponse<CustomerAudit>>(`/customers/${id}/audits`)

    return data
  } catch (error) {
    throw toApiError(error)
  }
}

/**
 * Remove a máscara dos campos antes de enviar.
 *
 * O backend também normaliza, mas enviar o valor já limpo evita depender
 * disso e mantém o contrato explícito de um lado só.
 */
function normalize(payload: CustomerPayload): Record<string, unknown> {
  return {
    ...payload,
    cpf: onlyDigits(payload.cpf),
    phone: onlyDigits(payload.phone),
    postal_code: onlyDigits(payload.postal_code),

    // Complemento vazio vai como null, e não como '', para que o banco não
    // acumule dois valores significando "sem complemento".
    complement: payload.complement?.trim() ? payload.complement.trim() : null,
  }
}

/**
 * Descarta parâmetros vazios da query string.
 *
 * Sem isto, `?name=&cpf=` chegaria ao backend com strings vazias e a URL
 * ficaria poluída de parâmetros sem efeito.
 */
function pruneEmpty(params: Record<string, unknown>): Record<string, unknown> {
  return Object.fromEntries(
    Object.entries(params).filter(
      ([, value]) => value !== undefined && value !== null && value !== '',
    ),
  )
}
