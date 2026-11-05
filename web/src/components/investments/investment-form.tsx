import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation } from '@tanstack/react-query'
import { useForm, useWatch } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'
import { CurrencySelect } from '@/components/app/currency-select'
import { DatePicker } from '@/components/app/date-picker'
import { FormField } from '@/components/app/form-field'
import { MoneyInput } from '@/components/app/money-input'
import { SelectField } from '@/components/app/select-field'
import { Button } from '@/components/ui/button'
import { FieldGroup, FieldLegend, FieldSet } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { useLedger } from '@/hooks/use-ledger'
import { api } from '@/lib/api/client'
import { useInvalidateLedger } from '@/lib/api/mutations'
import type { Investment, InvestmentKind, Resource } from '@/lib/api/types'
import { todayIso } from '@/lib/format'
import { handleFormError } from '@/lib/forms'
import { earnsInterest, tracksUnits } from '@/lib/investments'
import { frequencyLabels, investmentKindDescriptions, investmentKindLabels } from '@/lib/labels'
import { CashAccountSelect } from './cash-account-select'

const schema = z.object({
  kind: z.enum(['stock', 'etf', 'mutual_fund', 'deposit', 'savings_scheme', 'private', 'gold', 'property', 'other']),
  name: z.string().trim().min(1).max(255),
  symbol: z.string().max(32),
  currency_id: z.string(),
  interest_rate: z.string(),
  payout_frequency: z.string(),
  start_date: z.string().nullable(),
  maturity_date: z.string().nullable(),
  notes: z.string(),
  amount: z.string(),
  units: z.string(),
  date: z.string().nullable(),
  account_id: z.string(),
})

type Values = z.infer<typeof schema>
type InvestmentFormProps = { investment?: Investment; onSaved: (investment: Investment) => void; onCancel: () => void }

