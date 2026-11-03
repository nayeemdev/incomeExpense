import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { startOfMonth, subMonths } from 'date-fns'
import { Link, createFileRoute, useNavigate } from '@tanstack/react-router'
import { ArrowDownLeftIcon, ArrowRightIcon, ArrowUpRightIcon, LandmarkIcon, PlusIcon, SlidersHorizontalIcon } from 'lucide-react'
import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'
import { Money } from '@/components/app/money'
import { MonthSwitcher } from '@/components/app/month-switcher'
import { PageHeader } from '@/components/app/page-header'
import { CategoryIcon } from '@/components/app/category-icon'
import { StatCard } from '@/components/app/stat-card'
import { useQuickAdd } from '@/components/app/quick-add-context'
import { TransactionList } from '@/components/app/transaction-list'
import { IncomeExpenseChart } from '@/components/charts/income-expense-chart'
import { BillStatus } from '@/components/planning/bill-status'
import {
  DropdownMenu,
  DropdownMenuCheckboxItem,
  DropdownMenuContent,
  DropdownMenuLabel,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Button } from '@/components/ui/button'
import { Card, CardAction, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Skeleton } from '@/components/ui/skeleton'
import { useCanEdit, useLedger, useLocale } from '@/hooks/use-ledger'
import { api } from '@/lib/api/client'
import { billsQuery, overviewReportQuery, summaryQuery, transactionsQuery, userQuery } from '@/lib/api/queries'
import { toIsoDate, todayIso } from '@/lib/format'
import { notifyError } from '@/lib/forms'
import type { CurrencyAmount } from '@/lib/api/types'
import { monthRange } from '@/lib/period'
import { assetRoleLabels } from '@/lib/labels'

export const Route = createFileRoute('/_app/$ledgerId/')({
  validateSearch: z.object({
    month: z.string().regex(/^\d{4}-\d{2}$/).optional().catch(undefined),
    add: z.boolean().optional().catch(undefined),
  }),
  component: Dashboard,
})

