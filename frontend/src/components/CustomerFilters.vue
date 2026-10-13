<script setup lang="ts">
import { maskCpf } from '@/support/masks'
import type { CustomerStatus } from '@/types/customer'

/**
 * Campos de filtro da listagem.
 *
 * Nome e CPF, pesquisáveis individualmente ou em conjunto, como pede o
 * requisito. A situação é um filtro adicional, necessário para atender
 * "permitir visualizar clientes inativos" sem uma segunda tela.
 *
 * O componente não busca nada: emite a intenção e o composable decide
 * quando ir ao servidor. Isso mantém o atraso de digitação e o cancelamento
 * de requisição fora da camada de apresentação.
 */

const props = defineProps<{
  name: string
  cpf: string
  status: CustomerStatus
  loading: boolean
  hasActiveFilters: boolean
}>()

const emit = defineEmits<{
  'update:name': [value: string]
  'update:cpf': [value: string]
  'update:status': [value: CustomerStatus]
  /** Usuário digitou: buscar com atraso. */
  search: []
  /** Usuário confirmou (Enter ou lupa): buscar imediatamente. */
  searchNow: []
  clear: []
}>()

const STATUS_OPTIONS: Array<{ title: string; value: CustomerStatus }> = [
  { title: 'Somente ativos', value: 'active' },
  { title: 'Somente inativos', value: 'inactive' },
  { title: 'Ativos e inativos', value: 'all' },
]

function onNameInput(value: string): void {
  emit('update:name', value)
  emit('search')
}

function onCpfInput(value: string): void {
  // O filtro aceita CPF com ou sem máscara; mascarar na digitação deixa
  // claro que o campo é um CPF, e o serviço normaliza antes de enviar.
  emit('update:cpf', maskCpf(value))
  emit('search')
}
</script>

<template>
  <v-card class="mb-4" variant="flat" border>
    <v-card-text class="pa-4">
      <!-- `role="search"` dá à região um papel reconhecível por leitores de
           tela, que a anunciam como área de busca. -->
      <form role="search" @submit.prevent="emit('searchNow')">
        <v-row dense align="start">
          <v-col cols="12" md="5">
            <v-text-field
              :model-value="props.name"
              label="Nome do cliente"
              placeholder="Buscar por nome"
              prepend-inner-icon="$searchName"
              clearable
              :loading="props.loading"
              @update:model-value="onNameInput"
              @click:clear="onNameInput('')"
            />
          </v-col>

          <v-col cols="12" sm="6" md="3">
            <v-text-field
              :model-value="props.cpf"
              label="CPF"
              placeholder="000.000.000-00"
              prepend-inner-icon="$searchCpf"
              inputmode="numeric"
              maxlength="14"
              clearable
              @update:model-value="onCpfInput"
              @click:clear="onCpfInput('')"
            />
          </v-col>

          <v-col cols="12" sm="6" md="3">
            <v-select
              :model-value="props.status"
              label="Situação"
              :items="STATUS_OPTIONS"
              @update:model-value="(value: CustomerStatus) => emit('update:status', value)"
            />
          </v-col>

          <v-col cols="12" md="1" class="d-flex align-center">
            <!-- O botão de limpar só aparece quando há algo para limpar:
                 um botão permanentemente inerte ensina a ignorá-lo. -->
            <v-btn
              v-if="props.hasActiveFilters"
              variant="text"
              density="comfortable"
              icon="$filterClear"
              aria-label="Limpar filtros"
              title="Limpar filtros"
              @click="emit('clear')"
            />
          </v-col>
        </v-row>

        <!-- Submit acessível por teclado, sem ocupar espaço visual: quem
             navega com Tab alcança o botão e confirma a busca com Enter. -->
        <button type="submit" class="visually-hidden">Buscar</button>
      </form>
    </v-card-text>
  </v-card>
</template>

<style scoped>
.visually-hidden {
  position: absolute;
  width: 1px;
  height: 1px;
  margin: -1px;
  padding: 0;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}
</style>
