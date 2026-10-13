<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'

import AppNotifications from '@/components/AppNotifications.vue'
import { useAuth } from '@/composables/useAuth'
import { useNotifications } from '@/composables/useNotifications'

/**
 * Layout da aplicação.
 *
 * O cabeçalho só aparece quando há sessão: na tela de login ele não teria o
 * que mostrar, e exibir uma barra vazia com o botão de sair desabilitado é
 * pior do que não exibi-la.
 */

const auth = useAuth()
const router = useRouter()
const { notifyError } = useNotifications()

const signingOut = ref(false)

/** Iniciais do nome, para o avatar. */
const initials = computed(() => {
  const name = auth.user.value?.name ?? ''
  const parts = name.trim().split(/\s+/).filter(Boolean)

  if (parts.length === 0) return '?'
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase()

  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
})

async function signOut(): Promise<void> {
  if (signingOut.value) return

  signingOut.value = true

  try {
    await auth.signOut()
    await router.replace({ name: 'login' })
  } catch {
    notifyError('Não foi possível encerrar a sessão.')
  } finally {
    signingOut.value = false
  }
}
</script>

<template>
  <v-app>
    <v-app-bar v-if="auth.isAuthenticated.value" flat border="b" color="surface" height="64">
      <v-container fluid class="d-flex align-center pa-0 px-4 px-md-6 ga-2">
        <v-icon icon="$appLogo" color="primary" />

        <span class="text-subtitle-1 font-weight-medium">Nogle CRUD</span>

        <span class="text-caption text-medium-emphasis ms-1 d-none d-md-inline">
          Gerenciamento de clientes
        </span>

        <v-spacer />

        <!-- Identificação do usuário autenticado, exigida pelo requisito.
             O perfil fica visível junto do nome: é o que explica por que
             certas ações aparecem ou não para aquele usuário. -->
        <div class="d-flex align-center ga-2">
          <v-avatar color="primary" size="34">
            <span class="text-caption font-weight-medium">{{ initials }}</span>
          </v-avatar>

          <div class="d-none d-sm-block text-right">
            <p class="text-body-2 font-weight-medium mb-0 lh-1">{{ auth.user.value?.name }}</p>
            <p class="text-caption text-medium-emphasis mb-0 lh-1">
              {{ auth.user.value?.role_label }}
            </p>
          </div>

          <v-btn
            icon="$logout"
            variant="text"
            density="comfortable"
            aria-label="Sair da aplicação"
            title="Sair"
            :loading="signingOut"
            :disabled="signingOut"
            @click="signOut"
          />
        </div>
      </v-container>
    </v-app-bar>

    <v-main>
      <!-- Enquanto a sessão é verificada, nem a tela de login nem a de
           clientes devem aparecer: mostrar o login para quem já está
           autenticado causaria um salto visível a cada recarregamento. -->
      <div v-if="!auth.initialized.value" class="d-flex justify-center align-center pa-16">
        <v-progress-circular indeterminate color="primary" aria-label="Carregando aplicação" />
      </div>

      <RouterView v-else />
    </v-main>

    <AppNotifications />
  </v-app>
</template>

<style>
html,
body {
  font-family:
    system-ui,
    -apple-system,
    'Segoe UI',
    Roboto,
    'Helvetica Neue',
    Arial,
    sans-serif;
}

/* Indicador de foco visível em toda a aplicação. O requisito pede navegação
   por teclado, que é inutilizável sem saber onde o foco está. */
:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 2px;
}

.lh-1 {
  line-height: 1.25;
}
</style>
