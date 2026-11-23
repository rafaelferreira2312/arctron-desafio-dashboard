import type { CustomerRanking, ProductRanking } from '../api/types'
import { formatMoney, formatNumber } from '../lib/format'
import { EmptyState } from './States'

function Share({ value, max }: { value: string; max: number }) {
  const percent = max > 0 ? (Number(value) / max) * 100 : 0
  return (
    <span className="share" aria-hidden="true">
      <span style={{ width: `${percent}%` }} />
    </span>
  )
}

export function CustomersRanking({ rows }: { rows: CustomerRanking[] }) {
  if (rows.length === 0) return <EmptyState title="Nenhum cliente comprou nesse período" />

  const max = Number(rows[0].total_amount)

  return (
    <table className="table">
      <thead>
        <tr>
          <th scope="col">Cliente</th>
          <th scope="col" className="num">Compras</th>
          <th scope="col" className="num">Total</th>
          <th scope="col" className="num">Média</th>
        </tr>
      </thead>
      <tbody>
        {rows.map((row) => (
          <tr key={row.cpf}>
            <td>
              {row.name}
              <span className="sub">{row.email}</span>
              <Share value={row.total_amount} max={max} />
            </td>
            <td className="num">{formatNumber(row.purchases_count)}</td>
            <td className="num">{formatMoney(row.total_amount)}</td>
            <td className="num">{formatMoney(row.average_amount)}</td>
          </tr>
        ))}
      </tbody>
    </table>
  )
}

export function ProductsRanking({ rows }: { rows: ProductRanking[] }) {
  if (rows.length === 0) return <EmptyState title="Nenhum produto vendido nesse período" />

  const max = Number(rows[0].total_amount)

  return (
    <table className="table">
      <thead>
        <tr>
          <th scope="col">Produto</th>
          <th scope="col" className="num">Unidades</th>
          <th scope="col" className="num">Total</th>
          <th scope="col" className="num" title="Valor médio do produto em cada compra em que aparece">
            Média
          </th>
        </tr>
      </thead>
      <tbody>
        {rows.map((row) => (
          <tr key={row.name}>
            <td>
              {row.name}
              <span className="sub">em {formatNumber(row.purchases_count)} compra(s)</span>
              <Share value={row.total_amount} max={max} />
            </td>
            <td className="num">{formatNumber(row.quantity)}</td>
            <td className="num">{formatMoney(row.total_amount)}</td>
            <td className="num">{formatMoney(row.average_amount)}</td>
          </tr>
        ))}
      </tbody>
    </table>
  )
}
