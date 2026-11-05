import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation, useQuery } from '@tanstack/react-query'
import { useEffect } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'
import { DatePicker } from '@/components/app/date-picker'
import { FormDialog } from '@/components/app/form-dialog'
import { FormField } from '@/components/app/form-field'
import { MoneyInput } from '@/components/app/money-input'
import { SelectField } from '@/components/app/select-field'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { useLedger, useLocale } from '@/hooks/use-ledger'
import { api } from '@/lib/api/client'
import { useInvalidateLedger } from '@/lib/api/mutations'
import { membersQuery } from '@/lib/api/queries'
import type { Investment, InvestmentEventType } from '@/lib/api/types'
import { formatMoney, todayIso } from '@/lib/format'
import { handleFormError } from '@/lib/forms'
import { investmentEventLabels } from '@/lib/investments'
import { CashAccountSelect } from './cash-account-select'

const schema = z.object({
  date: z.string().nullable(),
  amount: z.string(),
  units: z.string(),
  price: z.string(),
  account_id: z.string(),
  close: z.boolean(),
  reinvest: z.boolean(),
  user_id: z.string(),
})

type Values = z.infer<typeof schema>
export type EventDraft = { type: InvestmentEventType; date?: string; amount?: string }
type InvestmentEventDialogProps = { investment: Investment; draft: EventDraft | null; onClose: () => void }

/**
 * One dialog for every kind of activity. The fields shown follow the activity and whether the investment counts units.
 */
export function InvestmentEventDialog({ investment, draft, onClose }: InvestmentEventDialogProps) {
  const { t } = useTranslation()
  const locale = useLocale()
  const ledger = useLedger()
  const invalidate = useInvalidateLedger(ledger.id)
  const form = useForm<Values>({ resolver: zodResolver(schema) })
  const [amount, units, reinvest] = useWatch({ control: form.control, name: ['amount', 'units', 'reinvest'] })
  const { data: members = [] } = useQuery(membersQuery(ledger.id))
  const type = draft?.type ?? 'buy'
  const labels = investmentEventLabels(investment.kind)
  const unitBased = investment.tracks_units
  const pricePerUnit = type === 'valuation' && unitBased
  const needsUnits = unitBased && (type === 'buy' || type === 'sell')
  const movesMoney = type !== 'valuation' && !(type === 'income' && reinvest)
  const currency = investment.currency.code

  useEffect(() => {
    if (draft) {
      form.reset({ date: draft.date ?? todayIso(), amount: draft.amount ?? '', units: '', price: '', account_id: type === 'buy' ? 'none' : '', close: false, reinvest: false, user_id: '' })
    }
  }, [draft, form, type])

  const save = useMutation({
    mutationFn: (values: Values) =>
      api.post(`/ledgers/${ledger.id}/investments/${investment.id}/events`, {
        type,
        date: values.date,
        amount: pricePerUnit ? null : values.amount,
        units: needsUnits ? values.units : null,
        price: pricePerUnit ? values.price : null,
        account_id: movesMoney && values.account_id && values.account_id !== 'none' ? values.account_id : null,
        close: type === 'sell' && !unitBased ? values.close : false,
        reinvest: type === 'income' && values.reinvest,
        user_id: type === 'buy' && values.user_id ? values.user_id : null,
      }),
    onSuccess: async () => {
      await invalidate()
      onClose()
    },
    onError: (error) => handleFormError(error, form.setError),
  })

  const perUnit = needsUnits && Number(units) > 0 && Number(amount) > 0 ? formatMoney(Number(amount) / Number(units), currency, locale) : null

  return (
    <FormDialog open={draft !== null} onOpenChange={(open) => !open && onClose()} title={t('{{action}}: {{name}}', { action: labels[type], name: investment.name })} size="md">
      <form onSubmit={form.handleSubmit((values) => save.mutate(values))} className="space-y-5">
        <FormField control={form.control} name="date" required label={t('Date')}>
          {(field) => <DatePicker id={field.id} value={field.value} onChange={field.onChange} />}
        </FormField>
        {needsUnits && (
          <FormField
            control={form.control}
            name="units"
            required
            label={investment.kind === 'gold' ? t('Quantity') : t('Units')}
            description={type === 'sell' ? t('You hold {{units}}.', { units: Number(investment.units).toLocaleString(locale) }) : undefined}
          >
            {(field) => <MoneyInput autoFocus {...field} />}
          </FormField>
        )}
        {pricePerUnit ? (
          <FormField control={form.control} name="price" required label={t('Price per unit')}>
            {(field) => <MoneyInput autoFocus currency={currency} {...field} />}
          </FormField>
        ) : (
          <FormField
            control={form.control}
            name="amount"
            required
            label={
              type === 'buy' ? t('Total paid') : type === 'sell' ? t('Total received') : type === 'income' ? t('Amount received') : t('Current value')
            }
            description={perUnit ? t('{{price}} per unit', { price: perUnit }) : undefined}
          >
            {(field) => <MoneyInput autoFocus={!needsUnits} currency={currency} {...field} />}
          </FormField>
        )}
        {movesMoney && (
          <FormField
            control={form.control}
            name="account_id"
            required={type !== 'buy'}
            label={type === 'buy' ? t('Paid from') : t('Received in')}
          >
            {(field) => (
              <CashAccountSelect
                id={field.id}
                value={field.value}
                onChange={field.onChange}
                currencyId={investment.currency.id}
                emptyLabel={type === 'buy' ? t('Already owned') : undefined}
              />
            )}
          </FormField>
        )}
        {type === 'income' && (
          <FormField
            control={form.control}
            name="reinvest"
            orientation="horizontal"
            label={t('Keep it invested')}
            description={t('The money stays in the investment and adds to what is invested.')}
          >
            {(field) => <Checkbox id={field.id} checked={field.value} onCheckedChange={(checked) => field.onChange(checked === true)} />}
          </FormField>
        )}
        {type === 'buy' && members.length > 1 && (
          <FormField control={form.control} name="user_id" label={t('Put in by')}>
            {(field) => (
              <SelectField
                id={field.id}
                value={field.value}
                onChange={field.onChange}
                placeholder={t('Me')}
                options={Object.fromEntries(members.map((member) => [member.id, member.name]))}
              />
            )}
          </FormField>
        )}
        {type === 'sell' && !unitBased && (
          <FormField control={form.control} name="close" orientation="horizontal" label={t('This closes the investment')}>
            {(field) => <Checkbox id={field.id} checked={field.value} onCheckedChange={(checked) => field.onChange(checked === true)} />}
          </FormField>
        )}
        {form.formState.errors.root && <p className="text-destructive text-sm">{form.formState.errors.root.message}</p>}
        <div className="flex justify-end gap-2">
          <Button type="button" variant="ghost" onClick={onClose}>
            {t('Cancel')}
          </Button>
          <Button type="submit" disabled={save.isPending}>
            {t('Save')}
          </Button>
        </div>
      </form>
    </FormDialog>
  )
}
