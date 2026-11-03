import { useTranslation } from 'react-i18next'
import { Bar, BarChart, CartesianGrid, XAxis, YAxis } from 'recharts'
import { ChartContainer, ChartLegend, ChartLegendContent, ChartTooltip, ChartTooltipContent, type ChartConfig } from '@/components/ui/chart'
import { useLocale } from '@/hooks/use-ledger'
import type { BreakdownRow } from '@/lib/api/types'
import { formatMoney } from '@/lib/format'
import { bucketLabel, compactMoney } from './format'

const SERIES = 6

/**
 * Stacked bars per period for the largest groups; the rest fold into "Other" so colors never repeat.
 */
export function BreakdownChart({ rows, currency, interval }: { rows: BreakdownRow[]; currency: string; interval: string }) {
  const { t } = useTranslation()
  const locale = useLocale()
  const top = rows.slice(0, SERIES)
  const rest = rows.slice(SERIES)
  const keys = top.map((_, index) => `s${index}`)
  const config: ChartConfig = Object.fromEntries(
    top.map((row, index) => [`s${index}`, { label: row.name ?? t('None'), color: `var(--chart-${index + 1})` }]),
  )

  if (rest.length > 0) {
    keys.push('other')
    config.other = { label: t('Other'), color: 'var(--muted-foreground)' }
  }

  const buckets = Object.keys(rows[0]?.buckets ?? {})
  const data = buckets.map((bucket) => ({
    label: bucketLabel(bucket, interval, locale),
    ...Object.fromEntries(top.map((row, index) => [`s${index}`, Number(row.buckets[bucket] ?? 0)])),
    ...(rest.length > 0 ? { other: rest.reduce((sum, row) => sum + Number(row.buckets[bucket] ?? 0), 0) } : {}),
  }))

  return (
    <ChartContainer config={config} className="aspect-auto h-72 w-full">
      <BarChart data={data} accessibilityLayer>
        <CartesianGrid vertical={false} strokeDasharray="3 3" />
        <XAxis dataKey="label" tickLine={false} axisLine={false} tickMargin={8} minTickGap={16} />
        <YAxis tickLine={false} axisLine={false} width={64} tickFormatter={(value: number) => compactMoney(value, currency, locale)} />
        <ChartTooltip content={<ChartTooltipContent formatter={(value, name) => `${config[String(name)]?.label ?? name}: ${formatMoney(Number(value), currency, locale)}`} />} />
        <ChartLegend content={<ChartLegendContent />} />
        {keys.map((key, index) => (
          <Bar
            key={key}
            dataKey={key}
            stackId="total"
            fill={`var(--color-${key})`}
            stroke="var(--background)"
            strokeWidth={1}
            radius={index === keys.length - 1 ? [4, 4, 0, 0] : 0}
            maxBarSize={40}
          />
        ))}
      </BarChart>
    </ChartContainer>
  )
}
