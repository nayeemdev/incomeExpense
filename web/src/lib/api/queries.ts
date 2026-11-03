import { keepPreviousData, queryOptions } from '@tanstack/react-query'
import { api } from './client'
import type {
  Account,
  AccountType,
  Attachment,
  AppNotification,
  AvailableBudget,
  NotificationSettings,
  RuleGroup,
  RuleOptions,
  Webhook,
  WebhookDelivery,
  Bill,
  Budget,
  LinkType,
  PiggyBank,
  Recurrence,
  TransactionLink,
  Category,
  Currency,
  Ledger,
  LedgerInvitation,
  LedgerMember,
  Paginated,
  PersonalAccessToken,
  Resource,
  Summary,
  Tag,
  Transaction,
  TransactionGroup,
  TransactionType,
  User,
} from './types'

export type TransactionFilters = {
  account_id?: number
  budget_id?: number
  bill_id?: number
  category_id?: number
  tag_id?: number
  type?: TransactionType[]
  start?: string
  end?: string
  search?: string
  page?: number
}

export const ledgerKey = (ledgerId: number) => ['ledgers', ledgerId] as const

export const userQuery = queryOptions({
  queryKey: ['user'],
  queryFn: () => api.get<Resource<User>>('/user').then((r) => r.data),
  staleTime: Infinity,
  retry: false,
})

export const currenciesQuery = queryOptions({
  queryKey: ['currencies'],
  queryFn: () => api.get<Resource<Currency[]>>('/currencies').then((r) => r.data),
  staleTime: Infinity,
})

export const ledgersQuery = queryOptions({
  queryKey: ['ledgers'],
  queryFn: () => api.get<Resource<Ledger[]>>('/ledgers').then((r) => r.data),
})

export const ledgerQuery = (ledgerId: number) =>
  queryOptions({
    queryKey: [...ledgerKey(ledgerId), 'detail'],
    queryFn: () => api.get<Resource<Ledger>>(`/ledgers/${ledgerId}`).then((r) => r.data),
  })

export const accountsQuery = (ledgerId: number, params: { type?: AccountType[]; active?: boolean; search?: string } = {}) =>
  queryOptions({
    queryKey: [...ledgerKey(ledgerId), 'accounts', params],
    queryFn: () =>
      api.get<Paginated<Account>>(`/ledgers/${ledgerId}/accounts`, {
        ...params,
        active: params.active === undefined ? undefined : Number(params.active),
        per_page: 200,
      }),
  })

export const accountQuery = (ledgerId: number, accountId: number) =>
  queryOptions({
    queryKey: [...ledgerKey(ledgerId), 'accounts', 'detail', accountId],
    queryFn: () => api.get<Resource<Account>>(`/ledgers/${ledgerId}/accounts/${accountId}`).then((r) => r.data),
  })

export const transactionsQuery = (ledgerId: number, filters: TransactionFilters) =>
  queryOptions({
    queryKey: [...ledgerKey(ledgerId), 'transactions', filters],
    queryFn: () => api.get<Paginated<Transaction>>(`/ledgers/${ledgerId}/transactions`, filters),
    placeholderData: keepPreviousData,
  })

export const transactionGroupQuery = (ledgerId: number, groupId: number) =>
  queryOptions({
    queryKey: [...ledgerKey(ledgerId), 'transactions', 'group', groupId],
    queryFn: () => api.get<Resource<TransactionGroup>>(`/ledgers/${ledgerId}/transactions/${groupId}`).then((r) => r.data),
  })

export const categoriesQuery = (ledgerId: number, search = '') =>
  queryOptions({
    queryKey: [...ledgerKey(ledgerId), 'categories', search],
    queryFn: () => api.get<Paginated<Category>>(`/ledgers/${ledgerId}/categories`, { search, per_page: 200 }),
    placeholderData: keepPreviousData,
  })

