import { expect, test } from '@playwright/test'

import {
  contadorDeRegistros,
  cpfValido,
  entrar,
  escolherSituacao,
  filtrarPorNome,
  preencherCliente,
  sair,
} from './apoio'

/**
 * Os seis fluxos que o requisito lista para os testes end-to-end:
 * login, cadastro, pesquisa, edição, inativação e reativação.
 *
 * Rodam em sequência sobre o mesmo cliente, porque é assim que um usuário de
 * verdade usa o sistema — e porque um fluxo que só funciona isolado não
 * prova grande coisa.
 */
test.describe.configure({ mode: 'serial' })

const CPF = cpfValido()
const NOME = `Cliente E2E ${Date.now()}`
const NOME_EDITADO = `${NOME} Editado`

test('1 · autenticação', async ({ page }) => {
  await page.goto('/')

  // Sem sessão, qualquer rota leva ao login.
  await expect(page).toHaveURL(/\/login/)

  await page.getByRole('textbox', { name: 'E-mail' }).fill('admin@nogle.test')
  await page.locator('input[type="password"]').fill('senha-errada')
  await page.getByRole('button', { name: 'Entrar' }).click()

  await expect(page.getByText('As credenciais informadas não conferem.')).toBeVisible()

  await page.locator('input[type="password"]').fill('senha123')
  await page.getByRole('button', { name: 'Entrar' }).click()

  await expect(page.getByRole('heading', { name: 'Clientes' })).toBeVisible()
  await expect(page.getByText('Administradora Demo')).toBeVisible()
})

test('2 · cadastro de cliente', async ({ page }) => {
  await entrar(page)

  await page.getByRole('button', { name: 'Novo cliente' }).click()

  const modal = page.locator('.v-dialog')
  await expect(modal.getByText('Novo cliente')).toBeVisible()

  // Enviar vazio precisa acusar erro por campo, não um aviso genérico.
  await modal.getByRole('button', { name: 'Salvar' }).click()
  await expect(modal.getByText('Informe o nome do cliente')).toBeVisible()
  await expect(modal.getByText('Informe o CPF')).toBeVisible()

  await preencherCliente(page, { nome: NOME, cpf: CPF })

  // O endereço veio da consulta de CEP, não digitado.
  await expect(modal.getByLabel('Endereço')).toHaveValue('Avenida Paulista')

  await modal.getByRole('button', { name: 'Salvar' }).click()

  await expect(page.getByText(`Cliente ${NOME} cadastrado com sucesso.`)).toBeVisible()
})

test('3 · pesquisa pelo cliente cadastrado', async ({ page }) => {
  await entrar(page)

  await filtrarPorNome(page, NOME)

  await expect(page.getByText(NOME, { exact: true })).toBeVisible()
  await expect(contadorDeRegistros(page)).toContainText('1 registro')
})

test('4 · cadastro com CPF duplicado é recusado', async ({ page }) => {
  await entrar(page)

  await page.getByRole('button', { name: 'Novo cliente' }).click()
  await preencherCliente(page, { nome: 'Tentativa Duplicada', cpf: CPF })

  const modal = page.locator('.v-dialog')
  await modal.getByRole('button', { name: 'Salvar' }).click()

  await expect(modal.getByText('Já existe um cliente cadastrado com este CPF.')).toBeVisible()
})

test('5 · edição mantendo o próprio CPF', async ({ page }) => {
  await entrar(page)
  await filtrarPorNome(page, NOME)

  await page.getByRole('button', { name: `Editar ${NOME}` }).click()

  const modal = page.locator('.v-dialog')
  await expect(modal.getByText('Editar cliente')).toBeVisible()

  // Altera o nome sem tocar no CPF: é onde a validação de unicidade costuma
  // falhar, por não ignorar o próprio registro.
  await modal.getByLabel('Nome do cliente').fill(NOME_EDITADO)
  await modal.getByRole('button', { name: 'Salvar' }).click()

  await expect(page.getByText(`Cliente ${NOME_EDITADO} atualizado com sucesso.`)).toBeVisible()
})

