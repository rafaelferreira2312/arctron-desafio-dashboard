import { describe, expect, it } from 'vitest'
import { toQueryString } from '../api/client'
import { countActive, EMPTY_FILTERS, filtersFromParams, paramsFromValues } from '../lib/filters'
import { formatDay, formatMoney } from '../lib/format'
import { periodFromPreset } from '../lib/period'

describe('formatação', () => {
  it('formata dinheiro em reais a partir da string decimal da API', () => {
    expect(formatMoney('1234.5').replace(/\s/g, ' ')).toBe('R$ 1.234,50')
  })

  it('formata dia sem deslocar pelo fuso', () => {
    expect(formatDay('2026-11-01')).toBe('01/11/2026')
  })
})

describe('query string', () => {
  it('ignora valores vazios', () => {
    expect(toQueryString({ reference: 'PED', customer_name: '  ', page: undefined, per_page: 20 })).toBe(
      '?reference=PED&per_page=20',
    )
    expect(toQueryString({})).toBe('')
  })
})

describe('filtros', () => {
  it('converte o formulário em parâmetros da URL', () => {
    const params = paramsFromValues({
      ...EMPTY_FILTERS,
      customer_name: ' Ana ',
      amount_min: '1.250,50',
      amount_max: '300',
    })

    expect(params).toEqual({ customer_name: 'Ana', amount_min: '1250.50', amount_max: '300' })
  })

  it('lê filtros e página da URL', () => {
    const filters = filtersFromParams(new URLSearchParams('customer_cpf=529&page=3&outro=x'))

    expect(filters).toEqual({ customer_cpf: '529', page: 3 })
    expect(countActive(filters)).toBe(1)
  })
})

describe('período', () => {
  it('calcula os últimos 7 dias incluindo hoje', () => {
    expect(periodFromPreset('7d', new Date(2026, 10, 23))).toEqual({ date_from: '2026-11-17', date_to: '2026-11-23' })
  })

  it('"tudo" não tem limite', () => {
    expect(periodFromPreset('all')).toEqual({})
  })
})
