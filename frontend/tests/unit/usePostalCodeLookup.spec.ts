import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import { usePostalCodeLookup } from '@/composables/usePostalCodeLookup'
import { PostalCodeError, type PostalCodeAddress } from '@/services/postalCode'

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

const CENTRO_RJ: PostalCodeAddress = {
  street: 'Avenida Rio Branco',
  neighborhood: 'Centro',
  city: 'Rio de Janeiro',
  state: 'RJ',
}

beforeEach(() => {
  vi.clearAllMocks()
})

afterEach(() => {
  vi.restoreAllMocks()
})

describe('quando consultar', () => {
  it('não consulta enquanto o CEP está incompleto', async () => {
    const cep = usePostalCodeLookup()

    for (const parcial of ['0', '010', '01001', '0100100']) {
      await expect(cep.lookup(parcial)).resolves.toBeNull()
    }

    // Consultar a cada tecla desperdiçaria requisições e faria o serviço
    // responder erro na maior parte delas.
    expect(lookupPostalCode).not.toHaveBeenCalled()
  })

  it('consulta assim que o CEP fica completo', async () => {
    vi.mocked(lookupPostalCode).mockResolvedValue(SE)
    const cep = usePostalCodeLookup()

    await expect(cep.lookup('01001000')).resolves.toEqual(SE)
    expect(lookupPostalCode).toHaveBeenCalledOnce()
  })

  it('aceita o CEP com máscara', async () => {
    vi.mocked(lookupPostalCode).mockResolvedValue(SE)
    const cep = usePostalCodeLookup()

    await cep.lookup('01001-000')

    expect(vi.mocked(lookupPostalCode).mock.calls[0][0]).toBe('01001000')
  })

  it('não repete a consulta do mesmo CEP', async () => {
    vi.mocked(lookupPostalCode).mockResolvedValue(SE)
    const cep = usePostalCodeLookup()

    await cep.lookup('01001000')
    await expect(cep.lookup('01001000')).resolves.toBeNull()

    expect(lookupPostalCode).toHaveBeenCalledOnce()
  })

  it('consulta de novo após o reset', async () => {
    // O reset acontece ao abrir o modal: editar um cliente cujo CEP é igual
    // ao do cliente aberto antes precisa disparar a consulta.
    vi.mocked(lookupPostalCode).mockResolvedValue(SE)
    const cep = usePostalCodeLookup()

    await cep.lookup('01001000')
    cep.reset()
    await cep.lookup('01001000')

    expect(lookupPostalCode).toHaveBeenCalledTimes(2)
  })
})

describe('estado de carregamento', () => {
  it('sinaliza carregamento durante a consulta', async () => {
    let resolver: ((value: PostalCodeAddress) => void) | undefined
    vi.mocked(lookupPostalCode).mockReturnValue(
      new Promise((resolve) => {
        resolver = resolve
      }),
    )

    const cep = usePostalCodeLookup()
    const promessa = cep.lookup('01001000')

    expect(cep.loading.value).toBe(true)

    resolver?.(SE)
    await promessa

    expect(cep.loading.value).toBe(false)
  })

  it('encerra o carregamento mesmo quando a consulta falha', async () => {
    vi.mocked(lookupPostalCode).mockRejectedValue(
      new PostalCodeError('not-found', 'CEP não encontrado.'),
    )

    const cep = usePostalCodeLookup()
    await cep.lookup('99999999')

    expect(cep.loading.value).toBe(false)
  })
})