export function InvestmentForm({ investment, onSaved, onCancel }: InvestmentFormProps) {
  const { t } = useTranslation()
  const ledger = useLedger()
  const invalidate = useInvalidateLedger(ledger.id)
  const form = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: {
      kind: investment?.kind ?? 'stock',
      name: investment?.name ?? '',
      symbol: investment?.symbol ?? '',
      currency_id: investment?.currency.id ?? ledger.primary_currency.id,
      interest_rate: investment?.interest_rate ? String(Number(investment.interest_rate)) : '',
      payout_frequency: investment?.payout_frequency ?? 'none',
      start_date: investment?.start_date ?? null,
      maturity_date: investment?.maturity_date ?? null,
      notes: investment?.notes ?? '',
      amount: '',
      units: '',
      date: todayIso(),
      account_id: 'none',
    },
  })
  const [kind, currencyId, amount, payoutFrequency] = useWatch({ control: form.control, name: ['kind', 'currency_id', 'amount', 'payout_frequency'] })
  const units = tracksUnits(kind)
  const interest = earnsInterest(kind)

  const save = useMutation({
    mutationFn: (values: Values) => {
      const withInterest = earnsInterest(values.kind)
      const body = {
        kind: values.kind,
        name: values.name,
        symbol: tracksUnits(values.kind) ? values.symbol || null : null,
        interest_rate: withInterest ? values.interest_rate || null : null,
        payout_frequency: withInterest && values.payout_frequency !== 'none' ? values.payout_frequency : null,
        start_date: values.start_date ?? values.date,
        maturity_date: withInterest ? values.maturity_date : null,
        notes: values.notes || null,
      }

      if (investment) {
        return api.patch<Resource<Investment>>(`/ledgers/${ledger.id}/investments/${investment.id}`, body).then((r) => r.data)
      }

      const purchase = values.amount
        ? {
            amount: values.amount,
            units: tracksUnits(values.kind) ? values.units || null : null,
            date: values.date,
            account_id: values.account_id === 'none' ? null : values.account_id,
          }
        : null

      return api
        .post<Resource<Investment>>(`/ledgers/${ledger.id}/investments`, { ...body, currency_id: values.currency_id, purchase })
        .then((r) => r.data)
    },
    onSuccess: async (saved) => {
      await invalidate()
      onSaved(saved)
    },
    onError: (error) =>
      handleFormError(error, (name, error) => form.setError(String(name).replace('purchase.', '') as keyof Values, error)),
  })

  return (
    <form onSubmit={form.handleSubmit((values) => save.mutate(values))} className="space-y-6">
      <FieldGroup>
        <FormField control={form.control} name="kind" required label={t('Type')} description={investmentKindDescriptions()[kind]}>
          {(field) => <SelectField id={field.id} value={field.value} onChange={(value) => field.onChange(value as InvestmentKind)} options={investmentKindLabels()} />}
        </FormField>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-[1fr_9rem]">
          <FormField control={form.control} name="name" required label={t('Name')}>
            {(field) => <Input autoFocus placeholder={interest ? t('e.g. City Bank FDR') : t('e.g. Square Pharma')} {...field} />}
          </FormField>
          {units ? (
            <FormField control={form.control} name="symbol" label={t('Symbol')}>
              {(field) => <Input placeholder={t('Optional')} {...field} />}
            </FormField>
          ) : (
            !investment && (
              <FormField control={form.control} name="currency_id" required label={t('Currency')}>
                {(field) => <CurrencySelect id={field.id} value={field.value} onChange={field.onChange} />}
              </FormField>
            )
          )}
        </div>
        {units && !investment && (
          <FormField control={form.control} name="currency_id" required label={t('Currency')}>
            {(field) => <CurrencySelect id={field.id} value={field.value} onChange={field.onChange} />}
          </FormField>
        )}
        {interest && (
          <>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <FormField control={form.control} name="interest_rate" label={t('Yearly return (%)')}>
                {(field) => <MoneyInput {...field} placeholder={t('e.g. 10')} />}
              </FormField>
              <FormField
                control={form.control}
                name="payout_frequency"
                label={t('Pays out')}
                description={payoutFrequency === 'none' ? t('Interest builds up and is paid at the end.') : undefined}
              >
                {(field) => (
                  <SelectField id={field.id} value={field.value} onChange={field.onChange} options={{ none: t('At maturity'), ...frequencyLabels() }} />
                )}
              </FormField>
            </div>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <FormField control={form.control} name="start_date" label={t('Start date')}>
                {(field) => <DatePicker id={field.id} value={field.value} onChange={field.onChange} placeholder={t('Same as the first payment')} />}
              </FormField>
              <FormField control={form.control} name="maturity_date" label={t('Maturity date')}>
                {(field) => <DatePicker id={field.id} value={field.value} onChange={field.onChange} placeholder={t('Optional')} />}
              </FormField>
            </div>
          </>
        )}
        {!investment && (
          <FieldSet>
            <FieldLegend variant="label">{interest ? t('Amount put in') : t('First purchase')}</FieldLegend>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <FormField control={form.control} name="amount" label={t('Amount')}>
                {(field) => <MoneyInput {...field} placeholder={t('Optional')} />}
              </FormField>
              {units ? (
                <FormField control={form.control} name="units" required={!!amount} label={kind === 'gold' ? t('Quantity') : t('Units')}>
                  {(field) => <MoneyInput {...field} />}
                </FormField>
              ) : (
                <FormField control={form.control} name="date" label={t('Date')}>
                  {(field) => <DatePicker id={field.id} value={field.value} onChange={field.onChange} />}
                </FormField>
              )}
            </div>
            {units && (
              <FormField control={form.control} name="date" label={t('Date')}>
                {(field) => <DatePicker id={field.id} value={field.value} onChange={field.onChange} />}
              </FormField>
            )}
            <FormField
              control={form.control}
              name="account_id"
              label={t('Paid from')}
              description={t('Choose "Already owned" for holdings you had before using LedgerFly.')}
            >
              {(field) => <CashAccountSelect id={field.id} value={field.value} onChange={field.onChange} currencyId={currencyId} emptyLabel={t('Already owned')} />}
            </FormField>
          </FieldSet>
        )}
        <FormField control={form.control} name="notes" label={t('Notes')}>
          {(field) => <Textarea rows={2} {...field} />}
        </FormField>
      </FieldGroup>
      <div className="flex justify-end gap-2">
        <Button type="button" variant="ghost" onClick={onCancel}>
          {t('Cancel')}
        </Button>
        <Button type="submit" disabled={save.isPending}>
          {investment ? t('Save changes') : t('Add investment')}
        </Button>
      </div>
    </form>
  )
}
