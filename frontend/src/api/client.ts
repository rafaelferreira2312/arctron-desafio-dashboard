import type { PurchaseFilters, PurchaseList, Statistics } from './types'

export class ApiError extends Error {
  constructor(
    message: string,
    public readonly status: number,
  ) {
    super(message)
  }
}

export function toQueryString(params: Record<string, string | number | undefined | null>): string {
  const search = new URLSearchParams()

  for (const [key, value] of Object.entries(params)) {
    if (value === undefined || value === null || String(value).trim() === '') continue
    search.set(key, String(value).trim())
  }

  const query = search.toString()
  return query ? `?${query}` : ''
}

async function get<T>(path: string): Promise<T> {
  const response = await fetch(path, { headers: { Accept: 'application/json' } })
  const body = await response.json().catch(() => null)

  if (!response.ok) {
    throw new ApiError(body?.message ?? `A API respondeu com erro ${response.status}.`, response.status)
  }

  return body as T
}

export function fetchPurchases(filters: PurchaseFilters): Promise<PurchaseList> {
  return get(`/api/purchases${toQueryString({ ...filters })}`)
}

export function fetchStatistics(period: { date_from?: string; date_to?: string }): Promise<Statistics> {
  return get(`/api/statistics${toQueryString(period)}`)
}
