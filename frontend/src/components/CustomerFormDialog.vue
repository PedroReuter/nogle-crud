<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'

import { createCustomer, updateCustomer } from '@/services/customers'
import { ApiError, type ValidationErrors } from '@/services/http'
import { maskCpf, maskPhone, maskPostalCode } from '@/support/masks'
import {
  cpfRule,
  maxLength,
  minLength,
  phoneRule,
  postalCodeRule,
  required,
} from '@/support/validators'
import { BRAZILIAN_STATES, type Customer, type CustomerPayload } from '@/types/customer'

/**
 * Modal de cadastro e edição de cliente.
 *
 * Um único componente atende às duas operações, como pede o requisito. O que
 * difere entre elas é o título, o verbo HTTP e a mensagem de sucesso — o
 * formulário, as regras e o tratamento de erro são idênticos, e duplicá-los
 * em dois componentes garantiria que um dia divergissem.
 */

const props = defineProps<{
  modelValue: boolean
  /** Cliente a editar. `null` abre o modal em modo de cadastro. */
  customer: Customer | null
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  saved: [customer: Customer, mode: 'created' | 'updated']
}>()

const isEditing = computed(() => props.customer !== null)

const title = computed(() => (isEditing.value ? 'Editar cliente' : 'Novo cliente'))

function emptyForm(): CustomerPayload {
  return {
    name: '',
    cpf: '',
    phone: '',
    postal_code: '',
    state: null,
    city: '',
    neighborhood: '',
    street: '',
    number: '',
    complement: '',
  }
}

const form = ref<CustomerPayload>(emptyForm())

/**
 * Cópia do formulário como ele estava ao abrir.
 *
 * Serve para detectar alteração não salva. Comparar com o cliente original
 * não funcionaria no modo de cadastro, em que não há original.
 */
const pristine = ref(JSON.stringify(emptyForm()))

const isDirty = computed(() => JSON.stringify(form.value) !== pristine.value)

const saving = ref(false)

/** Erros vindos do backend, por campo. */
const apiErrors = ref<ValidationErrors>({})

/** Erro que não pertence a um campo específico. */
const formError = ref<string | null>(null)

const formRef = ref<{ validate: () => Promise<{ valid: boolean }> } | null>(null)
const firstFieldRef = ref<{ focus: () => void } | null>(null)
const confirmingDiscard = ref(false)

/** Preenche o formulário ao abrir, conforme a operação. */
watch(
  () => [props.modelValue, props.customer] as const,
  async ([open, customer]) => {
    if (!open) return

    form.value = customer
      ? {
          name: customer.name,
          // Os campos mascarados são exibidos com máscara; a camada de
          // serviço a desfaz antes de enviar.
          cpf: maskCpf(customer.cpf),
          phone: maskPhone(customer.phone),
          postal_code: maskPostalCode(customer.postal_code),
          state: customer.state,
          city: customer.city,
          neighborhood: customer.neighborhood,
          street: customer.street,
          number: customer.number,
          complement: customer.complement ?? '',
        }
      : emptyForm()

    pristine.value = JSON.stringify(form.value)
    apiErrors.value = {}
    formError.value = null
    saving.value = false

    // Foco no primeiro campo ao abrir, requisito de acessibilidade: quem
    // navega por teclado precisa começar dentro do formulário, não fora dele.
    await nextTick()
    firstFieldRef.value?.focus()
  },
  { immediate: true },
)

/**
 * Limpa o erro do backend ao editar o campo correspondente.
 *
 * Sem isto, a mensagem "Já existe um cliente com este CPF" permaneceria sob
 * o campo enquanto o usuário digita um CPF diferente, sugerindo que o novo
 * valor também é duplicado.
 */
function clearApiError(field: keyof CustomerPayload): void {
  if (apiErrors.value[field]) {
    const { [field]: _removed, ...rest } = apiErrors.value
    apiErrors.value = rest
  }
}

function errorsFor(field: keyof CustomerPayload): string[] {
  return apiErrors.value[field] ?? []
}

function onMaskedInput(field: 'cpf' | 'phone' | 'postal_code', value: string): void {
  const mask = { cpf: maskCpf, phone: maskPhone, postal_code: maskPostalCode }[field]
  form.value[field] = mask(value)
  clearApiError(field)
}

