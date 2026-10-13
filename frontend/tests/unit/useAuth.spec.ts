import { beforeEach, describe, expect, it, vi } from 'vitest'

import { ApiError } from '@/services/http'
import type { AuthenticatedUser } from '@/types/customer'

vi.mock('@/services/auth', () => ({
  login: vi.fn(),
  logout: vi.fn(),
  fetchCurrentUser: vi.fn(),
  fetchCsrfCookie: vi.fn(),
}))

const authService = await import('@/services/auth')

function usuario(overrides: Partial<AuthenticatedUser> = {}): AuthenticatedUser {
  return {
    id: 1,
    name: 'Administradora Demo',
    email: 'admin@nogle.test',
    role: 'admin',
    role_label: 'Administrador',
    permissions: { manage_customers: true },
    ...overrides,
  }
}

const LEITOR = usuario({
  id: 2,
  name: 'Leitor Demo',
  email: 'leitura@nogle.test',
  role: 'viewer',
  role_label: 'Leitura',
  permissions: { manage_customers: false },
})

/**
 * Importa o composable com o estado zerado.
 *
 * O estado da sessão vive em escopo de módulo, por ser compartilhado entre
 * cabeçalho, guard de rota e tela — inclusive a marca de "sessão já
 * verificada", que faz `initialize()` não consultar o servidor duas vezes.
 * Reinicializar o módulo é o que mantém cada teste independente, como o
 * requisito exige, sem precisar abrir na aplicação uma função de reset que
 * só existiria para os testes.
 */
