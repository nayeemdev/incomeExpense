import { useTranslation } from 'react-i18next'
import type { Account, TransactionType } from '@/lib/api/types'
import { allowedAccountTypes, isCounterparty } from '@/lib/transactions'
import { accountTypeLabels } from '@/lib/labels'
import type { AccountChoice } from '@/lib/transactions'
import { Combobox, type ComboboxOption } from './combobox'

type AccountPickerProps = {
  id?: string
  type: TransactionType
  side: 'source' | 'destination'
  accounts: Account[]
  value: AccountChoice
  onChange: (value: AccountChoice) => void
  'aria-invalid'?: boolean
}

export function AccountPicker({ id, type, side, accounts, value, onChange, ...props }: AccountPickerProps) {
  const { t } = useTranslation()
  const labels = accountTypeLabels()
  const allowed = allowedAccountTypes(type, side)
  const counterparty = isCounterparty(type, side)

  const options: ComboboxOption[] = accounts
    .filter((account) => allowed.includes(account.type) && account.active)
    .map((account) => ({
      value: String(account.id),
      label: account.name,
      group: labels[account.type],
      hint: account.currency?.code,
    }))

  const selected = value && 'id' in value ? String(value.id) : null
  const typedName = value && 'name' in value ? value.name : undefined

  return (
    <Combobox
      id={id}
      aria-invalid={props['aria-invalid']}
      options={options}
      value={selected}
      displayValue={typedName}
      onChange={(next) => onChange(next ? { id: Number(next) } : null)}
      onCreate={counterparty ? (name) => onChange({ name }) : undefined}
      createLabel={(name) => t('Use "{{name}}"', { name })}
      placeholder={
        counterparty
          ? type === 'withdrawal'
            ? t('Paid to…')
            : t('Received from…')
          : t('Choose an account')
      }
      searchPlaceholder={counterparty ? t('Search or type a new name…') : t('Search accounts…')}
    />
  )
}
