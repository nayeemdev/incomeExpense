import { useTranslation } from 'react-i18next'
import { Bar, BarChart, CartesianGrid, XAxis, YAxis } from 'recharts'
import { ChartContainer, ChartLegend, ChartLegendContent, ChartTooltip, ChartTooltipContent, type ChartConfig } from '@/components/ui/chart'
import { useLocale } from '@/hooks/use-ledger'
import { formatMoney } from '@/lib/format'
import { bucketLabel, compactMoney } from './format'

type IncomeExpenseChartProps = {
  data: Array<{ start: string; income: string; expense: string }>
  currency: string
  interval: string
}

export function IncomeExpenseChart({ data, currency, interval }: IncomeExpenseChartProps) {
  const { t } = useTranslation()
  const locale = useLocale()
  const config = {
    income: { label: t('Income'), color: 'var(--chart-1)' },
    expense: { label: t('Expenses'), color: 'var(--chart-2)' },
  } satisfies ChartConfig
  const rows = data.map((row) => ({ label: bucketLabel(row.start, interval, locale), income: Number(row.income), expense: Number(row.expense) }))

  return (
    <ChartContainer config={config} className="aspect-auto h-64 w-full">
      <BarChart data={rows} barGap={2} accessibilityLayer>
        <CartesianGrid vertical={false} strokeDasharray="3 3" />
        <XAxis dataKey="label" tickLine={false} axisLine={false} tickMargin={8} minTickGap={16} />
        <YAxis tickLine={false} axisLine={false} width={64} tickFormatter={(value: number) => compactMoney(value, currency, locale)} />
        <ChartTooltip cursor={{ fillOpacity: 0.4 }} content={<ChartTooltipContent formatter={(value, name) => `${config[name as keyof typeof config]?.label ?? name}: ${formatMoney(Number(value), currency, locale)}`} />} />
        <ChartLegend content={<ChartLegendContent />} />
        <Bar dataKey="income" fill="var(--color-income)" radius={[4, 4, 0, 0]} maxBarSize={28} />
        <Bar dataKey="expense" fill="var(--color-expense)" radius={[4, 4, 0, 0]} maxBarSize={28} />
      </BarChart>
    </ChartContainer>
  )
}
