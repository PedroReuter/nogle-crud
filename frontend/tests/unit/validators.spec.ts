import { describe, expect, it } from 'vitest'

import {
  cpfRule,
  isValidCpf,
  isValidPhone,
  isValidPostalCode,
  maxLength,
  minLength,
  phoneRule,
  postalCodeRule,
  required,
} from '@/support/validators'

describe('isValidCpf', () => {
  // Os mesmos casos do teste de backend (`tests/Unit/CpfTest.php`). Rodar os
  // dois conjuntos sobre a mesma tabela é o que mantém as duas
  // implementações consistentes — a do PHP vale, a do TypeScript só adianta
  // o retorno ao usuário.
  it.each([
    '529.982.247-25',
    '52998224725',
    '111.444.777-35',
    '11144477735',
    '012.953.109-02',
    '01295310902',
  ])('aceita o CPF válido %s', (cpf) => {
    expect(isValidCpf(cpf)).toBe(true)
  })

  it('rejeita dígito verificador incorreto', () => {
    expect(isValidCpf('529.982.247-26')).toBe(false)
  })

  it.each(['111.111.111-11', '000.000.000-00', '222.222.222-22', '999.999.999-99'])(
    'rejeita a sequência repetida %s',
    (cpf) => {
      expect(isValidCpf(cpf)).toBe(false)
    },
  )

  it.each(['1234567890', '123456789012', '1', ''])('rejeita o tamanho inválido %s', (cpf) => {
    expect(isValidCpf(cpf)).toBe(false)
  })

  it.each([null, undefined, 'abcdefghijk', '   '])('rejeita a entrada %s', (cpf) => {
    expect(isValidCpf(cpf)).toBe(false)
  })
})

describe('isValidPhone', () => {
  it.each(['(11) 98888-7777', '11988887777', '(11) 3333-4444', '1133334444'])(
    'aceita %s',
    (phone) => {
      expect(isValidPhone(phone)).toBe(true)
    },
  )

  it.each(['119', '119888', '119888877779', '', null])('rejeita %s', (phone) => {
    expect(isValidPhone(phone)).toBe(false)
  })
})

describe('isValidPostalCode', () => {
  it.each(['01001-000', '01001000'])('aceita %s', (cep) => {
    expect(isValidPostalCode(cep)).toBe(true)
  })

  it.each(['0100100', '010010000', '', null])('rejeita %s', (cep) => {
    expect(isValidPostalCode(cep)).toBe(false)
  })
})

describe('required', () => {
  it('aceita texto preenchido', () => {
    expect(required()('Joana')).toBe(true)
  })

  it.each(['', '   ', null, undefined])('rejeita %s', (valor) => {
    // Espaços são rejeitados porque preenchem o campo visualmente sem
    // constituir um valor — e o backend também os rejeita.
    expect(required()(valor)).toBe('Campo obrigatório')
  })

  it('usa a mensagem informada, que nomeia o campo', () => {
    expect(required('Informe o nome do cliente')).toBeDefined()
    expect(required('Informe o nome do cliente')('')).toBe('Informe o nome do cliente')
  })
})

describe('maxLength e minLength', () => {
  it('aceita dentro do limite', () => {
    expect(maxLength(5)('abcde')).toBe(true)
    expect(minLength(3)('abc')).toBe(true)
  })

  it('rejeita fora do limite', () => {
    expect(maxLength(5)('abcdef')).toBe('Máximo de 5 caracteres')
    expect(minLength(3)('ab')).toBe('Mínimo de 3 caracteres')
  })

  it('deixa o campo vazio para a regra de obrigatoriedade', () => {
    // Sem isto, um campo vazio mostraria dois erros ao mesmo tempo:
    // "Campo obrigatório" e "Mínimo de 3 caracteres".
    expect(minLength(3)('')).toBe(true)
    expect(minLength(3)('   ')).toBe(true)
  })
})

describe('regras de formato', () => {
  it('aceitam valor válido', () => {
    expect(cpfRule()('529.982.247-25')).toBe(true)
    expect(phoneRule()('(11) 98888-7777')).toBe(true)
    expect(postalCodeRule()('01001-000')).toBe(true)
  })

  it('devolvem a mensagem para valor inválido', () => {
    expect(cpfRule()('111.111.111-11')).toBe('CPF inválido')
    expect(phoneRule()('119')).toBe('Telefone inválido. Informe DDD e número')
    expect(postalCodeRule()('0100')).toBe('CEP deve ter 8 dígitos')
  })

  it('não acusam formato em campo vazio', () => {
    // Campo vazio é problema de obrigatoriedade, não de formato; acusar os
    // dois faria o formulário mostrar dois erros pelo mesmo motivo.
    expect(cpfRule()('')).toBe(true)
    expect(phoneRule()('')).toBe(true)
    expect(postalCodeRule()('')).toBe(true)
  })
})
