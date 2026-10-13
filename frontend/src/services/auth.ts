import type { AuthenticatedUser, Credentials } from '@/types/customer'

import { http, toApiError } from './http'

/**
 * Chamadas de sessão.
 *
 * A autenticação é por cookie de sessão: nenhum token é guardado no cliente,
 * e o navegador envia a credencial automaticamente. Em troca, cada operação
 * de escrita precisa do token de CSRF — que o Laravel entrega como cookie
 * `XSRF-TOKEN` e o axios reenvia como cabeçalho `X-XSRF-TOKEN` por conta
 * própria.
 */

/**
 * Obtém o cookie de CSRF antes de uma operação que o exige.
 *
 * Precisa anteceder o login: sem o cookie, o Laravel recusa a requisição
 * com 419 antes mesmo de avaliar as credenciais.
 *
 * O endereço é `/sanctum/csrf-cookie`, fora do prefixo `/api`, por isso a
 * URL absoluta — o cliente http tem `baseURL` igual a `/api`.
 */
export async function fetchCsrfCookie(): Promise<void> {
  try {
    await http.get('/sanctum/csrf-cookie', { baseURL: '/' })
  } catch (error) {
    throw toApiError(error)
  }
}

export async function login(credentials: Credentials): Promise<AuthenticatedUser> {
  try {
    await fetchCsrfCookie()

    const { data } = await http.post<{ data: AuthenticatedUser }>('/login', credentials)

    return data.data
  } catch (error) {
    throw toApiError(error)
  }
}

export async function logout(): Promise<void> {
  try {
    await http.post('/logout')
  } catch (error) {
    throw toApiError(error)
  }
}

/**
 * Descobre se já existe sessão ativa.
 *
 * Chamado quando o SPA carrega: um recarregamento de página perde o estado
 * em memória, mas não o cookie de sessão, e sem esta consulta o usuário
 * seria mandado ao login mesmo estando autenticado.
 */
export async function fetchCurrentUser(): Promise<AuthenticatedUser> {
  try {
    const { data } = await http.get<{ data: AuthenticatedUser }>('/me')

    return data.data
  } catch (error) {
    throw toApiError(error)
  }
}
