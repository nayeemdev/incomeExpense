import { FlagIcon, HandCoinsIcon, TrophyIcon } from 'lucide-react'
import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Area, CartesianGrid, ComposedChart, ReferenceDot, ReferenceLine, XAxis, YAxis } from 'recharts'
import { compactMoney } from '@/components/charts/format'
import { ChartContainer, ChartTooltip, ChartTooltipContent, type ChartConfig } from '@/components/ui/chart'
import { useLocale } from '@/hooks/use-ledger'
import type { InvestmentRoadmap } from '@/lib/api/types'
import { formatDate, formatMoney } from '@/lib/format'

type ReturnRoadmapProps = { roadmap: InvestmentRoadmap; invested: string; currency: string }

/**
 * The running total of payouts, solid for what has arrived and dashed for what is still to come, against what was put in.
 */
export function ReturnRoadmap({ roadmap, invested, currency }: ReturnRoadmapProps) {
  const { t } = useTranslation()
  const locale = useLocale()
  const statusLabels = { received: t('Received'), due: t('Due, not recorded'), expected: t('Expected') }
  const config = {
    received: { label: t('Received'), color: 'var(--chart-3)' },
    expected: { label: t('Expected'), color: 'var(--chart-1)' },
  } satisfies ChartConfig
  const lastReceived = roadmap.points.findLastIndex((point) => point.status === 'received')
  const rows = roadmap.points.map((point, index) => ({
    date: point.date,
    time: new Date(point.date).getTime(),
    status: point.status,
    amount: Number(point.amount),
    received: index <= lastReceived ? Number(point.cumulative) : null,
    expected: index >= Math.max(lastReceived, 0) ? Number(point.cumulative) : null,
  }))
  const breakEven = rows.find((row) => row.date === roadmap.break_even)
  const money = (amount: string | number) => formatMoney(amount, currency, locale)

  return (
    <div className="space-y-5">
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <Milestone icon={HandCoinsIcon} label={t('Return by {{date}}', { date: formatDate(roadmap.end, locale) })} value={money(roadmap.total_return)} />
        {roadmap.break_even && (
          <Milestone
            icon={FlagIcon}
            label={t('Money back')}
            value={formatDate(roadmap.break_even, locale)}
            hint={t('Payouts add up to the {{amount}} put in', { amount: money(invested) })}
          />
        )}
        {roadmap.maturity_value && (
          <Milestone
            icon={TrophyIcon}
            label={t('Value at maturity')}
            value={money(roadmap.maturity_value)}
            hint={roadmap.maturity_gain ? t('{{amount}} more than put in', { amount: money(roadmap.maturity_gain) }) : undefined}
          />
        )}
      </div>
      {rows.length > 0 && (
        <>
          <ChartContainer config={config} className="aspect-auto h-60 w-full">
            <ComposedChart data={rows} accessibilityLayer>
              <defs>
                <linearGradient id="roadmap-received" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stopColor="var(--color-received)" stopOpacity={0.3} />
                  <stop offset="100%" stopColor="var(--color-received)" stopOpacity={0} />
                </linearGradient>
                <linearGradient id="roadmap-expected" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stopColor="var(--color-expected)" stopOpacity={0.15} />
                  <stop offset="100%" stopColor="var(--color-expected)" stopOpacity={0} />
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
                minTickGap={32}
                tickFormatter={(value: number) => formatDate(new Date(value).toISOString().slice(0, 10), locale, { month: 'short', year: '2-digit' })}
              />
              <YAxis tickLine={false} axisLine={false} width={64} tickFormatter={(value: number) => compactMoney(value, currency, locale)} />
              <ChartTooltip
                content={
                  <ChartTooltipContent
                    hideIndicator
                    labelFormatter={(_, payload) => {
                      const point = payload?.[0]?.payload as (typeof rows)[number] | undefined

                      return point ? `${formatDate(point.date, locale)} · ${statusLabels[point.status]}` : ''
                    }}
                    formatter={(_, __, item) => {
                      const point = item.payload as (typeof rows)[number]

                      return item.dataKey === 'expected' && point.received !== null ? null : (
                        <div className="grid w-full gap-1">
                          <div className="flex justify-between gap-4">
                            <span className="text-muted-foreground">{t('This payout')}</span>
                            <span className="font-medium tabular-nums">{money(point.amount)}</span>
                          </div>
                          <div className="flex justify-between gap-4">
                            <span className="text-muted-foreground">{t('Total returned')}</span>
                            <span className="font-medium tabular-nums">{money(Number(item.value))}</span>
                          </div>
                        </div>
                      )
                    }}
                  />
                }
              />
              <ReferenceLine
                y={Number(invested)}
                stroke="var(--muted-foreground)"
                strokeDasharray="4 4"
                label={{ value: t('Put in'), position: 'insideTopLeft', fill: 'var(--muted-foreground)', fontSize: 11 }}
              />
              <Area dataKey="received" type="stepAfter" stroke="var(--color-received)" strokeWidth={2} fill="url(#roadmap-received)" connectNulls={false} />
              <Area
                dataKey="expected"
                type="stepAfter"
                stroke="var(--color-expected)"
                strokeWidth={2}
                strokeDasharray="5 4"
                fill="url(#roadmap-expected)"
                connectNulls={false}
              />
              {breakEven && (
                <ReferenceDot x={breakEven.time} y={Number(invested)} r={5} fill="var(--chart-4)" stroke="var(--card)" strokeWidth={2} />
              )}
            </ComposedChart>
          </ChartContainer>
          <ul className="text-muted-foreground flex flex-wrap gap-x-4 gap-y-1 text-xs">
            <li className="flex items-center gap-1.5">
              <span className="h-0.5 w-3 rounded" style={{ background: 'var(--chart-3)' }} aria-hidden />
              {t('Received so far')}
            </li>
            <li className="flex items-center gap-1.5">
              <span className="h-0.5 w-3 rounded border-t-2 border-dashed" style={{ borderColor: 'var(--chart-1)' }} aria-hidden />
              {t('Expected')}
            </li>
            {breakEven && (
              <li className="flex items-center gap-1.5">
                <span className="size-2.5 rounded-full" style={{ background: 'var(--chart-4)' }} aria-hidden />
                {t('Money back')}
              </li>
            )}
          </ul>
        </>
      )}
    </div>
  )
}

function Milestone({ icon: Icon, label, value, hint }: { icon: typeof FlagIcon; label: string; value: ReactNode; hint?: string }) {
  return (
    <div className="bg-muted/50 rounded-lg p-3">
      <p className="text-muted-foreground flex items-center gap-1.5 text-xs font-medium">
        <Icon className="size-3.5" aria-hidden />
        {label}
      </p>
      <p className="mt-1 font-semibold tabular-nums">{value}</p>
      {hint && <p className="text-muted-foreground mt-0.5 text-xs">{hint}</p>}
    </div>
  )
}
