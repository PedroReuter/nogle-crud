import { fileURLToPath, URL } from 'node:url'

import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vite'
import vuetify from 'vite-plugin-vuetify'

const API_TARGET = process.env.VITE_API_PROXY_TARGET ?? 'http://127.0.0.1:8001'

export default defineConfig({
  plugins: [
    vue(),

    // `autoImport` registra apenas os componentes Vuetify efetivamente
    // usados, em vez de embarcar a biblioteca inteira no bundle.
    vuetify({ autoImport: true }),
  ],

  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },

  server: {
    port: 5174,

    // As portas 5173 e 8000 estão ocupadas por outro projeto nesta máquina;
    // `strictPort` faz o Vite falhar em vez de escorregar para outra porta,
    // o que deixaria o proxy apontando para um servidor diferente do que a
    // documentação indica.
    strictPort: true,

    proxy: {
      // O proxy mantém o SPA e a API na MESMA ORIGEM durante o
      // desenvolvimento.
      //
      // Não é conveniência: a autenticação da Etapa 3 usa sessão com cookie,
      // e cookie cross-site exigiria `SameSite=None` com `Secure`, portanto
      // HTTPS também em desenvolvimento, além de CORS com credenciais. Com o
      // proxy, o navegador vê uma única origem e nada disso é necessário.
      //
      // Em produção o Nginx cumpre o mesmo papel: SPA em `/`, API em `/api`.
      '/api': {
        target: API_TARGET,
        changeOrigin: true,
      },
      '/up': { target: API_TARGET, changeOrigin: true },
    },
  },
})
