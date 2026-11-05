import { useMutation, useQuery } from '@tanstack/react-query'
import { Link, createFileRoute, useNavigate } from '@tanstack/react-router'
import { BanknoteArrowDownIcon, BanknoteArrowUpIcon, ChevronDownIcon, LineChartIcon, PencilIcon, PlusIcon, Trash2Icon } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ConfirmDialog } from '@/components/app/confirm-dialog'
import { FormDialog } from '@/components/app/form-dialog'
import { Money } from '@/components/app/money'
import { PageHeader } from '@/components/app/page-header'
import { StatCard } from '@/components/app/stat-card'
import { InvestmentEventDialog, type EventDraft } from '@/components/investments/investment-event-dialog'
import { InvestmentForm } from '@/components/investments/investment-form'
import { ReturnRoadmap } from '@/components/investments/return-roadmap'
import { ValueChart } from '@/components/investments/value-chart'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { Skeleton } from '@/components/ui/skeleton'
import { useCanEdit, useLedger, useLocale } from '@/hooks/use-ledger'
import { api } from '@/lib/api/client'
import { useInvalidateLedger } from '@/lib/api/mutations'
import { investmentQuery } from '@/lib/api/queries'
import type { Investment } from '@/lib/api/types'
import { formatDate, formatMoney, todayIso } from '@/lib/format'
import { notifyError } from '@/lib/forms'
import { investmentEventLabels, percent, units } from '@/lib/investments'
import { frequencyLabels, investmentKindLabels } from '@/lib/labels'

export const Route = createFileRoute('/_app/$ledgerId/investments/$investmentId')({
  component: InvestmentDetail,
})

