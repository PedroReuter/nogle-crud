<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { useDisplay } from 'vuetify'

import ConfirmDialog from '@/components/ConfirmDialog.vue'
import CustomerCardList from '@/components/CustomerCardList.vue'
import CustomerFilters from '@/components/CustomerFilters.vue'
import CustomerFormDialog from '@/components/CustomerFormDialog.vue'
import CustomerTable from '@/components/CustomerTable.vue'
import { useCustomerList } from '@/composables/useCustomerList'
import { useNotifications } from '@/composables/useNotifications'
import { deactivateCustomer, reactivateCustomer } from '@/services/customers'
import { ApiError } from '@/services/http'
import type { Customer, SortableColumn } from '@/types/customer'

/**
 * Tela de gerenciamento de clientes.
 *
 * Tela única, como exige o requisito: cadastrar, editar e inativar acontecem
 * em modais sobre esta mesma página, sem navegação para rotas separadas.
 *
 * O componente coordena — não contém regra de negócio nem chamada HTTP
 * montada à mão. O estado da listagem vive em `useCustomerList`, as chamadas
 * em `services/customers`, e as validações em `support/validators`.
 */

const list = useCustomerList()
const { notifySuccess, notifyError } = useNotifications()

// `mdAndUp` é o ponto em que a tabela de seis colunas passa a caber sem
// rolagem horizontal; abaixo disso a listagem vira cards.
const { mdAndUp } = useDisplay()

const formOpen = ref(false)
const editing = ref<Customer | null>(null)

const confirmOpen = ref(false)
const confirmTarget = ref<Customer | null>(null)
const confirmAction = ref<'deactivate' | 'reactivate'>('deactivate')
const confirmLoading = ref(false)

/**
 * Permissão de escrita.
 *
 * Fixa em `true` nesta etapa: a autenticação e as Policies entram na Etapa 3,
 * quando passará a refletir o perfil do usuário. A prop já existe nos
 * componentes para que a mudança seja de uma linha, e porque esconder a ação
 * depois seria retrabalho.
 *
 * A restrição visual nunca é a garantia: a autorização é verificada no
 * backend, e a interface apenas evita oferecer o que será recusado.
 */
const canManage = ref(true)

onMounted(() => list.fetchPage())

// Cancela requisição em voo e o temporizador do filtro ao desmontar, para
// que não tentem escrever em estado que já não existe.
onBeforeUnmount(() => list.dispose())

function openCreate(): void {
  editing.value = null
  formOpen.value = true
}

function openEdit(customer: Customer): void {
  editing.value = customer
  formOpen.value = true
}

function onSaved(customer: Customer, mode: 'created' | 'updated'): void {
  notifySuccess(
    mode === 'created'
      ? `Cliente ${customer.name} cadastrado com sucesso.`
      : `Cliente ${customer.name} atualizado com sucesso.`,
  )

  // Recarrega em vez de inserir o registro na lista local: o cliente novo
  // pode não pertencer ao filtro ou à página atuais, e inserir às cegas
  // mostraria uma lista que não corresponde aos filtros em vigor.
  list.refresh()
}

function askDeactivate(customer: Customer): void {
  confirmTarget.value = customer
  confirmAction.value = 'deactivate'
  confirmOpen.value = true
}

function askReactivate(customer: Customer): void {
  confirmTarget.value = customer
  confirmAction.value = 'reactivate'
  confirmOpen.value = true
}

async function confirmStatusChange(): Promise<void> {
  const customer = confirmTarget.value
  if (!customer || confirmLoading.value) return

  confirmLoading.value = true

  try {
    if (confirmAction.value === 'deactivate') {
      await deactivateCustomer(customer.id)
      notifySuccess(`Cliente ${customer.name} inativado.`)
    } else {
      await reactivateCustomer(customer.id)
      notifySuccess(`Cliente ${customer.name} reativado.`)
    }

    confirmOpen.value = false
    list.refresh()
  } catch (error) {
    notifyError(
      error instanceof ApiError ? error.message : 'Não foi possível alterar a situação do cliente.',
    )
  } finally {
    confirmLoading.value = false
  }
}

function onSort(sort: SortableColumn, direction: 'asc' | 'desc'): void {
  list.setSort(sort, direction)
}
</script>

