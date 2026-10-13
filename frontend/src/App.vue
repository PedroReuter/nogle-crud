<script setup lang="ts">
import AppNotifications from '@/components/AppNotifications.vue'
import CustomersView from '@/views/CustomersView.vue'

/**
 * Layout da aplicação.
 *
 * Uma única tela nesta etapa. O roteador entra na Etapa 3, quando houver a
 * tela de login — que o requisito permite ter rota própria, desde que não
 * comprometa a apresentação do CRUD em tela única.
 *
 * A identificação do usuário autenticado no cabeçalho também é da Etapa 3;
 * o espaço já está reservado para que a mudança não reorganize o layout.
 */
</script>

<template>
  <v-app>
    <v-app-bar flat border="b" color="surface" height="64">
      <v-container fluid class="d-flex align-center pa-0 px-4 px-md-6">
        <v-icon icon="$appLogo" color="primary" class="me-2" />

        <!-- O título da aplicação é o cabeçalho de nível mais alto do
             documento; o <h1> da página é o título da seção. -->
        <span class="text-subtitle-1 font-weight-medium">Nogle CRUD</span>

        <span class="text-caption text-medium-emphasis ms-2 d-none d-sm-inline">
          Gerenciamento de clientes
        </span>

        <v-spacer />
      </v-container>
    </v-app-bar>

    <v-main>
      <!-- `main` recebe o foco de "pular para o conteúdo" e é a landmark
           que leitores de tela usam para entrar direto no conteúdo. -->
      <CustomersView />
    </v-main>

    <AppNotifications />
  </v-app>
</template>

<style>
/* Fonte do sistema, em vez de uma web font baixada: economiza uma
   requisição bloqueante e evita o salto de layout da troca de fonte. */
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
</style>