function InvestmentDetail() {
  const { t } = useTranslation()
  const locale = useLocale()
  const navigate = useNavigate()
  const ledger = useLedger()
  const canEdit = useCanEdit()
  const invalidate = useInvalidateLedger(ledger.id)
  const [editing, setEditing] = useState(false)
  const [draft, setDraft] = useState<EventDraft | null>(null)
  const investmentId = Route.useParams().investmentId
  const { data: investment } = useQuery(investmentQuery(ledger.id, investmentId))

  const remove = useMutation({
    mutationFn: () => api.delete(`/ledgers/${ledger.id}/investments/${investmentId}`),
    onSuccess: async () => {
      await navigate({ to: '/$ledgerId/investments', params: { ledgerId: ledger.id } })
      await invalidate()
    },
    onError: notifyError,
  })

  const removeEvent = useMutation({
    mutationFn: (eventId: string) => api.delete(`/ledgers/${ledger.id}/investments/${investmentId}/events/${eventId}`),
    onSuccess: () => invalidate(),
    onError: notifyError,
  })

  if (!investment) return <Skeleton className="h-64 w-full" />

  const currency = investment.currency.code
  const labels = investmentEventLabels(investment.kind)
  const held = Number(investment.cost) > 0 || Number(investment.units) > 0

  return (
    <>
      <PageHeader
        title={investment.name}
        description={<Subtitle investment={investment} />}
        actions={
          canEdit && (
            <>
              <Button onClick={() => setDraft({ type: 'buy' })}>
                <PlusIcon />
                {labels.buy}
              </Button>
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button variant="outline">
                    {t('Record')}
                    <ChevronDownIcon />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                  <DropdownMenuItem onSelect={() => setDraft({ type: 'income' })}>
                    <BanknoteArrowUpIcon />
                    {labels.income}
                  </DropdownMenuItem>
                  {held && (
                    <>
                      <DropdownMenuItem onSelect={() => setDraft({ type: 'valuation' })}>
                        <LineChartIcon />
                        {labels.valuation}
                      </DropdownMenuItem>
                      <DropdownMenuItem onSelect={() => setDraft({ type: 'sell' })}>
                        <BanknoteArrowDownIcon />
                        {labels.sell}
                      </DropdownMenuItem>
                    </>
                  )}
                </DropdownMenuContent>
              </DropdownMenu>
              <Button variant="outline" size="icon" aria-label={t('Edit')} onClick={() => setEditing(true)}>
                <PencilIcon />
              </Button>
              <ConfirmDialog
                trigger={
                  <Button variant="outline" size="icon" aria-label={t('Delete')}>
                    <Trash2Icon />
                  </Button>
                }
                title={t('Delete {{name}}?', { name: investment.name })}
                description={t('This also deletes every transaction booked for it. To keep the history, sell or withdraw everything instead.')}
                onConfirm={() => remove.mutate()}
              />
            </>
          )
        }
      />
      <div className="stat-grid mb-6">
        <StatCard
          label={t('Current value')}
          value={<Money amount={investment.value} currency={currency} tone="none" />}
          hint={
            investment.tracks_units && investment.price
              ? t('{{units}} × {{price}}', { units: units(investment.units, locale), price: formatMoney(investment.price, currency, locale) })
              : investment.valued_on
                ? t('Valued on {{date}}', { date: formatDate(investment.valued_on, locale) })
                : undefined
          }
        />
        <StatCard label={t('Invested')} value={<Money amount={investment.cost} currency={currency} tone="none" />} />
        <StatCard
          label={t('Unrealised gain')}
          value={<Money amount={investment.gain} currency={currency} />}
          hint={percent(investment.gain_percent, locale)}
        />
        <StatCard
          label={t('Income and realised gains')}
          value={<Money amount={String(Number(investment.income) + Number(investment.realized))} currency={currency} />}
          hint={t('{{income}} income, {{realized}} from sales', {
            income: formatMoney(investment.income, currency, locale),
            realized: formatMoney(investment.realized, currency, locale),
          })}
        />
      </div>
      <div className="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div className="min-w-0 space-y-4">
          {investment.roadmap && (
            <Card>
              <CardHeader>
                <CardTitle>{t('Return roadmap')}</CardTitle>
              </CardHeader>
              <CardContent>
                <ReturnRoadmap roadmap={investment.roadmap} invested={investment.invested} currency={currency} />
              </CardContent>
            </Card>
          )}
          {investment.history && investment.history.length > 1 && investment.history.some((point) => point.value !== point.cost) && (
            <Card>
              <CardHeader>
                <CardTitle>{t('Value over time')}</CardTitle>
              </CardHeader>
              <CardContent>
                <ValueChart points={investment.history} currency={currency} labels={{ value: t('Value'), cost: t('Invested') }} />
              </CardContent>
            </Card>
          )}
          <Card>
            <CardHeader>
              <CardTitle>{t('Activity')}</CardTitle>
            </CardHeader>
            <CardContent>
              {investment.events?.length ? (
                <ul className="divide-y">
                  {investment.events.map((event) => (
                    <li key={event.id} className="flex items-center gap-3 py-2.5 text-sm">
                      <div className="min-w-0 flex-1">
                        <p className="font-medium">{labels[event.type]}</p>
                        <p className="text-muted-foreground text-xs">
                          {formatDate(event.date, locale)}
                          {event.units && ` · ${t('{{units}} units', { units: units(event.units, locale) })}`}
                          {event.price && ` · ${t('{{price}} each', { price: formatMoney(event.price, currency, locale) })}`}
                        </p>
                      </div>
                      <div className="text-right">
                        {event.transaction_group_id ? (
                          <Link
                            to="/$ledgerId/transactions/$groupId"
                            params={{ ledgerId: ledger.id, groupId: event.transaction_group_id }}
                            className="hover:underline"
                          >
                            <Money amount={event.amount} currency={currency} tone={event.type === 'income' ? 'positive' : 'none'} />
                          </Link>
                        ) : (
                          <Money amount={event.amount} currency={currency} tone="none" />
                        )}
                        {event.gain && Number(event.gain) !== 0 && (
                          <p className="text-xs">
                            <Money amount={event.gain} currency={currency} tone={Number(event.gain) < 0 ? 'negative' : 'positive'} />
                          </p>
                        )}
                      </div>
                      {canEdit && (
                        <ConfirmDialog
                          trigger={
                            <Button variant="ghost" size="icon-sm" aria-label={t('Delete')}>
                              <Trash2Icon />
                            </Button>
                          }
                          title={t('Delete this entry?')}
                          description={t('Any transaction booked with it is deleted too.')}
                          onConfirm={() => removeEvent.mutate(event.id)}
                        />
                      )}
                    </li>
                  ))}
                </ul>
              ) : (
                <p className="text-muted-foreground text-sm">{t('Nothing recorded yet.')}</p>
              )}
            </CardContent>
          </Card>
        </div>
        <div className="space-y-4">
          {investment.upcoming_payouts.length > 0 && (
            <Card>
              <CardHeader>
                <CardTitle>{t('Expected payouts')}</CardTitle>
              </CardHeader>
              <CardContent>
                <ul className="divide-y">
                  {investment.upcoming_payouts.map((payout) => (
                    <li key={payout.date} className="flex items-center justify-between gap-2 py-2 text-sm">
                      <div>
                        <p>{formatDate(payout.date, locale)}</p>
                        {payout.overdue && <Badge variant="destructive">{t('Not recorded yet')}</Badge>}
                      </div>
                      <div className="flex items-center gap-2">
                        {payout.amount && <Money amount={payout.amount} currency={currency} tone="none" />}
                        {canEdit && payout.date <= todayIso() && (
                          <Button size="sm" variant="outline" onClick={() => setDraft({ type: 'income', date: payout.date, amount: payout.amount ?? undefined })}>
                            {t('Record')}
                          </Button>
                        )}
                      </div>
                    </li>
                  ))}
                </ul>
              </CardContent>
            </Card>
          )}
          <Card>
            <CardHeader>
              <CardTitle>{t('Details')}</CardTitle>
            </CardHeader>
            <CardContent>
              <Details investment={investment} />
            </CardContent>
          </Card>
        </div>
      </div>
      <InvestmentEventDialog investment={investment} draft={draft} onClose={() => setDraft(null)} />
      <FormDialog open={editing} onOpenChange={setEditing} title={t('Edit {{name}}', { name: investment.name })} size="lg">
        <InvestmentForm investment={investment} onCancel={() => setEditing(false)} onSaved={() => setEditing(false)} />
      </FormDialog>
    </>
  )
}

