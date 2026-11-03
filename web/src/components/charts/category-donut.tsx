import { useTranslation } from 'react-i18next'
import { Cell, Label, Pie, PieChart } from 'recharts'
import { ChartContainer, type ChartConfig } from '@/components/ui/chart'
import { useLocale } from '@/hooks/use-ledger'
import { formatMoney } from '@/lib/format'
import type { DonutSlice } from './donut-slices'

type CategoryDonutProps = {
  slices: DonutSlice[]
  currency: string
  selected: string | null
  onSelect: (key: string | null) => void
}

/**
 * Click a slice to show its name, amount and share in the centre; click it again to go back to the total.
 */
export function CategoryDonut({ slices, currency, selected, onSelect }: CategoryDonutProps) {
  const { t } = useTranslation()
  const locale = useLocale()
  const total = slices.reduce((sum, slice) => sum + slice.value, 0)
  const active = slices.find((slice) => slice.key === selected)
  const config = Object.fromEntries(slices.map((slice) => [slice.key, { label: slice.name, color: slice.color }])) satisfies ChartConfig

  return (
    <ChartContainer config={config} className="mx-auto aspect-square h-56">
      <PieChart accessibilityLayer>
        <Pie
          data={slices}
          dataKey="value"
          nameKey="key"
          innerRadius="64%"
          outerRadius="100%"
          paddingAngle={2}
          cornerRadius={4}
          stroke="var(--card)"
          strokeWidth={2}
          isAnimationActive={false}
          onClick={(_, index) => onSelect(slices[index].key === selected ? null : slices[index].key)}
          className="cursor-pointer"
        >
          {slices.map((slice) => (
            <Cell key={slice.key} fill={slice.color} opacity={active && active.key !== slice.key ? 0.3 : 1} />
          ))}
          <Label
            content={({ viewBox }) =>
              viewBox && 'cx' in viewBox ? (
                <text x={viewBox.cx} y={viewBox.cy} textAnchor="middle" dominantBaseline="middle">
                  <tspan x={viewBox.cx} dy="-0.9em" className="fill-muted-foreground text-xs">
                    {active ? active.name : t('Total')}
                  </tspan>
                  <tspan x={viewBox.cx} dy="1.5em" className="fill-foreground text-lg font-bold">
                    {formatMoney(active ? active.value : total, currency, locale)}
                  </tspan>
                  {active && (
                    <tspan x={viewBox.cx} dy="1.5em" className="fill-muted-foreground text-xs">
                      {t('{{percent}}% of total', { percent: Math.round((active.value / total) * 100) })}
                    </tspan>
                  )}
                </text>
              ) : null
            }
          />
        </Pie>
      </PieChart>
    </ChartContainer>
  )
}
