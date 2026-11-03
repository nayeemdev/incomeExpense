import { useQuery } from '@tanstack/react-query'
import { Link, createFileRoute, useNavigate } from '@tanstack/react-router'
import { PlusIcon } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'
import { Money } from '@/components/app/money'
import { MonthSwitcher } from '@/components/app/month-switcher'
import { PageHeader } from '@/components/app/page-header'
import { BillForm } from '@/components/planning/bill-form'
import { BillStatus } from '@/components/planning/bill-status'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader } from '@/components/ui/card'
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { FormDialog } from '@/components/app/form-dialog'
import { Skeleton } from '@/components/ui/skeleton'
import { useCanEdit, useLedger, useLocale } from '@/hooks/use-ledger'
import { billsQuery } from '@/lib/api/queries'
import type { Bill } from '@/lib/api/types'
import { todayIso } from '@/lib/format'
import { frequencyLabels } from '@/lib/labels'
import { monthRange } from '@/lib/period'

export const Route = createFileRoute('/_app/$ledgerId/bills/')({
  validateSearch: z.object({ month: z.string().regex(/^\d{4}-\d{2}$/).optional().catch(undefined) }),
  component: Bills,
})

function Bills() {
  const { t } = useTranslation()
  const locale = useLocale()
  const navigate = useNavigate({ from: Route.fullPath })
  const ledger = useLedger()
  const canEdit = useCanEdit()
  const [today] = useState(() => new Date())
  const [creating, setCreating] = useState(false)
  const { month } = Route.useSearch()
  const { current, start, end, shift } = monthRange(month, today)
  const { data: bills } = useQuery(billsQuery(ledger.id, start, end))
  const frequencies = frequencyLabels()
  const groups = new Map<string, Bill[]>()
  bills?.forEach((bill) => groups.set(bill.object_group ?? '', [...(groups.get(bill.object_group ?? '') ?? []), bill]))

  const expectedTotal = bills?.filter((bill) => bill.active).reduce((sum, bill) => sum + Number(bill.amount_max) * (bill.expected_dates?.length ?? 0), 0) ?? 0
  const paidTotal = bills?.reduce((sum, bill) => sum + (bill.payments ?? []).reduce((inner, payment) => inner + Number(payment.amount), 0), 0) ?? 0

  return (
    <>
      <PageHeader
        title={t('Bills')}
        description={t('Regular payments like rent and subscriptions, with what is due and what is paid. Showing {{month}}.', { month: new Intl.DateTimeFormat(locale, { month: 'long', year: 'numeric' }).format(current) })}
        actions={
          <>
            <MonthSwitcher isCurrent={!month} onChange={(offset) => navigate({ search: offset === null ? {} : { month: shift(offset) } })} />
            {canEdit && (
              <Button onClick={() => setCreating(true)}>
                <PlusIcon />
                {t('New bill')}
              </Button>
            )}
          </>
        }
      />

      <div className="stat-grid mb-6">
        <Card>
          <CardHeader>
            <CardDescription>{t('Expected this month')}</CardDescription>
          </CardHeader>
          <CardContent>
            <Money amount={String(expectedTotal)} currency={ledger.primary_currency.code} tone="none" className="text-2xl font-semibold" />
          </CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardDescription>{t('Paid so far')}</CardDescription>
          </CardHeader>
          <CardContent>
            <Money amount={String(paidTotal)} currency={ledger.primary_currency.code} tone="none" className="text-2xl font-semibold" />
          </CardContent>
        </Card>
      </div>

      {!bills && <Skeleton className="h-48 w-full" />}
      {bills?.length === 0 && (
        <Empty className="surface-card">
          <EmptyHeader>
            <EmptyTitle>{t('No bills yet')}</EmptyTitle>
            <EmptyDescription>{t('Add rent, subscriptions and other regular payments to see what is due and get reminded.')}</EmptyDescription>
          </EmptyHeader>
          {canEdit && (
            <EmptyContent>
              <Button onClick={() => setCreating(true)}>{t('New bill')}</Button>
            </EmptyContent>
          )}
        </Empty>
      )}

      <div className="space-y-6">
        {[...groups.entries()].map(([group, items]) => (
          <section key={group}>
            {group && <h2 className="text-muted-foreground mb-2 text-sm font-medium">{group}</h2>}
            <ul className="surface-card divide-y overflow-hidden">
              {items.map((bill) => (
                <li key={bill.id} className="hover:bg-muted/50 relative flex flex-wrap items-center gap-3 px-4 py-3">
                  <div className="min-w-0 flex-1">
                    <Link
                      to="/$ledgerId/bills/$billId"
                      params={{ ledgerId: String(ledger.id), billId: String(bill.id) }}
                      className="font-medium after:absolute after:inset-0"
                    >
                      {bill.name}
                    </Link>
                    <p className="text-muted-foreground text-xs">
                      {frequencies[bill.repeat_freq]}
                      {bill.skip > 0 && ` · ${t('skips {{count}}', { count: bill.skip })}`}
                    </p>
                  </div>
                  <span className="text-sm tabular-nums">
                    <Money amount={bill.amount_min} currency={bill.currency.code} tone="none" />
                    {bill.amount_max !== bill.amount_min && (
                      <>
                        {` ${t('to')} `}
                        <Money amount={bill.amount_max} currency={bill.currency.code} tone="none" />
                      </>
                    )}
                  </span>
                  <BillStatus bill={bill} today={todayIso()} />
                </li>
              ))}
            </ul>
          </section>
        ))}
      </div>

      <FormDialog open={creating} onOpenChange={setCreating} title={t('New bill')} size="lg">
        <BillForm onCancel={() => setCreating(false)} onSaved={() => setCreating(false)} />
      </FormDialog>
    </>
  )
}
