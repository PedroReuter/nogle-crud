import { computed, readonly, ref } from 'vue'

import * as authService from '@/services/auth'
import { ApiError } from '@/services/http'
import type { AuthenticatedUser, Credentials } from '@/types/customer'

/**
 * Estado da sessão do usuário.
 *
 * O estado vive em escopo de módulo porque é genuinamente compartilhado: o
 * cabeçalho exibe quem está autenticado, a tabela esconde ações conforme o
 * perfil, e o guard de rota decide o acesso. Três consumidores lendo a mesma
 * verdade.
 *
 * É também o segundo e último estado compartilhado da aplicação, ao lado da
 * fila de avisos. Dois `ref` em escopo de módulo não justificam Pinia: a
 * store traria devtools e convenções cujo benefício não se paga aqui. Se
 * surgir um terceiro estado compartilhado com lógica própria, a conta muda.
 *
 * Nada é guardado em localStorage. A credencial é o cookie de sessão, que é
 * HttpOnly e portanto inacessível a JavaScript — o que atende ao requisito e
 * elimina a via mais comum de roubo de credencial por XSS.
 */

const user = ref<AuthenticatedUser | null>(null)

/** Se a verificação inicial de sessão já terminou. */
const initialized = ref(false)

const loading = ref(false)

export function useAuth() {
  const isAuthenticated = computed(() => user.value !== null)

  /**
   * Se o usuário pode alterar o cadastro de clientes.
   *
   * Lê a permissão que o backend resolveu, em vez de comparar o papel com
   * 'admin'. A interface esconder o botão é conveniência; a garantia está na
   * Policy, que verifica a mesma regra do lado do servidor.
   */
  const canManageCustomers = computed(() => user.value?.permissions.manage_customers ?? false)

  /**
   * Verifica se há sessão ativa ao carregar a aplicação.
   *
   * Um 401 aqui é a resposta esperada para quem não está autenticado, não um
   * erro a ser exibido.
   */
  async function initialize(): Promise<void> {
    if (initialized.value) return

    loading.value = true

    try {
      user.value = await authService.fetchCurrentUser()
    } catch (error) {
      if (!(error instanceof ApiError) || !error.isUnauthenticated) {
        // Falha de rede não deve ser confundida com ausência de sessão, mas
        // também não impede o uso: a tela de login aparece e a tentativa de
        // entrar dirá o que há de errado.
        console.warn('Não foi possível verificar a sessão:', error)
      }

      user.value = null
    } finally {
      initialized.value = true
      loading.value = false
    }
  }

  async function signIn(credentials: Credentials): Promise<void> {
    loading.value = true

    try {
      user.value = await authService.login(credentials)
    } finally {
      loading.value = false
    }
  }

  async function signOut(): Promise<void> {
    loading.value = true

    try {
      await authService.logout()
    } finally {
      // O estado local é limpo mesmo se a chamada falhar: insistir em
      // mostrar o usuário como autenticado depois de ele pedir para sair é
      // pior do que divergir do servidor por um instante.
      user.value = null
      loading.value = false
    }
  }

  /**
   * Limpa a sessão local sem chamar a API.
   *
   * Usado quando o servidor responde 401 a uma requisição qualquer: a sessão
   * expirou, e chamar logout devolveria outro 401.
   */
  function clearSession(): void {
    user.value = null
  }

  return {
    user: readonly(user),
    isAuthenticated,
    canManageCustomers,
    initialized: readonly(initialized),
    loading: readonly(loading),
    initialize,
    signIn,
    signOut,
    clearSession,
  }
}
