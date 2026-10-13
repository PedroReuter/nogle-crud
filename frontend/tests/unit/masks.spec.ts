import { describe, expect, it } from 'vitest'

import { maskCpf, maskPhone, maskPostalCode, onlyDigits } from '@/support/masks'

describe('onlyDigits', () => {
  it.each([
    ['529.982.247-25', '52998224725'],
    ['(11) 98888-7777', '11988887777'],
    ['01001-000', '01001000'],
    ['  11 3333-4444  ', '1133334444'],
  ])('remove a pontuação de %s', (entrada, esperado) => {
    expect(onlyDigits(entrada)).toBe(esperado)
  })

  it.each([null, undefined, '', '---', '(  ) -'])('devolve string vazia para %s', (entrada) => {
    expect(onlyDigits(entrada)).toBe('')
  })

  it('preserva zeros à esquerda', () => {
    expect(onlyDigits('01001-000')).toBe('01001000')
  })
})

describe('maskCpf', () => {
  it('aplica a máscara completa', () => {
    expect(maskCpf('52998224725')).toBe('529.982.247-25')
  })

  it.each([
    ['5', '5'],
    ['529', '529'],
    ['5299', '529.9'],
    ['529982', '529.982'],
    ['5299822', '529.982.2'],
    ['529982247', '529.982.247'],
    ['5299822472', '529.982.247-2'],
    ['52998224725', '529.982.247-25'],
  ])('mascara progressivamente: %s -> %s', (entrada, esperado) => {
    // A máscara é aplicada a cada tecla, então precisa produzir resultado
    // coerente para qualquer quantidade parcial de dígitos.
    expect(maskCpf(entrada)).toBe(esperado)
  })

  it('descarta dígitos além do décimo primeiro', () => {
    expect(maskCpf('529982247259999')).toBe('529.982.247-25')
  })

  it('é idempotente sobre um valor já mascarado', () => {
    // Importante porque o valor mascarado volta pelo v-model e é remascarado.
    expect(maskCpf('529.982.247-25')).toBe('529.982.247-25')
  })

  it('preserva zero à esquerda', () => {
    expect(maskCpf('01295310902')).toBe('012.953.109-02')
  })
})

describe('maskPostalCode', () => {
  it('aplica a máscara 00000-000', () => {
    expect(maskPostalCode('01001000')).toBe('01001-000')
  })

  it.each([
    ['0', '0'],
    ['01001', '01001'],
    ['010010', '01001-0'],
    ['01001000', '01001-000'],
  ])('mascara progressivamente: %s -> %s', (entrada, esperado) => {
    expect(maskPostalCode(entrada)).toBe(esperado)
  })

  it('é idempotente', () => {
    expect(maskPostalCode('01001-000')).toBe('01001-000')
  })
})

describe('maskPhone', () => {
  it('usa o formato de celular com 11 dígitos', () => {
    expect(maskPhone('11988887777')).toBe('(11) 98888-7777')
  })

  it('usa o formato de fixo com 10 dígitos', () => {
    expect(maskPhone('1133334444')).toBe('(11) 3333-4444')
  })

  it('reposiciona o hífen quando o décimo primeiro dígito é digitado', () => {
    // É o comportamento que distingue fixo de celular durante a digitação.
    expect(maskPhone('1133334444')).toBe('(11) 3333-4444')
    expect(maskPhone('11933334444')).toBe('(11) 93333-4444')
  })

  it.each([
    ['1', '1'],
    ['11', '11'],
    ['119', '(11) 9'],
    ['119888', '(11) 9888'],
    ['1198888', '(11) 9888-8'],
  ])('mascara progressivamente: %s -> %s', (entrada, esperado) => {
    expect(maskPhone(entrada)).toBe(esperado)
  })

  it('é idempotente', () => {
    expect(maskPhone('(11) 98888-7777')).toBe('(11) 98888-7777')
  })
})
