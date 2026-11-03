import { useQuery } from '@tanstack/react-query'
import { Link, createFileRoute, useNavigate } from '@tanstack/react-router'
import { PencilIcon, PlusIcon } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'
import { Money } from '@/components/app/money'
import { MonthSwitcher } from '@/components/app/month-switcher'
import { PageHeader } from '@/components/app/page-header'
import { ProgressBar } from '@/components/app/progress-bar'
import { AmountDialog } from '@/components/planning/amount-dialog'
import { BudgetDialog } from '@/components/planning/budget-dialog'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader } from '@/components/ui/card'
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Skeleton } from '@/components/ui/skeleton'
import { useCanEdit, useLedger, useLocale } from '@/hooks/use-ledger'
import { api } from '@/lib/api/client'
import { useInvalidateLedger } from '@/lib/api/mutations'
import { availableBudgetsQuery, budgetsQuery } from '@/lib/api/queries'
import type { Budget } from '@/lib/api/types'
import { autoBudgetLabels } from '@/lib/labels'
import { monthRange } from '@/lib/period'

export const Route = createFileRoute('/_app/$ledgerId/budgets/')({
  validateSearch: z.object({ month: z.string().regex(/^\d{4}-\d{2}$/).optional().catch(undefined) }),
  component: Budgets,
})

