// Valores monetários chegam como string decimal ("150.90") pra não perder precisão no JSON

export type PurchaseSource = 'api' | 'woocommerce' | 'seed'

export interface PurchaseProduct {
  name: string
  quantity: number
  unit_price: string
  total: string
}

export interface Purchase {
  reference: string
  amount: string
  discount: string
  products_total: string
  purchased_at: string
  notes: string | null
  source: PurchaseSource
  customer: { name: string; email: string; cpf: string }
  products: PurchaseProduct[]
}

export interface PurchaseList {
  data: Purchase[]
  meta: { page: number; per_page: number; total: number; total_pages: number }
}

export interface Summary {
  purchases_count: number
  customers_count: number
  items_quantity: number
  total_amount: string
  average_amount: string
  total_discount: string
  average_discount: string
  total_products: string
  average_products: string
}

export interface CustomerRanking {
  name: string
  email: string
  cpf: string
  purchases_count: number
  total_amount: string
  average_amount: string
}

export interface ProductRanking {
  name: string
  purchases_count: number
  quantity: number
  total_amount: string
  average_amount: string
  average_unit_price: string
}

export interface DailyTotal {
  day: string
  purchases_count: number
  total_amount: string
}

export interface Statistics {
  period: { date_from: string | null; date_to: string | null }
  summary: Summary
  by_customer: CustomerRanking[]
  by_product: ProductRanking[]
  daily: DailyTotal[]
}

export interface PurchaseFilters {
  reference?: string
  customer_name?: string
  customer_email?: string
  customer_cpf?: string
  date_from?: string
  date_to?: string
  amount_min?: string
  amount_max?: string
  page?: number
  per_page?: number
}
