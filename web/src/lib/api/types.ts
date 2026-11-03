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
  dashboard_hidden: string[]
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
  default_income_account_id: number | null
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
  object_group?: string | null
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
  low_balance_threshold?: string | null
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
  category?: CategoryRef | null
  budget?: { id: number; name: string } | null
  bill?: { id: number; name: string } | null
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

export type CategoryRef = { id: number; name: string; icon?: string | null; color?: string | null }

export type CategoryStyles = { icons: string[]; colors: string[] }

export type Category = {
  id: number
  name: string
  icon: string | null
  color: string | null
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
  net_worth_total: CurrencyAmount & { complete: boolean }
  spent_total: CurrencyAmount & { complete: boolean }
  earned_total: CurrencyAmount & { complete: boolean }
  spent: CurrencyAmount[]
  earned: CurrencyAmount[]
  top_categories: Array<{ id: number | null; name: string | null; icon: string | null; color: string | null } & CurrencyAmount>
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
  budget_id?: number | null
  bill_id?: number | null
  tags?: string[]
  notes?: string | null
}

export type TransactionGroupInput = { title?: string | null; transactions: SplitInput[] }

export type Frequency = 'daily' | 'weekly' | 'monthly' | 'quarterly' | 'half_year' | 'yearly'
export type AutoBudgetType = 'reset' | 'rollover' | 'adjusted'

export type BudgetLimit = {
  id: number
  budget_id: number
  currency: Currency
  start_date: string
  end_date: string
  amount: string
  spent: string | null
  generated: boolean
}

export type Budget = {
  id: number
  name: string
  active: boolean
  order: number
  notes: string | null
  auto_budget_type: AutoBudgetType | null
  auto_budget_period: Frequency | null
  auto_budget_currency: Currency | null
  auto_budget_amount: string | null
  limits: BudgetLimit[]
  spent?: CurrencyAmount[]
}

export type AvailableBudget = {
  id: number
  currency: Pick<Currency, 'id' | 'code' | 'decimal_places'>
  start_date: string
  end_date: string
  amount: string
}

export type Bill = {
  id: number
  name: string
  object_group: string | null
  currency: Currency
  amount_min: string
  amount_max: string
  date: string
  repeat_freq: Frequency
  skip: number
  end_date: string | null
  extension_date: string | null
  active: boolean
  order: number
  notes: string | null
  next_expected_date?: string | null
  expected_dates?: string[]
  payments?: Array<{ transaction_id: number; group_id: number; date: string; description: string; amount: string }>
}

export type PiggyBank = {
  id: number
  name: string
  object_group: string | null
  currency: Currency
  target_amount: string | null
  saved_amount: string
  left_to_save: string | null
  start_date: string | null
  target_date: string | null
  active: boolean
  order: number
  notes: string | null
  accounts: Array<{ id: number; name: string; current_amount: string }>
  events?: Array<{ id: number; account: { id: number; name: string }; amount: string; date: string }>
}

export type RepetitionType = 'daily' | 'weekly' | 'monthly' | 'ndom' | 'yearly'
export type WeekendHandling = 'keep' | 'skip' | 'before' | 'after'

export type Recurrence = {
  id: number
  type: 'withdrawal' | 'deposit' | 'transfer'
  title: string
  description: string | null
  first_date: string
  repeat_until: string | null
  max_occurrences: number | null
  occurrences_created: number
  latest_date: string | null
  apply_rules: boolean
  active: boolean
  repetition: { type: RepetitionType; moment: string; skip: number; weekend: WeekendHandling } | null
  transactions: Array<{
    description: string
    amount: string
    currency: Currency | null
    source: AccountRef
    destination: AccountRef
    category: CategoryRef | null
    budget: { id: number; name: string } | null
    bill: { id: number; name: string } | null
    tags: string[]
    notes: string | null
  }>
  upcoming: string[]
}

export type LinkType = { id: number; name: string; outward: string; inward: string; custom: boolean }

