import { beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'

import CustomerFormDialog from '@/components/CustomerFormDialog.vue'
import { PostalCodeError, type PostalCodeAddress } from '@/services/postalCode'

import { flush, mountWithVuetify, screen } from '../support/mount'

vi.mock('@/services/customers', () => ({
  createCustomer: vi.fn(),
  updateCustomer: vi.fn(),
}))

vi.mock('@/services/postalCode', async (original) => {
  const real = await original<typeof import('@/services/postalCode')>()

  return { ...real, lookupPostalCode: vi.fn() }
})

const { lookupPostalCode } = await import('@/services/postalCode')

const SE: PostalCodeAddress = {
  street: 'Praça da Sé',
  neighborhood: 'Sé',
  city: 'São Paulo',
  state: 'SP',
}

function montar() {
  return mountWithVuetify(CustomerFormDialog, {
    props: { modelValue: true, customer: null },
  })
}

function inputs() {
  return screen().findAll<HTMLInputElement>('.v-dialog input')
}

/** Índices dos campos no template: 3 é o CEP, 4 cidade, 6 endereço, 8 bairro. */
const CEP = 3
const CIDADE = 4
const ENDERECO = 6
const BAIRRO = 8

beforeEach(() => {
  vi.clearAllMocks()
})

describe('consulta automática de CEP', () => {
  it('não consulta enquanto o CEP está incompleto', async () => {
    montar()
    await flush()

    await inputs()[CEP].setValue('0100100')
    await flush()

    expect(lookupPostalCode).not.toHaveBeenCalled()
  })

  it('consulta assim que o CEP fica completo', async () => {
    vi.mocked(lookupPostalCode).mockResolvedValue(SE)
    montar()
    await flush()

    await inputs()[CEP].setValue('01001000')
    await flush()

    expect(lookupPostalCode).toHaveBeenCalledOnce()
  })

  it('preenche cidade e estado, como exige o requisito', async () => {
    vi.mocked(lookupPostalCode).mockResolvedValue(SE)
    montar()
    await flush()

    await inputs()[CEP].setValue('01001000')
    await flush()
    await nextTick()

    expect(inputs()[CIDADE].element.value).toBe('São Paulo')
    expect(screen().find('.v-dialog .v-select').text()).toContain('SP')
  })

  it('preenche também bairro e logradouro quando o serviço os devolve', async () => {
    vi.mocked(lookupPostalCode).mockResolvedValue(SE)
    montar()
    await flush()

    await inputs()[CEP].setValue('01001000')
    await flush()
    await nextTick()

    expect(inputs()[ENDERECO].element.value).toBe('Praça da Sé')
    expect(inputs()[BAIRRO].element.value).toBe('Sé')
  })

  it('não apaga o endereço digitado quando o serviço não devolve logradouro', async () => {
    // CEPs de logradouro único vêm sem logradouro e sem bairro; sobrescrever
    // apagaria o que o usuário já tinha preenchido.
    vi.mocked(lookupPostalCode).mockResolvedValue({
      street: '',
      neighborhood: '',
      city: 'Brasília',
      state: 'DF',
    })
    montar()
    await flush()

    await inputs()[ENDERECO].setValue('Quadra 10, Conjunto B')
    await inputs()[CEP].setValue('70000000')
    await flush()
    await nextTick()

    expect(inputs()[ENDERECO].element.value).toBe('Quadra 10, Conjunto B')
    expect(inputs()[CIDADE].element.value).toBe('Brasília')
  })

  it('permite corrigir manualmente o que o CEP preencheu', async () => {
    // O requisito pede ajuste manual: endereços novos ou com numeração
    // específica frequentemente precisam de correção.
    vi.mocked(lookupPostalCode).mockResolvedValue(SE)
    montar()
    await flush()

    await inputs()[CEP].setValue('01001000')
    await flush()
    await nextTick()

    await inputs()[CIDADE].setValue('Santo André')
    await nextTick()

    expect(inputs()[CIDADE].element.value).toBe('Santo André')
  })

  it('avisa que o endereço foi preenchido pelo CEP', async () => {
    vi.mocked(lookupPostalCode).mockResolvedValue(SE)
    montar()
    await flush()

    await inputs()[CEP].setValue('01001000')
    await flush()
    await nextTick()

    expect(screen().text()).toContain('Endereço preenchido pelo CEP')
  })
})

describe('CEP inexistente', () => {
  it('exibe a mensagem sem bloquear o cadastro', async () => {
    vi.mocked(lookupPostalCode).mockRejectedValue(
      new PostalCodeError('not-found', 'CEP não encontrado. Verifique o número digitado.'),
    )
    montar()
    await flush()

    await inputs()[CEP].setValue('99999999')
    await flush()
    await nextTick()

    expect(screen().text()).toContain('CEP não encontrado')

    // Os campos de endereço continuam editáveis: o requisito manda tratar o
    // caso, não impedir o cadastro.
    await inputs()[CIDADE].setValue('Cidade Digitada à Mão')
    expect(inputs()[CIDADE].element.value).toBe('Cidade Digitada à Mão')
  })

  it('não preenche nada quando o CEP não existe', async () => {
    vi.mocked(lookupPostalCode).mockRejectedValue(
      new PostalCodeError('not-found', 'CEP não encontrado.'),
    )
    montar()
    await flush()

    await inputs()[CEP].setValue('99999999')
    await flush()
    await nextTick()

    expect(inputs()[CIDADE].element.value).toBe('')
    expect(inputs()[ENDERECO].element.value).toBe('')
  })
})

describe('serviço indisponível', () => {
  it('orienta o preenchimento manual', async () => {
    vi.mocked(lookupPostalCode).mockRejectedValue(
      new PostalCodeError(
        'network',
        'Não foi possível consultar o CEP. Preencha o endereço manualmente.',
      ),
    )
    montar()
    await flush()

    await inputs()[CEP].setValue('01001000')
    await flush()
    await nextTick()

    expect(screen().text()).toContain('Preencha o endereço manualmente')
  })
})

describe('estado de carregamento', () => {
  it('indica a consulta em andamento no campo de CEP', async () => {
    let resolver: ((value: PostalCodeAddress) => void) | undefined
    vi.mocked(lookupPostalCode).mockReturnValue(
      new Promise((resolve) => {
        resolver = resolve
      }),
    )

    montar()
    await flush()

    await inputs()[CEP].setValue('01001000')
    await nextTick()

    expect(screen().find('.v-dialog .v-progress-circular').exists()).toBe(true)

    resolver?.(SE)
    await flush()
    await nextTick()

    expect(screen().find('.v-dialog .v-progress-circular').exists()).toBe(false)
  })
})
