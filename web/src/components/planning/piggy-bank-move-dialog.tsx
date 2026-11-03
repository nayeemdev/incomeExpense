import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { SelectField } from '@/components/app/select-field'
import { Field, FieldLabel } from '@/components/ui/field'
import { useLedger } from '@/hooks/use-ledger'
import { api } from '@/lib/api/client'
import { useInvalidateLedger } from '@/lib/api/mutations'
import type { PiggyBank } from '@/lib/api/types'
import { AmountDialog } from './amount-dialog'

type MoveDialogProps = { piggyBank: PiggyBank; direction: 'add' | 'remove' | null; onClose: () => void }

export function PiggyBankMoveDialog({ piggyBank, direction, onClose }: MoveDialogProps) {
  const { t } = useTranslation()
  const ledger = useLedger()
  const invalidate = useInvalidateLedger(ledger.id)
  const [accountId, setAccountId] = useState(String(piggyBank.accounts[0]?.id ?? ''))

  return (
    <AmountDialog
      open={direction !== null}
      onOpenChange={(open) => !open && onClose()}
      title={direction === 'remove' ? t('Take money out of {{name}}', { name: piggyBank.name }) : t('Add money to {{name}}', { name: piggyBank.name })}
      currency={piggyBank.currency.code}
      onSave={async (amount) => {
        await api.post(`/ledgers/${ledger.id}/piggy-banks/${piggyBank.id}/move`, {
          account_id: Number(accountId),
          amount: direction === 'remove' ? `-${amount}` : amount,
        })
        await invalidate()
      }}
    >
      {piggyBank.accounts.length > 1 && (
        <Field>
          <FieldLabel>{t('Account')}</FieldLabel>
          <SelectField
            value={accountId}
            onChange={setAccountId}
            options={Object.fromEntries(piggyBank.accounts.map((account) => [String(account.id), account.name]))}
          />
        </Field>
      )}
    </AmountDialog>
  )
}
