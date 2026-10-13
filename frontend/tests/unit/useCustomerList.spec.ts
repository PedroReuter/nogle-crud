import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'

import { useCustomerList } from '@/composables/useCustomerList'
import { ApiError } from '@/services/http'
import type { Customer, PaginatedResponse } from '@/types/customer'

vi.mock('@/services/customers', () => ({ listCustomers: vi.fn() }))

const { listCustomers } = await import('@/services/customers')

function cliente(overrides: Partial<Customer> = {}): Customer {
  return {
    id: 1,
    name: 'Joana Pereira',
    cpf: '52998224725',
    cpf_formatted: '529.982.247-25',
    phone: '11988887777',
    phone_formatted: '(11) 98888-7777',
    postal_code: '01001000',
    postal_code_formatted: '01001-000',
    street: 'Praça da Sé',
    number: '100',
    complement: null,
    neighborhood: 'Sé',
    city: 'São Paulo',
    state: 'SP',
    full_address: 'Praça da Sé, 100 - Sé, São Paulo/SP - CEP 01001-000',
    is_active: true,
    created_at: null,
    updated_at: null,
    ...overrides,
  }
}

function pagina(items: Customer[], total = items.length): PaginatedResponse<Customer> {
  return {
    data: items,
    meta: {
      current_page: 1,
      from: items.length ? 1 : null,
      last_page: Math.max(1, Math.ceil(total / 15)),
      per_page: 15,
      to: items.length || null,
      total,
    },
  }
}

beforeEach(() => {
  vi.clearAllMocks()
  vi.mocked(listCustomers).mockResolvedValue(pagina([cliente()]))
})

afterEach(() => {
  vi.useRealTimers()
})

describe('carregamento', () => {
  it('busca a primeira página e expõe os resultados', async () => {
    const list = useCustomerList()
    await list.fetchPage()

    expect(list.customers.value).toHaveLength(1)
    expect(list.meta.value.total).toBe(1)
    expect(list.loading.value).toBe(false)
  })

  it('sinaliza carregamento durante a requisição', async () => {
    let resolver: ((value: PaginatedResponse<Customer>) => void) | undefined
    vi.mocked(listCustomers).mockReturnValue(
      new Promise((resolve) => {
        resolver = resolve
      }),
    )

    const list = useCustomerList()
    const promessa = list.fetchPage()

    // O indicador precisa estar ligado ENQUANTO a requisição está em voo;
    // verificar só depois não provaria que ele chegou a aparecer.
    expect(list.loading.value).toBe(true)

    resolver?.(pagina([cliente()]))
    await promessa

    expect(list.loading.value).toBe(false)
  })

  it('envia os filtros, a ordenação e a paginação à API', async () => {
    const list = useCustomerList()
    list.filters.value.name = 'Joana'
    list.filters.value.cpf = '529'
    list.perPage.value = 25

    await list.fetchPage()

    expect(vi.mocked(listCustomers).mock.calls[0][0]).toMatchObject({
      name: 'Joana',
      cpf: '529',
      status: 'active',
      sort: 'name',
      direction: 'asc',
      per_page: 25,
      page: 1,
    })
  })

  it('omite filtros vazios em vez de enviar strings em branco', async () => {
    const list = useCustomerList()
    list.filters.value.name = '   '

    await list.fetchPage()

    expect(vi.mocked(listCustomers).mock.calls[0][0].name).toBeUndefined()
  })
})

describe('tratamento de erro', () => {
  it('expõe a mensagem do erro e esvazia a lista', async () => {
    vi.mocked(listCustomers).mockRejectedValue(
      new ApiError('Não foi possível conectar ao servidor.', null),
    )

    const list = useCustomerList()
    await list.fetchPage()

    expect(list.error.value).toBe('Não foi possível conectar ao servidor.')
    expect(list.customers.value).toEqual([])
    expect(list.loading.value).toBe(false)
  })

  it('limpa o erro anterior ao tentar novamente com sucesso', async () => {
    vi.mocked(listCustomers).mockRejectedValueOnce(new ApiError('Falhou', 500))

    const list = useCustomerList()
    await list.fetchPage()
    expect(list.error.value).toBe('Falhou')

    vi.mocked(listCustomers).mockResolvedValue(pagina([cliente()]))
    await list.fetchPage()

    expect(list.error.value).toBeNull()
    expect(list.customers.value).toHaveLength(1)
  })
})

