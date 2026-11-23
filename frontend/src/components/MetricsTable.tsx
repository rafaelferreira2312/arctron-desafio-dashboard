import type { Summary } from '../api/types'
import { formatMoney } from '../lib/format'

export function MetricsTable({ summary }: { summary: Summary }) {
  return (
    <div className="panel table-wrap">
      <table className="metrics">
        <thead>
          <tr>
            <th scope="col" style={{ textAlign: 'left' }}>
              Indicador
            </th>
            <th scope="col">Total</th>
            <th scope="col">Média por compra</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <th scope="row">Valor das compras</th>
            <td>{formatMoney(summary.total_amount)}</td>
            <td>{formatMoney(summary.average_amount)}</td>
          </tr>
          <tr>
            <th scope="row">Valor dos produtos</th>
            <td>{formatMoney(summary.total_products)}</td>
            <td>{formatMoney(summary.average_products)}</td>
          </tr>
          <tr>
            <th scope="row">Descontos</th>
            <td className="is-discount">{formatMoney(summary.total_discount)}</td>
            <td className="is-discount">{formatMoney(summary.average_discount)}</td>
          </tr>
        </tbody>
      </table>
    </div>
  )
}
