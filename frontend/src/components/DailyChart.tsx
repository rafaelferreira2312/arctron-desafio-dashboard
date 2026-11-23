import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'
import type { DailyTotal } from '../api/types'
import { formatDay, formatMoney, formatShortDay } from '../lib/format'

const compact = new Intl.NumberFormat('pt-BR', { notation: 'compact', maximumFractionDigits: 1 })

export function DailyChart({ data }: { data: DailyTotal[] }) {
  const points = data.map((day) => ({ ...day, total: Number(day.total_amount) }))

  return (
    <div className="panel chart">
      <ResponsiveContainer width="100%" height="100%">
        <BarChart data={points} margin={{ left: 8, right: 8 }}>
          <CartesianGrid vertical={false} stroke="#e4e9e7" />
          <XAxis
            dataKey="day"
            tickFormatter={formatShortDay}
            tick={{ fill: '#5d6a73', fontSize: 12 }}
            tickLine={false}
            axisLine={{ stroke: '#dce2e0' }}
            minTickGap={16}
          />
          <YAxis
            tickFormatter={(value: number) => `R$ ${compact.format(value)}`}
            tick={{ fill: '#5d6a73', fontSize: 12 }}
            tickLine={false}
            axisLine={false}
            width={72}
          />
          <Tooltip
            cursor={{ fill: 'rgba(23, 50, 77, 0.06)' }}
            labelFormatter={(day: string) => formatDay(day)}
            formatter={(value: number, _name, item) => [
              `${formatMoney(value)} em ${item.payload.purchases_count} compra(s)`,
              'Vendido',
            ]}
          />
          <Bar dataKey="total" fill="#127a5b" radius={[3, 3, 0, 0]} maxBarSize={28} />
        </BarChart>
      </ResponsiveContainer>
    </div>
  )
}
