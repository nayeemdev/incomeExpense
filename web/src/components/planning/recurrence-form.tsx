import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation, useQuery } from '@tanstack/react-query'
import { useForm, useWatch } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'
import { AccountPicker } from '@/components/app/account-picker'
import { Combobox } from '@/components/app/combobox'
import { DatePicker } from '@/components/app/date-picker'
import { FormField } from '@/components/app/form-field'
import { MoneyInput } from '@/components/app/money-input'
import { SelectField } from '@/components/app/select-field'
import { Button } from '@/components/ui/button'
import { FieldGroup, FieldSeparator } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group'
import { useLedger, useLocale } from '@/hooks/use-ledger'
import { api } from '@/lib/api/client'
import { useInvalidateLedger } from '@/lib/api/mutations'
import { accountsQuery, activeBudgetsQuery, categoriesQuery } from '@/lib/api/queries'
import type { Recurrence, Resource } from '@/lib/api/types'
import { todayIso } from '@/lib/format'
import { handleFormError } from '@/lib/forms'
import { repetitionLabels, transactionTypeLabels, weekendLabels } from '@/lib/labels'
import { ordinals, weekdayNames } from '@/lib/recurrence'
import type { AccountChoice } from '@/lib/transactions'

const accountChoice = z.union([z.object({ id: z.number() }), z.object({ name: z.string().min(1) })]).nullable()

const schema = z
  .object({
    type: z.enum(['withdrawal', 'deposit', 'transfer']),
    title: z.string().trim().min(1).max(255),
    amount: z.string().regex(/^\d+(\.\d{1,12})?$/, 'Enter an amount'),
    description: z.string().trim().min(1).max(255),
    source: accountChoice,
    destination: accountChoice,
    category: z.object({ id: z.number().optional(), name: z.string() }).nullable(),
    budget_id: z.number().nullable(),
    first_date: z.string().min(1),
    repetition_type: z.enum(['daily', 'weekly', 'monthly', 'ndom', 'yearly']),
    weekday: z.string(),
    day: z.string(),
    nth: z.string(),
    skip: z.string(),
    weekend: z.enum(['keep', 'skip', 'before', 'after']),
    ends: z.enum(['never', 'date', 'count']),
    repeat_until: z.string().nullable(),
    max_occurrences: z.string(),
    active: z.boolean(),
  })
  .superRefine((values, context) => {
    for (const side of ['source', 'destination'] as const) {
      if (!values[side]) context.addIssue({ code: 'custom', path: [side], message: 'Choose an account' })
    }
  })

type Values = z.infer<typeof schema>

function toValues(recurrence?: Recurrence): Values {
  const split = recurrence?.transactions[0]
  const repetition = recurrence?.repetition
  const ref = (account?: { id: number; name: string; type: string }): AccountChoice =>
    !account ? null : account.type === 'expense' || account.type === 'revenue' ? { name: account.name } : { id: account.id }
  const [nth, weekday] = repetition?.type === 'ndom' ? repetition.moment.split(',') : ['1', '1']
  const today = new Date()

  return {
    type: recurrence?.type ?? 'withdrawal',
    title: recurrence?.title ?? '',
    amount: split?.amount ?? '',
    description: split?.description ?? '',
    source: ref(split?.source),
    destination: ref(split?.destination),
    category: split?.category ?? null,
    budget_id: split?.budget?.id ?? null,
    first_date: recurrence?.first_date ?? todayIso(),
    repetition_type: repetition?.type ?? 'monthly',
    weekday: repetition?.type === 'weekly' ? repetition.moment : repetition?.type === 'ndom' ? weekday : String(((today.getDay() + 6) % 7) + 1),
    day: repetition?.type === 'monthly' ? repetition.moment : String(today.getDate()),
    nth,
    skip: String(repetition?.skip ?? 0),
    weekend: repetition?.weekend ?? 'keep',
    ends: recurrence?.repeat_until ? 'date' : recurrence?.max_occurrences ? 'count' : 'never',
    repeat_until: recurrence?.repeat_until ?? null,
    max_occurrences: recurrence?.max_occurrences ? String(recurrence.max_occurrences) : '',
    active: recurrence?.active ?? true,
  }
}

