import { createApp } from 'vue'

import App from './App.vue'
import { useAuth } from './composables/useAuth'
import { router } from './router'
import { vuetify } from './plugins/vuetify'
import { setUnauthenticatedHandler } from './services/http'

/**
 * Reação a sessão expirada.
 *
 * Registrado como callback, e não como import dentro do cliente HTTP, para
 * evitar dependência circular entre o cliente e o estado de sessão.
 *
 * A sessão pode cair entre duas ações do usuário — expiração, logout em
 * outra aba, servidor reiniciado. Sem isto a aplicação continuaria exibindo
 * a tela de clientes enquanto cada requisição falharia em silêncio.
 */
setUnauthenticatedHandler(() => {
  useAuth().clearSession()

  if (router.currentRoute.value.name !== 'login') {
    void router.replace({ name: 'login' })
  }
})

createApp(App).use(router).use(vuetify).mount('#app')
