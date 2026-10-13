import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import { lookupPostalCode, PostalCodeError } from '@/services/postalCode'

/**
 * O `fetch` é substituído em todos os casos.
 *
 * O requisito é explícito: "não depender de chamadas externas para testar a
 * lógica de CEP". Um teste que chamasse o ViaCEP de verdade falharia quando
 * o serviço estivesse fora do ar, quando não houvesse rede, e no pipeline de
 * CI — e nenhuma dessas falhas diria nada sobre o código.
 */
const RESPOSTA_SE = {
  cep: '01001-000',
  logradouro: 'Praça da Sé',
  complemento: 'lado ímpar',
  bairro: 'Sé',
  localidade: 'São Paulo',
  uf: 'SP',
}

function responderCom(body: unknown, ok = true, status = 200): void {
  vi.stubGlobal(
    'fetch',
    vi.fn().mockResolvedValue({
      ok,
      status,
      json: async () => body,
    }),
  )
}

beforeEach(() => {
  vi.clearAllMocks()
})

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('consulta bem-sucedida', () => {
  it('devolve o endereço do CEP', async () => {
    responderCom(RESPOSTA_SE)

    await expect(lookupPostalCode('01001000')).resolves.toEqual({
      street: 'Praça da Sé',
      neighborhood: 'Sé',
      city: 'São Paulo',
      state: 'SP',
    })
  })

  it('aceita o CEP com máscara', async () => {
    responderCom(RESPOSTA_SE)

    await lookupPostalCode('01001-000')

    expect(fetch).toHaveBeenCalledWith('https://viacep.com.br/ws/01001000/json/', expect.anything())
  })

  it('normaliza a UF para maiúsculas', async () => {
    responderCom({ ...RESPOSTA_SE, uf: 'sp' })

    const endereco = await lookupPostalCode('01001000')

    expect(endereco.state).toBe('SP')
  })

  it('devolve string vazia para campos ausentes na resposta', async () => {
    // CEPs de logradouro único vêm sem logradouro e sem bairro; o
    // composable usa isso para não sobrescrever o que o usuário digitou.
    responderCom({ cep: '70000-000', localidade: 'Brasília', uf: 'DF' })

    await expect(lookupPostalCode('70000000')).resolves.toEqual({
      street: '',
      neighborhood: '',
      city: 'Brasília',
      state: 'DF',
    })
  })
})

describe('CEP inexistente', () => {
  it('rejeita quando o serviço responde com erro no corpo', async () => {
    // O ViaCEP devolve HTTP 200 com `{ "erro": true }` para CEP inexistente;
    // tratar apenas pelo status deixaria isso passar como sucesso.
    responderCom({ erro: true })

    await expect(lookupPostalCode('99999999')).rejects.toThrow(PostalCodeError)
    await expect(lookupPostalCode('99999999')).rejects.toMatchObject({ kind: 'not-found' })
  })

  it('aceita também a forma textual do campo de erro', async () => {
    // O serviço já respondeu "erro" como string em algumas versões.
    responderCom({ erro: 'true' })

    await expect(lookupPostalCode('99999999')).rejects.toMatchObject({ kind: 'not-found' })
  })

  it('traz mensagem compreensível, não técnica', async () => {
    responderCom({ erro: true })

    await expect(lookupPostalCode('99999999')).rejects.toThrow(
      'CEP não encontrado. Verifique o número digitado.',
    )
  })
})

describe('CEP malformado', () => {
  it('rejeita antes de chamar o serviço', async () => {
    responderCom(RESPOSTA_SE)

    await expect(lookupPostalCode('0100100')).rejects.toMatchObject({ kind: 'invalid' })

    // Economiza uma requisição que o serviço recusaria de qualquer forma.
    expect(fetch).not.toHaveBeenCalled()
  })

  it.each(['', '123', 'abcdefgh', '0100100099'])('rejeita %s', async (cep) => {
    responderCom(RESPOSTA_SE)

    await expect(lookupPostalCode(cep)).rejects.toBeInstanceOf(PostalCodeError)
  })
})

describe('indisponibilidade do serviço', () => {
  it('trata resposta de erro HTTP', async () => {
    responderCom({}, false, 503)

    await expect(lookupPostalCode('01001000')).rejects.toMatchObject({ kind: 'network' })
  })

  it('trata falha de rede', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')))

    await expect(lookupPostalCode('01001000')).rejects.toMatchObject({ kind: 'network' })
  })

  it('orienta o preenchimento manual quando o serviço falha', async () => {
    // O requisito pede "permitir ajustes manuais"; a mensagem precisa dizer
    // ao usuário o que fazer, não apenas que algo deu errado.
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')))

    await expect(lookupPostalCode('01001000')).rejects.toThrow('Preencha o endereço manualmente')
  })

  it('trata resposta que não é JSON válido', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: true,
        status: 200,
        json: async () => {
          throw new SyntaxError('Unexpected token')
        },
      }),
    )

    await expect(lookupPostalCode('01001000')).rejects.toMatchObject({ kind: 'network' })
  })
})

describe('cancelamento', () => {
  it('identifica o cancelamento pedido por quem chamou', async () => {
    const controller = new AbortController()
    controller.abort()

    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new DOMException('Aborted', 'AbortError')))

    await expect(lookupPostalCode('01001000', controller.signal)).rejects.toMatchObject({
      kind: 'cancelled',
    })
  })

  it('repassa o sinal de cancelamento ao fetch', async () => {
    responderCom(RESPOSTA_SE)
    const controller = new AbortController()

    await lookupPostalCode('01001000', controller.signal)

    const opcoes = vi.mocked(fetch).mock.calls[0][1] as RequestInit
    expect(opcoes.signal).toBeInstanceOf(AbortSignal)
  })
})
