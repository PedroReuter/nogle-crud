<script setup lang="ts">
import { useNotifications } from '@/composables/useNotifications'

/**
 * Exibe a fila de avisos de sucesso e erro.
 *
 * Montado uma única vez no layout. Avisos de erro permanecem mais tempo do
 * que os de sucesso: um erro precisa ser lido e às vezes anotado, enquanto
 * um sucesso só confirma o que o usuário acabou de fazer.
 */

const { notifications, dismiss } = useNotifications()

const ICONS = {
  success: '$success',
  error: '$error',
  info: '$info',
} as const

const TIMEOUTS = { success: 4000, error: 8000, info: 5000 } as const
</script>

<template>
  <div class="notification-stack">
    <v-snackbar
      v-for="item in notifications"
      :key="item.id"
      :model-value="true"
      :color="item.kind"
      :timeout="TIMEOUTS[item.kind]"
      location="bottom right"
      variant="flat"
      @update:model-value="dismiss(item.id)"
    >
      <div class="d-flex align-center">
        <v-icon :icon="ICONS[item.kind]" class="me-2" />
        <span>{{ item.message }}</span>
      </div>

      <template #actions>
        <v-btn
          variant="text"
          density="comfortable"
          aria-label="Fechar aviso"
          @click="dismiss(item.id)"
        >
          Fechar
        </v-btn>
      </template>
    </v-snackbar>
  </div>
</template>
