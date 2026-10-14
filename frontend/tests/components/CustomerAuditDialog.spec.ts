import { beforeEach, describe, expect, it, vi } from 'vitest'

import CustomerAuditDialog from '@/components/CustomerAuditDialog.vue'
import { ApiError } from '@/services/http'
import type { Customer, CustomerAudit } from '@/types/customer'

import { flush, mountWithVuetify, screen } from '../support/mount'

vi.mock('@/services/customers', () => ({ fetchCustomerAudits: vi.fn() }))

const { fetchCustomerAudits } = await import('@/services/customers')

const CLIENTE = { id: 7, name: 'José Ávila Gonçalves' } as Customer

function evento(overrides: Partial<CustomerAudit> = {}): CustomerAudit {
  return {
    id: 1,
    event: 'created',
    event_label: 'Cadastrado',
    changed_fields: null,
    user: { id: 1, name: 'Administradora Demo' },
    created_at: '2026-10-14T12:00:00+00:00',
    ...overrides,
  }
}

function pagina(items: CustomerAudit[]) {
  return {
    data: items,
    meta: {
      current_page: 1,
      from: 1,
      last_page: 1,
      per_page: 20,
      to: items.length,
      total: items.length,
    },
  }
}

function montar() {
  return mountWithVuetify(CustomerAuditDialog, {
    props: { modelValue: true, customer: CLIENTE },
  })
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('exibição do histórico', () => {
  it('lista os eventos do cliente', async () => {
    vi.mocked(fetchCustomerAudits).mockResolvedValue(
      pagina([
        evento({ id: 2, event: 'deactivated', event_label: 'Inativado' }),
        evento({ id: 1 }),
      ]),
    )

    montar()
    await flush()

    expect(screen().text()).toContain('Inativado')
    expect(screen().text()).toContain('Cadastrado')
  })

  it('identifica quem realizou cada alteração', async () => {
    vi.mocked(fetchCustomerAudits).mockResolvedValue(pagina([evento()]))

    montar()
    await flush()

    expect(screen().text()).toContain('Administradora Demo')
  })

  it('traduz os nomes de campo para rótulos legíveis', async () => {
    // A trilha guarda nomes de coluna; exibir "postal_code" cru seria
    // jogar detalhe de banco na cara do usuário.
    vi.mocked(fetchCustomerAudits).mockResolvedValue(
      pagina([
        evento({
          event: 'updated',
          event_label: 'Alterado',
          changed_fields: ['postal_code', 'neighborhood'],
        }),
      ]),
    )

    montar()
    await flush()

    const texto = screen().text()
    expect(texto).toContain('CEP')
    expect(texto).toContain('bairro')
    expect(texto).not.toContain('postal_code')
  })

  it('explica que a trilha não guarda valores', async () => {
    // Sem o aviso, alguém procuraria o valor anterior e concluiria que falta
    // informação, em vez de entender que é decisão de minimização.
    vi.mocked(fetchCustomerAudits).mockResolvedValue(pagina([evento()]))

    montar()
    await flush()

    expect(screen().text()).toContain('não os valores')
  })

  it('indica quando não há eventos', async () => {
    vi.mocked(fetchCustomerAudits).mockResolvedValue(pagina([]))

    montar()
    await flush()

    expect(screen().text()).toContain('Nenhum evento registrado')
  })

  it('atribui ao sistema o evento sem operador', async () => {
    // Acontece no seeder, que roda sem sessão.
    vi.mocked(fetchCustomerAudits).mockResolvedValue(pagina([evento({ user: null })]))

    montar()
    await flush()

    expect(screen().text()).toContain('sistema')
  })
})

describe('estados', () => {
  it('indica o carregamento', async () => {
    vi.mocked(fetchCustomerAudits).mockReturnValue(new Promise(() => {}))

    montar()
    await flush()

    expect(screen().find('.v-progress-circular').exists()).toBe(true)
  })

  it('exibe erro sem quebrar o diálogo', async () => {
    vi.mocked(fetchCustomerAudits).mockRejectedValue(
      new ApiError('Não foi possível conectar ao servidor.', null),
    )

    montar()
    await flush()

    expect(screen().text()).toContain('Não foi possível conectar ao servidor.')
  })

  it('não consulta quando está fechado', async () => {
    mountWithVuetify(CustomerAuditDialog, {
      props: { modelValue: false, customer: CLIENTE },
    })
    await flush()

    expect(fetchCustomerAudits).not.toHaveBeenCalled()
  })
})
