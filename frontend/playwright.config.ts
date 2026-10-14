import { defineConfig, devices } from '@playwright/test'

/**
 * Testes end-to-end.
 *
 * Diferente das outras suítes, estes exercitam a aplicação inteira: navegador
 * de verdade, backend de verdade, banco de verdade. É a única camada que
 * pega defeito na junção — e foi exatamente onde os defeitos deste projeto se
 * concentraram.
 *
 * Por padrão apontam para o ambiente Docker, em http://localhost:8080, que é
 * o mais próximo do que será avaliado: Nginx no lugar do proxy do Vite,
 * APP_ENV de produção e caches de configuração e rota ligados.
 *
 *   docker compose up -d
 *   npm run test:e2e
 *
 * Para rodar contra o ambiente de desenvolvimento:
 *   E2E_BASE_URL=http://localhost:5174 npm run test:e2e
 */
const BASE_URL = process.env.E2E_BASE_URL ?? 'http://localhost:8080'

export default defineConfig({
  testDir: './tests/e2e',

  // Sem paralelismo: os testes compartilham o mesmo banco, e um cadastro
  // concorrente com o mesmo CPF faria falhar por motivo alheio ao que se
  // pretende verificar.
  fullyParallel: false,
  workers: 1,

  // Nenhuma repetição automática. Teste que só passa na segunda tentativa
  // está escondendo instabilidade, não contornando-a.
  retries: 0,

  reporter: process.env.CI ? [['github'], ['list']] : [['list']],

  timeout: 30_000,
  expect: { timeout: 10_000 },

  use: {
    baseURL: BASE_URL,
    locale: 'pt-BR',
    timezoneId: 'America/Sao_Paulo',

    // Rastro e captura apenas quando falha: guardar sempre enche o disco e
    // ninguém abre o rastro de um teste que passou.
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'off',
  },

  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
      // Os testes de responsividade pertencem ao projeto `mobile`; rodá-los
      // aqui verificaria a apresentação em cards numa janela de desktop,
      // onde ela não existe.
      testIgnore: /responsividade\.spec\.ts/,
    },
    {
      // Confirma que os fluxos funcionam na apresentação em cards, que é
      // outro componente — não a mesma tabela redimensionada.
      name: 'mobile',
      use: { ...devices['Pixel 7'] },
      testMatch: /responsividade\.spec\.ts/,
    },
  ],
})
