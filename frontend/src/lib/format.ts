const currency = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' })
const integer = new Intl.NumberFormat('pt-BR')

export function formatMoney(value: string | number): string {
  return currency.format(Number(value))
}

export function formatNumber(value: number): string {
  return integer.format(value)
}

/** "2026-11-20T14:30:00-03:00" -> "20/11/2026 14:30" no fuso de São Paulo */
export function formatDateTime(iso: string): string {
  return new Intl.DateTimeFormat('pt-BR', {
    dateStyle: 'short',
    timeStyle: 'short',
    timeZone: 'America/Sao_Paulo',
  }).format(new Date(iso))
}

/** "2026-11-20" -> "20/11/2026" sem passar por Date (evita deslocar o dia pelo fuso) */
export function formatDay(day: string): string {
  const [year, month, date] = day.split('-')
  return `${date}/${month}/${year}`
}

export function formatShortDay(day: string): string {
  const [, month, date] = day.split('-')
  return `${date}/${month}`
}

const SOURCE_LABELS: Record<string, string> = {
  api: 'API',
  woocommerce: 'WooCommerce',
  seed: 'Exemplo',
}

export function sourceLabel(source: string): string {
  return SOURCE_LABELS[source] ?? source
}
