import { useQuery } from '@tanstack/react-query'
import { useSearchParams } from 'react-router-dom'
import { fetchStatistics } from '../api/client'
import type { Summary } from '../api/types'
import { DailyChart } from '../components/DailyChart'
import { MetricsTable } from '../components/MetricsTable'
import { PeriodPicker } from '../components/PeriodPicker'
import { CustomersRanking, ProductsRanking } from '../components/Rankings'
import { EmptyState, ErrorState, Skeleton } from '../components/States'
import { formatDay, formatMoney, formatNumber } from '../lib/format'
import { PRESETS, periodFromPreset, type Period, type PeriodPreset } from '../lib/period'

const DEFAULT_PRESET: PeriodPreset = '30d'

function usePeriod() {
  const [params, setParams] = useSearchParams()
  const custom = params.has('date_from') || params.has('date_to')
  const preset = (custom ? 'custom' : (params.get('periodo') ?? DEFAULT_PRESET)) as PeriodPreset

  const period: Period = custom
    ? { date_from: params.get('date_from') ?? undefined, date_to: params.get('date_to') ?? undefined }
    : periodFromPreset(preset)

  return {
    preset,
    period,
    choosePreset: (value: PeriodPreset) => setParams(value === DEFAULT_PRESET ? {} : { periodo: value }),
    chooseCustom: (dateFrom: string, dateTo: string) => {
      const next: Record<string, string> = {}
      if (dateFrom) next.date_from = dateFrom
      if (dateTo) next.date_to = dateTo
      setParams(next)
    },
  }
}

function describePeriod(preset: PeriodPreset, period: Period): string {
  if (preset === 'all') return 'desde a primeira compra registrada'
  if (preset !== 'custom') return `nos últimos ${PRESETS.find((p) => p.value === preset)?.label}`
  if (period.date_from && period.date_to) return `de ${formatDay(period.date_from)} a ${formatDay(period.date_to)}`
  if (period.date_from) return `a partir de ${formatDay(period.date_from)}`
  if (period.date_to) return `até ${formatDay(period.date_to)}`
  return 'em todo o período'
}

function Headline({ summary, description }: { summary: Summary; description: string }) {
  return (
    <h1 className="headline">
      <span className="headline__amount">{formatMoney(summary.total_amount)}</span>
      <span className="headline__text">
        em {formatNumber(summary.purchases_count)} {summary.purchases_count === 1 ? 'compra' : 'compras'} de{' '}
        {formatNumber(summary.customers_count)} {summary.customers_count === 1 ? 'cliente' : 'clientes'},{' '}
        {formatNumber(summary.items_quantity)} itens vendidos {description}
      </span>
    </h1>
  )
}

export function DashboardPage() {
  const { preset, period, choosePreset, chooseCustom } = usePeriod()

  const { data, isPending, isError, error, refetch } = useQuery({
    queryKey: ['statistics', period],
    queryFn: () => fetchStatistics(period),
  })

  return (
    <>
      <PeriodPicker
        preset={preset}
        dateFrom={period.date_from}
        dateTo={period.date_to}
        onPreset={choosePreset}
        onCustom={chooseCustom}
      />

      {isPending && (
        <div className="headline">
          <Skeleton height={64} width="45%" />
          <div style={{ height: 12 }} />
          <Skeleton height={24} width="70%" />
        </div>
      )}

      {isError && (
        <div className="section panel">
          <ErrorState error={error} onRetry={() => refetch()} />
        </div>
      )}

      {data && data.summary.purchases_count === 0 && (
        <div className="section panel">
          <EmptyState title="Nenhuma compra nesse período">
            <p>Escolha outro período ou gere dados de exemplo com <code>make seed</code>.</p>
          </EmptyState>
        </div>
      )}

      {data && data.summary.purchases_count > 0 && (
        <>
          <Headline summary={data.summary} description={describePeriod(preset, period)} />

          <section className="section" aria-labelledby="metrics-title">
            <h2 id="metrics-title" className="section__title">
              Totais e médias
            </h2>
            <MetricsTable summary={data.summary} />
          </section>

          <section className="section" aria-labelledby="daily-title">
            <h2 id="daily-title" className="section__title">
              Vendas por dia
            </h2>
            <DailyChart data={data.daily} />
          </section>

          <div className="section grid-2">
            <section aria-labelledby="customers-title">
              <h2 id="customers-title" className="section__title">
                Clientes que mais compraram
              </h2>
              <div className="panel table-wrap">
                <CustomersRanking rows={data.by_customer} />
              </div>
            </section>

            <section aria-labelledby="products-title">
              <h2 id="products-title" className="section__title">
                Produtos mais vendidos
              </h2>
              <div className="panel table-wrap">
                <ProductsRanking rows={data.by_product} />
              </div>
            </section>
          </div>
        </>
      )}
    </>
  )
}
