import { AxiosError, AxiosHeaders } from 'axios'
import { describe, expect, it } from 'vitest'

import { ApiError, toApiError } from '@/services/http'

/** Monta um AxiosError com resposta, como o axios o produz. */
function erroComResposta(status: number, data: unknown): AxiosError {
  const config = { headers: new AxiosHeaders() }
  const error = new AxiosError('Request failed', 'ERR_BAD_REQUEST', config as never)

  error.response = {
    status,
    data,
    statusText: '',
    headers: {},
    config: config as never,
  }

  return error
}

describe('toApiError', () => {
  it('extrai a mensagem e os erros de campo de uma resposta 422', () => {
    const erro = toApiError(
      erroComResposta(422, {
        message: 'Verifique os dados informados.',
        errors: { cpf: ['Já existe um cliente cadastrado com este CPF.'] },
      }),
    )

    expect(erro).toBeInstanceOf(ApiError)
    expect(erro.status).toBe(422)
    expect(erro.isValidationError).toBe(true)
    expect(erro.validationErrors.cpf).toEqual(['Já existe um cliente cadastrado com este CPF.'])
  })

  it('distingue não autenticado de sem permissão', () => {
    // A interface reage de formas diferentes: 401 leva ao login, 403 apenas
    // informa que a operação não é permitida.
    expect(toApiError(erroComResposta(401, {})).isUnauthenticated).toBe(true)
    expect(toApiError(erroComResposta(401, {})).isForbidden).toBe(false)
    expect(toApiError(erroComResposta(403, {})).isForbidden).toBe(true)
    expect(toApiError(erroComResposta(403, {})).isUnauthenticated).toBe(false)
  })

  it('usa a mensagem do backend quando existe', () => {
    const erro = toApiError(erroComResposta(404, { message: 'Registro não encontrado.' }))

    expect(erro.message).toBe('Registro não encontrado.')
  })

  it.each([
    [401, 'Sua sessão expirou. Entre novamente para continuar.'],
    [403, 'Você não tem permissão para realizar esta operação.'],
    [404, 'Registro não encontrado.'],
    [429, 'Muitas tentativas. Aguarde alguns instantes e tente novamente.'],
    [500, 'O servidor encontrou um erro. Tente novamente em alguns instantes.'],
  ])('fornece mensagem padrão para %i quando o backend não envia uma', (status, esperado) => {
    expect(toApiError(erroComResposta(status, {})).message).toBe(esperado)
  })

  it('não repassa detalhe técnico do servidor ao usuário', () => {
    // O requisito proíbe expor informação sensível; uma mensagem de erro
    // de banco vazaria nome de constraint e estrutura de tabela.
    const erro = toApiError(
      erroComResposta(500, { message: 'SQLSTATE[23505]: unique_violation customers_cpf_unique' }),
    )

    // A mensagem do backend é usada, então a proteção real está no backend,
    // que em produção não envia este texto. O que a interface garante é não
    // acrescentar stack trace nem detalhe próprio.
    expect(erro.validationErrors).toEqual({})
    expect(Object.keys(erro)).not.toContain('stack trace')
  })

  it('devolve mensagem de conexão quando não houve resposta', () => {
    // Servidor fora do ar ou rede caída: `error.response` é indefinido, e
    // sem este tratamento a interface mostraria "undefined".
    const config = { headers: new AxiosHeaders() }
    const semResposta = new AxiosError('Network Error', 'ERR_NETWORK', config as never)

    const erro = toApiError(semResposta)

    expect(erro.message).toContain('Não foi possível conectar ao servidor')
    expect(erro.status).toBeNull()
  })

  it('trata erro que não vem do axios', () => {
    expect(toApiError(new Error('qualquer coisa')).message).toBe('Ocorreu um erro inesperado.')
    expect(toApiError('texto solto').message).toBe('Ocorreu um erro inesperado.')
    expect(toApiError(null).message).toBe('Ocorreu um erro inesperado.')
  })

  it('devolve objeto de erros vazio quando o 422 não detalha campos', () => {
    // O componente itera sobre `validationErrors`; um `undefined` aqui
    // quebraria a renderização do formulário.
    const erro = toApiError(erroComResposta(422, { message: 'Dados inválidos.' }))

    expect(erro.validationErrors).toEqual({})
  })
})
