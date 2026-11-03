import { useMutation, useQuery } from '@tanstack/react-query'
import { createFileRoute } from '@tanstack/react-router'
import { MoreHorizontalIcon, PlusIcon } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { Money } from '@/components/app/money'
import { PageHeader } from '@/components/app/page-header'
import { RecurrenceForm } from '@/components/planning/recurrence-form'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet'
import { Skeleton } from '@/components/ui/skeleton'
import { useCanEdit, useLedger, useLocale } from '@/hooks/use-ledger'
import { api } from '@/lib/api/client'
import { useInvalidateLedger } from '@/lib/api/mutations'
import { recurrencesQuery } from '@/lib/api/queries'
import type { Recurrence } from '@/lib/api/types'
import { formatDate } from '@/lib/format'
import { notifyError } from '@/lib/forms'
import { describeSchedule } from '@/lib/recurrence'
import { accountDisplayName } from '@/lib/transactions'

export const Route = createFileRoute('/_app/$ledgerId/recurring/')({
  component: RecurringTransactions,
})

function RecurringTransactions() {
  const { t } = useTranslation()
  const locale = useLocale()
  const ledger = useLedger()
  const canEdit = useCanEdit()
  const invalidate = useInvalidateLedger(ledger.id)
  const [editing, setEditing] = useState<Recurrence | 'new' | null>(null)
  const { data: recurrences } = useQuery(recurrencesQuery(ledger.id))

  const trigger = useMutation({
    mutationFn: (id: number) => api.post(`/ledgers/${ledger.id}/recurrences/${id}/trigger`),
    onSuccess: async () => {
      await invalidate()
      toast.success(t('Transaction created for today'))
    },
    onError: notifyError,
  })

  const remove = useMutation({
    mutationFn: (id: number) => api.delete(`/ledgers/${ledger.id}/recurrences/${id}`),
    onSuccess: () => invalidate(),
    onError: notifyError,
  })

  return (
    <>
      <PageHeader
        title={t('Recurring transactions')}
        description={t('Salary, rent and subscriptions are recorded for you on schedule.')}
        actions={
          canEdit && (
            <Button onClick={() => setEditing('new')}>
              <PlusIcon />
              {t('New recurring transaction')}
            </Button>
          )
        }
      />
      {!recurrences && <Skeleton className="h-48 w-full" />}
      {recurrences?.length === 0 && (
        <Empty className="border">
          <EmptyHeader>
            <EmptyTitle>{t('Nothing repeats yet')}</EmptyTitle>
            <EmptyDescription>{t('Set it up once and LedgerFly records it every time it is due.')}</EmptyDescription>
          </EmptyHeader>
          {canEdit && (
            <EmptyContent>
              <Button onClick={() => setEditing('new')}>{t('New recurring transaction')}</Button>
            </EmptyContent>
          )}
        </Empty>
      )}
      {recurrences && recurrences.length > 0 && (
        <ul className="divide-y rounded-lg border">
          {recurrences.map((recurrence) => {
            const split = recurrence.transactions[0]

            return (
              <li key={recurrence.id} className="flex flex-wrap items-center gap-3 px-4 py-3">
                <div className="min-w-0 flex-1">
                  <p className="flex items-center gap-2 font-medium">
                    <button type="button" className="truncate hover:underline" onClick={() => canEdit && setEditing(recurrence)}>
                      {recurrence.title}
                    </button>
                    {!recurrence.active && <Badge variant="outline">{t('Paused')}</Badge>}
                  </p>
                  <p className="text-muted-foreground truncate text-xs">
                    {describeSchedule(recurrence, locale)}
                    {split && ` · ${accountDisplayName(split.source)} → ${accountDisplayName(split.destination)}`}
                  </p>
                </div>
                <div className="text-right text-sm">
                  {split?.currency && (
                    <Money
                      amount={recurrence.type === 'withdrawal' ? `-${split.amount}` : split.amount}
                      currency={split.currency.code}
                      tone={recurrence.type === 'transfer' ? 'none' : 'auto'}
                      className="font-medium"
                    />
                  )}
                  <p className="text-muted-foreground text-xs">
                    {recurrence.upcoming[0] ? t('Next {{date}}', { date: formatDate(recurrence.upcoming[0], locale) }) : t('No upcoming dates')}
                  </p>
                </div>
                {canEdit && (
                  <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                      <Button variant="ghost" size="icon" className="size-8" aria-label={t('Actions')}>
                        <MoreHorizontalIcon />
                      </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                      <DropdownMenuItem onSelect={() => setEditing(recurrence)}>{t('Edit')}</DropdownMenuItem>
                      <DropdownMenuItem onSelect={() => trigger.mutate(recurrence.id)}>{t('Create for today')}</DropdownMenuItem>
                      <DropdownMenuItem variant="destructive" onSelect={() => remove.mutate(recurrence.id)}>
                        {t('Delete')}
                      </DropdownMenuItem>
                    </DropdownMenuContent>
                  </DropdownMenu>
                )}
              </li>
            )
          })}
        </ul>
      )}
      <Sheet open={editing !== null} onOpenChange={(open) => !open && setEditing(null)}>
        <SheetContent className="w-full overflow-y-auto sm:max-w-xl">
          <SheetHeader>
            <SheetTitle>{editing === 'new' ? t('New recurring transaction') : t('Edit recurring transaction')}</SheetTitle>
          </SheetHeader>
          <div className="px-4 pb-4">
            {editing !== null && (
              <RecurrenceForm
                key={editing === 'new' ? 'new' : editing.id}
                recurrence={editing === 'new' ? undefined : editing}
                onCancel={() => setEditing(null)}
                onSaved={() => setEditing(null)}
              />
            )}
          </div>
        </SheetContent>
      </Sheet>
    </>
  )
}
