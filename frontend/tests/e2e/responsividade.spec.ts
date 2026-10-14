import { expect, test } from '@playwright/test'

import { entrar } from './apoio'

/**
 * Verificações em tela de celular.
 *
 * Rodam no projeto `mobile` do playwright.config.ts, com emulação de
 * dispositivo. Importam porque abaixo do ponto de quebra a listagem é outro
 * componente — cards em vez de tabela —, não a mesma tabela redimensionada.
 */

test('a listagem vira cards e não há rolagem horizontal', async ({ page }) => {
  await entrar(page)

  // A tabela não existe nessa largura.
  await expect(page.locator('table')).toHaveCount(0)
  await expect(page.locator('.customer-card-list')).toBeVisible()

  // Rolagem horizontal é o defeito mais comum em tela pequena, e o mais
  // irritante de usar.
  const estouro = await page.evaluate(
    () => document.documentElement.scrollWidth > window.innerWidth,
  )
  expect(estouro).toBe(false)
})

test('nenhum elemento ultrapassa a largura da tela', async ({ page }) => {
  await entrar(page)

  const excedentes = await page.evaluate(() =>
    [...document.querySelectorAll('body *')]
      .filter((el) => el.getBoundingClientRect().right > window.innerWidth + 1)
      .map((el) => el.tagName + '.' + String(el.className).slice(0, 40)),
  )

  expect(excedentes).toEqual([])
})

test('as ações trazem rótulo visível, não apenas ícone', async ({ page }) => {
  // Num toque não há hover para revelar o significado de um ícone.
  await entrar(page)

  await expect(page.getByRole('button', { name: /^Editar / }).first()).toBeVisible()
  await expect(page.locator('.customer-card-list').getByText('EDITAR').first()).toBeVisible()
})

test('o modal continua utilizável em tela pequena', async ({ page }) => {
  await entrar(page)

  await page.getByRole('button', { name: 'Novo cliente' }).click()

  const modal = page.locator('.v-dialog')
  await expect(modal.getByLabel('Nome do cliente')).toBeVisible()
  await expect(modal.getByRole('button', { name: 'Salvar' })).toBeVisible()

  // Os campos precisam ser alcançáveis, não só existir.
  await modal.getByLabel('Nome do cliente').fill('Teste em celular')
  await expect(modal.getByLabel('Nome do cliente')).toHaveValue('Teste em celular')
})
