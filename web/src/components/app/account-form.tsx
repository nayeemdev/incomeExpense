import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation } from '@tanstack/react-query'
import { useForm, useWatch } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'
import { Button } from '@/components/ui/button'
import { FieldGroup, FieldSeparator } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import { useLedger } from '@/hooks/use-ledger'
import { api } from '@/lib/api/client'
import { useInvalidateLedger } from '@/lib/api/mutations'
import type { Account, AccountType, Resource } from '@/lib/api/types'
import { todayIso } from '@/lib/format'
import { handleFormError } from '@/lib/forms'
import { assetRoleLabels, liabilityDirectionLabels, liabilityTypeLabels } from '@/lib/labels'
import { CurrencySelect } from './currency-select'
import { DatePicker } from './date-picker'
import { FormField } from './form-field'
import { MoneyInput } from './money-input'
import { SelectField } from './select-field'

const schema = z.object({
  name: z.string().trim().min(1).max(255),
  currency_id: z.number().nullable(),
  asset_role: z.string(),
  liability_type: z.string(),
  liability_direction: z.string(),
  interest: z.string(),
  interest_period: z.string(),
  credit_card_payment_day: z.string(),
  opening_balance: z.string(),
  opening_balance_date: z.string().nullable(),
  iban: z.string(),
  bic: z.string(),
  account_number: z.string(),
  include_net_worth: z.boolean(),
  active: z.boolean(),
  notes: z.string(),
  object_group: z.string(),
  low_balance_threshold: z.string(),
})

type Values = z.infer<typeof schema>

type AccountFormProps = {
  type: AccountType
  account?: Account
  onSaved: (account: Account) => void
  onCancel?: () => void
}

