import { z } from 'zod'
import type { Account, SplitInput, Transaction, TransactionGroup, TransactionGroupInput } from '@/lib/api/types'
import { todayIso } from '@/lib/format'
import type { AccountChoice } from '@/lib/transactions'

const amount = z.string().regex(/^\d+(\.\d{1,12})?$/, 'Enter an amount').refine((value) => Number(value) > 0, 'Enter an amount')
const accountChoice = z.union([z.object({ id: z.number() }), z.object({ name: z.string().min(1) })]).nullable()

export const transactionSchema = z.object({
  type: z.enum(['withdrawal', 'deposit', 'transfer']),
  date: z.string().min(1, 'Pick a date'),
  title: z.string(),
  splits: z
    .array(
      z
        .object({
          description: z.string().trim().min(1, 'Describe the transaction'),
          amount,
          source: accountChoice,
          destination: accountChoice,
          category: z.object({ id: z.number().optional(), name: z.string() }).nullable(),
          budget_id: z.number().nullable(),
          bill_id: z.number().nullable(),
          tags: z.array(z.string()),
          notes: z.string(),
          foreign_amount: z.string(),
        })
        .superRefine((split, context) => {
          for (const side of ['source', 'destination'] as const) {
            if (!split[side]) context.addIssue({ code: 'custom', path: [side], message: 'Choose an account' })
          }
        }),
    )
    .min(1),
})

export type TransactionFormValues = z.infer<typeof transactionSchema>
export type SplitValues = TransactionFormValues['splits'][number]
export type EditableType = TransactionFormValues['type']

export function emptySplit(overrides: Partial<SplitValues> = {}): SplitValues {
  return {
    description: '',
    amount: '',
    source: null,
    destination: null,
    category: null,
    budget_id: null,
    bill_id: null,
    tags: [],
    notes: '',
    foreign_amount: '',
    ...overrides,
  }
}

export function newTransactionValues(type: EditableType = 'withdrawal', accountId?: number): TransactionFormValues {
  const own = accountId ? { id: accountId } : null

  return {
    type,
    date: todayIso(),
    title: '',
    splits: [emptySplit(type === 'deposit' ? { destination: own } : { source: own })],
  }
}

export function accountRef(account: Transaction['source']): AccountChoice {
  return account.type === 'expense' || account.type === 'revenue' ? { name: account.name } : { id: account.id }
}

export function groupToFormValues(group: TransactionGroup): TransactionFormValues {
  const first = group.transactions[0]

  return {
    type: first.type as EditableType,
    date: first.date,
    title: group.title ?? '',
    splits: group.transactions.map((transaction) =>
      emptySplit({
        description: transaction.description,
        amount: transaction.amount,
        source: accountRef(transaction.source),
        destination: accountRef(transaction.destination),
        category: transaction.category ? { id: transaction.category.id, name: transaction.category.name } : null,
        budget_id: transaction.budget?.id ?? null,
        bill_id: transaction.bill?.id ?? null,
        tags: transaction.tags?.map((tag) => tag.name) ?? [],
        notes: transaction.notes ?? '',
        foreign_amount: transaction.foreign_amount ?? '',
      }),
    ),
  }
}

export function toInput(values: TransactionFormValues, accounts: Account[]): TransactionGroupInput {
  const byId = new Map(accounts.map((account) => [account.id, account]))

  return {
    title: values.splits.length > 1 ? values.title || null : null,
    transactions: values.splits.map((split): SplitInput => {
      const destination = split.destination && 'id' in split.destination ? byId.get(split.destination.id) : undefined
      const needsForeign = values.type === 'transfer' && !!split.foreign_amount

      return {
        type: values.type,
        date: values.date,
        description: split.description.trim(),
        amount: split.amount,
        source_id: split.source && 'id' in split.source ? split.source.id : null,
        source_name: split.source && 'name' in split.source ? split.source.name : null,
        destination_id: split.destination && 'id' in split.destination ? split.destination.id : null,
        destination_name: split.destination && 'name' in split.destination ? split.destination.name : null,
        foreign_amount: needsForeign ? split.foreign_amount : null,
        foreign_currency_id: needsForeign ? (destination?.currency?.id ?? null) : null,
        category_id: split.category?.id ?? null,
        category_name: split.category && !split.category.id ? split.category.name : null,
        budget_id: values.type === 'withdrawal' ? split.budget_id : null,
        bill_id: values.type === 'withdrawal' ? split.bill_id : null,
        tags: split.tags,
        notes: split.notes || null,
      }
    }),
  }
}
