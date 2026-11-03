import { useQuery } from '@tanstack/react-query'
import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { Skeleton } from '@/components/ui/skeleton'
import { useLedger } from '@/hooks/use-ledger'
import { useSaveTransaction } from '@/lib/api/mutations'
import { transactionGroupQuery } from '@/lib/api/queries'
import { FormDialog } from './form-dialog'
import type { QuickAddDefaults } from './quick-add-context'
import { TransactionForm } from './transaction-form'
import { emptySplit, groupToFormValues, newTransactionValues } from './transaction-form-values'

type QuickAddDialogProps = {
  open: boolean
  onOpenChange: (open: boolean) => void
  defaults: QuickAddDefaults
}

/**
 * Adding, splitting and editing transactions all happen in this one dialog.
 */
export function QuickAddDialog({ open, onOpenChange, defaults }: QuickAddDialogProps) {
  const { t } = useTranslation()
  const ledger = useLedger()
  const editing = defaults.groupId !== undefined
  const save = useSaveTransaction(ledger.id, defaults.groupId)
  const { data: group } = useQuery({ ...transactionGroupQuery(ledger.id, defaults.groupId ?? 0), enabled: open && editing })
  const type = defaults.type === 'deposit' || defaults.type === 'transfer' ? defaults.type : 'withdrawal'
  const accountId = defaults.accountId ?? ledger.default_account_id ?? undefined

  const values = useMemo(() => {
    if (editing) return group ? groupToFormValues(group) : null

    const base = newTransactionValues(type, accountId)

    return defaults.split ? { ...base, splits: [...base.splits, emptySplit({ source: base.splits[0].source })] } : base
  }, [editing, group, type, accountId, defaults.split])

  return (
    <FormDialog
      open={open}
      onOpenChange={onOpenChange}
      size="xl"
      title={editing ? t('Edit transaction') : defaults.split ? t('New split transaction') : t('New transaction')}
      description={editing ? undefined : t('Tip: press N anywhere to open this, and ⌘ Enter to save.')}
    >
      {values ? (
        <TransactionForm
          defaultValues={values}
          submitLabel={editing ? t('Save changes') : t('Save')}
          onCancel={() => onOpenChange(false)}
          onSubmit={async (input) => {
            await save.mutateAsync(input)
            toast.success(editing ? t('Transaction updated') : t('Transaction saved'))
            onOpenChange(false)
          }}
        />
      ) : (
        <Skeleton className="h-80 w-full" />
      )}
    </FormDialog>
  )
}
