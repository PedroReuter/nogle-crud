<script setup lang="ts">
/**
 * Confirmação de ação destrutiva ou de impacto.
 *
 * Genérico para atender à inativação e à reativação sem duplicar markup. O
 * requisito exige "informar claramente qual cliente será inativado", por
 * isso o chamador passa o nome no corpo da mensagem em vez de um texto
 * genérico do tipo "Confirma a operação?".
 */

const props = defineProps<{
  modelValue: boolean
  title: string
  message: string
  confirmLabel: string
  confirmColor?: string
  loading?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  confirm: []
}>()
</script>

<template>
  <!-- `alertdialog` em vez de `dialog`: comunica ao leitor de tela que a
       caixa requer uma decisão, não apenas informa. -->
  <v-dialog
    :model-value="props.modelValue"
    max-width="460"
    :persistent="props.loading"
    role="alertdialog"
    @update:model-value="(value: boolean) => emit('update:modelValue', value)"
  >
    <v-card>
      <v-card-title class="text-h6 pa-4">{{ props.title }}</v-card-title>

      <v-card-text class="pb-2">{{ props.message }}</v-card-text>

      <v-card-actions class="pa-4">
        <v-spacer />

        <v-btn variant="text" :disabled="props.loading" @click="emit('update:modelValue', false)">
          Cancelar
        </v-btn>

        <v-btn
          :color="props.confirmColor ?? 'primary'"
          variant="flat"
          :loading="props.loading"
          :disabled="props.loading"
          @click="emit('confirm')"
        >
          {{ props.confirmLabel }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
