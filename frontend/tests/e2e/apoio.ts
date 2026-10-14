import { expect, type Page } from '@playwright/test'

/**
 * Apoio comum aos testes end-to-end.
 */

export const CONTAS = {
  admin: { email: 'admin@nogle.test', senha: 'senha123' },
  leitura: { email: 'leitura@nogle.test', senha: 'senha123' },
} as const

/**
 * CPF válido e único por execução.
 *
 * Os testes rodam contra um banco compartilhado e persistente: um CPF fixo
 * falharia por duplicidade na segunda execução, por um motivo que nada tem a
 * ver com o que o teste verifica. Os dígitos verificadores são calculados
 * para que o valor passe na mesma validação que a aplicação usa.
 */
export function cpfValido(): string {
  let base = ''
  for (let i = 0; i < 9; i++) base += Math.floor(Math.random() * 10)

  const digito = (parcial: string, peso: number): number => {
    let soma = 0
    for (let i = 0; i < parcial.length; i++) soma += Number(parcial[i]) * (peso - i)
    const resto = soma % 11

    return resto < 2 ? 0 : 11 - resto
  }

  const d1 = digito(base, 10)
  const d2 = digito(base + d1, 11)

  return `${base}${d1}${d2}`
}

export async function entrar(page: Page, conta: keyof typeof CONTAS = 'admin'): Promise<void> {
  const { email, senha } = CONTAS[conta]

  await page.goto('/login')

  // `getByRole('textbox')` em vez de `getByLabel`: o Vuetify rotula o ícone
  // de revelar senha como "Senha ação depois", e uma busca por rótulo
  // parcial casaria com os dois elementos.
  await page.getByRole('textbox', { name: 'E-mail' }).fill(email)
  await page.locator('input[type="password"]').fill(senha)
  await page.getByRole('button', { name: 'Entrar' }).click()

  // A listagem carregada é o sinal de que a sessão foi estabelecida.
  await expect(page.getByRole('heading', { name: 'Clientes' })).toBeVisible()
}

export async function sair(page: Page): Promise<void> {
  await page.getByRole('button', { name: 'Sair da aplicação' }).click()
  await expect(page.getByRole('button', { name: 'Entrar' })).toBeVisible()
}

/** Preenche o formulário do modal com um cliente válido. */
export async function preencherCliente(
  page: Page,
  dados: { nome: string; cpf: string },
): Promise<void> {
  const modal = page.locator('.v-dialog')

  await modal.getByLabel('Nome do cliente').fill(dados.nome)
  await modal.getByLabel('CPF').fill(dados.cpf)
  await modal.getByLabel('Telefone').fill('11988887777')

  // O CEP dispara a consulta ao ViaCEP; aguardar o preenchimento da cidade é
  // o que confirma que ela respondeu.
  await modal.getByLabel('CEP').fill('01310100')
  await expect(modal.getByLabel('Cidade')).toHaveValue('São Paulo', { timeout: 15_000 })

  await modal.getByLabel('Número').fill('1578')
}

/**
 * O contador de registros do cabeçalho.
 *
 * Localizado por `data-testid` porque o atributo `aria-live` não distingue:
 * o snackbar do Vuetify usa o mesmo, e a busca casaria com os dois.
 */
export function contadorDeRegistros(page: Page) {
  return page.getByTestId('contador-registros')
}

/**
 * Escolhe a situação no filtro.
 *
 * Clica no invólucro do campo, e não no rótulo: o `input` interno do VSelect
 * é somente leitura e não abre o menu, de modo que `getByLabel(...).click()`
 * encontra o elemento mas não produz efeito — o teste trava esperando uma
 * opção que nunca aparece.
 */
export async function escolherSituacao(page: Page, opcao: string): Promise<void> {
  await page.locator('.v-select').filter({ hasText: 'ativos' }).locator('.v-field').first().click()
  await page.getByRole('option', { name: opcao }).click()

  // A troca de situação dispara nova busca; esperar o contador assentar evita
  // asserções sobre a lista anterior.
  await expect(contadorDeRegistros(page)).toContainText(/registro/, { timeout: 10_000 })
}

/** Busca pelo nome e espera a listagem assentar. */
export async function filtrarPorNome(page: Page, termo: string): Promise<void> {
  await page.getByLabel('Nome do cliente').first().fill(termo)

  // A busca é adiada enquanto o usuário digita; esperar a contagem mudar é
  // mais confiável que esperar um tempo fixo.
  await expect(contadorDeRegistros(page)).toContainText('registro', { timeout: 10_000 })
}