async function submit(): Promise<void> {
  // Proteção contra envio duplo: um duplo clique no botão salvar criaria
  // dois clientes, e o segundo falharia na constraint de CPF único,
  // mostrando um erro confuso depois de a operação já ter dado certo.
  if (saving.value) return

  const result = await formRef.value?.validate()
  if (!result?.valid) return

  saving.value = true
  apiErrors.value = {}
  formError.value = null

  try {
    const saved = props.customer
      ? await updateCustomer(props.customer.id, form.value)
      : await createCustomer(form.value)

    emit('saved', saved, props.customer ? 'updated' : 'created')
    close(true)
  } catch (error) {
    if (error instanceof ApiError && error.isValidationError) {
      // Erro de validação do backend é apresentado campo a campo, no mesmo
      // lugar em que apareceria um erro de validação local.
      apiErrors.value = error.validationErrors

      // Um 422 sem detalhe por campo precisa de algum lugar para aparecer,
      // senão o modal pareceria não responder ao clique em salvar.
      if (Object.keys(error.validationErrors).length === 0) {
        formError.value = error.message
      }
    } else {
      formError.value =
        error instanceof ApiError ? error.message : 'Não foi possível salvar o cliente.'
    }
  } finally {
    saving.value = false
  }
}

/** Pedido de fechamento: confirma se há alteração não salva. */
function requestClose(): void {
  if (saving.value) return

  if (isDirty.value) {
    confirmingDiscard.value = true

    return
  }

  close(false)
}

function close(force: boolean): void {
  confirmingDiscard.value = false
  if (force || !saving.value) {
    emit('update:modelValue', false)
  }
}

const nameRules = [required('Informe o nome do cliente'), minLength(3), maxLength(120)]
const cpfRules = [required('Informe o CPF'), cpfRule()]
const phoneRules = [required('Informe o telefone'), phoneRule()]
const postalCodeRules = [required('Informe o CEP'), postalCodeRule()]
const cityRules = [required('Informe a cidade'), maxLength(120)]
const stateRules = [required('Selecione o estado')]
const streetRules = [required('Informe o endereço'), maxLength(180)]
const neighborhoodRules = [required('Informe o bairro'), maxLength(120)]
const numberRules = [required('Informe o número'), maxLength(20)]
const complementRules = [maxLength(120)]
</script>

