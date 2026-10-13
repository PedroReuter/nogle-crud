import { beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'

import LoginView from '@/views/LoginView.vue'
import { ApiError } from '@/services/http'

import { flush, mountWithVuetify, screen } from '../support/mount'

const replace = vi.fn()

vi.mock('vue-router', () => ({
  useRouter: () => ({ replace }),
  useRoute: () => ({ query: {} }),
}))

const signIn = vi.fn()

vi.mock('@/composables/useAuth', () => ({
  useAuth: () => ({ signIn }),
}))

function montar() {
  return mountWithVuetify(LoginView)
}

function inputs() {
  return screen().findAll<HTMLInputElement>('input')
}

function botao(texto: string) {
  const alvo = screen()
    .findAll('button')
    .find((b) => b.text() === texto)

  if (!alvo) throw new Error(`Botão "${texto}" não encontrado`)

  return alvo
}

async function preencher(email = 'admin@nogle.test', senha = 'senha123') {
  await inputs()[0].setValue(email)
  await inputs()[1].setValue(senha)
  await nextTick()
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('formulário', () => {
  it('apresenta campos de e-mail e senha', async () => {
    montar()
    await flush()

    expect(screen().text()).toContain('E-mail')
    expect(screen().text()).toContain('Senha')
    expect(inputs()).toHaveLength(2)
  })

  it('oculta a senha por padrão', async () => {
    montar()
    await flush()

    expect(inputs()[1].attributes('type')).toBe('password')
  })

  it('permite revelar a senha', async () => {
    // Ajuda quem digita senha longa em teclado de celular.
    montar()
    await flush()

    await screen().find('.v-field__append-inner .v-icon').trigger('click')
    await nextTick()

    expect(inputs()[1].attributes('type')).toBe('text')
  })
})

describe('validação', () => {
  it('não envia com campos vazios', async () => {
    montar()
    await flush()

    await botao('Entrar').trigger('click')
    await flush()

    expect(signIn).not.toHaveBeenCalled()
  })

  it('aponta cada campo faltante', async () => {
    montar()
    await flush()

    await botao('Entrar').trigger('click')
    await flush()

    expect(screen().text()).toContain('Informe o e-mail')
    expect(screen().text()).toContain('Informe a senha')
  })
})

describe('autenticação', () => {
  it('envia as credenciais e redireciona', async () => {
    signIn.mockResolvedValue(undefined)
    montar()
    await flush()

    await preencher()
    await botao('Entrar').trigger('click')
    await flush()

    expect(signIn).toHaveBeenCalledWith({ email: 'admin@nogle.test', password: 'senha123' })
    expect(replace).toHaveBeenCalledWith('/')
  })

  it('remove espaços em volta do e-mail', async () => {
    // Copiar e colar um e-mail costuma trazer espaço junto.
    signIn.mockResolvedValue(undefined)
    montar()
    await flush()

    await preencher('  admin@nogle.test  ')
    await botao('Entrar').trigger('click')
    await flush()

    expect(signIn).toHaveBeenCalledWith({ email: 'admin@nogle.test', password: 'senha123' })
  })

  it('exibe o erro de credencial devolvido pela API', async () => {
    signIn.mockRejectedValue(
      new ApiError('Verifique os dados', 422, {
        email: ['As credenciais informadas não conferem.'],
      }),
    )
    montar()
    await flush()

    await preencher()
    await botao('Entrar').trigger('click')
    await flush()
    await nextTick()

    expect(screen().text()).toContain('As credenciais informadas não conferem.')
    expect(replace).not.toHaveBeenCalled()
  })

  it('exibe o aviso de bloqueio por excesso de tentativas', async () => {
    signIn.mockRejectedValue(
      new ApiError('Verifique os dados', 422, {
        email: ['Muitas tentativas de acesso. Tente novamente em 60 segundos.'],
      }),
    )
    montar()
    await flush()

    await preencher()
    await botao('Entrar').trigger('click')
    await flush()
    await nextTick()

    expect(screen().text()).toContain('Muitas tentativas de acesso')
  })

  it('limpa a senha após falha', async () => {
    // Evita que uma nova tentativa reenvie a mesma credencial por engano.
    signIn.mockRejectedValue(new ApiError('Falhou', 422, { email: ['Não confere.'] }))
    montar()
    await flush()

    await preencher()
    await botao('Entrar').trigger('click')
    await flush()
    await nextTick()

    expect(inputs()[1].element.value).toBe('')
    expect(inputs()[0].element.value).toBe('admin@nogle.test')
  })

  it('exibe erro de servidor fora do campo', async () => {
    signIn.mockRejectedValue(
      new ApiError('O servidor encontrou um erro. Tente novamente em alguns instantes.', 500),
    )
    montar()
    await flush()

    await preencher()
    await botao('Entrar').trigger('click')
    await flush()
    await nextTick()

    expect(screen().find('[role="alert"]').exists()).toBe(true)
    expect(screen().text()).toContain('O servidor encontrou um erro')
  })

  it('não envia duas vezes em caso de duplo clique', async () => {
    // Cada envio extra consome uma tentativa do limite de acesso.
    let resolver: (() => void) | undefined
    signIn.mockReturnValue(
      new Promise<void>((resolve) => {
        resolver = resolve
      }),
    )
    montar()
    await flush()

    await preencher()
    await botao('Entrar').trigger('click')
    await flush()
    await botao('Entrar').trigger('click')
    await botao('Entrar').trigger('click')
    await flush()

    expect(signIn).toHaveBeenCalledOnce()

    resolver?.()
    await flush()
  })
})

describe('credenciais de demonstração', () => {
  it('preenche o formulário ao escolher um perfil', async () => {
    montar()
    await flush()

    await botao('Administrador').trigger('click')
    await nextTick()

    expect(inputs()[0].element.value).toBe('admin@nogle.test')
    expect(inputs()[1].element.value).toBe('senha123')
  })

  it('oferece os dois perfis do requisito', async () => {
    montar()
    await flush()

    const textos = screen()
      .findAll('button')
      .map((b) => b.text())

    expect(textos).toContain('Administrador')
    expect(textos).toContain('Leitura')
  })
})