describe('filtros', () => {
  it('adia a busca enquanto o usuário digita', async () => {
    vi.useFakeTimers()
    const list = useCustomerList()

    // Três teclas em sequência devem resultar numa única requisição, não três.
    list.scheduleSearch()
    list.scheduleSearch()
    list.scheduleSearch()

    expect(listCustomers).not.toHaveBeenCalled()

    await vi.advanceTimersByTimeAsync(400)

    expect(listCustomers).toHaveBeenCalledOnce()
  })

  it('volta para a primeira página ao aplicar um filtro', async () => {
    const list = useCustomerList()
    list.page.value = 3

    list.applyFilters()
    await nextTick()

    // Sem isto, filtrar estando na página 3 traria uma lista vazia e
    // pareceria "nada encontrado".
    expect(list.page.value).toBe(1)
  })

  it('limpa os filtros e volta à situação padrão', async () => {
    const list = useCustomerList()
    list.filters.value = { name: 'Joana', cpf: '529', status: 'all' }

    list.clearFilters()
    await nextTick()

    expect(list.filters.value).toEqual({ name: '', cpf: '', status: 'active' })
  })

  it('informa quando há filtro ativo', () => {
    const list = useCustomerList()
    expect(list.hasActiveFilters.value).toBe(false)

    list.filters.value.name = 'Joana'
    expect(list.hasActiveFilters.value).toBe(true)

    list.filters.value.name = '   '
    expect(list.hasActiveFilters.value).toBe(false)
  })
})

describe('respostas fora de ordem', () => {
  it('descarta a resposta de uma busca que já foi substituída', async () => {
    // Cenário: o usuário digita "jo", a busca sai; digita "joana" e a
    // segunda busca sai e responde primeiro. Sem o cancelamento, a resposta
    // de "jo" chegaria depois e sobrescreveria a lista correta.
    const antiga = pagina([cliente({ id: 1, name: 'Resultado antigo' })])
    const nova = pagina([cliente({ id: 2, name: 'Resultado novo' })])

    let resolverAntiga: ((v: PaginatedResponse<Customer>) => void) | undefined

    vi.mocked(listCustomers)
      .mockImplementationOnce(
        () =>
          new Promise((resolve) => {
            resolverAntiga = resolve
          }),
      )
      .mockResolvedValueOnce(nova)

    const list = useCustomerList()

    const primeira = list.fetchPage()
    const segunda = list.fetchPage()

    await segunda
    expect(list.customers.value[0].name).toBe('Resultado novo')

    // A primeira responde atrasada e não pode mais alterar o estado.
    resolverAntiga?.(antiga)
    await primeira

    expect(list.customers.value[0].name).toBe('Resultado novo')
  })

  it('cancela a requisição anterior ao iniciar outra', async () => {
    const list = useCustomerList()

    void list.fetchPage()
    await list.fetchPage()

    const primeiroSinal = vi.mocked(listCustomers).mock.calls[0][1]
    expect(primeiroSinal?.aborted).toBe(true)
  })
})

describe('paginação', () => {
  it('navega para a página solicitada', async () => {
    vi.mocked(listCustomers).mockResolvedValue(pagina([cliente()], 100))

    const list = useCustomerList()
    await list.fetchPage()

    list.goToPage(3)
    await nextTick()

    expect(list.page.value).toBe(3)
  })

  it('ignora página fora do intervalo', async () => {
    vi.mocked(listCustomers).mockResolvedValue(pagina([cliente()], 20))

    const list = useCustomerList()
    await list.fetchPage()
    vi.mocked(listCustomers).mockClear()

    list.goToPage(0)
    list.goToPage(-1)
    list.goToPage(99)

    // Nenhuma requisição: a API rejeitaria `page=0` com 422, e um erro de
    // validação por um clique na paginação seria confuso.
    expect(listCustomers).not.toHaveBeenCalled()
    expect(list.page.value).toBe(1)
  })

  it('ignora navegação para a página já exibida', async () => {
    const list = useCustomerList()
    await list.fetchPage()
    vi.mocked(listCustomers).mockClear()

    list.goToPage(1)

    expect(listCustomers).not.toHaveBeenCalled()
  })
})

describe('ordenação', () => {
  it('aplica a ordenação e volta à primeira página', async () => {
    const list = useCustomerList()
    list.page.value = 4

    list.setSort('cpf', 'desc')
    await nextTick()

    expect(list.sort.value).toBe('cpf')
    expect(list.direction.value).toBe('desc')
    expect(list.page.value).toBe(1)
  })
})

describe('estado vazio', () => {
  it('sinaliza lista vazia somente após o carregamento terminar', async () => {
    vi.mocked(listCustomers).mockResolvedValue(pagina([]))

    const list = useCustomerList()

    // Durante o carregamento não é "vazio": é "ainda não sei". Confundir os
    // dois faria a tela piscar "nenhum cliente encontrado" a cada busca.
    const promessa = list.fetchPage()
    expect(list.isEmpty.value).toBe(false)

    await promessa
    expect(list.isEmpty.value).toBe(true)
  })
})