export const tagsQuery = (ledgerId: number, search = '') =>
  queryOptions({
    queryKey: [...ledgerKey(ledgerId), 'tags', search],
    queryFn: () => api.get<Paginated<Tag>>(`/ledgers/${ledgerId}/tags`, { search, per_page: 200 }),
    placeholderData: keepPreviousData,
  })

export const summaryQuery = (ledgerId: number, start: string, end: string) =>
  queryOptions({
    queryKey: [...ledgerKey(ledgerId), 'summary', start, end],
    queryFn: () => api.get<Resource<Summary>>(`/ledgers/${ledgerId}/summary`, { start, end }).then((r) => r.data),
    placeholderData: keepPreviousData,
  })

export const suggestionsQuery = (ledgerId: number, search: string) =>
  queryOptions({
    queryKey: [...ledgerKey(ledgerId), 'suggestions', search],
    queryFn: () =>
      api.get<Resource<Transaction[]>>(`/ledgers/${ledgerId}/autocomplete/transactions`, { search }).then((r) => r.data),
    enabled: search.trim().length > 1,
    staleTime: 30_000,
  })

export const attachmentsQuery = (ledgerId: number, type: Attachment['attachable_type'], id: number) =>
  queryOptions({
    queryKey: [...ledgerKey(ledgerId), 'attachments', type, id],
    queryFn: () =>
      api
        .get<Resource<Attachment[]>>(`/ledgers/${ledgerId}/attachments`, { attachable_type: type, attachable_id: id })
        .then((r) => r.data),
  })

export const membersQuery = (ledgerId: number) =>
  queryOptions({
    queryKey: [...ledgerKey(ledgerId), 'members'],
    queryFn: () => api.get<Resource<LedgerMember[]>>(`/ledgers/${ledgerId}/members`).then((r) => r.data),
  })

export const invitationsQuery = (ledgerId: number) =>
  queryOptions({
    queryKey: [...ledgerKey(ledgerId), 'invitations'],
    queryFn: () => api.get<Resource<LedgerInvitation[]>>(`/ledgers/${ledgerId}/invitations`).then((r) => r.data),
  })

export const tokensQuery = queryOptions({
  queryKey: ['tokens'],
  queryFn: () => api.get<Resource<PersonalAccessToken[]>>('/tokens').then((r) => r.data),
})

export const budgetsQuery = (ledgerId: number, start: string, end: string) =>
  queryOptions({
    queryKey: [...ledgerKey(ledgerId), 'budgets', start, end],
    queryFn: () => api.get<Resource<Budget[]>>(`/ledgers/${ledgerId}/budgets`, { start, end }).then((r) => r.data),
    placeholderData: keepPreviousData,
  })

export const budgetQuery = (ledgerId: number, budgetId: number, start: string, end: string) =>
  queryOptions({
    queryKey: [...ledgerKey(ledgerId), 'budgets', 'detail', budgetId, start, end],
    queryFn: () => api.get<Resource<Budget>>(`/ledgers/${ledgerId}/budgets/${budgetId}`, { start, end }).then((r) => r.data),
  })

export const activeBudgetsQuery = (ledgerId: number) =>
  queryOptions({
    queryKey: [...ledgerKey(ledgerId), 'budgets', 'active'],
    queryFn: () => api.get<Resource<Budget[]>>(`/ledgers/${ledgerId}/budgets`, { active: 1 }).then((r) => r.data),
  })

export const availableBudgetsQuery = (ledgerId: number, start: string, end: string) =>
  queryOptions({
    queryKey: [...ledgerKey(ledgerId), 'available-budgets', start, end],
    queryFn: () => api.get<Resource<AvailableBudget[]>>(`/ledgers/${ledgerId}/available-budgets`, { start, end }).then((r) => r.data),
  })

export const billsQuery = (ledgerId: number, start?: string, end?: string) =>
  queryOptions({
    queryKey: [...ledgerKey(ledgerId), 'bills', start, end],
    queryFn: () => api.get<Resource<Bill[]>>(`/ledgers/${ledgerId}/bills`, { start, end }).then((r) => r.data),
    placeholderData: keepPreviousData,
  })

