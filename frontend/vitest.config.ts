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
    include: ['tests/unit/**/*.spec.ts', 'tests/components/**/*.spec.ts'],

    // Os testes end-to-end ficam de fora: usam @playwright/test, que tem
    // runner próprio, e falhariam na coleta aqui. Separados por diretório em
    // vez de por extensão, para que o nome do arquivo continue dizendo o que
    // ele é, e não qual ferramenta o executa.
    exclude: ['tests/e2e/**', 'node_modules/**'],

    // O Vuetify é publicado como ESM e precisa ser transformado pelo Vite
    // em vez de carregado como dependência externa.
    server: {
      deps: { inline: ['vuetify'] },
    },
  },
})
