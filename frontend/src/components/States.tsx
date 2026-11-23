import { ApiError } from '../api/client'

export function ErrorState({ error, onRetry }: { error: unknown; onRetry?: () => void }) {
  const message =
    error instanceof ApiError
      ? error.message
      : 'Não foi possível falar com a API. Confira se o serviço está rodando em http://localhost:8000.'

  return (
    <div className="state state--error" role="alert">
      <strong>Os dados não carregaram</strong>
      <p>{message}</p>
      {onRetry && (
        <button type="button" className="button button--ghost" onClick={onRetry}>
          Tentar de novo
        </button>
      )}
    </div>
  )
}

export function EmptyState({ title, children }: { title: string; children?: React.ReactNode }) {
  return (
    <div className="state">
      <strong>{title}</strong>
      {children}
    </div>
  )
}

export function Skeleton({ height, width = '100%' }: { height: number; width?: number | string }) {
  return <div className="skeleton" style={{ height, width }} aria-hidden="true" />
}