function Budgets() {
  const { t } = useTranslation()
  const locale = useLocale()
  const navigate = useNavigate({ from: Route.fullPath })
  const ledger = useLedger()
  const canEdit = useCanEdit()
  const invalidate = useInvalidateLedger(ledger.id)
  const [today] = useState(() => new Date())
  const { month } = Route.useSearch()
  const { current, start, end, shift } = monthRange(month, today)
  const currency = ledger.primary_currency
  const { data: budgets } = useQuery(budgetsQuery(ledger.id, start, end))
  const { data: available = [] } = useQuery(availableBudgetsQuery(ledger.id, start, end))
  const [editing, setEditing] = useState<Budget | 'new' | null>(null)
  const [limitFor, setLimitFor] = useState<Budget | null>(null)
  const [settingAvailable, setSettingAvailable] = useState(false)

  const active = budgets?.filter((budget) => budget.active) ?? []
  const limitOf = (budget: Budget) => budget.limits.find((limit) => limit.currency.id === currency.id)
  const budgeted = active.reduce((sum, budget) => sum + Number(limitOf(budget)?.amount ?? 0), 0)
  const spent = active.reduce((sum, budget) => sum + Number(budget.spent?.find((item) => item.currency === currency.code)?.amount ?? 0), 0)
  const availableAmount = available.find((item) => item.currency.id === currency.id && item.start_date === start && item.end_date === end)

  return (
    <>
      <PageHeader
        title={t('Budgets')}
        description={t('Decide how much to spend on each part of life and see what is left. Showing {{month}}.', { month: new Intl.DateTimeFormat(locale, { month: 'long', year: 'numeric' }).format(current) })}
        actions={
          <>
            <MonthSwitcher isCurrent={!month} onChange={(offset) => navigate({ search: offset === null ? {} : { month: shift(offset) } })} />
            {canEdit && (
              <Button onClick={() => setEditing('new')}>
                <PlusIcon />
                {t('New budget')}
              </Button>
            )}
          </>
        }
      />

      <div className="stat-grid mb-6">
        <Card>
          <CardHeader>
            <CardDescription className="flex items-center justify-between">
              {t('Available to budget')}
              {canEdit && (
                <Button variant="ghost" size="icon" className="size-7" aria-label={t('Set available amount')} onClick={() => setSettingAvailable(true)}>
                  <PencilIcon />
                </Button>
              )}
            </CardDescription>
          </CardHeader>
          <CardContent>
            {availableAmount ? (
              <Money amount={availableAmount.amount} currency={currency.code} className="text-2xl font-semibold" />
            ) : (
              <Button variant="link" className="h-auto p-0" disabled={!canEdit} onClick={() => setSettingAvailable(true)}>
                {t('Set how much you can spend')}
              </Button>
            )}
          </CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardDescription>{t('Budgeted')}</CardDescription>
          </CardHeader>
          <CardContent className="space-y-1">
            <Money amount={String(budgeted)} currency={currency.code} tone="none" className="text-2xl font-semibold" />
            {availableAmount && (
              <p className="text-muted-foreground text-xs">
                {t('{{amount}} left to assign', {
                  amount: new Intl.NumberFormat(locale, { style: 'currency', currency: currency.code }).format(Number(availableAmount.amount) - budgeted),
                })}
              </p>
            )}
          </CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardDescription>{t('Spent from budgets')}</CardDescription>
          </CardHeader>
          <CardContent className="space-y-2">
            <Money amount={String(spent)} currency={currency.code} tone="none" className="text-2xl font-semibold" />
            <ProgressBar value={spent} max={budgeted} />
          </CardContent>
        </Card>
      </div>

      {!budgets && <Skeleton className="h-48 w-full" />}
      {budgets?.length === 0 && (
        <Empty className="surface-card">
          <EmptyHeader>
            <EmptyTitle>{t('No budgets yet')}</EmptyTitle>
            <EmptyDescription>{t('Budgets cap what you spend on things like groceries or eating out each month.')}</EmptyDescription>
          </EmptyHeader>
          {canEdit && (
            <EmptyContent>
              <Button onClick={() => setEditing('new')}>{t('New budget')}</Button>
            </EmptyContent>
          )}
        </Empty>
      )}

      {budgets && budgets.length > 0 && (
        <ul className="surface-card divide-y overflow-hidden">
          {budgets.map((budget) => {
            const limit = limitOf(budget)
            const budgetSpent = Number(budget.spent?.find((item) => item.currency === currency.code)?.amount ?? 0)
            const left = limit ? Number(limit.amount) - Number(limit.spent ?? 0) : null

            return (
              <li key={budget.id} className="space-y-2 px-4 py-3">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <div className="flex min-w-0 items-center gap-2">
                    <Link
                      to="/$ledgerId/budgets/$budgetId"
                      params={{ ledgerId: String(ledger.id), budgetId: String(budget.id) }}
                      search={month ? { month } : {}}
                      className="truncate font-medium hover:underline"
                    >
                      {budget.name}
                    </Link>
                    {budget.auto_budget_type && <Badge variant="secondary">{autoBudgetLabels()[budget.auto_budget_type]}</Badge>}
                    {!budget.active && <Badge variant="outline">{t('Inactive')}</Badge>}
                  </div>
                  <div className="flex items-center gap-3 text-sm">
                    <span className="text-muted-foreground">
                      <Money amount={String(budgetSpent)} currency={currency.code} tone="none" />
                      {limit && (
                        <>
                          {' / '}
                          <Money amount={limit.amount} currency={currency.code} tone="none" />
                        </>
                      )}
                    </span>
                    {canEdit && (
                      <Button variant="outline" size="sm" onClick={() => setLimitFor(budget)}>
                        {limit ? t('Change') : t('Set amount')}
                      </Button>
                    )}
                    {canEdit && (
                      <Button variant="ghost" size="icon" className="size-8" aria-label={t('Edit')} onClick={() => setEditing(budget)}>
                        <PencilIcon />
                      </Button>
                    )}
                  </div>
                </div>
                {limit && (
                  <>
                    <ProgressBar value={Number(limit.spent ?? 0)} max={Number(limit.amount)} />
                    <p className={left !== null && left < 0 ? 'text-destructive text-xs' : 'text-muted-foreground text-xs'}>
                      {left !== null && left < 0
                        ? t('{{amount}} over budget', { amount: new Intl.NumberFormat(locale, { style: 'currency', currency: currency.code }).format(-left) })
                        : t('{{amount}} left', { amount: new Intl.NumberFormat(locale, { style: 'currency', currency: currency.code }).format(left ?? 0) })}
                    </p>
                  </>
                )}
              </li>
            )
          })}
        </ul>
      )}

      <BudgetDialog open={editing !== null} onOpenChange={(open) => !open && setEditing(null)} budget={editing === 'new' || editing === null ? undefined : editing} />

      <AmountDialog
        open={limitFor !== null}
        onOpenChange={(open) => !open && setLimitFor(null)}
        title={t('Budget for {{name}}', { name: limitFor?.name })}
        description={t('Decide how much to spend on each part of life and see what is left. Showing {{month}}.', { month: new Intl.DateTimeFormat(locale, { month: 'long', year: 'numeric' }).format(current) })}
        currency={currency.code}
        initial={limitFor ? (limitOf(limitFor)?.amount ?? '') : ''}
        onSave={async (amount) => {
          await api.post(`/ledgers/${ledger.id}/budgets/${limitFor?.id}/limits`, { currency_id: currency.id, start_date: start, end_date: end, amount })
          await invalidate()
        }}
      />

      <AmountDialog
        open={settingAvailable}
        onOpenChange={setSettingAvailable}
        title={t('Available to budget')}
        description={t('Usually your expected income for the month.')}
        currency={currency.code}
        initial={availableAmount?.amount ?? ''}
        onSave={async (amount) => {
          await api.post(`/ledgers/${ledger.id}/available-budgets`, { currency_id: currency.id, start_date: start, end_date: end, amount })
          await invalidate()
        }}
      />
    </>
  )
}
