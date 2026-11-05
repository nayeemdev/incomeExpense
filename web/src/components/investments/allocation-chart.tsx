import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { CategoryDonut } from '@/components/charts/category-donut'
import { Money } from '@/components/app/money'
import type { PortfolioSummary } from '@/lib/api/types'
import { investmentKindLabels } from '@/lib/labels'

type AllocationChartProps = { allocation: PortfolioSummary['allocation']; currency: string }

export function AllocationChart({ allocation, currency }: AllocationChartProps) {
  const { t } = useTranslation()
  const [selected, setSelected] = useState<string | null>(null)
  const kinds = investmentKindLabels()
  const slices = allocation
    .filter((item) => Number(item.value) > 0)
    .map((item, index) => ({ key: item.kind, name: kinds[item.kind], value: Number(item.value), color: `var(--chart-${index + 1})` }))
  const total = slices.reduce((sum, slice) => sum + slice.value, 0)

  return (
    <div className="space-y-4">
      <CategoryDonut slices={slices} currency={currency} selected={selected} onSelect={setSelected} />
      <ul className="space-y-1.5 text-sm" aria-label={t('Allocation')}>
        {slices.map((slice) => (
          <li key={slice.key} className="flex items-center gap-2">
            <span className="size-2.5 shrink-0 rounded-full" style={{ background: slice.color }} aria-hidden />
            <span className="min-w-0 flex-1 truncate">{slice.name}</span>
            <span className="text-muted-foreground tabular-nums">{Math.round((slice.value / total) * 100)}%</span>
            <Money amount={String(slice.value)} currency={currency} tone="none" className="w-28 text-right" />
          </li>
        ))}
      </ul>
    </div>
  )
}