function Subtitle({ investment }: { investment: Investment }) {
  const { t } = useTranslation()
  const locale = useLocale()
  const parts = [investmentKindLabels()[investment.kind], investment.symbol]

  if (investment.closed_at) parts.push(t('Closed {{date}}', { date: formatDate(investment.closed_at, locale) }))

  return <>{parts.filter(Boolean).join(' · ')}</>
}

function Details({ investment }: { investment: Investment }) {
  const { t } = useTranslation()
  const locale = useLocale()
  const ledger = useLedger()
  const rows: Array<[string, React.ReactNode]> = []

  if (investment.interest_rate) rows.push([t('Yearly return'), `${Number(investment.interest_rate)}%`])
  if (investment.interest_rate)
    rows.push([t('Pays out'), investment.payout_frequency ? frequencyLabels()[investment.payout_frequency] : t('At maturity')])
  if (investment.payout_amount) rows.push([t('Each payout'), formatMoney(investment.payout_amount, investment.currency.code, locale)])
  if (investment.start_date) rows.push([t('Start date'), formatDate(investment.start_date, locale)])
  if (investment.maturity_date) rows.push([t('Maturity date'), formatDate(investment.maturity_date, locale)])
  rows.push([t('Total put in'), formatMoney(investment.invested, investment.currency.code, locale)])
  rows.push([
    t('Account'),
    <Link key="account" to="/$ledgerId/accounts/$accountId" params={{ ledgerId: ledger.id, accountId: investment.account_id }} className="hover:underline">
      {investment.name}
    </Link>,
  ])

  return (
    <dl className="space-y-2 text-sm">
      {rows.map(([label, value]) => (
        <div key={label} className="flex justify-between gap-3">
          <dt className="text-muted-foreground">{label}</dt>
          <dd className="text-right">{value}</dd>
        </div>
      ))}
      {investment.notes && <p className="text-muted-foreground border-t pt-2 whitespace-pre-line">{investment.notes}</p>}
    </dl>
  )
}
