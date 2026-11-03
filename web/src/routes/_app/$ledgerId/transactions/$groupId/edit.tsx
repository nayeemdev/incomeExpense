import { useQuery } from '@tanstack/react-query'
import { createFileRoute, useNavigate } from '@tanstack/react-router'
import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { PageHeader } from '@/components/app/page-header'
import { TransactionForm } from '@/components/app/transaction-form'
import { groupToFormValues } from '@/components/app/transaction-form-values'
import { Card, CardContent } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { useLedger } from '@/hooks/use-ledger'
import { useSaveTransaction } from '@/lib/api/mutations'
import { transactionGroupQuery } from '@/lib/api/queries'

export const Route = createFileRoute('/_app/$ledgerId/transactions/$groupId/edit')({
  component: EditTransaction,
})

function EditTransaction() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const ledger = useLedger()
  const groupId = Number(Route.useParams().groupId)
  const { data: group } = useQuery(transactionGroupQuery(ledger.id, groupId))
  const save = useSaveTransaction(ledger.id, groupId)
  const values = useMemo(() => (group ? groupToFormValues(group) : null), [group])
  const params = { ledgerId: String(ledger.id), groupId: String(groupId) }

  return (
    <>
      <PageHeader title={t('Edit transaction')} />
      <Card>
        <CardContent>
          {values ? (
            <TransactionForm
              defaultValues={values}
              submitLabel={t('Save changes')}
              onCancel={() => navigate({ to: '/$ledgerId/transactions/$groupId', params })}
              onSubmit={async (input) => {
                await save.mutateAsync(input)
                toast.success(t('Transaction updated'))
                await navigate({ to: '/$ledgerId/transactions/$groupId', params })
              }}
            />
          ) : (
            <Skeleton className="h-96 w-full" />
          )}
        </CardContent>
      </Card>
    </>
  )
}
