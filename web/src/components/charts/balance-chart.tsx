import { Area, AreaChart, CartesianGrid, XAxis, YAxis } from 'recharts'
import { ChartContainer, ChartTooltip, ChartTooltipContent, type ChartConfig } from '@/components/ui/chart'
import { useLocale } from '@/hooks/use-ledger'
import { formatDate, formatMoney } from '@/lib/format'
import { compactMoney } from './format'

type BalanceChartProps = { points: Array<{ date: string; balance: string }>; currency: string; label: string; className?: string }

export function BalanceChart({ points, currency, label, className = 'aspect-auto h-56 w-full' }: BalanceChartProps) {
  const locale = useLocale()
  const config = { balance: { label, color: 'var(--chart-1)' } } satisfies ChartConfig
  const rows = points.map((point) => ({ date: point.date, balance: Number(point.balance) }))

  return (
    <ChartContainer config={config} className={className}>
      <AreaChart data={rows} accessibilityLayer>
        <defs>
          <linearGradient id="balance-fill" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stopColor="var(--color-balance)" stopOpacity={0.25} />
            <stop offset="100%" stopColor="var(--color-balance)" stopOpacity={0} />
          </linearGradient>
        </defs>
        <CartesianGrid vertical={false} strokeDasharray="3 3" />
        <XAxis
          dataKey="date"
          tickLine={false}
          axisLine={false}
          tickMargin={8}
          minTickGap={24}
          tickFormatter={(value: string) => formatDate(value, locale, { day: 'numeric', month: 'short' })}
        />
        <YAxis tickLine={false} axisLine={false} width={64} tickFormatter={(value: number) => compactMoney(value, currency, locale)} />
        <ChartTooltip
          content={
            <ChartTooltipContent
              labelFormatter={(_, payload) => formatDate(String(payload?.[0]?.payload?.date ?? ''), locale)}
              formatter={(value) => formatMoney(Number(value), currency, locale)}
            />
          }
        />
        <Area dataKey="balance" type="monotone" stroke="var(--color-balance)" strokeWidth={2} fill="url(#balance-fill)" activeDot={{ r: 4 }} />
      </AreaChart>
    </ChartContainer>
  )
}
