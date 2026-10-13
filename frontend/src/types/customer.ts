/**
 * Contrato de dados trocado com a API.
 *
 * Espelha `App\Http\Resources\CustomerResource` do backend. É a única
 * fonte de verdade de formato no frontend: qualquer divergência aparece
 * como erro de tipo no build em vez de como campo vazio na tela.
 */

/** As 27 unidades federativas, espelhando o enum `BrazilianState`. */
// prettier-ignore — uma sigla por linha renderia 27 linhas sem ganho de leitura.
export const BRAZILIAN_STATES = [
  'AC',
  'AL',
  'AP',
  'AM',
  'BA',
  'CE',
  'DF',
  'ES',
  'GO',
  'MA',
  'MT',
  'MS',
  'MG',
  'PA',
  'PB',
  'PR',
  'PE',
  'PI',
  'RJ',
  'RN',
  'RS',
  'RO',
  'RR',
  'SC',
  'SP',
  'SE',
  'TO',
] as const

export type BrazilianState = (typeof BRAZILIAN_STATES)[number]

/**
 * Cliente como a API o devolve.
 *
 * Os campos vêm em pares — o valor normalizado, que reenviamos ao salvar, e
 * o `_formatted`, que exibimos. Montados no backend para que a interface não
 * reimplemente as máscaras e as duas visões não divirjam.
 */
export interface Customer {
  id: number
  name: string

  /** 11 dígitos, sem pontuação. */
  cpf: string
  cpf_formatted: string

  /** 10 ou 11 dígitos, sem pontuação. */
  phone: string
  phone_formatted: string

  /** 8 dígitos, sem pontuação. */
  postal_code: string
  postal_code_formatted: string

  street: string
  number: string
  complement: string | null
  neighborhood: string
  city: string
  state: BrazilianState

  /** Endereço em linha única, para a coluna exigida na listagem. */
  full_address: string

  is_active: boolean
  created_at: string | null
  updated_at: string | null
}

/**
 * Campos que o formulário envia ao cadastrar ou editar.
 *
 * Deliberadamente sem `id`, `is_active`, `created_at` e os `_formatted`: são
 * campos que o backend não aceita por atribuição em massa, e incluí-los aqui
 * sugeriria que a interface pode alterá-los.
 */
export interface CustomerPayload {
  name: string
  cpf: string
  phone: string
  postal_code: string
  state: BrazilianState | null
  city: string
  neighborhood: string
  street: string
  number: string
  complement: string | null
}

/** Situação usada no filtro da listagem. */
export type CustomerStatus = 'active' | 'inactive' | 'all'

/** Colunas pelas quais o backend autoriza ordenar. */
export const SORTABLE_COLUMNS = ['name', 'cpf', 'created_at', 'city', 'state'] as const

export type SortableColumn = (typeof SORTABLE_COLUMNS)[number]

/** Parâmetros aceitos por `GET /api/customers`. */
export interface CustomerQuery {
  name?: string
  cpf?: string
  status?: CustomerStatus
  sort?: SortableColumn
  direction?: 'asc' | 'desc'
  per_page?: number
  page?: number
}

/** Metadados de paginação devolvidos pelo Laravel. */
export interface PaginationMeta {
  current_page: number
  from: number | null
  last_page: number
  per_page: number
  to: number | null
  total: number
}

export interface PaginatedResponse<T> {
  data: T[]
  meta: PaginationMeta
}
