import { fileURLToPath, URL } from 'node:url'

import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vitest/config'

// Configuração separada da do Vite, e sem o plugin do Vuetify: o
// `autoImport` resolve componentes a partir da análise do template em tempo
// de build, o que não se aplica aos testes — nos testes o plugin do Vuetify
// é registrado explicitamente pelo helper de montagem.
export default defineConfig({
  plugins: [vue()],

  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },

  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: ['./tests/setup.ts'],
    include: ['tests/**/*.spec.ts'],

    // O Vuetify é publicado como ESM e precisa ser transformado pelo Vite
    // em vez de carregado como dependência externa.
    server: {
      deps: { inline: ['vuetify'] },
    },
  },
})