export const piggyBanksQuery = (ledgerId: number) =>
  queryOptions({
    queryKey: [...ledgerKey(ledgerId), 'piggy-banks'],
    queryFn: () => api.get<Resource<PiggyBank[]>>(`/ledgers/${ledgerId}/piggy-banks`).then((r) => r.data),
  })

export const piggyBankQuery = (ledgerId: number, piggyBankId: number) =>
  queryOptions({
    queryKey: [...ledgerKey(ledgerId), 'piggy-banks', 'detail', piggyBankId],
    queryFn: () => api.get<Resource<PiggyBank>>(`/ledgers/${ledgerId}/piggy-banks/${piggyBankId}`).then((r) => r.data),
  })

export const recurrencesQuery = (ledgerId: number) =>
  queryOptions({
    queryKey: [...ledgerKey(ledgerId), 'recurrences'],
    queryFn: () => api.get<Resource<Recurrence[]>>(`/ledgers/${ledgerId}/recurrences`).then((r) => r.data),
  })

export const linkTypesQuery = (ledgerId: number) =>
  queryOptions({
    queryKey: [...ledgerKey(ledgerId), 'link-types'],
    queryFn: () => api.get<Resource<LinkType[]>>(`/ledgers/${ledgerId}/link-types`).then((r) => r.data),
    staleTime: Infinity,
  })

export const transactionLinksQuery = (ledgerId: number, groupId: number) =>
  queryOptions({
    queryKey: [...ledgerKey(ledgerId), 'transactions', 'links', groupId],
    queryFn: () => api.get<Resource<TransactionLink[]>>(`/ledgers/${ledgerId}/transactions/${groupId}/links`).then((r) => r.data),
  })

export const objectGroupsQuery = (ledgerId: number) =>
  queryOptions({
    queryKey: [...ledgerKey(ledgerId), 'object-groups'],
    queryFn: () => api.get<Resource<Array<{ id: number; title: string; order: number }>>>(`/ledgers/${ledgerId}/object-groups`).then((r) => r.data),
  })

export const notificationsQuery = queryOptions({
  queryKey: ['notifications'],
  queryFn: () =>
    api.get<{ data: AppNotification[]; meta: { unread: number; total: number } }>('/notifications', { per_page: 15 }),
  refetchInterval: 60_000,
})

export const notificationSettingsQuery = queryOptions({
  queryKey: ['notification-settings'],
  queryFn: () => api.get<Resource<NotificationSettings>>('/notification-settings').then((r) => r.data),
})

export const ruleGroupsQuery = (ledgerId: number) =>
  queryOptions({
    queryKey: [...ledgerKey(ledgerId), 'rule-groups'],
    queryFn: () => api.get<Resource<RuleGroup[]>>(`/ledgers/${ledgerId}/rule-groups`).then((r) => r.data),
  })

export const ruleOptionsQuery = queryOptions({
  queryKey: ['rule-options'],
  queryFn: () => api.get<Resource<RuleOptions>>('/rule-options').then((r) => r.data),
  staleTime: Infinity,
})

export const webhooksQuery = (ledgerId: number) =>
  queryOptions({
    queryKey: [...ledgerKey(ledgerId), 'webhooks'],
    queryFn: () => api.get<Resource<Webhook[]>>(`/ledgers/${ledgerId}/webhooks`).then((r) => r.data),
  })

export const webhookDeliveriesQuery = (ledgerId: number, webhookId: number) =>
  queryOptions({
    queryKey: [...ledgerKey(ledgerId), 'webhooks', webhookId, 'deliveries'],
    queryFn: () => api.get<Resource<WebhookDelivery[]>>(`/ledgers/${ledgerId}/webhooks/${webhookId}/deliveries`).then((r) => r.data),
  })
