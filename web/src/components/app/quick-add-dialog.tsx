import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { useLedger } from '@/hooks/use-ledger'
import { useSaveTransaction } from '@/lib/api/mutations'
import type { QuickAddDefaults } from './quick-add-context'
import { TransactionForm } from './transaction-form'
import { newTransactionValues } from './transaction-form-values'

type QuickAddDialogProps = {
  open: boolean
  onOpenChange: (open: boolean) => void
  defaults: QuickAddDefaults
}

export function QuickAddDialog({ open, onOpenChange, defaults }: QuickAddDialogProps) {
  const { t } = useTranslation()
  const ledger = useLedger()
  const save = useSaveTransaction(ledger.id)
  const type = defaults.type === 'deposit' || defaults.type === 'transfer' ? defaults.type : 'withdrawal'
  const accountId = defaults.accountId ?? ledger.default_account_id ?? undefined
  const values = useMemo(() => newTransactionValues(type, accountId), [type, accountId])

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle>{t('New transaction')}</DialogTitle>
          <DialogDescription>{t('Tip: press N anywhere to open this, and ⌘ Enter to save.')}</DialogDescription>
        </DialogHeader>
        <TransactionForm
          defaultValues={values}
          allowSplits={false}
          submitLabel={t('Save')}
          onCancel={() => onOpenChange(false)}
          onSubmit={async (input) => {
            await save.mutateAsync(input)
            toast.success(t('Transaction saved'))
            onOpenChange(false)
          }}
        />
      </DialogContent>
    </Dialog>
  )
}
