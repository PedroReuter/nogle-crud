import { computed, ref, watch } from 'vue'

import { listCustomers } from '@/services/customers'
import { ApiError } from '@/services/http'
import type { Customer, CustomerStatus, PaginationMeta, SortableColumn } from '@/types/customer'

/** Intervalo entre a última tecla digitada e a busca, em milissegundos. */
const SEARCH_DEBOUNCE_MS = 350

const EMPTY_META: PaginationMeta = {
  current_page: 1,
  from: null,
  last_page: 1,
  per_page: 15,
  to: null,
  total: 0,
}

/**
 * Estado da listagem de clientes.
 *
 * Reúne filtros, paginação, ordenação e estados assíncronos num só lugar,
 * para que a tela apenas exiba e dispare ações. Três problemas concretos são
 * resolvidos aqui, e nenhum deles é visível no componente:
 *
 * 1. Digitar no filtro não dispara uma requisição por tecla (debounce).
 * 2. Uma resposta antiga não sobrescreve uma mais recente (AbortController).
 * 3. Alterar um filtro volta para a primeira página — sem isso, filtrar
 *    estando na página 3 traria uma lista vazia e pareceria "nada
 *    encontrado".
 *
 * O estado é local à função, não de módulo: há uma listagem na aplicação, e
 * se houver uma segunda ela deve ter estado próprio.
 */
export function useCustomerList() {
  const customers = ref<Customer[]>([])
  const meta = ref<PaginationMeta>({ ...EMPTY_META })

  const loading = ref(false)
  const error = ref<string | null>(null)

  const filters = ref({
    name: '',
    cpf: '',
    status: 'active' as CustomerStatus,
  })

  const page = ref(1)
  const perPage = ref(15)
  const sort = ref<SortableColumn>('name')
  const direction = ref<'asc' | 'desc'>('asc')

  /** Requisição em voo, para ser cancelada se outra começar. */
  let inFlight: AbortController | null = null
  let debounceTimer: ReturnType<typeof setTimeout> | null = null

  const isEmpty = computed(() => !loading.value && customers.value.length === 0)

  const hasActiveFilters = computed(
    () => filters.value.name.trim() !== '' || filters.value.cpf.trim() !== '',
  )

  async function fetchPage(): Promise<void> {
    // Cancela a requisição anterior. Sem isto, trocar o filtro rapidamente
    // deixa duas respostas em voo, e a que chegar por último vence — que
    // pode ser a do filtro antigo.
    inFlight?.abort()
    inFlight = new AbortController()
    const controller = inFlight

    loading.value = true
    error.value = null

    try {
      const response = await listCustomers(
        {
          name: filters.value.name.trim() || undefined,
          cpf: filters.value.cpf.trim() || undefined,
          status: filters.value.status,
          sort: sort.value,
          direction: direction.value,
          per_page: perPage.value,
          page: page.value,
        },
        controller.signal,
      )

      // Só a requisição mais recente escreve no estado.
      //
      // O `abort()` acima faz o axios rejeitar a requisição anterior, mas
      // há uma janela real em que a resposta já chegou e está sendo
      // processada quando o cancelamento acontece — nesse caso ela resolve
      // normalmente e, sem esta guarda, sobrescreveria o resultado da busca
      // mais nova. É o mesmo motivo da guarda no `catch`, aplicado ao
      // caminho de sucesso.
      if (controller !== inFlight) return

      customers.value = response.data
      meta.value = response.meta
    } catch (caught) {
      // A requisição cancelada por uma mais nova não é falha: o estado
      // pertence à requisição que a substituiu, e mexer nele aqui apagaria
      // o resultado correto.
      if (controller.signal.aborted) return

      error.value =
        caught instanceof ApiError ? caught.message : 'Não foi possível carregar os clientes.'
      customers.value = []
      meta.value = { ...EMPTY_META }
    } finally {
      // Só a requisição mais recente encerra o carregamento, senão o
      // spinner desapareceria enquanto a busca atual ainda está em curso.
      if (controller === inFlight) {
        loading.value = false
      }
    }
  }

  /** Volta à primeira página e busca. Usado quando um filtro muda. */
  function applyFilters(): void {
    page.value = 1
    void fetchPage()
  }

  /** Agenda a busca, reiniciando o relógio a cada tecla digitada. */
  function scheduleSearch(): void {
    if (debounceTimer) clearTimeout(debounceTimer)
    debounceTimer = setTimeout(applyFilters, SEARCH_DEBOUNCE_MS)
  }

  function clearFilters(): void {
    filters.value = { name: '', cpf: '', status: 'active' }
    applyFilters()
  }

  function goToPage(next: number): void {
    if (next < 1 || next > meta.value.last_page || next === page.value) return

    page.value = next
    void fetchPage()
  }

  function setSort(column: SortableColumn, dir: 'asc' | 'desc'): void {
    sort.value = column
    direction.value = dir
    applyFilters()
  }

  function setPerPage(size: number): void {
    perPage.value = size
    applyFilters()
  }

  /**
   * Recarrega a página atual.
   *
   * Usado após cadastrar, editar ou inativar: a lista precisa refletir a
   * mudança, inclusive quando ela remove o registro do filtro vigente.
   */
  function refresh(): void {
    void fetchPage()
  }

  // Mudança de situação é um clique, não digitação: busca de imediato.
  watch(() => filters.value.status, applyFilters)

  function dispose(): void {
    if (debounceTimer) clearTimeout(debounceTimer)
    inFlight?.abort()
  }

  return {
    customers,
    meta,
    loading,
    error,
    filters,
    page,
    perPage,
    sort,
    direction,
    isEmpty,
    hasActiveFilters,
    fetchPage,
    applyFilters,
    scheduleSearch,
    clearFilters,
    goToPage,
    setSort,
    setPerPage,
    refresh,
    dispose,
  }
}
