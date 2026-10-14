<script setup lang="ts">
import { ref, watch } from 'vue'

import { fetchCustomerAudits } from '@/services/customers'
import { ApiError } from '@/services/http'
import type { Customer, CustomerAudit } from '@/types/customer'

/**
 * Histórico de alterações de um cliente.
 *
 * Existe porque uma trilha de auditoria que ninguém consegue consultar tem
 * valor limitado: o princípio de responsabilização da LGPD é poder demonstrar
 * o tratamento dado aos dados, e demonstrar pressupõe ver.
 *
 * Disponível também para o perfil de leitura. Auditoria restrita a quem pode
 * alterar perderia o sentido — quem fiscaliza não é necessariamente quem
 * opera.
 */

const props = defineProps<{
  modelValue: boolean
  customer: Customer | null
}>()

const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>()

const audits = ref<CustomerAudit[]>([])
const loading = ref(false)
const error = ref<string | null>(null)

/** Rótulo legível para cada campo, para não exibir nome de coluna cru. */
const FIELD_LABELS: Record<string, string> = {
  name: 'nome',
  cpf: 'CPF',
  phone: 'telefone',
  postal_code: 'CEP',
  state: 'estado',
  city: 'cidade',
  neighborhood: 'bairro',
  street: 'endereço',
  number: 'número',
  complement: 'complemento',
  is_active: 'situação',
}

const EVENT_COLORS: Record<string, string> = {
  created: 'success',
  updated: 'info',
  deactivated: 'warning',
  reactivated: 'success',
  deleted: 'error',
}

watch(
  () => [props.modelValue, props.customer] as const,
  async ([open, customer]) => {
    if (!open || !customer) return

    loading.value = true
    error.value = null
    audits.value = []

    try {
      const response = await fetchCustomerAudits(customer.id)
      audits.value = response.data
    } catch (caught) {
      error.value =
        caught instanceof ApiError ? caught.message : 'Não foi possível carregar o histórico.'
    } finally {
      loading.value = false
    }
  },
  { immediate: true },
)

function describeFields(fields: string[] | null): string {
  if (!fields || fields.length === 0) return ''

  return fields.map((f) => FIELD_LABELS[f] ?? f).join(', ')
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'medium' })
}
</script>

<template>
  <v-dialog
    :model-value="modelValue"
    max-width="620"
    scrollable
    @update:model-value="(value: boolean) => emit('update:modelValue', value)"
  >
    <v-card>
      <v-card-title class="d-flex align-center pa-4">
        <v-icon icon="$history" class="me-2" />
        <div class="min-width-0">
          <p class="text-h6 mb-0">Histórico de alterações</p>
          <p class="text-caption text-medium-emphasis mb-0 text-truncate">
            {{ props.customer?.name }}
          </p>
        </div>

        <v-spacer />

        <v-btn
          icon="$close"
          variant="text"
          density="comfortable"
          aria-label="Fechar"
          @click="emit('update:modelValue', false)"
        />
      </v-card-title>

      <v-divider />

      <v-card-text class="pa-4">
        <div v-if="loading" class="py-6 text-center">
          <v-progress-circular indeterminate color="primary" aria-label="Carregando histórico" />
        </div>

        <v-alert v-else-if="error" type="error" variant="tonal" role="alert" :text="error" />

        <p v-else-if="audits.length === 0" class="text-body-2 text-medium-emphasis mb-0">
          Nenhum evento registrado.
        </p>

        <!-- Lista semântica: o leitor de tela anuncia a quantidade de eventos
             e a posição atual. -->
        <ul v-else class="audit-list">
          <li v-for="audit in audits" :key="audit.id" class="audit-item">
            <v-chip :color="EVENT_COLORS[audit.event]" size="small" label variant="tonal">
              {{ audit.event_label }}
            </v-chip>

            <div class="min-width-0">
              <p class="text-body-2 mb-0">
                {{ formatDate(audit.created_at) }}
                <span class="text-medium-emphasis"> · {{ audit.user?.name ?? 'sistema' }} </span>
              </p>

              <p
                v-if="describeFields(audit.changed_fields)"
                class="text-caption text-medium-emphasis mb-0"
              >
                Campos alterados: {{ describeFields(audit.changed_fields) }}
              </p>
            </div>
          </li>
        </ul>

        <!-- O requisito de minimização explicado na própria tela, para que
             ninguém procure um valor que a trilha deliberadamente não guarda. -->
        <v-alert
          v-if="audits.length > 0"
          type="info"
          variant="tonal"
          density="compact"
          class="mt-4"
          text="O histórico registra quais campos mudaram, não os valores — para não duplicar dados pessoais."
        />
      </v-card-text>
    </v-card>
  </v-dialog>
</template>

<style scoped>
.audit-list {
  list-style: none;
  padding: 0;
  margin: 0;
}

.audit-item {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 10px 0;
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.audit-item:last-child {
  border-bottom: none;
}

.min-width-0 {
  min-width: 0;
}
</style>
