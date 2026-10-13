<script setup lang="ts">
import type { Customer } from '@/types/customer'

/**
 * Listagem em cards, para telas pequenas.
 *
 * O requisito pede, para telas pequenas, "evitar rolagem horizontal
 * excessiva" e "considerar apresentação dos registros em cards no lugar da
 * tabela". Uma tabela de seis colunas — uma delas o endereço completo — não
 * cabe em 360px sem rolagem lateral, e rolar na horizontal para ler cada
 * linha é a pior leitura possível num celular.
 *
 * O card inverte a hierarquia: o nome é o título, e os demais dados viram
 * linhas rotuladas. Nenhum dado é omitido em relação à tabela.
 */

const props = defineProps<{
  customers: Customer[]
  canManage: boolean
}>()

const emit = defineEmits<{
  edit: [customer: Customer]
  deactivate: [customer: Customer]
  reactivate: [customer: Customer]
}>()
</script>

<template>
  <!-- Lista semântica: leitores de tela anunciam a quantidade de itens e a
       posição atual, o que uma pilha de <div> não comunica. -->
  <ul class="customer-card-list">
    <li v-for="customer in props.customers" :key="customer.id">
      <v-card variant="flat" border class="mb-3">
        <v-card-text class="pa-4">
          <div class="d-flex align-start justify-space-between mb-3">
            <div class="min-width-0">
              <p class="text-subtitle-1 font-weight-medium mb-1">{{ customer.name }}</p>
              <p class="text-body-2 text-medium-emphasis numeric mb-0">
                {{ customer.cpf_formatted }}
              </p>
            </div>

            <v-chip
              :color="customer.is_active ? 'success' : undefined"
              :variant="customer.is_active ? 'tonal' : 'outlined'"
              size="small"
              label
              class="flex-shrink-0 ms-2"
            >
              {{ customer.is_active ? 'Ativo' : 'Inativo' }}
            </v-chip>
          </div>

          <dl class="customer-details mb-0">
            <dt>Telefone</dt>
            <dd class="numeric">{{ customer.phone_formatted }}</dd>

            <dt>Endereço</dt>
            <dd>{{ customer.full_address }}</dd>
          </dl>
        </v-card-text>

        <v-divider />

        <!-- Ações com rótulo visível, não apenas ícone: num toque não há
             hover para revelar o significado do ícone. -->
        <v-card-actions class="pa-2">
          <v-btn
            variant="text"
            size="small"
            prepend-icon="$edit"
            :disabled="!props.canManage"
            :aria-label="`Editar ${customer.name}`"
            @click="emit('edit', customer)"
          >
            Editar
          </v-btn>

          <v-spacer />

          <v-btn
            v-if="customer.is_active"
            variant="text"
            size="small"
            color="error"
            prepend-icon="$deactivate"
            :disabled="!props.canManage"
            :aria-label="`Inativar ${customer.name}`"
            @click="emit('deactivate', customer)"
          >
            Inativar
          </v-btn>

          <v-btn
            v-else
            variant="text"
            size="small"
            color="success"
            prepend-icon="$reactivate"
            :disabled="!props.canManage"
            :aria-label="`Reativar ${customer.name}`"
            @click="emit('reactivate', customer)"
          >
            Reativar
          </v-btn>
        </v-card-actions>
      </v-card>
    </li>
  </ul>
</template>

<style scoped>
.customer-card-list {
  list-style: none;
  padding: 0;
  margin: 0;
}

.customer-details {
  display: grid;
  grid-template-columns: auto 1fr;
  column-gap: 12px;
  row-gap: 4px;
  font-size: 0.875rem;
}

.customer-details dt {
  color: rgb(var(--v-theme-on-surface));
  opacity: 0.6;
  white-space: nowrap;
}

.customer-details dd {
  margin: 0;
  /* Endereços longos precisam quebrar, senão estouram a largura do card. */
  overflow-wrap: anywhere;
}

.numeric {
  font-variant-numeric: tabular-nums;
}

.min-width-0 {
  min-width: 0;
}
</style>
