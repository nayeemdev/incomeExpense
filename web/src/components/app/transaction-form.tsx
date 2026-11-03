import { zodResolver } from '@hookform/resolvers/zod'
import { useQuery } from '@tanstack/react-query'
import { PlusIcon, Trash2Icon } from 'lucide-react'
import { useEffect } from 'react'
import { useFieldArray, useForm, useWatch, type UseFormReturn } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { FieldGroup } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { Separator } from '@/components/ui/separator'
import { Textarea } from '@/components/ui/textarea'
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group'
import { useLedger } from '@/hooks/use-ledger'
import { ApiError } from '@/lib/api/client'
import { accountsQuery, activeBudgetsQuery, billsQuery, categoriesQuery, frequentQuery, tagsQuery } from '@/lib/api/queries'
import type { Account, Transaction, TransactionGroupInput } from '@/lib/api/types'
import { notifyError } from '@/lib/forms'
import { transactionTypeLabels } from '@/lib/labels'
import { allowedAccountTypes, type AccountChoice } from '@/lib/transactions'
import { AccountPicker } from './account-picker'
import { Combobox } from './combobox'
import { DatePicker } from './date-picker'
import { DescriptionInput } from './description-input'
import { FormField } from './form-field'
import { Money } from './money'
import { MoneyInput } from './money-input'
import { QuickPicks } from './quick-picks'
import { TagInput } from './tag-input'
import {
  accountRef,
  emptySplit,
  toInput,
  transactionSchema,
  type EditableType,
  type SplitValues,
  type TransactionFormValues,
} from './transaction-form-values'

const serverFieldMap: Record<string, keyof SplitValues> = {
  source_id: 'source',
  source_name: 'source',
  destination_id: 'destination',
  destination_name: 'destination',
  category_id: 'category',
  category_name: 'category',
  foreign_amount: 'foreign_amount',
  foreign_currency_id: 'foreign_amount',
  description: 'description',
  amount: 'amount',
  notes: 'notes',
}

function applyServerErrors(error: unknown, form: UseFormReturn<TransactionFormValues>) {
  if (!(error instanceof ApiError && error.isValidation)) {
    notifyError(error)

    return
  }

  for (const [key, messages] of Object.entries(error.errors)) {
    const match = key.match(/^transactions\.(\d+)\.(\w+)/)
    const field = match ? serverFieldMap[match[2]] : undefined

    if (match && field) {
      form.setError(`splits.${Number(match[1])}.${field}`, { message: messages[0] })
    } else {
      form.setError('root', { message: messages[0] })
    }
  }
}

type TransactionFormProps = {
  defaultValues: TransactionFormValues
  onSubmit: (input: TransactionGroupInput) => Promise<unknown>
  submitLabel: string
  allowSplits?: boolean
  quickPicks?: boolean
  onCancel?: () => void
}

