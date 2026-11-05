import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { CategoryDonut } from '@/components/charts/category-donut'
import type { PortfolioSummary } from '@/lib/api/types'
import { investmentKindLabels } from '@/lib/labels'
import { cn } from '@/lib/utils'

const OFF_TARGET_POINTS = 5

type AllocationChartProps = { allocation: PortfolioSummary['allocation']; targets: PortfolioSummary['targets']; currency: string }

/**
 * The mix by kind, and next to each share the target when one is set, highlighted when it is more than a few points off.
 */
export function AllocationChart({ allocation, targets, currency }: AllocationChartProps) {
  const { t } = useTranslation()
  const [selected, setSelected] = useState<string | null>(null)
  const kinds = investmentKindLabels()
  const slices = allocation
    .filter((item) => Number(item.value) > 0)
    .map((item, index) => ({ key: item.kind, name: kinds[item.kind], value: Number(item.value), color: `var(--chart-${index + 1})` }))
  const total = slices.reduce((sum, slice) => sum + slice.value, 0)
  const targetOf = Object.fromEntries(targets.map((target) => [target.kind, target.percent]))
  const rows = [
    ...slices,
    ...targets
      .filter((target) => !slices.some((slice) => slice.key === target.kind))
      .map((target) => ({ key: target.kind, name: kinds[target.kind], value: 0, color: 'var(--muted)' })),
  ]

  return (
    <div className="space-y-4">
      {slices.length > 0 && <CategoryDonut slices={slices} currency={currency} selected={selected} onSelect={setSelected} />}
      <table className="w-full text-sm" aria-label={t('Allocation')}>
        {targets.length > 0 && (
          <thead>
            <tr className="text-muted-foreground text-xs">
              <th className="pb-1 text-left font-normal">{t('Kind')}</th>
              <th className="pb-1 text-right font-normal">{t('Now')}</th>
              <th className="pb-1 text-right font-normal">{t('Target')}</th>
            </tr>
          </thead>
        )}
        <tbody>
          {rows.map((row) => {
            const share = total > 0 ? (row.value / total) * 100 : 0
            const target = targetOf[row.key]
            const off = target !== undefined && Math.abs(share - target) > OFF_TARGET_POINTS

            return (
              <tr key={row.key}>
                <td className="py-1">
                  <span className="flex min-w-0 items-center gap-2">
                    <span className="size-2.5 shrink-0 rounded-full" style={{ background: row.color }} aria-hidden />
                    <span className="truncate">{row.name}</span>
                  </span>
                </td>
                <td className={cn('py-1 text-right tabular-nums', off && 'font-semibold text-amber-600 dark:text-amber-400')}>{Math.round(share)}%</td>
                {targets.length > 0 && (
                  <td className="text-muted-foreground py-1 text-right tabular-nums">{target === undefined ? '' : `${Math.round(target)}%`}</td>
                )}
              </tr>
            )
          })}
        </tbody>
      </table>
    </div>
  )
}
