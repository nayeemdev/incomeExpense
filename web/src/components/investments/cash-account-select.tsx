import { useQuery } from '@tanstack/react-query'
import { SelectField } from '@/components/app/select-field'
import { useLedger } from '@/hooks/use-ledger'
import { accountsQuery } from '@/lib/api/queries'

type CashAccountSelectProps = {
  id?: string
  value: string
  onChange: (value: string) => void
  currencyId: string
  emptyLabel?: string
}

/**
 * Asset accounts money can come from or go to, in the investment's currency. Investment accounts are left out.
 */
export function CashAccountSelect({ id, value, onChange, currencyId, emptyLabel }: CashAccountSelectProps) {
  const ledger = useLedger()
  const { data: accounts = [] } = useQuery({ ...accountsQuery(ledger.id, { type: ['asset'], active: true }), select: (page) => page.data })
  const options = Object.fromEntries(
    accounts
      .filter((account) => account.asset_role !== 'investment' && account.currency?.id === currencyId)
      .map((account) => [account.id, account.name]),
  )

  return <SelectField id={id} value={value} onChange={onChange} options={emptyLabel ? { none: emptyLabel, ...options } : options} />
}
