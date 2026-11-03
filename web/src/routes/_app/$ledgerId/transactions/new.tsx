import { createFileRoute, useNavigate } from '@tanstack/react-router'
import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { PageHeader } from '@/components/app/page-header'
import { TransactionForm } from '@/components/app/transaction-form'
import { emptySplit, newTransactionValues } from '@/components/app/transaction-form-values'
import { Card, CardContent } from '@/components/ui/card'
import { useLedger } from '@/hooks/use-ledger'
import { useSaveTransaction } from '@/lib/api/mutations'

export const Route = createFileRoute('/_app/$ledgerId/transactions/new')({
  component: NewTransaction,
})

function NewTransaction() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const ledger = useLedger()
  const save = useSaveTransaction(ledger.id)
  const ledgerId = String(ledger.id)
  const values = useMemo(() => {
    const base = newTransactionValues('withdrawal', ledger.default_account_id ?? undefined)

    return { ...base, splits: [...base.splits, emptySplit({ source: base.splits[0].source })] }
  }, [ledger.default_account_id])

  return (
    <>
      <PageHeader title={t('New split transaction')} description={t('Split one payment across categories, for example a supermarket receipt.')} />
      <Card>
        <CardContent>
          <TransactionForm
            defaultValues={values}
            submitLabel={t('Save')}
            onCancel={() => navigate({ to: '/$ledgerId/transactions', params: { ledgerId } })}
            onSubmit={async (input) => {
              const group = await save.mutateAsync(input)
              toast.success(t('Transaction saved'))
              await navigate({ to: '/$ledgerId/transactions/$groupId', params: { ledgerId, groupId: String(group.id) } })
            }}
          />
        </CardContent>
      </Card>
    </>
  )
}