export type TransactionLink = {
  id: number
  link_type_id: number
  label: string
  group: { id: number; title: string | null; date: string | null }
  notes: string | null
}

export type AppNotification = {
  id: string
  event: string
  title: string
  body: string
  path: string
  ledger_id: number | null
  read_at: string | null
  created_at: string
}

export type SystemSettings = {
  registration_enabled: boolean
  invitation_expiry_days: number
  exchange_rates_enabled: boolean
  exchange_rates_url: string
  bill_reminder_days: number
  budget_warning_percentage: number
  ntfy_server: string
  attachments_max_size_kb: number
  mail_mailer: 'smtp' | 'log'
  mail_host: string | null
  mail_port: number | null
  mail_scheme: 'smtp' | 'smtps' | null
  mail_username: string | null
  /** True when a password is saved; a string only while the admin is typing a new one. */
  mail_password: boolean | string
  mail_from_address: string | null
  mail_from_name: string | null
}

export type NotificationSettings = {
  channels: string[]
  events: Record<string, string[]>
  quiet_hours: { start: string; end: string } | null
  large_transaction_amount: string | number | null
  push_public_key: string | null
  ntfy: { server: string; topic: string; has_token: boolean; private: boolean } | null
  ntfy_default_server: string
}

export type RuleTrigger = { type: string; value: string | null; negated: boolean }
export type RuleAction = { type: string; value: string | null }

export type Rule = {
  id: number
  rule_group_id: number
  title: string
  description: string | null
  trigger_on: 'store' | 'update' | 'both'
  strict: boolean
  stop_processing: boolean
  active: boolean
  order: number
  triggers: RuleTrigger[]
  actions: RuleAction[]
}

export type RuleGroup = {
  id: number
  title: string
  description: string | null
  order: number
  active: boolean
  stop_processing: boolean
  rules: Rule[]
}

export type RuleOptions = {
  triggers: Array<{ type: string; needs_value: boolean }>
  actions: Array<{ type: string; needs_value: boolean }>
}

export type Webhook = { id: number; title: string; url: string; events: string[]; active: boolean; created_at: string }

export type WebhookDelivery = {
  id: number
  event: string
  status_code: number | null
  attempts: number
  response: string | null
  delivered_at: string | null
  failed_at: string | null
  created_at: string
}

export type ReportMeta = { currency: string; start: string; end: string; interval: 'day' | 'week' | 'month' | 'year'; complete: boolean }

export type BreakdownRow = { id: number | null; name: string | null; total: string; buckets: Record<string, string> }

export type OverviewReport = {
  accounts: Array<{ id: number; name: string; currency: string | null; start: string; end: string; change: string }>
  income_expense: Array<{ start: string; end: string; income: string; expense: string }>
  expense_categories: BreakdownRow[]
  income_categories: BreakdownRow[]
  budgets: Array<{ id: number; name: string; budgeted: string; spent: string }>
  net_worth: Array<{ date: string; balance: string }>
}

export type ExchangeRate = { id: number; from: string; to: string; date: string; rate: string; manual: boolean }

export type SearchToken = { key: string; value: string; negated: boolean }

export type ImportConfig = {
  account_id?: number
  columns?: Record<string, number | null>
  has_header?: boolean
  date_format?: string | null
  decimal_separator?: string | null
  negate?: boolean
  skip_duplicates?: boolean
  apply_rules?: boolean
}

export type ImportJob = {
  id: number
  filename: string
  format: 'csv' | 'camt053'
  status: 'uploaded' | 'queued' | 'running' | 'completed' | 'failed'
  config: ImportConfig | null
  stats: { created: number; duplicates: number; failed: number; failures: Array<{ line: number; message: string }> } | null
  error: string | null
  created_at: string
  completed_at: string | null
  sample?: string[][]
  suggested_columns?: Record<string, number>
}

export type ImportPreviewRow = {
  line: number
  date: string | null
  amount: string | null
  description: string
  counterparty: string | null
  category: string | null
  errors: string[]
  duplicate: boolean
}
