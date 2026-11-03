export type Paginated<T> = {
  data: T[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

export type Resource<T> = { data: T }

export type Preferences = {
  locale: string
  timezone: string
  theme: 'system' | 'light' | 'dark'
  first_day_of_week: number
  view_range: 'week' | 'month' | 'quarter' | 'year'
}

export type User = {
  id: number
  name: string
  email: string
  is_admin: boolean
  two_factor_enabled: boolean
  preferences: Preferences
  created_at: string
}

export type Currency = { id: number; code: string; name: string; decimal_places: number }

export type LedgerRole = 'owner' | 'editor' | 'viewer'

export type Ledger = {
  id: number
  name: string
  primary_currency: Currency
  fiscal_year_start: string
  role: LedgerRole
  default_account_id: number | null
  created_at: string
}

export type LedgerMember = { id: number; name: string; email: string; role: LedgerRole; joined_at: string }

export type LedgerInvitation = {
  id: number
  email: string
  role: LedgerRole
  ledger?: Ledger
  invited_by?: string
  expires_at: string
  created_at: string
}

export type AccountType = 'asset' | 'expense' | 'revenue' | 'liability'
export type AssetRole = 'default' | 'shared' | 'savings' | 'cash_wallet' | 'credit_card'
export type LiabilityType = 'loan' | 'debt' | 'mortgage'
export type LiabilityDirection = 'owed' | 'lent'
export type InterestPeriod = 'daily' | 'monthly' | 'yearly'

export type Account = {
  id: number
  type: AccountType
  name: string
  currency: Currency | null
  asset_role: AssetRole | null
  liability_type: LiabilityType | null
  liability_direction: LiabilityDirection | null
  interest: string | null
  interest_period: InterestPeriod | null
  credit_card_payment_day: number | null
  iban: string | null
  bic: string | null
  account_number: string | null
  virtual_balance: string | null
  balance: string | null
  opening_balance?: string | null
  opening_balance_date?: string | null
  active: boolean
  include_net_worth: boolean
  order: number
  notes: string | null
  created_at: string
  updated_at: string
}

export type TransactionType = 'withdrawal' | 'deposit' | 'transfer' | 'opening_balance' | 'reconciliation'

export type AccountRef = { id: number; name: string; type: AccountType | 'initial_balance' | 'reconciliation' }

export type Transaction = {
  id: number
  group_id: number
  group_title?: string | null
  type: TransactionType
  date: string
  description: string
  source: AccountRef
  destination: AccountRef
  currency: Currency
  amount: string
  foreign_currency: Currency | null
  foreign_amount: string | null
  category?: { id: number; name: string } | null
  tags?: Array<{ id: number; name: string }>
  notes: string | null
  running_balance?: string
  created_at: string
  updated_at: string
}

export type TransactionGroup = {
  id: number
  title: string | null
  transactions: Transaction[]
  created_by?: { id: number; name: string } | null
  created_at: string
  updated_at: string
}

export type Category = {
  id: number
  name: string
  notes: string | null
  transactions_count?: number
  created_at: string
  updated_at: string
}

export type Tag = {
  id: number
  name: string
  description: string | null
  date: string | null
  transactions_count?: number
  created_at: string
  updated_at: string
}

export type Attachment = {
  id: number
  attachable_type: 'account' | 'transaction_group'
  attachable_id: number
  filename: string
  title: string | null
  mime_type: string
  size: number
  created_at: string
}

export type PersonalAccessToken = {
  id: number
  name: string
  abilities: string[]
  last_used_at: string | null
  created_at: string
}

export type CurrencyAmount = { currency: string; amount: string }

export type Summary = {
  net_worth: CurrencyAmount[]
  spent: CurrencyAmount[]
  earned: CurrencyAmount[]
  top_categories: Array<{ id: number | null; name: string | null } & CurrencyAmount>
  accounts: Account[]
}

export type SplitInput = {
  type: TransactionType
  date: string
  description: string
  amount: string
  source_id?: number | null
  source_name?: string | null
  destination_id?: number | null
  destination_name?: string | null
  foreign_amount?: string | null
  foreign_currency_id?: number | null
  category_id?: number | null
  category_name?: string | null
  tags?: string[]
  notes?: string | null
}

export type TransactionGroupInput = { title?: string | null; transactions: SplitInput[] }
