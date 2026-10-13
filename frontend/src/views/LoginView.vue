<script setup lang="ts">
import { nextTick, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { useAuth } from '@/composables/useAuth'
import { ApiError, type ValidationErrors } from '@/services/http'
import { required } from '@/support/validators'

/**
 * Tela de autenticação.
 *
 * Única rota fora do CRUD, como o requisito permite. Mantida deliberadamente
 * simples: um formulário, sem recuperação de senha nem cadastro, porque o
 * desafio não os pede e cada fluxo a mais é superfície para manter.
 */

const auth = useAuth()
const router = useRouter()
const route = useRoute()

const email = ref('')
const password = ref('')
const showPassword = ref(false)
const submitting = ref(false)

const apiErrors = ref<ValidationErrors>({})
const formError = ref<string | null>(null)

const formRef = ref<{ validate: () => Promise<{ valid: boolean }> } | null>(null)
const emailRef = ref<{ focus: () => void } | null>(null)

onMounted(async () => {
  await nextTick()
  emailRef.value?.focus()
})

function errorsFor(field: 'email' | 'password'): string[] {
  return apiErrors.value[field] ?? []
}

async function submit(): Promise<void> {
  // Mesma proteção do formulário de cliente: um duplo clique dispararia duas
  // autenticações, e a segunda consumiria uma tentativa do limite.
  if (submitting.value) return

  const result = await formRef.value?.validate()
  if (!result?.valid) return

  submitting.value = true
  apiErrors.value = {}
  formError.value = null

  try {
    await auth.signIn({ email: email.value.trim(), password: password.value })

    // Volta ao destino que o usuário tentou acessar antes de ser mandado
    // para o login.
    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : '/'
    await router.replace(redirect)
  } catch (error) {
    if (error instanceof ApiError && error.isValidationError) {
      apiErrors.value = error.validationErrors

      if (Object.keys(error.validationErrors).length === 0) {
        formError.value = error.message
      }
    } else {
      formError.value =
        error instanceof ApiError ? error.message : 'Não foi possível entrar. Tente novamente.'
    }

    // A senha é limpa após falha para que uma nova tentativa não reenvie a
    // mesma credencial por engano.
    password.value = ''
  } finally {
    submitting.value = false
  }
}

const emailRules = [required('Informe o e-mail')]
const passwordRules = [required('Informe a senha')]

/**
 * Credenciais de demonstração.
 *
 * Exibidas apenas em desenvolvimento, para que o avaliador não precise
 * procurá-las no README. Em produção o bloco não é renderizado, e as senhas
 * valem só para o ambiente demonstrativo.
 */
const isDevelopment = import.meta.env.DEV

const demoAccounts = [
  { label: 'Administrador', email: 'admin@nogle.test', password: 'senha123' },
  { label: 'Leitura', email: 'leitura@nogle.test', password: 'senha123' },
]

function useDemoAccount(account: (typeof demoAccounts)[number]): void {
  email.value = account.email
  password.value = account.password
}
</script>

<template>
  <v-container class="fill-height pa-4" fluid>
    <v-row justify="center" align="center">
      <v-col cols="12" sm="8" md="5" lg="4">
        <div class="text-center mb-6">
          <v-icon icon="$appLogo" color="primary" size="44" class="mb-2" />
          <h1 class="text-h5 font-weight-medium mb-1">Nogle CRUD</h1>
          <p class="text-body-2 text-medium-emphasis mb-0">Gerenciamento de clientes</p>
        </div>

        <v-card variant="flat" border>
          <v-card-text class="pa-5">
            <v-alert
              v-if="formError"
              type="error"
              variant="tonal"
              density="comfortable"
              class="mb-4"
              role="alert"
              :text="formError"
            />

            <v-form ref="formRef" :disabled="submitting" @submit.prevent="submit">
              <v-text-field
                ref="emailRef"
                v-model="email"
                label="E-mail"
                type="email"
                autocomplete="username"
                prepend-inner-icon="$email"
                class="mb-3"
                :rules="emailRules"
                :error-messages="errorsFor('email')"
                @update:model-value="apiErrors = {}"
              />

              <v-text-field
                v-model="password"
                label="Senha"
                :type="showPassword ? 'text' : 'password'"
                autocomplete="current-password"
                :append-inner-icon="showPassword ? '$hidePassword' : '$showPassword'"
                :rules="passwordRules"
                :error-messages="errorsFor('password')"
                @click:append-inner="showPassword = !showPassword"
                @update:model-value="apiErrors = {}"
              />

              <v-btn
                type="submit"
                color="primary"
                size="large"
                block
                class="mt-5"
                :loading="submitting"
                :disabled="submitting"
              >
                Entrar
              </v-btn>
            </v-form>
          </v-card-text>
        </v-card>

        <!-- Só em desenvolvimento. -->
        <v-card v-if="isDevelopment" variant="tonal" class="mt-4" color="surface-variant">
          <v-card-text class="pa-4">
            <p class="text-caption text-medium-emphasis mb-2">
              Contas de demonstração — dados fictícios, ambiente de avaliação
            </p>

            <div class="d-flex flex-wrap ga-2">
              <v-btn
                v-for="account in demoAccounts"
                :key="account.email"
                size="small"
                variant="outlined"
                @click="useDemoAccount(account)"
              >
                {{ account.label }}
              </v-btn>
            </div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>
