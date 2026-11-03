import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { rangePresets } from '@/lib/period'
import { DatePicker } from './date-picker'
import { SelectField } from './select-field'

type RangePickerProps = { start: string; end: string; onChange: (range: { start: string; end: string }) => void }

export function RangePicker({ start, end, onChange }: RangePickerProps) {
  const { t } = useTranslation()
  const [today] = useState(() => new Date())
  const presets = rangePresets(today)
  const current = Object.entries(presets).find(([, range]) => range.start === start && range.end === end)?.[0] ?? 'custom'
  const labels: Record<string, string> = {
    this_month: t('This month'),
    last_month: t('Last month'),
    last_3_months: t('Last 3 months'),
    this_year: t('This year'),
    last_year: t('Last year'),
    last_12_months: t('Last 12 months'),
    custom: t('Custom range'),
  }

  return (
    <div className="flex flex-wrap items-center gap-2">
      <SelectField
        className="w-44"
        value={current}
        options={labels}
        onChange={(value) => value !== 'custom' && onChange(presets[value as keyof typeof presets])}
      />
      {current === 'custom' && (
        <>
          <DatePicker className="w-40" value={start} onChange={(value) => value && onChange({ start: value, end })} />
          <DatePicker className="w-40" value={end} onChange={(value) => value && onChange({ start, end: value })} />
        </>
      )}
    </div>
  )
}
