<script setup lang="ts">
import type { Customer, SortableColumn } from '@/types/customer'

/**
 * Tabela de clientes, para telas médias e grandes.
 *
 * Usa `v-data-table-server` porque a paginação é server-side: a tabela nunca
 * recebe a coleção inteira, apenas a página atual mais o total. A variante
 * comum (`v-data-table`) pagina no cliente e pressupõe todos os registros
 * carregados, o que anularia a paginação do backend.
 *
 * As colunas são exatamente as exigidas pelo requisito — nome, CPF, telefone
 * e endereço completo — mais situação e ações.
 *
 * O componente não busca nem altera nada: recebe a página e emite intenções.
 */

const props = defineProps<{
  customers: Customer[]
  total: number
  loading: boolean
  page: number
  perPage: number
  sort: SortableColumn
  direction: 'asc' | 'desc'
  /** Se o usuário pode alterar o cadastro. Ligado à Policy na Etapa 3. */
  canManage: boolean
}>()

const emit = defineEmits<{
  edit: [customer: Customer]
  history: [customer: Customer]
  erase: [customer: Customer]
  deactivate: [customer: Customer]
  reactivate: [customer: Customer]
  'update:page': [page: number]
  'update:perPage': [perPage: number]
  'update:sort': [sort: SortableColumn, direction: 'asc' | 'desc']
}>()

/**
 * Apenas as colunas que o backend autoriza ordenar são marcadas como
 * ordenáveis. Oferecer na interface uma ordenação que a API rejeita com 422
 * produziria um erro que o usuário não causou de propósito.
 */
const headers = [
  { title: 'Nome', key: 'name', sortable: true },
  { title: 'CPF', key: 'cpf_formatted', sortable: false },
  { title: 'Telefone', key: 'phone_formatted', sortable: false },
  { title: 'Endereço completo', key: 'full_address', sortable: false },
  { title: 'Situação', key: 'is_active', sortable: false, width: 110 },
  { title: 'Ações', key: 'actions', sortable: false, align: 'end' as const, width: 170 },
]

type SortItem = { key: string; order?: 'asc' | 'desc' }

function onSortUpdate(sortBy: SortItem[]): void {
  const [first] = sortBy

  // Terceiro clique no cabeçalho limpa a ordenação. O backend exige uma
  // ordem previsível, então voltamos ao padrão em vez de enviar vazio.
  if (!first) {
    emit('update:sort', 'name', 'asc')

    return
  }

  emit('update:sort', first.key as SortableColumn, first.order ?? 'asc')
}
</script>

<template>
  <v-data-table-server
    :headers="headers"
    :items="props.customers"
    :items-length="props.total"
    :loading="props.loading"
    :page="props.page"
    :items-per-page="props.perPage"
    :items-per-page-options="[10, 15, 25, 50]"
    :sort-by="[{ key: props.sort, order: props.direction }]"
    item-value="id"
    density="comfortable"
    hover
    loading-text="Carregando clientes…"
    items-per-page-text="Registros por página"
    @update:page="(value: number) => emit('update:page', value)"
    @update:items-per-page="(value: number) => emit('update:perPage', value)"
    @update:sort-by="onSortUpdate"
  >
    <template #[`item.name`]="{ item }">
      <span class="font-weight-medium">{{ item.name }}</span>
    </template>

    <template #[`item.cpf_formatted`]="{ item }">
      <!-- Tabular para que os dígitos alinhem entre as linhas, o que torna
           a varredura visual de uma coluna de CPFs muito mais rápida. -->
      <span class="numeric-cell">{{ item.cpf_formatted }}</span>
    </template>

    <template #[`item.phone_formatted`]="{ item }">
      <span class="numeric-cell">{{ item.phone_formatted }}</span>
    </template>

    <template #[`item.full_address`]="{ item }">
      <span class="text-body-2">{{ item.full_address }}</span>
    </template>

    <template #[`item.is_active`]="{ item }">
      <!-- A situação traz texto além da cor: cor isolada não é legível para
           quem não distingue verde de cinza. -->
      <v-chip
        :color="item.is_active ? 'success' : undefined"
        :variant="item.is_active ? 'tonal' : 'outlined'"
        size="small"
        label
      >
        {{ item.is_active ? 'Ativo' : 'Inativo' }}
      </v-chip>
    </template>

    <template #[`item.actions`]="{ item }">
      <div class="d-flex justify-end">
        <v-btn
          icon="$edit"
          variant="text"
          size="small"
          density="comfortable"
          :disabled="!props.canManage"
          :aria-label="`Editar ${item.name}`"
          :title="props.canManage ? `Editar ${item.name}` : 'Sem permissão para editar'"
          @click="emit('edit', item)"
        />

        <v-btn
          v-if="item.is_active"
          icon="$deactivate"
          variant="text"
          size="small"
          density="comfortable"
          color="error"
          :disabled="!props.canManage"
          :aria-label="`Inativar ${item.name}`"
          :title="props.canManage ? `Inativar ${item.name}` : 'Sem permissão para inativar'"
          @click="emit('deactivate', item)"
        />

        <v-btn
          v-else
          icon="$reactivate"
          variant="text"
          size="small"
          density="comfortable"
          color="success"
          :disabled="!props.canManage"
          :aria-label="`Reativar ${item.name}`"
          :title="props.canManage ? `Reativar ${item.name}` : 'Sem permissão para reativar'"
          @click="emit('reactivate', item)"
        />

        <!-- Histórico disponível também para o perfil de leitura:
             fiscalizar não exige poder alterar. -->
        <v-btn
          icon="$history"
          variant="text"
          size="small"
          density="comfortable"
          :aria-label="`Histórico de ${item.name}`"
          :title="`Histórico de alterações de ${item.name}`"
          @click="emit('history', item)"
        />

        <!-- Eliminação definitiva só aparece para cliente já inativo. São
             dois passos deliberados: inativar primeiro permite reverter, e
             impede apagar cadastro em uso por um clique errado. -->
        <v-btn
          v-if="!item.is_active && props.canManage"
          icon="$erase"
          variant="text"
          size="small"
          density="comfortable"
          color="error"
          :aria-label="`Eliminar definitivamente ${item.name}`"
          title="Eliminar definitivamente (LGPD)"
          @click="emit('erase', item)"
        />
      </div>
    </template>

    <template #no-data>
      <slot name="empty" />
    </template>
  </v-data-table-server>
</template>

<style scoped>
.numeric-cell {
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}
</style>
