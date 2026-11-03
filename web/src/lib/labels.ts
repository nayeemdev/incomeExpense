import i18n from '@/i18n'
import type { AccountType, AssetRole, LedgerRole, LiabilityDirection, LiabilityType, TransactionType } from './api/types'

const t = (key: string) => i18n.t(key)

export const accountTypeLabels = (): Record<AccountType, string> => ({
  asset: t('Asset accounts'),
  expense: t('Expense accounts'),
  revenue: t('Revenue accounts'),
  liability: t('Liabilities'),
})

export const accountTypeSingular = (): Record<AccountType, string> => ({
  asset: t('Asset account'),
  expense: t('Expense account'),
  revenue: t('Revenue account'),
  liability: t('Liability'),
})

export const assetRoleLabels = (): Record<AssetRole, string> => ({
  default: t('Checking'),
  shared: t('Shared'),
  savings: t('Savings'),
  cash_wallet: t('Cash wallet'),
  credit_card: t('Credit card'),
})

export const liabilityTypeLabels = (): Record<LiabilityType, string> => ({
  loan: t('Loan'),
  debt: t('Debt'),
  mortgage: t('Mortgage'),
})

export const liabilityDirectionLabels = (): Record<LiabilityDirection, string> => ({
  owed: t('I owe this'),
  lent: t('I am owed this'),
})

export const transactionTypeLabels = (): Record<TransactionType, string> => ({
  withdrawal: t('Expense'),
  deposit: t('Income'),
  transfer: t('Transfer'),
  opening_balance: t('Opening balance'),
  reconciliation: t('Reconciliation'),
})

export const roleLabels = (): Record<LedgerRole, string> => ({
  owner: t('Owner'),
  editor: t('Editor'),
  viewer: t('Viewer'),
})
