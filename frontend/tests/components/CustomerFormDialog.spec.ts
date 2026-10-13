import { beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'

import CustomerFormDialog from '@/components/CustomerFormDialog.vue'
import { ApiError } from '@/services/http'
import type { Customer } from '@/types/customer'

import { flush, mountWithVuetify, screen } from '../support/mount'

// A camada de serviço é substituída: estes testes verificam o
// comportamento do formulário, não o HTTP. Chamar a API de verdade os
// tornaria dependentes de um servidor no ar, contrariando o requisito de
// testes independentes.
vi.mock('@/services/customers', () => ({
  createCustomer: vi.fn(),
  updateCustomer: vi.fn(),
}))

const { createCustomer, updateCustomer } = await import('@/services/customers')

const CLIENTE: Customer = {
  id: 7,
  name: 'José Ávila Gonçalves',
  cpf: '52998224725',
  cpf_formatted: '529.982.247-25',
  phone: '11988887777',
  phone_formatted: '(11) 98888-7777',
  postal_code: '01001000',
  postal_code_formatted: '01001-000',
  street: 'Praça da Sé',
  number: '100',
  complement: 'Apto 12',
  neighborhood: 'Sé',
  city: 'São Paulo',
  state: 'SP',
  full_address: 'Praça da Sé, 100 - Apto 12 - Sé, São Paulo/SP - CEP 01001-000',
  is_active: true,
  created_at: '2026-10-13T12:00:00+00:00',
  updated_at: '2026-10-13T12:00:00+00:00',
}

function montar(customer: Customer | null = null) {
  return mountWithVuetify(CustomerFormDialog, {
    props: { modelValue: true, customer },
  })
}

/**
 * Campos do formulário, na ordem em que aparecem no template.
 *
 * Buscados no documento e não no wrapper porque o VDialog teleporta seu
 * conteúdo para fora da árvore do componente.
 */
function inputs() {
  return screen().findAll<HTMLInputElement>('.v-dialog input')
}

function campo(rotulo: string) {
  const alvo = screen()
    .findAll('.v-dialog .v-input')
    .find((input) => input.text().includes(rotulo))

  if (!alvo) throw new Error(`Campo "${rotulo}" não encontrado`)

  return alvo
}

/** Botão do modal pelo texto visível. */
function botao(texto: string) {
  const alvo = screen()
    .findAll('.v-dialog button')
    .find((b) => b.text() === texto)

  if (!alvo) throw new Error(`Botão "${texto}" não encontrado`)

  return alvo
}

/** Texto visível do modal de formulário. */
function textoDoModal(): string {
  return screen().find('.v-dialog').text()
}

/**
 * Texto de tudo que está na tela.
 *
 * A confirmação de descarte é um diálogo próprio, irmão do formulário no
 * container de overlays — `textoDoModal()` encontraria apenas o primeiro.
 */
function textoDaTela(): string {
  return screen().text()
}

async function preencherValido(wrapper: ReturnType<typeof montar>) {
  const campos = inputs()
  // Ordem do template: nome, CPF, telefone, CEP, cidade, estado,
  // endereço, número, bairro, complemento.
  await campos[0].setValue('Joana Pereira dos Santos')
  await campos[1].setValue('52998224725')
  await campos[2].setValue('11988887777')
  await campos[3].setValue('01001000')
  await campos[4].setValue('São Paulo')
  await campos[6].setValue('Praça da Sé')
  await campos[7].setValue('100')
  await campos[8].setValue('Sé')

  // O estado é um VSelect: seu <input> é somente leitura e a seleção vem do
  // menu, que renderiza em outro overlay. Definir pelo componente vai direto
  // ao model, que é o que as regras de validação consultam.
  await wrapper.findComponent({ name: 'VSelect' }).setValue('SP')

  await nextTick()
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('modo de cadastro', () => {
  it('abre com o título de cadastro e campos vazios', async () => {
    montar(null)
    await flush()

    expect(textoDoModal()).toContain('Novo cliente')
    expect(inputs()[0].element.value).toBe('')
  })

  it('apresenta todos os campos exigidos pelo requisito', async () => {
    montar(null)
    await flush()

    // Os dez campos da tabela do desafio, nem mais nem menos.
    for (const rotulo of [
      'Nome do cliente',
      'CPF',
      'Telefone',
      'CEP',
      'Cidade',
      'Estado',
      'Endereço',
      'Bairro',
      'Número',
      'Complemento',
    ]) {
      expect(campo(rotulo).exists()).toBe(true)
    }
  })

  it('marca o complemento como o único campo opcional', async () => {
    montar(null)
    await flush()

    // O asterisco distingue obrigatório de opcional na interface.
    expect(campo('Nome do cliente').text()).toContain('*')
    expect(campo('Complemento').text()).not.toContain('*')
  })

  it('oferece os botões Salvar e Cancelar', async () => {
    montar(null)
    await flush()

    const textos = screen()
      .findAll('.v-dialog button')
      .map((b) => b.text())

    expect(textos).toContain('Salvar')
    expect(textos).toContain('Cancelar')
  })
})

describe('modo de edição', () => {
  it('abre com o título de edição e os dados do cliente', async () => {
    montar(CLIENTE)
    await flush()

    expect(textoDoModal()).toContain('Editar cliente')

    const campos = inputs()
    expect(campos[0].element.value).toBe('José Ávila Gonçalves')
    // Os valores normalizados vindos da API são exibidos com máscara.
    expect(campos[1].element.value).toBe('529.982.247-25')
    expect(campos[2].element.value).toBe('(11) 98888-7777')
    expect(campos[3].element.value).toBe('01001-000')
  })

  it('usa o mesmo componente para cadastrar e editar', async () => {
    // O requisito exige que uma única instância atenda às duas operações:
    // trocar a prop muda o modo, sem recriar o componente.
    const wrapper = montar(null)
    await flush()
    expect(textoDoModal()).toContain('Novo cliente')

    await wrapper.setProps({ customer: CLIENTE })
    await flush()
    expect(textoDoModal()).toContain('Editar cliente')
  })

  it('envia a edição pelo endpoint de atualização, com o id do cliente', async () => {
    vi.mocked(updateCustomer).mockResolvedValue(CLIENTE)

    montar(CLIENTE)
    await flush()

    await inputs()[0].setValue('José Ávila Atualizado')
    await botao('Salvar').trigger('click')
    await flush()

    expect(updateCustomer).toHaveBeenCalledOnce()
    expect(vi.mocked(updateCustomer).mock.calls[0][0]).toBe(7)
    expect(createCustomer).not.toHaveBeenCalled()
  })

  it('mantém o próprio CPF do cliente ao salvar sem alterá-lo', async () => {
    vi.mocked(updateCustomer).mockResolvedValue(CLIENTE)

    montar(CLIENTE)
    await flush()

    await botao('Salvar').trigger('click')
    await flush()

    expect(vi.mocked(updateCustomer).mock.calls[0][1].cpf).toBe('529.982.247-25')
  })
})

describe('validação', () => {
  it('não envia nada quando os campos obrigatórios estão vazios', async () => {
    montar(null)
    await flush()

    await botao('Salvar').trigger('click')
    await flush()

    expect(createCustomer).not.toHaveBeenCalled()
  })

  it('exibe mensagem específica por campo, não uma genérica', async () => {
    montar(null)
    await flush()

    await botao('Salvar').trigger('click')
    await flush()

    const texto = textoDoModal()
    expect(texto).toContain('Informe o nome do cliente')
    expect(texto).toContain('Informe o CPF')
    expect(texto).toContain('Informe o telefone')
    expect(texto).toContain('Informe o CEP')
  })

  it('rejeita CPF com dígito verificador inválido', async () => {
    const wrapper = montar(null)
    await preencherValido(wrapper)
    await inputs()[1].setValue('11111111111')

    await botao('Salvar').trigger('click')
    await flush()

    expect(textoDoModal()).toContain('CPF inválido')
    expect(createCustomer).not.toHaveBeenCalled()
  })

  it('rejeita telefone incompleto', async () => {
    const wrapper = montar(null)
    await preencherValido(wrapper)
    await inputs()[2].setValue('119')

    await botao('Salvar').trigger('click')
    await flush()

    expect(textoDoModal()).toContain('Telefone inválido')
    expect(createCustomer).not.toHaveBeenCalled()
  })

  it('rejeita nome composto apenas por espaços', async () => {
    const wrapper = montar(null)
    await preencherValido(wrapper)
    await inputs()[0].setValue('     ')

    await botao('Salvar').trigger('click')
    await flush()

    expect(createCustomer).not.toHaveBeenCalled()
  })
})

describe('máscaras', () => {
  it('aplica a máscara enquanto o usuário digita', async () => {
    montar(null)
    await flush()

    const campos = inputs()

    await campos[1].setValue('52998224725')
    expect(campos[1].element.value).toBe('529.982.247-25')

    await campos[2].setValue('11988887777')
    expect(campos[2].element.value).toBe('(11) 98888-7777')

    await campos[3].setValue('01001000')
    expect(campos[3].element.value).toBe('01001-000')
  })
})

describe('erros devolvidos pela API', () => {
  it('exibe o erro de validação do backend sob o campo correspondente', async () => {
    vi.mocked(createCustomer).mockRejectedValue(
      new ApiError('Verifique os dados informados.', 422, {
        cpf: ['Já existe um cliente cadastrado com este CPF.'],
      }),
    )

    const wrapper = montar(null)
    await preencherValido(wrapper)

    await botao('Salvar').trigger('click')
    await flush()
    await nextTick()

    expect(textoDoModal()).toContain('Já existe um cliente cadastrado com este CPF.')
    // O modal permanece aberto para que o usuário corrija.
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
  })

  it('limpa o erro do backend quando o campo é editado', async () => {
    vi.mocked(createCustomer).mockRejectedValue(
      new ApiError('Verifique os dados informados.', 422, {
        cpf: ['Já existe um cliente cadastrado com este CPF.'],
      }),
    )

    const wrapper = montar(null)
    await preencherValido(wrapper)
    await botao('Salvar').trigger('click')
    await flush()
    await nextTick()
    expect(textoDoModal()).toContain('Já existe um cliente cadastrado')

    // Sem isto, a mensagem continuaria sob o campo enquanto o usuário digita
    // um CPF diferente, sugerindo que o novo valor também é duplicado.
    await inputs()[1].setValue('11144477735')
    await nextTick()

    expect(textoDoModal()).not.toContain('Já existe um cliente cadastrado')
  })

  it('exibe erro não relacionado a campo num alerta do formulário', async () => {
    vi.mocked(createCustomer).mockRejectedValue(
      new ApiError('O servidor encontrou um erro. Tente novamente em alguns instantes.', 500),
    )

    const wrapper = montar(null)
    await preencherValido(wrapper)
    await botao('Salvar').trigger('click')
    await flush()
    await nextTick()

    expect(textoDoModal()).toContain('O servidor encontrou um erro')
    expect(screen().find('.v-dialog [role="alert"]').exists()).toBe(true)
  })
})

describe('proteção contra envio duplicado', () => {
  it('não envia duas vezes quando o botão é clicado repetidamente', async () => {
    // Um duplo clique criaria dois clientes; o segundo falharia na
    // constraint de CPF único e mostraria erro depois de a operação já ter
    // dado certo.
    let resolver: ((customer: Customer) => void) | undefined
    vi.mocked(createCustomer).mockReturnValue(
      new Promise<Customer>((resolve) => {
        resolver = resolve
      }),
    )

    const wrapper = montar(null)
    await preencherValido(wrapper)

    const salvar = botao('Salvar')

    await salvar.trigger('click')
    await flush()
    await salvar.trigger('click')
    await salvar.trigger('click')
    await flush()

    expect(createCustomer).toHaveBeenCalledOnce()

    resolver?.(CLIENTE)
    await flush()
  })
})

describe('alterações não salvas', () => {
  it('fecha sem avisar quando nada foi alterado', async () => {
    const wrapper = montar(null)
    await flush()

    await botao('Cancelar').trigger('click')
    await nextTick()

    expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([false])
  })

  it('pede confirmação ao fechar com alterações pendentes', async () => {
    const wrapper = montar(null)
    await flush()

    await inputs()[0].setValue('Nome parcialmente digitado')
    await nextTick()

    await botao('Cancelar').trigger('click')
    await nextTick()
    await flush()

    expect(textoDaTela()).toContain('Descartar alterações?')
    // Ainda não fechou: o usuário precisa decidir.
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
  })

  it('fecha ao confirmar o descarte', async () => {
    const wrapper = montar(null)
    await flush()

    await inputs()[0].setValue('Nome parcialmente digitado')
    await nextTick()
    await botao('Cancelar').trigger('click')
    await flush()

    await botao('Descartar').trigger('click')
    await nextTick()

    expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([false])
  })
})

describe('sucesso', () => {
  it('emite o cliente salvo e fecha após cadastrar', async () => {
    vi.mocked(createCustomer).mockResolvedValue(CLIENTE)

    const wrapper = montar(null)
    await preencherValido(wrapper)
    await botao('Salvar').trigger('click')
    await flush()

    expect(wrapper.emitted('saved')?.[0]).toEqual([CLIENTE, 'created'])
    expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([false])
  })

  it('normaliza o complemento vazio antes de enviar', async () => {
    vi.mocked(createCustomer).mockResolvedValue(CLIENTE)

    const wrapper = montar(null)
    await preencherValido(wrapper)
    await botao('Salvar').trigger('click')
    await flush()

    expect(vi.mocked(createCustomer).mock.calls[0][0].complement).toBe('')
  })
})
