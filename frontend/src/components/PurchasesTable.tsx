import { Fragment, useState } from 'react'
import type { Purchase } from '../api/types'
import { formatDateTime, formatMoney, sourceLabel } from '../lib/format'

function Details({ purchase }: { purchase: Purchase }) {
  return (
    <div className="details__inner">
      <table className="table">
        <thead>
          <tr>
            <th scope="col">Produto</th>
            <th scope="col" className="num">Qtd.</th>
            <th scope="col" className="num">Unitário</th>
            <th scope="col" className="num">Subtotal</th>
          </tr>
        </thead>
        <tbody>
          {purchase.products.map((product, index) => (
            <tr key={`${product.name}-${index}`}>
              <td>{product.name}</td>
              <td className="num">{product.quantity}</td>
              <td className="num">{formatMoney(product.unit_price)}</td>
              <td className="num">{formatMoney(product.total)}</td>
            </tr>
          ))}
        </tbody>
      </table>

      <dl>
        <dt>Produtos</dt>
        <dd>{formatMoney(purchase.products_total)}</dd>
        <dt>Desconto</dt>
        <dd>{formatMoney(purchase.discount)}</dd>
        <dt>Valor pago</dt>
        <dd>
          <strong>{formatMoney(purchase.amount)}</strong>
        </dd>
        <dt>CPF</dt>
        <dd>{purchase.customer.cpf}</dd>
        <dt>E-mail</dt>
        <dd>{purchase.customer.email}</dd>
        <dt>Observações</dt>
        <dd>{purchase.notes ?? <span className="muted">Nenhuma</span>}</dd>
      </dl>
    </div>
  )
}

export function PurchasesTable({ purchases }: { purchases: Purchase[] }) {
  const [open, setOpen] = useState<string | null>(null)

  return (
    <div className="table-wrap">
      <table className="table">
        <thead>
          <tr>
            <th scope="col">Referência</th>
            <th scope="col">Data</th>
            <th scope="col">Cliente</th>
            <th scope="col">Origem</th>
            <th scope="col" className="num">Desconto</th>
            <th scope="col" className="num">Valor</th>
          </tr>
        </thead>
        <tbody>
          {purchases.map((purchase) => {
            const expanded = open === purchase.reference
            const detailsId = `details-${purchase.reference}`

            return (
              <Fragment key={purchase.reference}>
                <tr>
                  <td>
                    <button
                      type="button"
                      className="row-toggle"
                      aria-expanded={expanded}
                      aria-controls={detailsId}
                      onClick={() => setOpen(expanded ? null : purchase.reference)}
                    >
                      {purchase.reference}
                    </button>
                  </td>
                  <td>{formatDateTime(purchase.purchased_at)}</td>
                  <td>
                    {purchase.customer.name}
                    <span className="sub">{purchase.customer.cpf}</span>
                  </td>
                  <td>
                    <span className={`tag tag--${purchase.source}`}>{sourceLabel(purchase.source)}</span>
                  </td>
                  <td className="num">{Number(purchase.discount) > 0 ? formatMoney(purchase.discount) : '—'}</td>
                  <td className="num">
                    <strong>{formatMoney(purchase.amount)}</strong>
                  </td>
                </tr>
                {expanded && (
                  <tr className="details" id={detailsId}>
                    <td colSpan={6}>
                      <Details purchase={purchase} />
                    </td>
                  </tr>
                )}
              </Fragment>
            )
          })}
        </tbody>
      </table>
    </div>
  )
}