function moment(values: Values) {
  switch (values.repetition_type) {
    case 'weekly':
      return values.weekday
    case 'monthly':
      return values.day
    case 'ndom':
      return `${values.nth},${values.weekday}`
    case 'yearly':
      return values.first_date.slice(5)
    default:
      return ''
  }
}

type RecurrenceFormProps = { recurrence?: Recurrence; onSaved: (recurrence: Recurrence) => void; onCancel: () => void }

export function RecurrenceForm({ recurrence, onSaved, onCancel }: RecurrenceFormProps) {
  const { t } = useTranslation()
  const locale = useLocale()
  const ledger = useLedger()
  const invalidate = useInvalidateLedger(ledger.id)
  const { data: accounts = [] } = useQuery({ ...accountsQuery(ledger.id), select: (page) => page.data })
  const { data: categories = [] } = useQuery({ ...categoriesQuery(ledger.id), select: (page) => page.data })
  const { data: budgets = [] } = useQuery(activeBudgetsQuery(ledger.id))
  const form = useForm<Values>({ resolver: zodResolver(schema), defaultValues: toValues(recurrence) })
  const [type, repetitionType, ends] = useWatch({ control: form.control, name: ['type', 'repetition_type', 'ends'] })
  const days = Object.fromEntries(weekdayNames(locale).map((name, index) => [String(index + 1), name]))

  const save = useMutation({
    mutationFn: (values: Values) => {
      const body = {
        type: values.type,
        title: values.title,
        first_date: values.first_date,
        active: values.active,
        repeat_until: values.ends === 'date' ? values.repeat_until : null,
        max_occurrences: values.ends === 'count' ? Number(values.max_occurrences) || null : null,
        repetition: { type: values.repetition_type, moment: moment(values), skip: Number(values.skip) || 0, weekend: values.weekend },
        transactions: [
          {
            description: values.description,
            amount: values.amount,
            source_id: values.source && 'id' in values.source ? values.source.id : null,
            source_name: values.source && 'name' in values.source ? values.source.name : null,
            destination_id: values.destination && 'id' in values.destination ? values.destination.id : null,
            destination_name: values.destination && 'name' in values.destination ? values.destination.name : null,
            category_id: values.category?.id ?? null,
            category_name: values.category && !values.category.id ? values.category.name : null,
            budget_id: values.type === 'withdrawal' ? values.budget_id : null,
          },
        ],
      }
      const path = `/ledgers/${ledger.id}/recurrences`

      return (recurrence ? api.put<Resource<Recurrence>>(`${path}/${recurrence.id}`, body) : api.post<Resource<Recurrence>>(path, body)).then((r) => r.data)
    },
    onSuccess: async (saved) => {
      await invalidate()
      onSaved(saved)
    },
    onError: (error) => handleFormError(error, form.setError),
  })

  return (
    <form onSubmit={form.handleSubmit((values) => save.mutate(values))} className="space-y-6">
      <FieldGroup>
        <ToggleGroup
          type="single"
          variant="outline"
          value={type}
          onValueChange={(value) => {
            if (!value) return
            form.setValue('type', value as Values['type'])
            form.setValue('source', null)
            form.setValue('destination', null)
          }}
          className="justify-start"
        >
          {(['withdrawal', 'deposit', 'transfer'] as const).map((value) => (
            <ToggleGroupItem key={value} value={value} className="px-4">
              {transactionTypeLabels()[value]}
            </ToggleGroupItem>
          ))}
        </ToggleGroup>
        <FormField control={form.control} name="title" required label={t('Name')}>
          {(field) => <Input autoFocus placeholder={t('e.g. Rent or Salary')} {...field} />}
        </FormField>
        <div className="grid gap-4 sm:grid-cols-[10rem_1fr]">
          <FormField control={form.control} name="amount" required label={t('Amount')}>
            {(field) => <MoneyInput {...field} />}
          </FormField>
          <FormField control={form.control} name="description" required label={t('Description')}>
            {(field) => <Input {...field} />}
          </FormField>
        </div>
        <div className="grid gap-4 sm:grid-cols-2">
          <FormField control={form.control} name="source" required label={t('From')}>
            {(field) => <AccountPicker id={field.id} type={type} side="source" accounts={accounts} value={field.value} onChange={field.onChange} />}
          </FormField>
          <FormField control={form.control} name="destination" required label={t('To')}>
            {(field) => <AccountPicker id={field.id} type={type} side="destination" accounts={accounts} value={field.value} onChange={field.onChange} />}
          </FormField>
        </div>
        {type !== 'transfer' && (
          <div className="grid gap-4 sm:grid-cols-2">
            <FormField control={form.control} name="category" label={t('Category')}>
              {(field) => (
                <Combobox
                  id={field.id}
                  options={categories.map((category) => ({ value: String(category.id), label: category.name }))}
                  value={field.value?.id ? String(field.value.id) : null}
                  displayValue={field.value?.name}
                  onChange={(next, option) => field.onChange(next && option ? { id: Number(option.value), name: option.label } : null)}
                  onCreate={(name) => field.onChange({ name })}
                  placeholder={t('No category')}
                />
              )}
            </FormField>
            {type === 'withdrawal' && budgets.length > 0 && (
              <FormField control={form.control} name="budget_id" label={t('Budget')}>
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
          </div>
        )}

        <FieldSeparator>{t('Schedule')}</FieldSeparator>

        <div className="grid gap-4 sm:grid-cols-2">
          <FormField control={form.control} name="first_date" required label={t('Starts on')}>
            {(field) => <DatePicker id={field.id} value={field.value} onChange={field.onChange} />}
          </FormField>
          <FormField control={form.control} name="repetition_type" required label={t('Repeats')}>
            {(field) => <SelectField id={field.id} value={field.value} onChange={field.onChange} options={repetitionLabels()} />}
          </FormField>
        </div>
        <div className="grid gap-4 sm:grid-cols-2">
          {repetitionType === 'ndom' && (
            <FormField control={form.control} name="nth" required label={t('Which one')}>
              {(field) => (
                <SelectField id={field.id} value={field.value} onChange={field.onChange} options={Object.fromEntries(ordinals().map((label, index) => [String(index + 1), label]))} />
              )}
            </FormField>
          )}
          {(repetitionType === 'weekly' || repetitionType === 'ndom') && (
            <FormField control={form.control} name="weekday" required label={t('Day of the week')}>
              {(field) => <SelectField id={field.id} value={field.value} onChange={field.onChange} options={days} />}
            </FormField>
          )}
          {repetitionType === 'monthly' && (
            <FormField control={form.control} name="day" required label={t('Day of the month')} description={t('Shorter months use their last day.')}>
              {(field) => <Input type="number" min={1} max={31} {...field} />}
            </FormField>
          )}
          <FormField control={form.control} name="skip" label={t('Skip')} description={t('1 means every other time.')}>
            {(field) => <Input type="number" min={0} max={31} {...field} />}
          </FormField>
        </div>
        <div className="grid gap-4 sm:grid-cols-2">
          <FormField control={form.control} name="weekend" label={t('When it falls on a weekend')}>
            {(field) => <SelectField id={field.id} value={field.value} onChange={field.onChange} options={weekendLabels()} />}
          </FormField>
          <FormField control={form.control} name="ends" label={t('Ends')}>
            {(field) => (
              <SelectField
                id={field.id}
                value={field.value}
                onChange={field.onChange}
                options={{ never: t('Never'), date: t('On a date'), count: t('After a number of times') }}
              />
            )}
          </FormField>
        </div>
        {ends === 'date' && (
          <FormField control={form.control} name="repeat_until" required label={t('Last date')}>
            {(field) => <DatePicker id={field.id} value={field.value} onChange={field.onChange} />}
          </FormField>
        )}
        {ends === 'count' && (
          <FormField control={form.control} name="max_occurrences" required label={t('Number of times')}>
            {(field) => <Input type="number" min={1} {...field} />}
          </FormField>
        )}
        {recurrence && (
          <FormField control={form.control} name="active" label={t('Active')} orientation="horizontal">
            {(field) => <Switch id={field.id} checked={field.value} onCheckedChange={field.onChange} />}
          </FormField>
        )}
      </FieldGroup>
      <div className="flex justify-end gap-2">
        <Button type="button" variant="ghost" onClick={onCancel}>
          {t('Cancel')}
        </Button>
        <Button type="submit" disabled={save.isPending}>
          {recurrence ? t('Save changes') : t('Create')}
        </Button>
      </div>
    </form>
  )
}
