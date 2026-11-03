import { useMutation, useQuery } from '@tanstack/react-query'
import { createFileRoute, useNavigate } from '@tanstack/react-router'
import { PencilIcon, Trash2Icon } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ConfirmDialog } from '@/components/app/confirm-dialog'
import { Money } from '@/components/app/money'
import { PageHeader } from '@/components/app/page-header'
import { Pagination } from '@/components/app/pagination'
import { TransactionList } from '@/components/app/transaction-list'
import { BillForm } from '@/components/planning/bill-form'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader } from '@/components/ui/card'
import { FormDialog } from '@/components/app/form-dialog'
import { Skeleton } from '@/components/ui/skeleton'
import { useCanEdit, useLedger, useLocale } from '@/hooks/use-ledger'
import { api } from '@/lib/api/client'
import { useInvalidateLedger } from '@/lib/api/mutations'
import { transactionsQuery } from '@/lib/api/queries'
import type { Bill, Resource } from '@/lib/api/types'
import { formatDate } from '@/lib/format'
import { notifyError } from '@/lib/forms'
import { frequencyLabels } from '@/lib/labels'
import { ledgerKey } from '@/lib/api/queries'

export const Route = createFileRoute('/_app/$ledgerId/bills/$billId')({
  component: BillDetail,
})

function BillDetail() {
  const { t } = useTranslation()
  const locale = useLocale()
  const navigate = useNavigate()
  const ledger = useLedger()
  const canEdit = useCanEdit()
  const invalidate = useInvalidateLedger(ledger.id)
  const [editing, setEditing] = useState(false)
  const [page, setPage] = useState(1)
  const billId = Number(Route.useParams().billId)
  const { data: bill } = useQuery({
    queryKey: [...ledgerKey(ledger.id), 'bills', 'detail', billId],
    queryFn: () => api.get<Resource<Bill>>(`/ledgers/${ledger.id}/bills/${billId}`).then((r) => r.data),
  })
  const { data: transactions } = useQuery(transactionsQuery(ledger.id, { bill_id: billId, page }))

  const remove = useMutation({
    mutationFn: () => api.delete(`/ledgers/${ledger.id}/bills/${billId}`),
    onSuccess: async () => {
      await navigate({ to: '/$ledgerId/bills', params: { ledgerId: String(ledger.id) } })
      await invalidate()
    },
    onError: notifyError,
  })

  if (!bill) return <Skeleton className="h-64 w-full" />

  return (
    <>
      <PageHeader
        title={bill.name}
        description={frequencyLabels()[bill.repeat_freq]}
        actions={
          canEdit && (
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
                title={t('Delete {{name}}?', { name: bill.name })}
                description={t('Payments are kept, they are just no longer linked to a bill.')}
                onConfirm={() => remove.mutate()}
              />
            </>
          )
        }
      />
      <div className="mb-6 grid gap-4 sm:grid-cols-2">
        <Card>
          <CardHeader>
            <CardDescription>{t('Expected amount')}</CardDescription>
          </CardHeader>
          <CardContent className="text-xl font-semibold">
            <Money amount={bill.amount_min} currency={bill.currency.code} tone="none" />
            {bill.amount_max !== bill.amount_min && (
              <>
                {` ${t('to')} `}
                <Money amount={bill.amount_max} currency={bill.currency.code} tone="none" />
              </>
            )}
          </CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardDescription>{t('Next due')}</CardDescription>
          </CardHeader>
          <CardContent className="text-xl font-semibold">
            {bill.next_expected_date ? formatDate(bill.next_expected_date, locale, { dateStyle: 'long' }) : t('Not scheduled')}
          </CardContent>
        </Card>
      </div>
      <h2 className="mb-3 font-medium">{t('Payments')}</h2>
      <TransactionList
        transactions={transactions?.data}
        empty={<p className="text-muted-foreground surface-card py-10 text-center text-sm">{t('Link a payment to this bill when you record it.')}</p>}
      />
      {transactions && <Pagination page={transactions.meta.current_page} lastPage={transactions.meta.last_page} onChange={setPage} />}
      <FormDialog open={editing} onOpenChange={setEditing} title={t('Edit {{name}}', { name: bill.name })} size="lg">
        <BillForm bill={bill} onCancel={() => setEditing(false)} onSaved={() => setEditing(false)} />
      </FormDialog>
    </>
  )
}
