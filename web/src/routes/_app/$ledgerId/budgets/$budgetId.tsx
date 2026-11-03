import { useMutation, useQuery } from '@tanstack/react-query'
import { createFileRoute, useNavigate } from '@tanstack/react-router'
import { PencilIcon, Trash2Icon } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'
import { ConfirmDialog } from '@/components/app/confirm-dialog'
import { Money } from '@/components/app/money'
import { MonthSwitcher } from '@/components/app/month-switcher'
import { PageHeader } from '@/components/app/page-header'
import { ProgressBar } from '@/components/app/progress-bar'
import { TransactionList } from '@/components/app/transaction-list'
import { BudgetDialog } from '@/components/planning/budget-dialog'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { useCanEdit, useLedger, useLocale } from '@/hooks/use-ledger'
import { api } from '@/lib/api/client'
import { useInvalidateLedger } from '@/lib/api/mutations'
import { budgetQuery, transactionsQuery } from '@/lib/api/queries'
import { notifyError } from '@/lib/forms'
import { monthRange } from '@/lib/period'

export const Route = createFileRoute('/_app/$ledgerId/budgets/$budgetId')({
  validateSearch: z.object({ month: z.string().regex(/^\d{4}-\d{2}$/).optional().catch(undefined) }),
  component: BudgetDetail,
})

function BudgetDetail() {
  const { t } = useTranslation()
  const locale = useLocale()
  const navigate = useNavigate({ from: Route.fullPath })
  const ledger = useLedger()
  const canEdit = useCanEdit()
  const invalidate = useInvalidateLedger(ledger.id)
  const [today] = useState(() => new Date())
  const [editing, setEditing] = useState(false)
  const { month } = Route.useSearch()
  const budgetId = Route.useParams().budgetId
  const { current, start, end, shift } = monthRange(month, today)
  const { data: budget } = useQuery(budgetQuery(ledger.id, budgetId, start, end))
  const { data: transactions } = useQuery(transactionsQuery(ledger.id, { budget_id: budgetId, start, end }))

  const remove = useMutation({
    mutationFn: () => api.delete(`/ledgers/${ledger.id}/budgets/${budgetId}`),
    onSuccess: async () => {
      await navigate({ to: '/$ledgerId/budgets', params: { ledgerId: ledger.id } })
      await invalidate()
    },
    onError: notifyError,
  })

  if (!budget) return <Skeleton className="h-64 w-full" />

  return (
    <>
      <PageHeader
        title={budget.name}
        description={new Intl.DateTimeFormat(locale, { month: 'long', year: 'numeric' }).format(current)}
        actions={
          <>
            <MonthSwitcher isCurrent={!month} onChange={(offset) => navigate({ search: offset === null ? {} : { month: shift(offset) } })} />
            {canEdit && (
              <>
                <Button variant="outline" onClick={() => setEditing(true)}>
                  <PencilIcon />
                  {t('Edit')}
                </Button>
                <ConfirmDialog
                  trigger={
                    <Button variant="outline" size="icon" aria-label={t('Delete')}>
                      <Trash2Icon />
                    </Button>
                  }
                  title={t('Delete {{name}}?', { name: budget.name })}
                  description={t('Transactions are kept, they are just no longer part of a budget.')}
                  onConfirm={() => remove.mutate()}
                />
              </>
            )}
          </>
        }
      />

      {budget.limits.map((limit) => (
        <Card key={limit.id} className="mb-4">
          <CardContent className="space-y-2">
            <div className="flex justify-between text-sm">
              <span>
                <Money amount={limit.spent} currency={limit.currency.code} tone="none" className="font-medium" /> {t('of')}{' '}
                <Money amount={limit.amount} currency={limit.currency.code} tone="none" />
              </span>
              <span className="text-muted-foreground">
                {limit.start_date} → {limit.end_date}
              </span>
            </div>
            <ProgressBar value={Number(limit.spent ?? 0)} max={Number(limit.amount)} />
          </CardContent>
        </Card>
      ))}

      <TransactionList
        transactions={transactions?.data}
        empty={<p className="text-muted-foreground surface-card py-10 text-center text-sm">{t('Nothing spent from this budget in this month.')}</p>}
      />

      <BudgetDialog open={editing} onOpenChange={setEditing} budget={budget} />
    </>
  )
}