export function TransactionForm({ defaultValues, onSubmit, submitLabel, allowSplits = true, quickPicks = true, onCancel }: TransactionFormProps) {
  const { t } = useTranslation()
  const ledger = useLedger()
  const labels = transactionTypeLabels()
  const { data: accounts = [] } = useQuery({ ...accountsQuery(ledger.id), select: (page) => page.data })
  const form = useForm<TransactionFormValues>({ resolver: zodResolver(transactionSchema), defaultValues })
  const { fields, append, remove } = useFieldArray({ control: form.control, name: 'splits' })
  const type = useWatch({ control: form.control, name: 'type' })

  useEffect(() => form.reset(defaultValues), [defaultValues, form])

  const submit = form.handleSubmit(async (values) => {
    try {
      await onSubmit(toInput(values, accounts))
    } catch (error) {
      applyServerErrors(error, form)
    }
  })

  const changeType = (next: EditableType) => {
    const previous = form.getValues('type')

    form.setValue('type', next)
    form.getValues('splits').forEach((split, index) => {
      for (const side of ['source', 'destination'] as const) {
        const choice = split[side]
        const account = choice && 'id' in choice ? accounts.find((item) => item.id === choice.id) : undefined
        const keep = account ? allowedAccountTypes(next, side).includes(account.type) : next === previous

        if (!keep) form.setValue(`splits.${index}.${side}`, null)
      }
    })
  }

  return (
    <form
      onSubmit={submit}
      onKeyDown={(event) => {
        if (event.key === 'Enter' && (event.metaKey || event.ctrlKey)) void submit()
      }}
      className="space-y-6"
    >
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <ToggleGroup
          type="single"
          variant="outline"
          value={type}
          onValueChange={(value) => value && changeType(value as EditableType)}
        >
          {(['withdrawal', 'deposit', 'transfer'] as const).map((value) => (
            <ToggleGroupItem key={value} value={value} className="px-4">
              {labels[value]}
            </ToggleGroupItem>
          ))}
        </ToggleGroup>
        <FormField control={form.control} name="date" className="sm:w-48">
          {(field) => <DatePicker id={field.id} value={field.value} onChange={field.onChange} aria-invalid={field['aria-invalid']} />}
        </FormField>
      </div>

      {fields.length > 1 && (
        <FormField control={form.control} name="title" label={t('Title')} description={t('A name for the whole split transaction.')}>
          {(field) => <Input {...field} />}
        </FormField>
      )}

      {fields.map((item, index) => (
        <div key={item.id} className="space-y-4">
          {index > 0 && <Separator />}
          {fields.length > 1 && (
            <div className="flex items-center justify-between">
              <span className="text-muted-foreground text-sm font-medium">{t('Split {{number}}', { number: index + 1 })}</span>
              <Button type="button" variant="ghost" size="sm" onClick={() => remove(index)}>
                <Trash2Icon />
                {t('Remove')}
              </Button>
            </div>
          )}
          <SplitFields form={form} index={index} accounts={accounts} autoFocus={index === 0} showQuickPicks={quickPicks && fields.length === 1} />
        </div>
      ))}

      {form.formState.errors.root && <p className="text-destructive text-sm">{form.formState.errors.root.message}</p>}

      <div className="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
        {allowSplits ? (
          <Button
            type="button"
            variant="outline"
            onClick={() => {
              const first = form.getValues('splits.0')
              append(emptySplit({ source: first.source, destination: type === 'withdrawal' ? null : first.destination }))
            }}
          >
            <PlusIcon />
            {t('Add split')}
          </Button>
        ) : (
          <span />
        )}
        <div className="flex gap-2">
          {onCancel && (
            <Button type="button" variant="ghost" onClick={onCancel}>
              {t('Cancel')}
            </Button>
          )}
          <Button type="submit" disabled={form.formState.isSubmitting}>
            {submitLabel}
          </Button>
        </div>
      </div>
    </form>
  )
}

type SplitFieldsProps = {
  form: UseFormReturn<TransactionFormValues>
  index: number
  accounts: Account[]
  autoFocus?: boolean
  showQuickPicks?: boolean
}

