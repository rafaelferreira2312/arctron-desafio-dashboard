import { useState, type FormEvent } from 'react'
import { EMPTY_FILTERS, type FilterField, type FilterValues } from '../lib/filters'

const FIELDS: { name: FilterField; label: string; type?: string; placeholder?: string; inputMode?: 'decimal' | 'numeric' }[] = [
  { name: 'reference', label: 'Referência', placeholder: 'PED-1001' },
  { name: 'customer_name', label: 'Nome do cliente' },
  { name: 'customer_email', label: 'E-mail do cliente', type: 'search' },
  { name: 'customer_cpf', label: 'CPF', placeholder: '000.000.000-00', inputMode: 'numeric' },
  { name: 'date_from', label: 'Comprado a partir de', type: 'date' },
  { name: 'date_to', label: 'Comprado até', type: 'date' },
  { name: 'amount_min', label: 'Valor mínimo (R$)', placeholder: '0,00', inputMode: 'decimal' },
  { name: 'amount_max', label: 'Valor máximo (R$)', placeholder: '0,00', inputMode: 'decimal' },
]

interface Props {
  initial: Partial<FilterValues>
  onSearch: (values: FilterValues) => void
}

export function FiltersForm({ initial, onSearch }: Props) {
  const [values, setValues] = useState<FilterValues>({ ...EMPTY_FILTERS, ...initial })

  function submit(event: FormEvent) {
    event.preventDefault()
    onSearch(values)
  }

  function clear() {
    setValues(EMPTY_FILTERS)
    onSearch(EMPTY_FILTERS)
  }

  return (
    <form className="panel filters" onSubmit={submit} role="search" aria-label="Filtrar compras">
      {FIELDS.map((field) => (
        <label key={field.name} className="field">
          {field.label}
          <input
            name={field.name}
            type={field.type ?? 'text'}
            inputMode={field.inputMode}
            placeholder={field.placeholder}
            value={values[field.name]}
            onChange={(event) => setValues((current) => ({ ...current, [field.name]: event.target.value }))}
          />
        </label>
      ))}

      <div className="filters__actions">
        <button type="button" className="button button--ghost" onClick={clear}>
          Limpar filtros
        </button>
        <button type="submit" className="button">
          Buscar compras
        </button>
      </div>
    </form>
  )
}
