import i18n from '@/i18n'
import type { AccountType, AutoBudgetType, Frequency, RepetitionType, WeekendHandling, AssetRole, LedgerRole, LiabilityDirection, LiabilityType, TransactionType } from './api/types'

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

export const accountTypeDescriptions = (): Record<AccountType, string> => ({
  asset: t('Bank accounts, cards and cash you hold. Their balances add up to your net worth.'),
  expense: t('Shops, services and people you pay. Each is created the first time you pay them.'),
  revenue: t('Employers, clients and people who pay you. Each is created the first time they pay you.'),
  liability: t('Loans and debts in both directions: money you owe, like a car loan, and money owed to you, like a loan to a friend. Record borrowing and paying back as transactions to see what is left.'),
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

export const frequencyLabels = (): Record<Frequency, string> => ({
  daily: t('Daily'),
  weekly: t('Weekly'),
  monthly: t('Monthly'),
  quarterly: t('Quarterly'),
  half_year: t('Every half year'),
  yearly: t('Yearly'),
})

export const autoBudgetLabels = (): Record<AutoBudgetType, string> => ({
  reset: t('Same amount every period'),
  rollover: t('Carry over what is left'),
  adjusted: t('Subtract overspending'),
})

export const repetitionLabels = (): Record<RepetitionType, string> => ({
  daily: t('Every day'),
  weekly: t('Every week'),
  monthly: t('Every month'),
  ndom: t('Every month on a weekday'),
  yearly: t('Every year'),
})

export const weekendLabels = (): Record<WeekendHandling, string> => ({
  keep: t('Keep the date'),
  skip: t('Skip it'),
  before: t('Move to the Friday before'),
  after: t('Move to the Monday after'),
})

export const notificationEventLabels = (): Record<string, string> => ({
  bill_due: t('Bill due soon'),
  bill_overdue: t('Bill overdue'),
  budget_warning: t('Budget almost used'),
  budget_exceeded: t('Budget exceeded'),
  large_transaction: t('Large transaction'),
  low_balance: t('Low account balance'),
  recurring_created: t('Recurring transaction recorded'),
  piggy_bank_reached: t('Piggy bank goal reached'),
  rule_matched: t('Rule with a notify action matched'),
  webhook_failing: t('Webhook failing'),
  weekly_summary: t('Weekly summary'),
  new_login: t('New sign-in'),
  token_created: t('New access token'),
})

export const channelLabels = (): Record<string, string> => ({
  database: t('In app'),
  mail: t('Email'),
  webpush: t('Push'),
})

export const triggerLabels = (): Record<string, string> => ({
  description_is: t('Description is'),
  description_contains: t('Description contains'),
  description_starts: t('Description starts with'),
  description_ends: t('Description ends with'),
  amount_is: t('Amount is'),
  amount_more: t('Amount is more than'),
  amount_less: t('Amount is less than'),
  source_account_is: t('Source account is'),
  source_account_contains: t('Source account contains'),
  destination_account_is: t('Destination account is'),
  destination_account_contains: t('Destination account contains'),
  category_is: t('Category is'),
  budget_is: t('Budget is'),
  bill_is: t('Bill is'),
  tag_is: t('Has tag'),
  has_any_tag: t('Has any tag'),
  has_no_category: t('Has no category'),
  has_no_budget: t('Has no budget'),
  notes_contain: t('Notes contain'),
  currency_is: t('Currency is'),
  transaction_type: t('Type is (withdrawal, deposit, transfer)'),
  date_before: t('Date is before'),
  date_after: t('Date is after'),
})

export const actionLabels = (): Record<string, string> => ({
  set_category: t('Set category to'),
  clear_category: t('Clear category'),
  set_budget: t('Set budget to'),
  clear_budget: t('Clear budget'),
  link_to_bill: t('Link to bill'),
  add_tag: t('Add tag'),
  remove_tag: t('Remove tag'),
  remove_all_tags: t('Remove all tags'),
  set_description: t('Set description to'),
  prepend_description: t('Put text before description'),
  append_description: t('Put text after description'),
  set_notes: t('Set notes to'),
  append_notes: t('Add to notes'),
  clear_notes: t('Clear notes'),
  set_destination_account: t('Set payee (expense account) to'),
  set_source_account: t('Set payer (revenue account) to'),
  delete_transaction: t('Delete the transaction'),
  notify: t('Notify ledger members'),
})