async function carregarUseAuth() {
  vi.resetModules()

  const modulo = await import('@/composables/useAuth')

  return modulo.useAuth()
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('verificação inicial de sessão', () => {
  it('reconhece sessão ativa ao carregar', async () => {
    vi.mocked(authService.fetchCurrentUser).mockResolvedValue(usuario())

    const auth = await carregarUseAuth()
    await auth.initialize()

    expect(auth.isAuthenticated.value).toBe(true)
    expect(auth.user.value?.email).toBe('admin@nogle.test')
  })

  it('trata 401 como ausência de sessão, não como erro', async () => {
    // É a resposta esperada para quem não está autenticado; exibir uma
    // mensagem de erro ao abrir a aplicação seria alarme falso.
    vi.mocked(authService.fetchCurrentUser).mockRejectedValue(new ApiError('Não autenticado', 401))

    const auth = await carregarUseAuth()
    await auth.initialize()

    expect(auth.isAuthenticated.value).toBe(false)
    expect(auth.initialized.value).toBe(true)
  })

  it('conclui a inicialização mesmo quando a rede falha', async () => {
    // Sem isso, a aplicação ficaria presa no indicador de carregamento.
    vi.mocked(authService.fetchCurrentUser).mockRejectedValue(
      new ApiError('Não foi possível conectar ao servidor.', null),
    )
    vi.spyOn(console, 'warn').mockImplementation(() => {})

    const auth = await carregarUseAuth()
    await auth.initialize()

    expect(auth.initialized.value).toBe(true)
    expect(auth.isAuthenticated.value).toBe(false)
  })

  it('não consulta o servidor duas vezes', async () => {
    vi.mocked(authService.fetchCurrentUser).mockResolvedValue(usuario())

    const auth = await carregarUseAuth()
    await auth.initialize()
    await auth.initialize()

    expect(authService.fetchCurrentUser).toHaveBeenCalledOnce()
  })
})

describe('entrada', () => {
  it('guarda o usuário autenticado', async () => {
    vi.mocked(authService.login).mockResolvedValue(usuario())

    const auth = await carregarUseAuth()
    await auth.signIn({ email: 'admin@nogle.test', password: 'senha123' })

    expect(auth.isAuthenticated.value).toBe(true)
    expect(auth.user.value?.name).toBe('Administradora Demo')
  })

  it('propaga a falha de credencial para a tela tratar', async () => {
    vi.mocked(authService.login).mockRejectedValue(
      new ApiError('Verifique os dados', 422, {
        email: ['As credenciais informadas não conferem.'],
      }),
    )

    const auth = await carregarUseAuth()

    await expect(
      auth.signIn({ email: 'admin@nogle.test', password: 'errada' }),
    ).rejects.toBeInstanceOf(ApiError)

    expect(auth.isAuthenticated.value).toBe(false)
  })

  it('encerra o carregamento mesmo quando a entrada falha', async () => {
    vi.mocked(authService.login).mockRejectedValue(new ApiError('Falhou', 422))

    const auth = await carregarUseAuth()
    await auth.signIn({ email: 'a@b.test', password: 'x' }).catch(() => {})

    expect(auth.loading.value).toBe(false)
  })
})

describe('permissões', () => {
  it('reconhece que o administrador pode gerenciar clientes', async () => {
    vi.mocked(authService.login).mockResolvedValue(usuario())

    const auth = await carregarUseAuth()
    await auth.signIn({ email: 'admin@nogle.test', password: 'senha123' })

    expect(auth.canManageCustomers.value).toBe(true)
  })

  it('reconhece que o perfil de leitura não pode', async () => {
    vi.mocked(authService.login).mockResolvedValue(LEITOR)

    const auth = await carregarUseAuth()
    await auth.signIn({ email: 'leitura@nogle.test', password: 'senha123' })

    expect(auth.canManageCustomers.value).toBe(false)
  })

  it('lê a permissão resolvida pelo backend, não o nome do papel', async () => {
    // Se a interface deduzisse de `role === 'admin'`, esta resposta — papel
    // de administrador sem a permissão — seria interpretada errado. Ler a
    // permissão evita que a inferência do frontend divirja da Policy.
    vi.mocked(authService.login).mockResolvedValue(
      usuario({ role: 'admin', permissions: { manage_customers: false } }),
    )

    const auth = await carregarUseAuth()
    await auth.signIn({ email: 'admin@nogle.test', password: 'senha123' })

    expect(auth.canManageCustomers.value).toBe(false)
  })

  it('não concede permissão alguma sem sessão', async () => {
    const auth = await carregarUseAuth()

    expect(auth.canManageCustomers.value).toBe(false)
    expect(auth.isAuthenticated.value).toBe(false)
  })
})

describe('saída', () => {
  it('limpa a sessão', async () => {
    vi.mocked(authService.login).mockResolvedValue(usuario())
    vi.mocked(authService.logout).mockResolvedValue(undefined)

    const auth = await carregarUseAuth()
    await auth.signIn({ email: 'admin@nogle.test', password: 'senha123' })
    await auth.signOut()

    expect(auth.isAuthenticated.value).toBe(false)
    expect(auth.user.value).toBeNull()
  })

  it('limpa a sessão local mesmo se a chamada falhar', async () => {
    // Continuar exibindo o usuário como autenticado depois de ele pedir para
    // sair é pior do que divergir do servidor por um instante.
    vi.mocked(authService.login).mockResolvedValue(usuario())
    vi.mocked(authService.logout).mockRejectedValue(new ApiError('Falhou', 500))

    const auth = await carregarUseAuth()
    await auth.signIn({ email: 'admin@nogle.test', password: 'senha123' })
    await auth.signOut().catch(() => {})

    expect(auth.isAuthenticated.value).toBe(false)
  })
})

describe('sessão expirada', () => {
  it('limpa o estado sem chamar a API', async () => {
    // Quando o servidor responde 401 a uma requisição qualquer, a sessão já
    // caiu: chamar logout devolveria outro 401.
    vi.mocked(authService.login).mockResolvedValue(usuario())

    const auth = await carregarUseAuth()
    await auth.signIn({ email: 'admin@nogle.test', password: 'senha123' })

    auth.clearSession()

    expect(auth.isAuthenticated.value).toBe(false)
    expect(authService.logout).not.toHaveBeenCalled()
  })
})

describe('armazenamento de credencial', () => {
  it('não grava nada no localStorage nem no sessionStorage', async () => {
    // O requisito proíbe guardar token sensível no localStorage. A
    // credencial é o cookie de sessão, que é HttpOnly e inacessível a
    // JavaScript — este teste fixa que nenhuma cópia é feita.
    vi.mocked(authService.login).mockResolvedValue(usuario())

    const localSpy = vi.spyOn(Storage.prototype, 'setItem')

    const auth = await carregarUseAuth()
    await auth.signIn({ email: 'admin@nogle.test', password: 'senha123' })

    expect(localSpy).not.toHaveBeenCalled()
    expect(localStorage.length).toBe(0)
    expect(sessionStorage.length).toBe(0)
  })
})