<template>
  <v-container fluid class="pa-4 pa-md-6">
    <!-- Cabeçalho da página: título, contagem e ação principal. -->
    <div class="d-flex flex-wrap align-center justify-space-between ga-3 mb-4">
      <div>
        <h1 class="text-h5 font-weight-medium mb-1">Clientes</h1>

        <!-- `aria-live` faz o leitor de tela anunciar a nova contagem após
             uma busca, que de outro modo mudaria em silêncio. -->
        <p class="text-body-2 text-medium-emphasis mb-0" aria-live="polite">
          <template v-if="list.loading.value && list.meta.value.total === 0">
            Carregando…
          </template>
          <template v-else-if="list.meta.value.total === 0"> Nenhum registro </template>
          <template v-else-if="list.meta.value.total === 1"> 1 registro </template>
          <template v-else>
            {{ list.meta.value.total }} registros
            <template v-if="list.meta.value.from && list.meta.value.to">
              · exibindo {{ list.meta.value.from }}–{{ list.meta.value.to }}
            </template>
          </template>
        </p>
      </div>

      <v-btn
        v-if="canManage"
        color="primary"
        size="large"
        prepend-icon="$customerAdd"
        @click="openCreate"
      >
        Novo cliente
      </v-btn>
    </div>

    <CustomerFilters
      v-model:name="list.filters.value.name"
      v-model:cpf="list.filters.value.cpf"
      v-model:status="list.filters.value.status"
      :loading="list.loading.value"
      :has-active-filters="list.hasActiveFilters.value"
      @search="list.scheduleSearch"
      @search-now="list.applyFilters"
      @clear="list.clearFilters"
    />

    <!-- Falha de carregamento: mensagem com ação de repetir, em vez de uma
         lista vazia que pareceria "nenhum cliente cadastrado". -->
    <v-alert
      v-if="list.error.value"
      type="error"
      variant="tonal"
      class="mb-4"
      role="alert"
      :text="list.error.value"
    >
      <template #append>
        <v-btn variant="text" size="small" @click="list.refresh">Tentar novamente</v-btn>
      </template>
    </v-alert>

    <v-card v-if="mdAndUp" variant="flat" border>
      <CustomerTable
        :customers="list.customers.value"
        :total="list.meta.value.total"
        :loading="list.loading.value"
        :page="list.page.value"
        :per-page="list.perPage.value"
        :sort="list.sort.value"
        :direction="list.direction.value"
        :can-manage="canManage"
        @edit="openEdit"
        @deactivate="askDeactivate"
        @reactivate="askReactivate"
        @update:page="list.goToPage"
        @update:per-page="list.setPerPage"
        @update:sort="onSort"
      >
        <template #empty>
          <div class="py-8 text-center">
            <v-icon icon="$emptyResults" size="40" class="mb-2 text-medium-emphasis" />
            <p class="text-body-1 mb-1">Nenhum cliente encontrado</p>
            <p class="text-body-2 text-medium-emphasis mb-3">
              {{
                list.hasActiveFilters.value
                  ? 'Nenhum registro corresponde aos filtros aplicados.'
                  : 'Comece cadastrando o primeiro cliente.'
              }}
            </p>
            <v-btn
              v-if="list.hasActiveFilters.value"
              variant="text"
              size="small"
              @click="list.clearFilters"
            >
              Limpar filtros
            </v-btn>
          </div>
        </template>
      </CustomerTable>
    </v-card>

    <!-- Abaixo de md: cards, e paginação própria, porque o rodapé da
         v-data-table-server não vem junto. -->
    <template v-else>
      <div v-if="list.loading.value" class="py-6 text-center">
        <v-progress-circular indeterminate color="primary" />
        <p class="text-body-2 text-medium-emphasis mt-3 mb-0">Carregando clientes…</p>
      </div>

      <v-card v-else-if="list.isEmpty.value" variant="flat" border>
        <v-card-text class="py-8 text-center">
          <v-icon icon="$emptyResults" size="40" class="mb-2 text-medium-emphasis" />
          <p class="text-body-1 mb-1">Nenhum cliente encontrado</p>
          <p class="text-body-2 text-medium-emphasis mb-3">
            {{
              list.hasActiveFilters.value
                ? 'Nenhum registro corresponde aos filtros aplicados.'
                : 'Comece cadastrando o primeiro cliente.'
            }}
          </p>
          <v-btn
            v-if="list.hasActiveFilters.value"
            variant="text"
            size="small"
            @click="list.clearFilters"
          >
            Limpar filtros
          </v-btn>
        </v-card-text>
      </v-card>

      <template v-else>
        <CustomerCardList
          :customers="list.customers.value"
          :can-manage="canManage"
          @edit="openEdit"
          @deactivate="askDeactivate"
          @reactivate="askReactivate"
        />

        <v-pagination
          v-if="list.meta.value.last_page > 1"
          :model-value="list.page.value"
          :length="list.meta.value.last_page"
          :total-visible="4"
          density="comfortable"
          class="mt-2"
          @update:model-value="list.goToPage"
        />
      </template>
    </template>

    <CustomerFormDialog v-model="formOpen" :customer="editing" @saved="onSaved" />

    <ConfirmDialog
      v-model="confirmOpen"
      :title="confirmAction === 'deactivate' ? 'Inativar cliente?' : 'Reativar cliente?'"
      :message="
        confirmAction === 'deactivate'
          ? `O cliente ${confirmTarget?.name ?? ''} deixará de aparecer na listagem de ativos. O registro é preservado e pode ser reativado depois.`
          : `O cliente ${confirmTarget?.name ?? ''} voltará a aparecer na listagem de ativos.`
      "
      :confirm-label="confirmAction === 'deactivate' ? 'Inativar' : 'Reativar'"
      :confirm-color="confirmAction === 'deactivate' ? 'error' : 'success'"
      :loading="confirmLoading"
      @confirm="confirmStatusChange"
    />
  </v-container>
</template>