function SplitFields({ form, index, accounts, autoFocus, showQuickPicks }: SplitFieldsProps) {
  const { t } = useTranslation()
  const ledger = useLedger()
  const type = useWatch({ control: form.control, name: 'type' })
  const split = useWatch({ control: form.control, name: `splits.${index}` })
  const { data: categories = [] } = useQuery({ ...categoriesQuery(ledger.id), select: (page) => page.data })
  const { data: tags = [] } = useQuery({ ...tagsQuery(ledger.id), select: (page) => page.data })
  const { data: frequent } = useQuery({ ...frequentQuery(ledger.id, type), enabled: showQuickPicks })
  const { data: budgets = [] } = useQuery({ ...activeBudgetsQuery(ledger.id), enabled: type === 'withdrawal' })
  const { data: bills = [] } = useQuery({ ...billsQuery(ledger.id), enabled: type === 'withdrawal', select: (items) => items.filter((bill) => bill.active) })

  const accountById = (choice: AccountChoice) =>
    choice && 'id' in choice ? accounts.find((account) => account.id === choice.id) : undefined
  const source = accountById(split.source)
  const destination = accountById(split.destination)
  const currency = (type === 'deposit' ? destination : source)?.currency ?? ledger.primary_currency
  const needsForeign =
    type === 'transfer' && !!source?.currency && !!destination?.currency && source.currency.id !== destination.currency.id

  const counterpartySide = type === 'deposit' ? 'source' : 'destination'
  const counterparty = split[counterpartySide]
  const ownSide = type === 'deposit' ? 'destination' : 'source'
  const assets = accounts.filter((account) => account.type === 'asset' && account.active)
  const onlyAssetId = assets.length === 1 ? assets[0].id : null

  useEffect(() => {
    if (type !== 'transfer' && onlyAssetId !== null && !form.getValues(`splits.${index}.${ownSide}`)) {
      form.setValue(`splits.${index}.${ownSide}`, { id: onlyAssetId })
    }
  }, [form, index, onlyAssetId, ownSide, type])

  const payeePicks = (side: 'source' | 'destination') =>
    showQuickPicks && counterpartySide === side && type !== 'transfer' ? (
      <QuickPicks
        label={t('Frequent payees')}
        items={(frequent?.counterparties ?? []).slice(0, 5).map((account) => ({
          key: String(account.id),
          label: account.name,
          active: !!counterparty && ('name' in counterparty ? counterparty.name === account.name : counterparty.id === account.id),
        }))}
        onPick={(key) => {
          const account = frequent?.counterparties.find((item) => String(item.id) === key)
          if (account) form.setValue(`splits.${index}.${side}`, accountRef(account), { shouldValidate: true })
        }}
      />
    ) : null

  const applyAll = (transaction: Transaction) => {
    const path = `splits.${index}` as const

    form.setValue(`${path}.description`, transaction.description)
    form.setValue(`${path}.amount`, transaction.amount)
    form.setValue(`${path}.source`, accountRef(transaction.source))
    form.setValue(`${path}.destination`, accountRef(transaction.destination))
    form.setValue(`${path}.category`, transaction.category ?? null)
    form.setValue(`${path}.budget_id`, transaction.budget?.id ?? null)
    form.setValue(`${path}.bill_id`, transaction.bill?.id ?? null)
    form.setValue(`${path}.tags`, transaction.tags?.map((tag) => tag.name) ?? [])
    form.clearErrors(path)
  }

  const prefill = (transaction: Transaction) => {
    const path = `splits.${index}` as const
    const current = form.getValues(path)

    form.setValue(`${path}.description`, transaction.description)
    if (transaction.type !== type) return

    if (!current.amount) form.setValue(`${path}.amount`, transaction.amount)
    if (!current.source) form.setValue(`${path}.source`, accountRef(transaction.source))
    if (!current.destination) form.setValue(`${path}.destination`, accountRef(transaction.destination))
    if (!current.category && transaction.category) form.setValue(`${path}.category`, transaction.category)
    if (current.tags.length === 0 && transaction.tags) form.setValue(`${path}.tags`, transaction.tags.map((tag) => tag.name))
    if (!current.budget_id && transaction.budget) form.setValue(`${path}.budget_id`, transaction.budget.id)
    if (!current.bill_id && transaction.bill) form.setValue(`${path}.bill_id`, transaction.bill.id)
  }

  return (
    <FieldGroup className="gap-4">
      {showQuickPicks && !split.description && (frequent?.transactions.length ?? 0) > 0 && (
        <QuickPicks
          label={t('Frequent transactions')}
          items={(frequent?.transactions ?? []).map((transaction) => ({
            key: String(transaction.id),
            label: transaction.description,
            hint: <Money amount={transaction.amount} currency={transaction.currency.code} tone="none" />,
          }))}
          onPick={(key) => {
            const transaction = frequent?.transactions.find((item) => String(item.id) === key)
            if (transaction) applyAll(transaction)
          }}
        />
      )}
      <div className="grid gap-4 sm:grid-cols-[10rem_1fr]">
        <FormField control={form.control} name={`splits.${index}.amount`} label={t('Amount')}>
          {(field) => (
            <MoneyInput {...field} autoFocus={autoFocus} currency={currency.code} className="font-medium" />
          )}
        </FormField>
        <FormField control={form.control} name={`splits.${index}.description`} label={t('Description')}>
          {(field) => (
            <DescriptionInput
              id={field.id}
              ledgerId={ledger.id}
              value={field.value}
              onChange={field.onChange}
              onPick={prefill}
              placeholder={t('What was it for?')}
              aria-invalid={field['aria-invalid']}
            />
          )}
        </FormField>
      </div>
      <div className="grid gap-4 sm:grid-cols-2">
        <div className="space-y-2">
          <FormField control={form.control} name={`splits.${index}.source`} label={t('From')}>
            {(field) => (
              <AccountPicker id={field.id} type={type} side="source" accounts={accounts} value={field.value} onChange={field.onChange} aria-invalid={field['aria-invalid']} />
            )}
          </FormField>
          {payeePicks('source')}
        </div>
        <div className="space-y-2">
          <FormField control={form.control} name={`splits.${index}.destination`} label={t('To')}>
            {(field) => (
              <AccountPicker id={field.id} type={type} side="destination" accounts={accounts} value={field.value} onChange={field.onChange} aria-invalid={field['aria-invalid']} />
            )}
          </FormField>
          {payeePicks('destination')}
        </div>
      </div>
      {needsForeign && destination?.currency && (
        <FormField
          control={form.control}
          name={`splits.${index}.foreign_amount`}
          label={t('Amount received')}
          description={t('How much arrived in {{account}}.', { account: destination.name })}
        >
          {(field) => <MoneyInput {...field} currency={destination.currency?.code} />}
        </FormField>
      )}
      {type !== 'transfer' && (
        <div className="grid gap-4 sm:grid-cols-2">
          <div className="space-y-2">
            <FormField control={form.control} name={`splits.${index}.category`} label={t('Category')}>
              {(field) => (
                <Combobox
                  id={field.id}
                  options={categories.map((category) => ({ value: String(category.id), label: category.name }))}
                  value={field.value?.id ? String(field.value.id) : null}
                  displayValue={field.value?.name}
                  onChange={(next, option) => field.onChange(next && option ? { id: Number(option.value), name: option.label } : null)}
                  onCreate={(name) => field.onChange({ name })}
                  placeholder={t('No category')}
                  searchPlaceholder={t('Search or create…')}
                />
              )}
            </FormField>
            {showQuickPicks && (frequent?.categories.length ?? 0) > 0 && (
              <QuickPicks
                label={t('Frequent categories')}
                items={(frequent?.categories ?? []).slice(0, 5).map((category) => ({
                  key: String(category.id),
                  label: category.name,
                  active: split.category?.id === category.id,
                }))}
                onPick={(key) => {
                  const category = frequent?.categories.find((item) => String(item.id) === key)
                  if (category) form.setValue(`splits.${index}.category`, split.category?.id === category.id ? null : category)
                }}
              />
            )}
          </div>
          <FormField control={form.control} name={`splits.${index}.tags`} label={t('Tags')}>
            {(field) => (
              <TagInput id={field.id} value={field.value || []} onChange={field.onChange} suggestions={tags.map((tag) => tag.name)} />
            )}
          </FormField>
        </div>
      )}
      {type === 'withdrawal' && (budgets.length > 0 || bills.length > 0) && (
        <div className="grid gap-4 sm:grid-cols-2">
          {budgets.length > 0 && (
            <FormField control={form.control} name={`splits.${index}.budget_id`} label={t('Budget')}>
              {(field) => (
                <Combobox
                  id={field.id}
                  options={budgets.map((budget) => ({ value: String(budget.id), label: budget.name }))}
                  value={field.value ? String(field.value) : null}
                  onChange={(next) => field.onChange(next ? Number(next) : null)}
                  placeholder={t('No budget')}
                />
              )}
            </FormField>
          )}
          {bills.length > 0 && (
            <FormField control={form.control} name={`splits.${index}.bill_id`} label={t('Bill')}>
              {(field) => (
                <Combobox
                  id={field.id}
                  options={bills.map((bill) => ({ value: String(bill.id), label: bill.name }))}
                  value={field.value ? String(field.value) : null}
                  onChange={(next) => field.onChange(next ? Number(next) : null)}
                  placeholder={t('Not a bill')}
                />
              )}
            </FormField>
          )}
        </div>
      )}
      <FormField control={form.control} name={`splits.${index}.notes`} label={t('Notes')}>
        {(field) => <Textarea rows={2} {...field} />}
      </FormField>
    </FieldGroup>
  )
}
