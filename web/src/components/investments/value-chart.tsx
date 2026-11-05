import { Area, ComposedChart, CartesianGrid, Line, XAxis, YAxis } from 'recharts'
import { ChartContainer, ChartLegend, ChartLegendContent, ChartTooltip, ChartTooltipContent, type ChartConfig } from '@/components/ui/chart'
import { compactMoney } from '@/components/charts/format'
import { useLocale } from '@/hooks/use-ledger'
import { formatDate, formatMoney } from '@/lib/format'

type ValueChartProps = { points: Array<{ date: string; value: string; cost: string }>; currency: string; labels: { value: string; cost: string } }

export function ValueChart({ points, currency, labels }: ValueChartProps) {
  const locale = useLocale()
  const config = { value: { label: labels.value, color: 'var(--chart-1)' }, cost: { label: labels.cost, color: 'var(--muted-foreground)' } } satisfies ChartConfig
  const rows = points.map((point) => ({ date: point.date, time: new Date(point.date).getTime(), value: Number(point.value), cost: Number(point.cost) }))

  return (
    <ChartContainer config={config} className="aspect-auto h-60 w-full">
      <ComposedChart data={rows} accessibilityLayer>
        <defs>
          <linearGradient id="investment-fill" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stopColor="var(--color-value)" stopOpacity={0.25} />
            <stop offset="100%" stopColor="var(--color-value)" stopOpacity={0} />
          </linearGradient>
        </defs>
        <CartesianGrid vertical={false} strokeDasharray="3 3" />
        <XAxis
          dataKey="time"
          type="number"
          scale="time"
          domain={['dataMin', 'dataMax']}
          tickLine={false}
          axisLine={false}
          tickMargin={8}
          minTickGap={24}
          tickFormatter={(value: number) => formatDate(new Date(value).toISOString().slice(0, 10), locale, { month: 'short', year: '2-digit' })}
        />
        <YAxis tickLine={false} axisLine={false} width={64} tickFormatter={(value: number) => compactMoney(value, currency, locale)} />
        <ChartTooltip
          content={
            <ChartTooltipContent
              labelFormatter={(_, payload) => formatDate(String(payload?.[0]?.payload?.date ?? ''), locale)}
              formatter={(value, name) => (
                <div className="flex w-full justify-between gap-4">
                  <span className="text-muted-foreground">{config[name as keyof typeof config]?.label}</span>
                  <span className="font-medium tabular-nums">{formatMoney(Number(value), currency, locale)}</span>
                </div>
              )}
            />
          }
        />
        <ChartLegend content={<ChartLegendContent />} />
        <Area dataKey="value" type="stepAfter" stroke="var(--color-value)" strokeWidth={2} fill="url(#investment-fill)" activeDot={{ r: 4 }} />
        <Line dataKey="cost" type="stepAfter" stroke="var(--color-cost)" strokeWidth={2} strokeDasharray="4 4" dot={false} />
      </ComposedChart>
    </ChartContainer>
  )
}