function Amounts({
  amounts,
  total,
  tone,
}: {
  amounts: CurrencyAmount[] | undefined
  total: (CurrencyAmount & { complete: boolean }) | undefined
  tone?: 'auto' | 'none'
}) {
  const { t } = useTranslation()

  if (!amounts || !total) return <Skeleton className="h-8 w-32" />

  const others = amounts.filter((item) => item.currency !== total.currency)

  return (
    <div className="space-y-1">
      <Money amount={total.amount} currency={total.currency} tone={tone} className="block" />
      {others.length > 0 && (
        <p className="text-muted-foreground text-xs font-normal tracking-normal">
          {total.complete ? t('Includes') : t('Without rates for some of')}{' '}
          {others.map((item, index) => (
            <span key={item.currency}>
              {index > 0 && ', '}
              <Money amount={item.amount} currency={item.currency} tone="none" />
            </span>
          ))}
        </p>
      )}
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
  const { month, add } = Route.useSearch()

  useEffect(() => {
    if (!add) return

    quickAdd()
    void navigate({ search: (previous) => ({ ...previous, add: undefined }), replace: true })
  }, [add, quickAdd, navigate])
  const [today] = useState(() => new Date())
  const { current, start, end, shift } = monthRange(month, today)
  const ledgerId = String(ledger.id)
  const currency = ledger.primary_currency.code

  const { data: summary } = useQuery(summaryQuery(ledger.id, start, end))
  const { data: user } = useQuery(userQuery)
  const hidden = new Set(user?.preferences.dashboard_hidden ?? [])
  const show = (widget: string) => !hidden.has(widget)
  const trendRange = { start: toIsoDate(startOfMonth(subMonths(current, 5))), end, interval: 'month' }
  const { data: trend } = useQuery({ ...overviewReportQuery(ledger.id, trendRange), enabled: show('trend') })
  const { data: bills } = useQuery({
    ...billsQuery(ledger.id, start, end),
    select: (items) => items.filter((bill) => bill.active && (bill.expected_dates?.length ?? 0) > (bill.payments?.length ?? 0)),
  })
  const { data: recent } = useQuery({ ...transactionsQuery(ledger.id, { start, end }), select: (page) => page.data.slice(0, 8) })

  const maxCategory = Math.max(...(summary?.top_categories.map((item) => Number(item.amount)) ?? [0]), 1)

  if (summary && summary.accounts.length === 0 && canEdit) {
    return (
      <Empty className="surface-card">
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
        description={t('{{ledger}}: how this month is going at a glance.', { ledger: ledger.name })}
        actions={
          <div className="flex items-center gap-2">
            <MonthSwitcher isCurrent={!month} onChange={(offset) => navigate({ search: offset === null ? {} : { month: shift(offset) } })} />
            <DashboardCustomizer hidden={hidden} />
          </div>
        }
      />

      <div className="stat-grid">
        <StatCard label={t('Net worth')} icon={LandmarkIcon} tone="brand" value={<Amounts amounts={summary?.net_worth} total={summary?.net_worth_total} />} />
        <StatCard label={t('Spent')} icon={ArrowUpRightIcon} tone="danger" value={<Amounts amounts={summary?.spent} total={summary?.spent_total} tone="none" />} />
        <StatCard label={t('Earned')} icon={ArrowDownLeftIcon} tone="success" value={<Amounts amounts={summary?.earned} total={summary?.earned_total} tone="none" />} />
      </div>

      {show('trend') && trend && (
        <Card className="mt-4">
          <CardHeader>
            <CardTitle>{t('Last 6 months')}</CardTitle>
          </CardHeader>
          <CardContent>
            <IncomeExpenseChart data={trend.data.income_expense} currency={trend.meta.currency} interval="month" />
          </CardContent>
        </Card>
      )}

      <div className="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-[2fr_1fr]">
        {show('recent') && (
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
        )}

        <div className="space-y-4">
          {show('accounts') && (
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
          )}

          {show('bills') && bills && bills.length > 0 && (
            <Card>
              <CardHeader>
                <CardTitle>{t('Bills to pay')}</CardTitle>
                <CardAction>
                  <Button variant="ghost" size="sm" asChild>
                    <Link to="/$ledgerId/bills" params={{ ledgerId }} search={month ? { month } : {}}>
                      {t('View all')}
                      <ArrowRightIcon />
                    </Link>
                  </Button>
                </CardAction>
              </CardHeader>
              <CardContent className="space-y-3">
                {bills.map((bill) => (
                  <div key={bill.id} className="flex items-center justify-between gap-2 text-sm">
                    <span className="min-w-0 truncate">{bill.name}</span>
                    <BillStatus bill={bill} today={todayIso()} />
                  </div>
                ))}
              </CardContent>
            </Card>
          )}

          {show('top_spending') && (
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
                  <div className="flex items-center justify-between gap-2 text-sm">
                    <span className="flex min-w-0 items-center gap-2">
                      <CategoryIcon icon={item.icon} color={item.color} className="size-7" />
                      <span className="truncate">{item.name ?? t('Uncategorised')}</span>
                    </span>
                    <Money amount={item.amount} currency={item.currency} tone="none" />
                  </div>
                  <div className="bg-muted h-1.5 overflow-hidden rounded-full">
                    <div className="bg-primary h-full rounded-full" style={{ width: `${(Number(item.amount) / maxCategory) * 100}%` }} />
                  </div>
                </div>
              ))}
            </CardContent>
          </Card>
          )}
        </div>
      </div>
    </>
  )
}

function DashboardCustomizer({ hidden }: { hidden: Set<string> }) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const widgets: Record<string, string> = {
    trend: t('Last 6 months chart'),
    recent: t('Recent transactions'),
    accounts: t('Accounts'),
    bills: t('Bills to pay'),
    top_spending: t('Top spending'),
  }

  const save = useMutation({
    mutationFn: (next: string[]) => api.patch('/user/preferences', { dashboard_hidden: next }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: userQuery.queryKey }),
    onError: notifyError,
  })

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="outline" size="icon" aria-label={t('Customize dashboard')}>
          <SlidersHorizontalIcon />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="min-w-56">
        <DropdownMenuLabel>{t('Show on dashboard')}</DropdownMenuLabel>
        {Object.entries(widgets).map(([key, label]) => (
          <DropdownMenuCheckboxItem
            key={key}
            checked={!hidden.has(key)}
            onSelect={(event) => event.preventDefault()}
            onCheckedChange={(checked) => save.mutate(checked ? [...hidden].filter((item) => item !== key) : [...hidden, key])}
          >
            {label}
          </DropdownMenuCheckboxItem>
        ))}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
