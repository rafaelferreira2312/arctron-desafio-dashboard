import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { useSearchParams } from 'react-router-dom'
import { fetchPurchases } from '../api/client'
import { FiltersForm } from '../components/FiltersForm'
import { PurchasesTable } from '../components/PurchasesTable'
import { EmptyState, ErrorState, Skeleton } from '../components/States'
import { formatNumber } from '../lib/format'
import { FILTER_FIELDS, countActive, filtersFromParams, paramsFromValues } from '../lib/filters'

export function PurchasesPage() {
  const [params, setParams] = useSearchParams()
  const filters = filtersFromParams(params)
  const page = filters.page ?? 1

  const { data, isPending, isError, error, refetch, isPlaceholderData } = useQuery({
    queryKey: ['purchases', filters],
    queryFn: () => fetchPurchases(filters),
    placeholderData: keepPreviousData,
  })

  function goToPage(next: number) {
    const updated = new URLSearchParams(params)
    if (next > 1) updated.set('page', String(next))
    else updated.delete('page')
    setParams(updated)
  }

  const activeFilters = countActive(filters)

  return (
    <>
      <h1 className="section__title" style={{ fontSize: 24, marginBottom: 18 }}>
        Compras
      </h1>

      <FiltersForm
        // remonta o formulário quando a URL muda por fora (voltar do navegador, limpar)
        key={params.toString()}
        initial={Object.fromEntries(FILTER_FIELDS.map((field) => [field, params.get(field) ?? '']))}
        onSearch={(values) => setParams(paramsFromValues(values))}
      />

      <div className="results__header">
        <span className="muted" aria-live="polite">
          {data &&
            `${formatNumber(data.meta.total)} ${data.meta.total === 1 ? 'compra encontrada' : 'compras encontradas'}${
              activeFilters > 0 ? ` com ${activeFilters} ${activeFilters === 1 ? 'filtro' : 'filtros'}` : ''
            }`}
        </span>
      </div>

      <div className="panel" style={{ opacity: isPlaceholderData ? 0.6 : 1 }}>
        {isPending && (
          <div style={{ padding: 20, display: 'grid', gap: 12 }}>
            {Array.from({ length: 6 }, (_, i) => (
              <Skeleton key={i} height={22} />
            ))}
          </div>
        )}

        {isError && <ErrorState error={error} onRetry={() => refetch()} />}

        {data && data.data.length === 0 && (
          <EmptyState title="Nenhuma compra com esses filtros">
            <p>Tente remover algum filtro ou ampliar o período.</p>
          </EmptyState>
        )}

        {data && data.data.length > 0 && (
          <>
            <PurchasesTable purchases={data.data} />

            {data.meta.total_pages > 1 && (
              <nav className="pagination" aria-label="Paginação">
                <span className="muted">
                  Página {page} de {data.meta.total_pages}
                </span>
                <div>
                  <button
                    type="button"
                    className="button button--ghost"
                    disabled={page <= 1}
                    onClick={() => goToPage(page - 1)}
                  >
                    Anterior
                  </button>
                  <button
                    type="button"
                    className="button button--ghost"
                    disabled={page >= data.meta.total_pages}
                    onClick={() => goToPage(page + 1)}
                  >
                    Próxima
                  </button>
                </div>
              </nav>
            )}
          </>
        )}
      </div>
    </>
  )
}