describe('tratamento de erro', () => {
  it('expõe a mensagem de CEP inexistente', async () => {
    vi.mocked(lookupPostalCode).mockRejectedValue(
      new PostalCodeError('not-found', 'CEP não encontrado. Verifique o número digitado.'),
    )

    const cep = usePostalCodeLookup()
    await expect(cep.lookup('99999999')).resolves.toBeNull()

    expect(cep.error.value).toBe('CEP não encontrado. Verifique o número digitado.')
  })

  it('expõe a mensagem de serviço indisponível', async () => {
    vi.mocked(lookupPostalCode).mockRejectedValue(
      new PostalCodeError('network', 'Não foi possível consultar o CEP.'),
    )

    const cep = usePostalCodeLookup()
    await cep.lookup('01001000')

    expect(cep.error.value).toBe('Não foi possível consultar o CEP.')
  })

  it('não transforma cancelamento em mensagem de erro', async () => {
    // Quem cancelou foi a própria aplicação, ao iniciar uma consulta mais
    // nova; exibir isso ao usuário seria ruído sobre algo que funcionou.
    vi.mocked(lookupPostalCode).mockRejectedValue(
      new PostalCodeError('cancelled', 'Consulta cancelada.'),
    )

    const cep = usePostalCodeLookup()
    await cep.lookup('01001000')

    expect(cep.error.value).toBeNull()
  })

  it('limpa o erro anterior ao consultar de novo', async () => {
    vi.mocked(lookupPostalCode).mockRejectedValueOnce(
      new PostalCodeError('not-found', 'CEP não encontrado.'),
    )

    const cep = usePostalCodeLookup()
    await cep.lookup('99999999')
    expect(cep.error.value).not.toBeNull()

    vi.mocked(lookupPostalCode).mockResolvedValue(SE)
    await cep.lookup('01001000')

    expect(cep.error.value).toBeNull()
  })

  it('limpa o erro quando o CEP volta a ficar incompleto', async () => {
    vi.mocked(lookupPostalCode).mockRejectedValue(
      new PostalCodeError('not-found', 'CEP não encontrado.'),
    )

    const cep = usePostalCodeLookup()
    await cep.lookup('99999999')
    expect(cep.error.value).not.toBeNull()

    // O usuário apagou um dígito para corrigir: a mensagem do CEP anterior
    // não deve continuar na tela.
    await cep.lookup('9999999')

    expect(cep.error.value).toBeNull()
  })
})

describe('respostas fora de ordem', () => {
  it('descarta a resposta de um CEP que já foi substituído', async () => {
    // O requisito nomeia este caso: "evitar que respostas antigas
    // sobrescrevam informações de um CEP digitado posteriormente".
    //
    // Cenário: o usuário digita 01001000 (São Paulo), corrige para 20040002
    // (Rio) e a consulta do Rio responde primeiro. Sem o descarte, a
    // resposta de São Paulo chegaria depois e preencheria a cidade errada.
    let resolverPrimeira: ((value: PostalCodeAddress) => void) | undefined

    vi.mocked(lookupPostalCode)
      .mockImplementationOnce(
        () =>
          new Promise((resolve) => {
            resolverPrimeira = resolve
          }),
      )
      .mockResolvedValueOnce(CENTRO_RJ)

    const cep = usePostalCodeLookup()

    const primeira = cep.lookup('01001000')
    const segunda = cep.lookup('20040002')

    await expect(segunda).resolves.toEqual(CENTRO_RJ)

    // A primeira responde atrasada e precisa ser ignorada.
    resolverPrimeira?.(SE)

    await expect(primeira).resolves.toBeNull()
  })

  it('cancela a consulta anterior ao iniciar outra', async () => {
    vi.mocked(lookupPostalCode).mockResolvedValue(SE)
    const cep = usePostalCodeLookup()

    void cep.lookup('01001000')
    await cep.lookup('20040002')

    const primeiroSinal = vi.mocked(lookupPostalCode).mock.calls[0][1]
    expect(primeiroSinal?.aborted).toBe(true)
  })

  it('não deixa o erro de uma consulta substituída aparecer na tela', async () => {
    let rejeitarPrimeira: ((erro: unknown) => void) | undefined

    vi.mocked(lookupPostalCode)
      .mockImplementationOnce(
        () =>
          new Promise((_resolve, reject) => {
            rejeitarPrimeira = reject
          }),
      )
      .mockResolvedValueOnce(CENTRO_RJ)

    const cep = usePostalCodeLookup()

    const primeira = cep.lookup('99999999')
    await cep.lookup('20040002')

    rejeitarPrimeira?.(new PostalCodeError('not-found', 'CEP não encontrado.'))
    await primeira

    // O CEP atual é válido; mostrar "não encontrado" referente ao anterior
    // contradiria o que está no campo.
    expect(cep.error.value).toBeNull()
  })

  it('cancela a consulta em voo quando o CEP fica incompleto', async () => {
    vi.mocked(lookupPostalCode).mockImplementation(() => new Promise(() => {}))
    const cep = usePostalCodeLookup()

    void cep.lookup('01001000')
    await cep.lookup('0100')

    const sinal = vi.mocked(lookupPostalCode).mock.calls[0][1]
    expect(sinal?.aborted).toBe(true)
    expect(cep.loading.value).toBe(false)
  })
})
