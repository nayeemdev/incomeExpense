import { useQuery } from '@tanstack/react-query'
import { Link, createFileRoute, useNavigate } from '@tanstack/react-router'
import { addMonths, endOfMonth, format, parse, startOfMonth } from 'date-fns'
import { ArrowRightIcon, ChevronLeftIcon, ChevronRightIcon, PlusIcon } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'
import { Money } from '@/components/app/money'
import { PageHeader } from '@/components/app/page-header'
import { useQuickAdd } from '@/components/app/quick-add-context'
import { TransactionList } from '@/components/app/transaction-list'
import { Button } from '@/components/ui/button'
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Skeleton } from '@/components/ui/skeleton'
import { useCanEdit, useLedger, useLocale } from '@/hooks/use-ledger'
import { summaryQuery, transactionsQuery } from '@/lib/api/queries'
import type { CurrencyAmount } from '@/lib/api/types'
import { toIsoDate } from '@/lib/format'
import { assetRoleLabels } from '@/lib/labels'

export const Route = createFileRoute('/_app/$ledgerId/')({
  validateSearch: z.object({ month: z.string().regex(/^\d{4}-\d{2}$/).optional().catch(undefined) }),
  component: Dashboard,
})

function Amounts({ amounts, fallbackCurrency, tone }: { amounts: CurrencyAmount[] | undefined; fallbackCurrency: string; tone?: 'auto' | 'none' }) {
  if (!amounts) return <Skeleton className="h-8 w-32" />

  const list = amounts.length ? amounts : [{ currency: fallbackCurrency, amount: '0' }]

  return (
    <div className="space-y-0.5">
      {list.map((item) => (
        <Money key={item.currency} amount={item.amount} currency={item.currency} tone={tone} className="block text-2xl font-semibold" />
      ))}
    </div>
  )
}

function Dashboard() {
  const { t } = useTranslation()
  const locale = useLocale()
  const navigate = useNavigate({ from: Route.fullPath })
  const ledger = useLedger()
  const canEdit = useCanEdit()
  const quickAdd = useQuickAdd()
  const roles = assetRoleLabels()
  const { month } = Route.useSearch()
  const [today] = useState(() => new Date())
  const current = month ? parse(month, 'yyyy-MM', today) : today
  const start = toIsoDate(startOfMonth(current))
  const end = toIsoDate(endOfMonth(current))
  const ledgerId = String(ledger.id)
  const currency = ledger.primary_currency.code

  const { data: summary } = useQuery(summaryQuery(ledger.id, start, end))
  const { data: recent } = useQuery({ ...transactionsQuery(ledger.id, { start, end }), select: (page) => page.data.slice(0, 8) })

  const goToMonth = (offset: number) => navigate({ search: { month: format(addMonths(current, offset), 'yyyy-MM') } })
  const maxCategory = Math.max(...(summary?.top_categories.map((item) => Number(item.amount)) ?? [0]), 1)

  if (summary && summary.accounts.length === 0 && canEdit) {
    return (
      <Empty className="border">
        <EmptyHeader>
          <EmptyTitle>{t('Add your first account')}</EmptyTitle>
          <EmptyDescription>{t('Start with the bank account or wallet you use most, with what is in it today.')}</EmptyDescription>
        </EmptyHeader>
        <EmptyContent>
          <Button asChild>
            <Link to="/$ledgerId/accounts" params={{ ledgerId }} search={{ type: 'asset', create: true }}>
              <PlusIcon />
              {t('Add account')}
            </Link>
          </Button>
        </EmptyContent>
      </Empty>
    )
  }

  return (
    <>
      <PageHeader
        title={new Intl.DateTimeFormat(locale, { month: 'long', year: 'numeric' }).format(current)}
        description={ledger.name}
        actions={
          <div className="flex items-center gap-1">
            <Button variant="outline" size="icon" aria-label={t('Previous month')} onClick={() => goToMonth(-1)}>
              <ChevronLeftIcon />
            </Button>
            <Button variant="outline" onClick={() => navigate({ search: {} })} disabled={!month}>
              {t('This month')}
            </Button>
            <Button variant="outline" size="icon" aria-label={t('Next month')} onClick={() => goToMonth(1)}>
              <ChevronRightIcon />
            </Button>
          </div>
        }
      />

      <div className="grid gap-4 md:grid-cols-3">
        <Card>
          <CardHeader>
            <CardDescription>{t('Net worth')}</CardDescription>
          </CardHeader>
          <CardContent>
            <Amounts amounts={summary?.net_worth} fallbackCurrency={currency} />
          </CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardDescription>{t('Spent')}</CardDescription>
          </CardHeader>
          <CardContent>
            <Amounts amounts={summary?.spent} fallbackCurrency={currency} tone="none" />
          </CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardDescription>{t('Earned')}</CardDescription>
          </CardHeader>
          <CardContent>
            <Amounts amounts={summary?.earned} fallbackCurrency={currency} tone="none" />
          </CardContent>
        </Card>
      </div>

      <div className="mt-4 grid gap-4 lg:grid-cols-[2fr_1fr]">
        <Card>
          <CardHeader>
            <CardTitle>{t('Recent transactions')}</CardTitle>
            <CardAction>
              <Button variant="ghost" size="sm" asChild>
                <Link to="/$ledgerId/transactions" params={{ ledgerId }} search={{ start, end }}>
                  {t('View all')}
                  <ArrowRightIcon />
                </Link>
              </Button>
            </CardAction>
          </CardHeader>
          <CardContent>
            <TransactionList
              transactions={recent}
              empty={
                <div className="text-muted-foreground py-8 text-center text-sm">
                  <p>{t('Nothing recorded this month yet.')}</p>
                  {canEdit && (
                    <Button variant="link" onClick={() => quickAdd()}>
                      {t('Add a transaction')}
                    </Button>
                  )}
                </div>
              }
            />
          </CardContent>
        </Card>

        <div className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>{t('Accounts')}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
              {!summary && <Skeleton className="h-24 w-full" />}
              {summary?.accounts.map((account) => (
                <Link
                  key={account.id}
                  to="/$ledgerId/accounts/$accountId"
                  params={{ ledgerId, accountId: String(account.id) }}
                  className="hover:bg-muted/50 -mx-2 flex items-center justify-between gap-3 rounded-md px-2 py-1.5"
                >
                  <span className="min-w-0">
                    <span className="block truncate text-sm font-medium">{account.name}</span>
                    <span className="text-muted-foreground text-xs">{account.asset_role && roles[account.asset_role]}</span>
                  </span>
                  <Money amount={account.balance} currency={account.currency?.code ?? currency} className="text-sm font-medium" />
                </Link>
              ))}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>{t('Top spending')}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
              {summary?.top_categories.length === 0 && (
                <p className="text-muted-foreground text-sm">{t('No spending this month.')}</p>
              )}
              {summary?.top_categories.map((item) => (
                <div key={`${item.id}-${item.currency}`} className="space-y-1">
                  <div className="flex justify-between gap-2 text-sm">
                    <span className="truncate">{item.name ?? t('Uncategorised')}</span>
                    <Money amount={item.amount} currency={item.currency} tone="none" />
                  </div>
                  <div className="bg-muted h-1.5 overflow-hidden rounded-full">
                    <div className="bg-primary h-full rounded-full" style={{ width: `${(Number(item.amount) / maxCategory) * 100}%` }} />
                  </div>
                </div>
              ))}
            </CardContent>
          </Card>
        </div>
      </div>
    </>
  )
}
