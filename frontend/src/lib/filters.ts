import type { PurchaseFilters } from '../api/types'

export const FILTER_FIELDS = [
  'reference',
  'customer_name',
  'customer_email',
  'customer_cpf',
  'date_from',
  'date_to',
  'amount_min',
  'amount_max',
] as const

export type FilterField = (typeof FILTER_FIELDS)[number]
export type FilterValues = Record<FilterField, string>

export const EMPTY_FILTERS: FilterValues = Object.fromEntries(FILTER_FIELDS.map((f) => [f, ''])) as FilterValues

export function filtersFromParams(params: URLSearchParams): PurchaseFilters {
  const filters: PurchaseFilters = {}

  for (const field of FILTER_FIELDS) {
    const value = params.get(field)?.trim()
    if (value) filters[field] = value
  }

  const page = Number(params.get('page'))
  if (Number.isInteger(page) && page > 1) filters.page = page

  return filters
}

/** Só leva pra URL o que foi preenchido. Valor com vírgula ("150,50") vira ponto. */
export function paramsFromValues(values: FilterValues): Record<string, string> {
  const params: Record<string, string> = {}

  for (const field of FILTER_FIELDS) {
    let value = values[field].trim()
    if (!value) continue
    if (field === 'amount_min' || field === 'amount_max') value = value.replace(/\./g, '').replace(',', '.')
    params[field] = value
  }

  return params
}

export function countActive(filters: PurchaseFilters): number {
  return FILTER_FIELDS.filter((field) => filters[field]).length
}
