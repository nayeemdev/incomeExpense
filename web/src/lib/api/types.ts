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
  id: string
  name: string
  email: string
  is_admin: boolean
  two_factor_enabled: boolean
  preferences: Preferences
  created_at: string
}

export type Currency = { id: string; code: string; name: string; decimal_places: number }

export type LedgerRole = 'owner' | 'editor' | 'viewer'

export type Ledger = {
  id: string
  name: string
  primary_currency: Currency
  fiscal_year_start: string
  role: LedgerRole
  default_account_id: string | null
  default_income_account_id: string | null
  created_at: string
}

export type LedgerMember = { id: string; name: string; email: string; role: LedgerRole; joined_at: string }

export type LedgerInvitation = {
  id: string
  email: string
  role: LedgerRole
  ledger?: Ledger
  invited_by?: string
  expires_at: string
  created_at: string
}

export type AccountType = 'asset' | 'expense' | 'revenue' | 'liability'
export type AssetRole = 'default' | 'shared' | 'savings' | 'cash_wallet' | 'credit_card' | 'investment'
export type LiabilityType = 'loan' | 'debt' | 'mortgage'
export type LiabilityDirection = 'owed' | 'lent'
export type InterestPeriod = 'daily' | 'monthly' | 'yearly'

export type Account = {
  id: string
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

export type AccountRef = { id: string; name: string; type: AccountType | 'initial_balance' | 'reconciliation' }

export type Transaction = {
  id: string
  group_id: string
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
  budget?: { id: string; name: string } | null
  bill?: { id: string; name: string } | null
  tags?: Array<{ id: string; name: string }>
  notes: string | null
  running_balance?: string
  created_at: string
  updated_at: string
}

export type TransactionGroup = {
  id: string
  title: string | null
  transactions: Transaction[]
  created_by?: { id: string; name: string } | null
  created_at: string
  updated_at: string
}

export type CategoryRef = { id: string; name: string; icon?: string | null; color?: string | null }

export type CategoryStyles = { icons: string[]; colors: string[] }

export type Category = {
  id: string
  name: string
  icon: string | null
  color: string | null
  notes: string | null
  transactions_count?: number
  created_at: string
  updated_at: string
}

export type Tag = {
  id: string
  name: string
  description: string | null
  date: string | null
  transactions_count?: number
  created_at: string
  updated_at: string
}

export type Attachment = {
  id: string
  attachable_type: 'account' | 'transaction_group'
  attachable_id: string
  filename: string
  title: string | null
  mime_type: string
  size: number
  created_at: string
}

export type PersonalAccessToken = {
  id: string
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
  top_categories: Array<{ id: string | null; name: string | null; icon: string | null; color: string | null } & CurrencyAmount>
  accounts: Account[]
}

export type SplitInput = {
  type: TransactionType
  date: string
  description: string
  amount: string
  source_id?: string | null
  source_name?: string | null
  destination_id?: string | null
  destination_name?: string | null
  foreign_amount?: string | null
  foreign_currency_id?: string | null
  category_id?: string | null
  category_name?: string | null
  budget_id?: string | null
  bill_id?: string | null
  tags?: string[]
  notes?: string | null
}

export type TransactionGroupInput = { title?: string | null; transactions: SplitInput[] }

export type Frequency = 'daily' | 'weekly' | 'monthly' | 'quarterly' | 'half_year' | 'yearly'
export type AutoBudgetType = 'reset' | 'rollover' | 'adjusted'

export type BudgetLimit = {
  id: string
  budget_id: string
  currency: Currency
  start_date: string
  end_date: string
  amount: string
  spent: string | null
  generated: boolean
}

export type Budget = {
  id: string
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
  id: string
  currency: Pick<Currency, 'id' | 'code' | 'decimal_places'>
  start_date: string
  end_date: string
  amount: string
}

export type Bill = {
  id: string
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
  payments?: Array<{ transaction_id: string; group_id: string; date: string; description: string; amount: string }>
}

export type PiggyBank = {
  id: string
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
  accounts: Array<{ id: string; name: string; current_amount: string }>
  events?: Array<{ id: string; account: { id: string; name: string }; amount: string; date: string }>
}

export type InvestmentKind = 'stock' | 'etf' | 'mutual_fund' | 'deposit' | 'savings_scheme' | 'private' | 'gold' | 'property' | 'other'
export type InvestmentEventType = 'buy' | 'sell' | 'income' | 'valuation'

export type InvestmentEvent = {
  id: string
  type: InvestmentEventType
  date: string
  units: string | null
  price: string | null
  amount: string
  gain: string | null
  transaction_group_id: string | null
  reinvested: boolean
  user: { id: string; name: string } | null
}

export type Investment = {
  id: string
  name: string
  kind: InvestmentKind
  tracks_units: boolean
  symbol: string | null
  account_id: string
  currency: Currency
  interest_rate: string | null
  payout_frequency: Frequency | null
  start_date: string | null
  maturity_date: string | null
  closed_at: string | null
  notes: string | null
  units: string
  price: string | null
  valued_on: string | null
  cost: string
  value: string
  gain: string
  gain_percent: string | null
  invested: string
  income: string
  realized: string
  payout_amount: string | null
  tax_rate: string | null
  annual_return: number | null
  contribution: { amount: string; frequency: Frequency; account_id: string; next_date: string | null } | null
  upcoming_payouts: Array<{ date: string; amount: string | null; overdue: boolean }>
  events?: InvestmentEvent[]
  history?: Array<{ date: string; value: string; cost: string }>
  roadmap?: InvestmentRoadmap | null
  contributors?: Array<{ id: string | null; name: string | null; invested: string; share: number; income: string; value: string }>
}

export type InvestmentRoadmap = {
  end: string
  points: Array<{ date: string; amount: string; cumulative: string; status: 'received' | 'due' | 'expected' }>
  total_return: string
  break_even: string | null
  installments_left: number
  installments_total: string
  maturity_value: string | null
  maturity_gain: string | null
}

export type InvestmentCalendarItem = {
  investment_id: string
  name: string
  kind: InvestmentKind
  currency: string
  type: 'payout' | 'installment' | 'maturity'
  date: string
  amount: string | null
  overdue: boolean
}

export type InvestmentIncome = {
  start: string
  end: string
  rows: Array<{ id: string; name: string; kind: InvestmentKind; currency: string; income: string; realized: string }>
  currency: string
  complete: boolean
  income: string
  realized: string
}

export type Zakat = {
  date: string
  currency: string
  complete: boolean
  items: Array<{ group: 'cash' | 'investments' | 'gold' | 'receivables' | 'debts'; name: string; amount: string }>
  total: string
  nisab: string
  nisab_gold_grams: number
  rate: number
  due: boolean
  zakat: string
}

export type PortfolioSummary = {
  currency: string
  complete: boolean
  value: string
  cost: string
  gain: string
  income: string
  realized: string
  gain_percent: string | null
  annual_return: number | null
  allocation: Array<{ kind: InvestmentKind; value: string }>
  targets: Array<{ kind: InvestmentKind; percent: number }>
}

export type RepetitionType = 'daily' | 'weekly' | 'monthly' | 'ndom' | 'yearly'
export type WeekendHandling = 'keep' | 'skip' | 'before' | 'after'

export type Recurrence = {
  id: string
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
    budget: { id: string; name: string } | null
    bill: { id: string; name: string } | null
    tags: string[]
    notes: string | null
  }>
  upcoming: string[]
}