export function AccountForm({ type, account, onSaved, onCancel }: AccountFormProps) {
  const { t } = useTranslation()
  const ledger = useLedger()
  const invalidate = useInvalidateLedger(ledger.id)
  const own = type === 'asset' || type === 'liability'

  const form = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: {
      name: account?.name ?? '',
      currency_id: account?.currency?.id ?? (own ? ledger.primary_currency.id : null),
      asset_role: account?.asset_role ?? 'default',
      liability_type: account?.liability_type ?? 'loan',
      liability_direction: account?.liability_direction ?? 'owed',
      interest: account?.interest ? String(Number(account.interest)) : '',
      interest_period: account?.interest_period ?? 'yearly',
      credit_card_payment_day: account?.credit_card_payment_day ? String(account.credit_card_payment_day) : '',
      opening_balance: account?.opening_balance ?? '',
      opening_balance_date: account?.opening_balance_date ?? todayIso(),
      iban: account?.iban ?? '',
      bic: account?.bic ?? '',
      account_number: account?.account_number ?? '',
      include_net_worth: account?.include_net_worth ?? true,
      active: account?.active ?? true,
      notes: account?.notes ?? '',
      object_group: account?.object_group ?? '',
      low_balance_threshold: account?.low_balance_threshold ?? '',
    },
  })
  const role = useWatch({ control: form.control, name: 'asset_role' })

  const save = useMutation({
    mutationFn: (values: Values) => {
      const body = {
        ...(account ? {} : { type }),
        name: values.name,
        notes: values.notes || null,
        object_group: values.object_group,
        active: values.active,
        currency_id: values.currency_id,
        ...(own && {
          include_net_worth: values.include_net_worth,
          opening_balance: values.opening_balance || null,
          opening_balance_date: values.opening_balance ? values.opening_balance_date : null,
          iban: values.iban || null,
          bic: values.bic || null,
          account_number: values.account_number || null,
          low_balance_threshold: values.low_balance_threshold || null,
        }),
        ...(type === 'asset' && {
          asset_role: values.asset_role,
          credit_card_payment_day: values.asset_role === 'credit_card' && values.credit_card_payment_day ? Number(values.credit_card_payment_day) : null,
        }),
        ...(type === 'liability' && {
          liability_type: values.liability_type,
          liability_direction: values.liability_direction,
          interest: values.interest || null,
          interest_period: values.interest ? values.interest_period : null,
        }),
      }

      return (
        account
          ? api.patch<Resource<Account>>(`/ledgers/${ledger.id}/accounts/${account.id}`, body)
          : api.post<Resource<Account>>(`/ledgers/${ledger.id}/accounts`, body)
      ).then((response) => response.data)
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
        <FormField control={form.control} name="name" required label={t('Name')}>
          {(field) => <Input autoFocus {...field} />}
        </FormField>

        {type === 'asset' && (
          <FormField control={form.control} name="asset_role" required label={t('Kind')}>
            {(field) => <SelectField id={field.id} value={field.value} onChange={field.onChange} options={assetRoleLabels()} />}
          </FormField>
        )}

        {type === 'liability' && (
          <div className="grid gap-4 sm:grid-cols-2">
            <FormField control={form.control} name="liability_type" required label={t('Kind')}>
              {(field) => <SelectField id={field.id} value={field.value} onChange={field.onChange} options={liabilityTypeLabels()} />}
            </FormField>
            <FormField
              control={form.control}
              name="liability_direction" required
              label={t('Direction')}
              description={t('Choose "I am owed this" for money you lent to someone.')}
            >
              {(field) => (
                <SelectField id={field.id} value={field.value} onChange={field.onChange} options={liabilityDirectionLabels()} />
              )}
            </FormField>
          </div>
        )}

        {own && (
          <FormField control={form.control} name="currency_id" required label={t('Currency')}>
            {(field) => <CurrencySelect id={field.id} value={field.value} onChange={field.onChange} aria-invalid={field['aria-invalid']} />}
          </FormField>
        )}

        {own && (
          <div className="grid gap-4 sm:grid-cols-2">
            <FormField
              control={form.control}
              name="opening_balance"
              label={type === 'liability' ? t('Amount at start') : t('Opening balance')}
            >
              {(field) => <MoneyInput {...field} allowNegative={type === 'asset'} />}
            </FormField>
            <FormField control={form.control} name="opening_balance_date" label={t('As of')}>
              {(field) => <DatePicker id={field.id} value={field.value} onChange={field.onChange} />}
            </FormField>
          </div>
        )}

        {own && (
          <FormField
            control={form.control}
            name="low_balance_threshold"
            label={t('Warn me below')}
            description={t('Optional. You get an alert when the balance drops under this amount.')}
          >
            {(field) => <MoneyInput {...field} allowNegative />}
          </FormField>
        )}

        {type === 'asset' && role === 'credit_card' && (
          <FormField control={form.control} name="credit_card_payment_day" label={t('Payment day of month')}>
            {(field) => <Input type="number" min={1} max={31} {...field} />}
          </FormField>
        )}

        {type === 'liability' && (
          <div className="grid gap-4 sm:grid-cols-2">
            <FormField control={form.control} name="interest" label={t('Interest rate (%)')}>
              {(field) => <MoneyInput {...field} />}
            </FormField>
            <FormField control={form.control} name="interest_period" label={t('Interest period')}>
              {(field) => (
                <SelectField
                  id={field.id}
                  value={field.value}
                  onChange={field.onChange}
                  options={{ daily: t('Per day'), monthly: t('Per month'), yearly: t('Per year') }}
                />
              )}
            </FormField>
          </div>
        )}

        {own && (
          <>
            <FieldSeparator>{t('Bank details')}</FieldSeparator>
            <div className="grid gap-4 sm:grid-cols-2">
              <FormField control={form.control} name="iban" label={t('IBAN')}>
                {(field) => <Input {...field} />}
              </FormField>
              <FormField control={form.control} name="bic" label={t('BIC')}>
                {(field) => <Input {...field} />}
              </FormField>
            </div>
            <FormField control={form.control} name="account_number" label={t('Account number')}>
              {(field) => <Input {...field} />}
            </FormField>
          </>
        )}

        <FormField control={form.control} name="object_group" label={t('Group')} description={t('Optional. Groups accounts together in lists, for example "Family".')}>
          {(field) => <Input {...field} />}
        </FormField>

        <FormField control={form.control} name="notes" label={t('Notes')}>
          {(field) => <Textarea rows={3} {...field} />}
        </FormField>

        {own && (
          <FormField control={form.control} name="include_net_worth" label={t('Include in net worth')} orientation="horizontal">
            {(field) => <Switch id={field.id} checked={field.value} onCheckedChange={field.onChange} />}
          </FormField>
        )}
        {account && (
          <FormField control={form.control} name="active" label={t('Active')} orientation="horizontal">
            {(field) => <Switch id={field.id} checked={field.value} onCheckedChange={field.onChange} />}
          </FormField>
        )}
      </FieldGroup>
      <div className="flex justify-end gap-2">
        {onCancel && (
          <Button type="button" variant="ghost" onClick={onCancel}>
            {t('Cancel')}
          </Button>
        )}
        <Button type="submit" disabled={save.isPending}>
          {account ? t('Save changes') : t('Create account')}
        </Button>
      </div>
    </form>
  )
}
