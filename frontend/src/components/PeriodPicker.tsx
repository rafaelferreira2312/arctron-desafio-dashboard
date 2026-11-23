import { PRESETS, type PeriodPreset } from '../lib/period'

interface Props {
  preset: PeriodPreset
  dateFrom?: string
  dateTo?: string
  onPreset: (preset: PeriodPreset) => void
  onCustom: (dateFrom: string, dateTo: string) => void
}

export function PeriodPicker({ preset, dateFrom = '', dateTo = '', onPreset, onCustom }: Props) {
  return (
    <div className="period">
      <div className="segmented" role="group" aria-label="Período">
        {PRESETS.map((option) => (
          <button
            key={option.value}
            type="button"
            aria-pressed={preset === option.value}
            onClick={() => onPreset(option.value)}
          >
            {option.label}
          </button>
        ))}
      </div>

      <div className="period__custom">
        <input
          type="date"
          aria-label="Data inicial"
          value={dateFrom}
          max={dateTo || undefined}
          onChange={(event) => onCustom(event.target.value, dateTo)}
        />
        até
        <input
          type="date"
          aria-label="Data final"
          value={dateTo}
          min={dateFrom || undefined}
          onChange={(event) => onCustom(dateFrom, event.target.value)}
        />
      </div>
    </div>
  )
}
