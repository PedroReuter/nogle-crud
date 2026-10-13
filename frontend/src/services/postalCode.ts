/**
 * Consulta de CEP no ViaCEP.
 *
 * Chamada direta do navegador, sem passar pelo backend. O requisito mais
 * delicado aqui é "evitar que respostas antigas sobrescrevam informações de
 * um CEP digitado posteriormente", que é cancelamento de requisição — algo
 * que se resolve no cliente, onde se sabe que o usuário continuou digitando.
 * Passar pelo backend acrescentaria um ponto de falha e latência sem
 * resolver esse problema.
 *
 * Usa `fetch` em vez do cliente axios da aplicação de propósito: aquele
 * envia credenciais e aponta para `/api`, e o ViaCEP é um serviço de
 * terceiros que não deve receber o cookie de sessão.
 */

const VIACEP_URL = 'https://viacep.com.br/ws'

/** Tempo máximo de espera, para não deixar o formulário travado. */
const TIMEOUT_MS = 8000

/** Endereço devolvido pelo ViaCEP, apenas com os campos que usamos. */
export interface PostalCodeAddress {
  street: string
  neighborhood: string
  city: string
  state: string
}

export type PostalCodeErrorKind = 'not-found' | 'invalid' | 'network' | 'cancelled'

export class PostalCodeError extends Error {
  readonly kind: PostalCodeErrorKind

  constructor(kind: PostalCodeErrorKind, message: string) {
    super(message)
    this.name = 'PostalCodeError'
    this.kind = kind
  }
}

/** Resposta crua do ViaCEP. */
interface ViaCepResponse {
  cep?: string
  logradouro?: string
  bairro?: string
  localidade?: string
  uf?: string
  /** O ViaCEP responde 200 com `{ "erro": true }` para CEP inexistente. */
  erro?: boolean | string
}

/**
 * Busca o endereço de um CEP.
 *
 * @param postalCode CEP com ou sem máscara.
 * @param signal Permite cancelar a consulta quando o usuário digita outro CEP.
 * @throws PostalCodeError
 */
export async function lookupPostalCode(
  postalCode: string,
  signal?: AbortSignal,
): Promise<PostalCodeAddress> {
  const digits = postalCode.replace(/\D/g, '')

  if (digits.length !== 8) {
    throw new PostalCodeError('invalid', 'O CEP deve conter 8 dígitos.')
  }

  // Combina o cancelamento de quem chamou com um limite de tempo próprio:
  // sem o limite, uma rede muito lenta deixaria o campo em carregamento
  // indefinidamente.
  const timeout = new AbortController()
  const timer = setTimeout(() => timeout.abort(), TIMEOUT_MS)
  const combined = anySignal([signal, timeout.signal])

  try {
    const response = await fetch(`${VIACEP_URL}/${digits}/json/`, { signal: combined })

    if (!response.ok) {
      throw new PostalCodeError(
        'network',
        'Não foi possível consultar o CEP. Preencha o endereço manualmente.',
      )
    }

    const data = (await response.json()) as ViaCepResponse

    // CEP inexistente volta com status 200 e `erro: true`, e não com 404 —
    // tratar apenas pelo status deixaria passar como sucesso.
    if (data.erro === true || data.erro === 'true') {
      throw new PostalCodeError('not-found', 'CEP não encontrado. Verifique o número digitado.')
    }

    return {
      street: data.logradouro ?? '',
      neighborhood: data.bairro ?? '',
      city: data.localidade ?? '',
      state: (data.uf ?? '').toUpperCase(),
    }
  } catch (error) {
    if (error instanceof PostalCodeError) throw error

    // Cancelamento pedido por quem chamou não é falha e não deve virar
    // mensagem na tela: quem cancelou sabe por quê.
    if (signal?.aborted) {
      throw new PostalCodeError('cancelled', 'Consulta cancelada.')
    }

    if (timeout.signal.aborted) {
      throw new PostalCodeError(
        'network',
        'A consulta de CEP demorou demais. Preencha o endereço manualmente.',
      )
    }

    throw new PostalCodeError(
      'network',
      'Não foi possível consultar o CEP. Preencha o endereço manualmente.',
    )
  } finally {
    clearTimeout(timer)
  }
}

/**
 * Combina vários sinais de cancelamento num só.
 *
 * `AbortSignal.any` existe nos navegadores atuais, mas não em todos os
 * ambientes de teste; esta implementação evita depender disso.
 */
function anySignal(signals: Array<AbortSignal | undefined>): AbortSignal {
  const controller = new AbortController()

  for (const signal of signals) {
    if (!signal) continue

    if (signal.aborted) {
      controller.abort()
      break
    }

    signal.addEventListener('abort', () => controller.abort(), { once: true })
  }

  return controller.signal
}