export type LinkType = { id: string; name: string; outward: string; inward: string; custom: boolean }

export type TransactionLink = {
  id: string
  link_type_id: string
  label: string
  group: { id: string; title: string | null; date: string | null }
  notes: string | null
}

export type AppNotification = {
  id: string
  event: string
  title: string
  body: string
  path: string
  ledger_id: string | null
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
  id: string
  rule_group_id: string
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
  id: string
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

export type Webhook = { id: string; title: string; url: string; events: string[]; active: boolean; created_at: string }

export type WebhookDelivery = {
  id: string
  event: string
  status_code: number | null
  attempts: number
  response: string | null
  delivered_at: string | null
  failed_at: string | null
  created_at: string
}

export type ReportMeta = { currency: string; start: string; end: string; interval: 'day' | 'week' | 'month' | 'year'; complete: boolean }

export type BreakdownRow = { id: string | null; name: string | null; total: string; buckets: Record<string, string> }

export type OverviewReport = {
  accounts: Array<{ id: string; name: string; currency: string | null; start: string; end: string; change: string }>
  income_expense: Array<{ start: string; end: string; income: string; expense: string }>
  expense_categories: BreakdownRow[]
  income_categories: BreakdownRow[]
  budgets: Array<{ id: string; name: string; budgeted: string; spent: string }>
  net_worth: Array<{ date: string; balance: string }>
}

export type ExchangeRate = { id: string; from: string; to: string; date: string; rate: string; manual: boolean }

export type SearchToken = { key: string; value: string; negated: boolean }

export type ImportConfig = {
  account_id?: string
  columns?: Record<string, number | null>
  has_header?: boolean
  date_format?: string | null
  decimal_separator?: string | null
  negate?: boolean
  skip_duplicates?: boolean
  apply_rules?: boolean
}

export type ImportJob = {
  id: string
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
