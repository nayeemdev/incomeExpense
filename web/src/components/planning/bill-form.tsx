import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'
import { CurrencySelect } from '@/components/app/currency-select'
import { DatePicker } from '@/components/app/date-picker'
import { FormField } from '@/components/app/form-field'
import { MoneyInput } from '@/components/app/money-input'
import { SelectField } from '@/components/app/select-field'
import { Button } from '@/components/ui/button'
import { FieldGroup } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import { useLedger } from '@/hooks/use-ledger'
import { api } from '@/lib/api/client'
import { useInvalidateLedger } from '@/lib/api/mutations'
import type { Bill, Resource } from '@/lib/api/types'
import { todayIso } from '@/lib/format'
import { handleFormError } from '@/lib/forms'
import { frequencyLabels } from '@/lib/labels'

const schema = z.object({
  name: z.string().trim().min(1).max(255),
  currency_id: z.string(),
  amount_min: z.string().min(1, 'Enter an amount'),
  amount_max: z.string().min(1, 'Enter an amount'),
  date: z.string().min(1),
  repeat_freq: z.string(),
  skip: z.string(),
  end_date: z.string().nullable(),
  object_group: z.string(),
  notes: z.string(),
  active: z.boolean(),
})

type BillFormProps = { bill?: Bill; onSaved: (bill: Bill) => void; onCancel: () => void }

export function BillForm({ bill, onSaved, onCancel }: BillFormProps) {
  const { t } = useTranslation()
  const ledger = useLedger()
  const invalidate = useInvalidateLedger(ledger.id)
  const form = useForm({
    resolver: zodResolver(schema),
    defaultValues: {
      name: bill?.name ?? '',
      currency_id: bill?.currency.id ?? ledger.primary_currency.id,
      amount_min: bill?.amount_min ?? '',
      amount_max: bill?.amount_max ?? '',
      date: bill?.date ?? todayIso(),
      repeat_freq: bill?.repeat_freq ?? 'monthly',
      skip: String(bill?.skip ?? 0),
      end_date: bill?.end_date ?? null,
      object_group: bill?.object_group ?? '',
      notes: bill?.notes ?? '',
      active: bill?.active ?? true,
    },
  })

  const save = useMutation({
    mutationFn: (values: z.infer<typeof schema>) => {
      const body = { ...values, skip: Number(values.skip) || 0, notes: values.notes || null }

      return (bill ? api.patch<Resource<Bill>>(`/ledgers/${ledger.id}/bills/${bill.id}`, body) : api.post<Resource<Bill>>(`/ledgers/${ledger.id}/bills`, body)).then(
        (response) => response.data,
      )
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
          {(field) => <Input autoFocus placeholder={t('e.g. Rent or Netflix')} {...field} />}
        </FormField>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <FormField control={form.control} name="amount_min" required label={t('Lowest amount')}>
            {(field) => (
              <MoneyInput
                {...field}
                onChange={(value) => {
                  field.onChange(value)
                  if (!form.getValues('amount_max')) form.setValue('amount_max', value)
                }}
              />
            )}
          </FormField>
          <FormField control={form.control} name="amount_max" required label={t('Highest amount')}>
            {(field) => <MoneyInput {...field} />}
          </FormField>
        </div>
        <FormField control={form.control} name="currency_id" required label={t('Currency')}>
          {(field) => <CurrencySelect id={field.id} value={field.value} onChange={field.onChange} />}
        </FormField>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <FormField control={form.control} name="date" required label={t('Next due date')}>
            {(field) => <DatePicker id={field.id} value={field.value} onChange={field.onChange} />}
          </FormField>
          <FormField control={form.control} name="repeat_freq" required label={t('Repeats')}>
            {(field) => <SelectField id={field.id} value={field.value} onChange={field.onChange} options={frequencyLabels()} />}
          </FormField>
        </div>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <FormField control={form.control} name="skip" label={t('Skip')} description={t('1 means every other period.')}>
            {(field) => <Input type="number" min={0} max={31} {...field} />}
          </FormField>
          <FormField control={form.control} name="end_date" label={t('Ends on')}>
            {(field) => <DatePicker id={field.id} value={field.value} onChange={field.onChange} placeholder={t('Never')} />}
          </FormField>
        </div>
        <FormField control={form.control} name="object_group" label={t('Group')}>
          {(field) => <Input {...field} />}
        </FormField>
        <FormField control={form.control} name="notes" label={t('Notes')}>
          {(field) => <Textarea rows={2} {...field} />}
        </FormField>
        {bill && (
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
          {bill ? t('Save changes') : t('Create bill')}
        </Button>
      </div>
    </form>
  )
}
