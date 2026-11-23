import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { App } from '../App'
import type { PurchaseList, Statistics } from '../api/types'

// recharts usa ResizeObserver, que não existe no jsdom
globalThis.ResizeObserver = class {
  observe() {}
  unobserve() {}
  disconnect() {}
}

const purchases: PurchaseList = {
  data: [
    {
      reference: 'WC-4821',
      amount: '394.80',
      discount: '30.00',
      products_total: '399.80',
      purchased_at: '2026-11-20T14:30:00-03:00',
      notes: 'Envio: SEDEX',
      source: 'woocommerce',
      customer: { name: 'Marina Albuquerque', email: 'marina@example.com', cpf: '390.533.447-05' },
      products: [{ name: 'Teclado Mecânico ABNT2', quantity: 1, unit_price: '249.90', total: '249.90' }],
    },
  ],
  meta: { page: 1, per_page: 20, total: 1, total_pages: 1 },
}

const statistics: Statistics = {
  period: { date_from: null, date_to: null },
  summary: {
    purchases_count: 3,
    customers_count: 2,
    items_quantity: 5,
    total_amount: '450.00',
    average_amount: '150.00',
    total_discount: '30.00',
    average_discount: '10.00',
    total_products: '480.00',
    average_products: '160.00',
  },
  by_customer: [
    { name: 'Ana Silva', email: 'ana@example.com', cpf: '529.982.247-25', purchases_count: 2, total_amount: '400.00', average_amount: '200.00' },
  ],
  by_product: [
    { name: 'Teclado', purchases_count: 2, quantity: 3, total_amount: '360.00', average_amount: '180.00', average_unit_price: '100.00' },
  ],
  daily: [{ day: '2026-11-10', purchases_count: 1, total_amount: '300.00' }],
}

function renderAt(path: string) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={[path]}>
        <App />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

const fetchMock = vi.fn()

beforeEach(() => {
  fetchMock.mockImplementation(async (url: string) => {
    const body = url.startsWith('/api/statistics') ? statistics : purchases
    return new Response(JSON.stringify(body), { status: 200 })
  })
  vi.stubGlobal('fetch', fetchMock)
})

afterEach(() => {
  vi.unstubAllGlobals()
  fetchMock.mockReset()
})

describe('visão geral', () => {
  it('mostra o total vendido e os rankings', async () => {
    renderAt('/?periodo=all')

    const headline = await screen.findByRole('heading', { level: 1 })
    expect(headline).toHaveTextContent('450,00')
    expect(headline).toHaveTextContent('3 compras de 2 clientes')
    expect(screen.getByText('Ana Silva')).toBeInTheDocument()
    expect(screen.getByText('Teclado')).toBeInTheDocument()
    expect(fetchMock).toHaveBeenCalledWith('/api/statistics', expect.anything())
  })

  it('explica o erro quando a API falha', async () => {
    fetchMock.mockResolvedValueOnce(
      new Response(JSON.stringify({ error: 'invalid_filter', message: 'date_from inválido' }), { status: 400 }),
    )

    renderAt('/?date_from=2026-11-01')

    expect(await screen.findByRole('alert')).toHaveTextContent('date_from inválido')
  })
})

describe('compras', () => {
  it('busca com os filtros e mostra os detalhes da compra', async () => {
    const user = userEvent.setup()
    renderAt('/compras')

    await screen.findByText('Marina Albuquerque')

    await user.type(screen.getByLabelText('Nome do cliente'), 'marina')
    await user.type(screen.getByLabelText('Valor mínimo (R$)'), '100,50')
    await user.click(screen.getByRole('button', { name: 'Buscar compras' }))

    expect(fetchMock).toHaveBeenLastCalledWith('/api/purchases?customer_name=marina&amount_min=100.50', expect.anything())

    await user.click(screen.getByRole('button', { name: 'WC-4821' }))
    const details = screen.getByText('Teclado Mecânico ABNT2').closest('tr')!.parentElement!
    expect(within(details).getByText('Teclado Mecânico ABNT2')).toBeInTheDocument()
    expect(screen.getByText('Envio: SEDEX')).toBeInTheDocument()
  })
})
