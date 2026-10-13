import axios, { AxiosError, type AxiosInstance } from 'axios'

/**
 * Cliente HTTP único da aplicação.
 *
 * Centralizado para que nenhum componente chame `axios` direto: o
 * tratamento de erro, os cabeçalhos e, na Etapa 3, o envio de credenciais
 * de sessão ficam num só lugar.
 */
export const http: AxiosInstance = axios.create({
  // Caminho relativo, sem host. Em desenvolvimento o proxy do Vite
  // encaminha `/api` ao Laravel; em produção o Nginx faz o mesmo. Fixar
  // um host aqui quebraria um dos dois ambientes.
  baseURL: '/api',

  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },

  // Envia o cookie de sessão. Necessário a partir da Etapa 3 e inofensivo
  // agora, porque front e API estão na mesma origem.
  withCredentials: true,
})

/** Erros de validação do backend, no formato `{ campo: [mensagens] }`. */
export type ValidationErrors = Record<string, string[]>

/**
 * Erro de uma chamada à API, já interpretado.
 *
 * Existe para que os componentes não precisem conhecer a forma de um
 * `AxiosError` nem o formato de resposta do Laravel. Um componente que
 * trate erro olha `validationErrors` e `message`, e nada mais.
 */
export class ApiError extends Error {
  // Campos declarados e atribuídos no corpo do construtor, e não como
  // propriedades de parâmetro: a opção `erasableSyntaxOnly` do tsconfig
  // proíbe sintaxe que gera código em tempo de execução em vez de apenas
  // apagar tipos, e propriedades de parâmetro geram as atribuições.
  readonly status: number | null
  readonly validationErrors: ValidationErrors

  constructor(message: string, status: number | null, validationErrors: ValidationErrors = {}) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.validationErrors = validationErrors
  }

  /** Se o backend reprovou os dados enviados (HTTP 422). */
  get isValidationError(): boolean {
    return this.status === 422
  }

  /** Se o usuário não está autenticado (HTTP 401). */
  get isUnauthenticated(): boolean {
    return this.status === 401
  }

  /** Se o usuário está autenticado mas sem permissão (HTTP 403). */
  get isForbidden(): boolean {
    return this.status === 403
  }
}

/**
 * Converte qualquer falha de requisição num `ApiError`.
 *
 * Distingue três situações que precisam de mensagens diferentes: resposta de
 * erro do servidor, requisição que não obteve resposta (rede caiu, API
 * parada) e falha antes do envio.
 */
export function toApiError(error: unknown): ApiError {
  if (!(error instanceof AxiosError)) {
    return new ApiError('Ocorreu um erro inesperado.', null)
  }

  // Requisição cancelada: não é falha, e não deve virar mensagem de erro
  // na tela. Quem cancelou sabe por quê.
  if (axios.isCancel(error)) {
    return new ApiError('Requisição cancelada.', null)
  }

  if (!error.response) {
    return new ApiError(
      'Não foi possível conectar ao servidor. Verifique sua conexão e tente novamente.',
      null,
    )
  }

  const { status, data } = error.response
  const body = (data ?? {}) as { message?: string; errors?: ValidationErrors }

  return new ApiError(body.message ?? defaultMessageFor(status), status, body.errors ?? {})
}

/**
 * Mensagem para quando o backend não envia uma.
 *
 * Nenhuma delas repassa detalhe técnico: o requisito proíbe expor
 * informação sensível, e "Erro 500: SQLSTATE[23505]" não ajuda o usuário.
 */
function defaultMessageFor(status: number): string {
  switch (status) {
    case 401:
      return 'Sua sessão expirou. Entre novamente para continuar.'
    case 403:
      return 'Você não tem permissão para realizar esta operação.'
    case 404:
      return 'Registro não encontrado.'
    case 422:
      return 'Verifique os dados informados.'
    case 429:
      return 'Muitas tentativas. Aguarde alguns instantes e tente novamente.'
    default:
      return status >= 500
        ? 'O servidor encontrou um erro. Tente novamente em alguns instantes.'
        : 'Não foi possível concluir a operação.'
  }
}

/* ----------------------------------------------------------------------
 * Sessão expirada
 * ------------------------------------------------------------------- */

type UnauthenticatedHandler = () => void

let onUnauthenticated: UnauthenticatedHandler | null = null

/**
 * Registra o que fazer quando o servidor responde 401.
 *
 * Um callback, e não um import direto do composable de sessão, para evitar
 * dependência circular: o composable de sessão usa este módulo para falar
 * com a API, e este módulo precisa avisá-lo quando a sessão cai.
 *
 * Importa porque a sessão pode expirar entre duas ações do usuário. Sem
 * isto, a aplicação continuaria mostrando a tela de clientes e cada
 * requisição falharia em silêncio.
 */
export function setUnauthenticatedHandler(handler: UnauthenticatedHandler): void {
  onUnauthenticated = handler
}

http.interceptors.response.use(
  (response) => response,
  (error: unknown) => {
    const apiError = toApiError(error)

    // O 401 do próprio /api/me durante a verificação inicial é esperado
    // para quem não está autenticado e não deve disparar o tratamento de
    // sessão expirada — não havia sessão para expirar.
    const url = error instanceof AxiosError ? (error.config?.url ?? '') : ''

    if (apiError.isUnauthenticated && !url.endsWith('/me') && !url.endsWith('/login')) {
      onUnauthenticated?.()
    }

    return Promise.reject(error)
  },
)