test('6 · inativação preserva o registro', async ({ page }) => {
  await entrar(page)
  await filtrarPorNome(page, NOME_EDITADO)

  await page.getByRole('button', { name: `Inativar ${NOME_EDITADO}` }).click()

  // A confirmação precisa nomear o cliente. A busca é escopada ao diálogo
  // porque o nome também aparece na linha da tabela por trás dele.
  const confirmacao = page.locator('.v-dialog')
  await expect(confirmacao.getByText(new RegExp(NOME_EDITADO))).toBeVisible()
  await confirmacao.getByRole('button', { name: 'Inativar', exact: true }).click()

  await expect(page.getByText(`Cliente ${NOME_EDITADO} inativado.`)).toBeVisible()

  // Some dos ativos, mas continua existindo entre os inativos.
  await expect(contadorDeRegistros(page)).toContainText('Nenhum registro')

  await escolherSituacao(page, 'Somente inativos')

  await expect(page.getByText(NOME_EDITADO, { exact: true })).toBeVisible()
})

test('7 · reativação', async ({ page }) => {
  await entrar(page)

  await escolherSituacao(page, 'Somente inativos')
  await filtrarPorNome(page, NOME_EDITADO)

  await page.getByRole('button', { name: `Reativar ${NOME_EDITADO}` }).click()
  await page.locator('.v-dialog').getByRole('button', { name: 'Reativar', exact: true }).click()

  await expect(page.getByText(`Cliente ${NOME_EDITADO} reativado.`)).toBeVisible()
})

test('8 · histórico registra tudo que aconteceu', async ({ page }) => {
  await entrar(page)
  await filtrarPorNome(page, NOME_EDITADO)

  await page.getByRole('button', { name: `Histórico de ${NOME_EDITADO}` }).click()

  const modal = page.locator('.v-dialog')
  await expect(modal.getByText('Histórico de alterações')).toBeVisible()

  for (const evento of ['Cadastrado', 'Alterado', 'Inativado', 'Reativado']) {
    await expect(modal.getByText(evento, { exact: true }).first()).toBeVisible()
  }

  // O operador fica identificado, e a trilha não guarda valores.
  await expect(modal.getByText('Administradora Demo').first()).toBeVisible()
  await expect(modal.getByText(/não os valores/)).toBeVisible()
})

test('9 · eliminação definitiva exige inativar antes', async ({ page }) => {
  await entrar(page)
  await filtrarPorNome(page, NOME_EDITADO)

  // Ativo: a ação de eliminar não é oferecida.
  await expect(
    page.getByRole('button', { name: `Eliminar definitivamente ${NOME_EDITADO}` }),
  ).toHaveCount(0)

  await page.getByRole('button', { name: `Inativar ${NOME_EDITADO}` }).click()
  await page.locator('.v-dialog').getByRole('button', { name: 'Inativar', exact: true }).click()
  await expect(page.getByText(`Cliente ${NOME_EDITADO} inativado.`)).toBeVisible()

  await escolherSituacao(page, 'Somente inativos')
  await filtrarPorNome(page, NOME_EDITADO)

  await page.getByRole('button', { name: `Eliminar definitivamente ${NOME_EDITADO}` }).click()

  // A confirmação precisa deixar claro que não há volta.
  const eliminacao = page.locator('.v-dialog')
  await expect(eliminacao.getByText(/NÃO poderão ser recuperados/)).toBeVisible()
  await eliminacao.getByRole('button', { name: 'Eliminar definitivamente', exact: true }).click()

  await expect(page.getByText(/eliminado definitivamente/)).toBeVisible()

  // O cadastro sumiu de verdade.
  await filtrarPorNome(page, NOME_EDITADO)
  await expect(contadorDeRegistros(page)).toContainText('Nenhum registro')
})

test('10 · perfil de leitura não altera nada', async ({ page }) => {
  await entrar(page, 'leitura')

  await expect(
    page.getByText('Seu perfil permite apenas consultar e filtrar clientes.'),
  ).toBeVisible()
  await expect(page.getByRole('button', { name: 'Novo cliente' })).toHaveCount(0)

  // Consulta funciona.
  await filtrarPorNome(page, 'a')
  await expect(contadorDeRegistros(page)).toContainText('registro')

  await sair(page)
})
