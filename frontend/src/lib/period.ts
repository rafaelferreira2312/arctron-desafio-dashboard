export type PeriodPreset = '7d' | '30d' | '90d' | 'all' | 'custom'

export interface Period {
  date_from?: string
  date_to?: string
}

export const PRESETS: { value: Exclude<PeriodPreset, 'custom'>; label: string }[] = [
  { value: '7d', label: '7 dias' },
  { value: '30d', label: '30 dias' },
  { value: '90d', label: '90 dias' },
  { value: 'all', label: 'Tudo' },
]

/** Data local no formato AAAA-MM-DD (toISOString usaria UTC e poderia virar o dia) */
export function isoDay(date: Date): string {
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

export function periodFromPreset(preset: PeriodPreset, today = new Date()): Period {
  const days = { '7d': 7, '30d': 30, '90d': 90 }[preset as '7d' | '30d' | '90d']

  if (!days) return {}

  const from = new Date(today)
  from.setDate(from.getDate() - (days - 1))

  return { date_from: isoDay(from), date_to: isoDay(today) }
}