<template>
  <v-dialog
    :model-value="modelValue"
    max-width="860"
    scrollable
    :persistent="saving"
    role="dialog"
    aria-labelledby="customer-form-title"
    @update:model-value="(value: boolean) => !value && requestClose()"
  >
    <v-card>
      <v-card-title id="customer-form-title" class="d-flex align-center pa-4">
        <v-icon :icon="isEditing ? '$customerEdit' : '$customerAdd'" class="me-2" />
        <span class="text-h6">{{ title }}</span>

        <v-spacer />

        <v-btn
          icon="$close"
          variant="text"
          density="comfortable"
          aria-label="Fechar"
          :disabled="saving"
          @click="requestClose"
        />
      </v-card-title>

      <v-divider />

      <v-card-text class="pa-4">
        <!-- Erro que não pertence a um campo. `role="alert"` faz o leitor
             de tela anunciar a mensagem assim que ela aparece. -->
        <v-alert
          v-if="formError"
          type="error"
          variant="tonal"
          density="comfortable"
          class="mb-4"
          role="alert"
          :text="formError"
        />

        <v-form ref="formRef" :disabled="saving" @submit.prevent="submit">
          <!-- Campos agrupados por assunto: identificação e contato primeiro,
               endereço depois, seguindo a ordem em que são preenchidos. -->
          <p class="text-overline text-medium-emphasis mb-2">Identificação</p>

          <v-row dense>
            <v-col cols="12" md="7">
              <v-text-field
                ref="firstFieldRef"
                v-model="form.name"
                label="Nome do cliente *"
                autocomplete="name"
                maxlength="120"
                counter="120"
                :rules="nameRules"
                :error-messages="errorsFor('name')"
                @update:model-value="clearApiError('name')"
              />
            </v-col>

            <v-col cols="12" sm="6" md="5">
              <v-text-field
                :model-value="form.cpf"
                label="CPF *"
                placeholder="000.000.000-00"
                inputmode="numeric"
                maxlength="14"
                :rules="cpfRules"
                :error-messages="errorsFor('cpf')"
                @update:model-value="(value: string) => onMaskedInput('cpf', value)"
              />
            </v-col>

            <v-col cols="12" sm="6" md="5">
              <v-text-field
                :model-value="form.phone"
                label="Telefone *"
                placeholder="(00) 00000-0000"
                inputmode="tel"
                autocomplete="tel"
                maxlength="16"
                :rules="phoneRules"
                :error-messages="errorsFor('phone')"
                @update:model-value="(value: string) => onMaskedInput('phone', value)"
              />
            </v-col>
          </v-row>

          <p class="text-overline text-medium-emphasis mt-4 mb-2">Endereço</p>

          <v-row dense>
            <v-col cols="12" sm="5" md="3">
              <v-text-field
                :model-value="form.postal_code"
                label="CEP *"
                placeholder="00000-000"
                inputmode="numeric"
                autocomplete="postal-code"
                maxlength="9"
                :rules="postalCodeRules"
                :error-messages="errorsFor('postal_code')"
                @update:model-value="(value: string) => onMaskedInput('postal_code', value)"
              />
            </v-col>

            <v-col cols="12" sm="7" md="6">
              <v-text-field
                v-model="form.city"
                label="Cidade *"
                autocomplete="address-level2"
                maxlength="120"
                :rules="cityRules"
                :error-messages="errorsFor('city')"
                @update:model-value="clearApiError('city')"
              />
            </v-col>

            <v-col cols="12" sm="5" md="3">
              <v-select
                v-model="form.state"
                label="Estado *"
                :items="BRAZILIAN_STATES"
                autocomplete="address-level1"
                :rules="stateRules"
                :error-messages="errorsFor('state')"
                @update:model-value="clearApiError('state')"
              />
            </v-col>

            <v-col cols="12" md="8">
              <v-text-field
                v-model="form.street"
                label="Endereço *"
                autocomplete="address-line1"
                maxlength="180"
                :rules="streetRules"
                :error-messages="errorsFor('street')"
                @update:model-value="clearApiError('street')"
              />
            </v-col>

            <v-col cols="7" sm="5" md="4">
              <v-text-field
                v-model="form.number"
                label="Número *"
                maxlength="20"
                :rules="numberRules"
                :error-messages="errorsFor('number')"
                @update:model-value="clearApiError('number')"
              />
            </v-col>

            <v-col cols="12" sm="7" md="6">
              <v-text-field
                v-model="form.neighborhood"
                label="Bairro *"
                maxlength="120"
                :rules="neighborhoodRules"
                :error-messages="errorsFor('neighborhood')"
                @update:model-value="clearApiError('neighborhood')"
              />
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field
                v-model="form.complement"
                label="Complemento"
                hint="Opcional"
                maxlength="120"
                :rules="complementRules"
                :error-messages="errorsFor('complement')"
                @update:model-value="clearApiError('complement')"
              />
            </v-col>
          </v-row>

          <p class="text-caption text-medium-emphasis mt-4 mb-0">
            Campos marcados com * são obrigatórios.
          </p>
        </v-form>
      </v-card-text>

      <v-divider />

      <v-card-actions class="pa-4">
        <v-spacer />

        <v-btn variant="text" :disabled="saving" @click="requestClose">Cancelar</v-btn>

        <v-btn
          color="primary"
          prepend-icon="$save"
          :loading="saving"
          :disabled="saving"
          @click="submit"
        >
          Salvar
        </v-btn>
      </v-card-actions>
    </v-card>

    <!-- Confirmação de descarte. Aparece sobre o modal em vez de fechá-lo,
         para que o usuário não perca o que digitou ao clicar fora por
         engano. -->
    <v-dialog v-model="confirmingDiscard" max-width="420" role="alertdialog">
      <v-card>
        <v-card-title class="text-h6 pa-4">Descartar alterações?</v-card-title>

        <v-card-text class="pb-2">
          As informações preenchidas não foram salvas e serão perdidas.
        </v-card-text>

        <v-card-actions class="pa-4">
          <v-spacer />
          <v-btn variant="text" @click="confirmingDiscard = false">Continuar editando</v-btn>
          <v-btn color="error" variant="flat" @click="close(true)">Descartar</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-dialog>
</template>
