import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation, useQuery } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'
import { CurrencySelect } from '@/components/app/currency-select'
import { DatePicker } from '@/components/app/date-picker'
import { FormField } from '@/components/app/form-field'
import { MoneyInput } from '@/components/app/money-input'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { useLedger } from '@/hooks/use-ledger'
import { api } from '@/lib/api/client'
import { useInvalidateLedger } from '@/lib/api/mutations'
import { accountsQuery } from '@/lib/api/queries'
import type { PiggyBank, Resource } from '@/lib/api/types'
import { handleFormError } from '@/lib/forms'

const schema = z.object({
  name: z.string().trim().min(1).max(255),
  currency_id: z.number(),
  target_amount: z.string(),
  target_date: z.string().nullable(),
  account_ids: z.array(z.number()).min(1, 'Choose at least one account'),
  object_group: z.string(),
  notes: z.string(),
})

type PiggyBankFormProps = { piggyBank?: PiggyBank; onSaved: (piggyBank: PiggyBank) => void; onCancel: () => void }

export function PiggyBankForm({ piggyBank, onSaved, onCancel }: PiggyBankFormProps) {
  const { t } = useTranslation()
  const ledger = useLedger()
  const invalidate = useInvalidateLedger(ledger.id)
  const { data: accounts = [] } = useQuery({ ...accountsQuery(ledger.id, { type: ['asset'], active: true }), select: (page) => page.data })
  const form = useForm({
    resolver: zodResolver(schema),
    defaultValues: {
      name: piggyBank?.name ?? '',
      currency_id: piggyBank?.currency.id ?? ledger.primary_currency.id,
      target_amount: piggyBank?.target_amount ?? '',
      target_date: piggyBank?.target_date ?? null,
      account_ids: piggyBank?.accounts.map((account) => account.id) ?? [],
      object_group: piggyBank?.object_group ?? '',
      notes: piggyBank?.notes ?? '',
    },
  })

  const save = useMutation({
    mutationFn: (values: z.infer<typeof schema>) => {
      const body = { ...values, target_amount: values.target_amount || null, notes: values.notes || null }
      const path = `/ledgers/${ledger.id}/piggy-banks`

      return (piggyBank ? api.patch<Resource<PiggyBank>>(`${path}/${piggyBank.id}`, body) : api.post<Resource<PiggyBank>>(path, body)).then((r) => r.data)
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
          {(field) => <Input autoFocus placeholder={t('e.g. Holiday or New laptop')} {...field} />}
        </FormField>
        <div className="grid gap-4 sm:grid-cols-2">
          <FormField control={form.control} name="target_amount" label={t('Target amount')}>
            {(field) => <MoneyInput {...field} placeholder={t('Optional')} />}
          </FormField>
          <FormField control={form.control} name="target_date" label={t('Target date')}>
            {(field) => <DatePicker id={field.id} value={field.value} onChange={field.onChange} placeholder={t('Optional')} />}
          </FormField>
        </div>
        <FormField control={form.control} name="currency_id" required label={t('Currency')}>
          {(field) => <CurrencySelect id={field.id} value={field.value} onChange={field.onChange} />}
        </FormField>
        <FormField control={form.control} name="account_ids" required label={t('Saved in')} description={t('Money set aside stays in these accounts.')}>
          {(field) => (
            <div className="space-y-2">
              {accounts.map((account) => {
                const checked = (field.value as number[]).includes(account.id)

                return (
                  <Field key={account.id} orientation="horizontal">
                    <Checkbox
                      id={`piggy-account-${account.id}`}
                      checked={checked}
                      onCheckedChange={(next) =>
                        field.onChange(next ? [...field.value, account.id] : (field.value as number[]).filter((id) => id !== account.id))
                      }
                    />
                    <FieldLabel htmlFor={`piggy-account-${account.id}`} className="font-normal">
                      {account.name}
                    </FieldLabel>
                  </Field>
                )
              })}
            </div>
          )}
        </FormField>
        <FormField control={form.control} name="object_group" label={t('Group')}>
          {(field) => <Input {...field} />}
        </FormField>
        <FormField control={form.control} name="notes" label={t('Notes')}>
          {(field) => <Textarea rows={2} {...field} />}
        </FormField>
      </FieldGroup>
      <div className="flex justify-end gap-2">
        <Button type="button" variant="ghost" onClick={onCancel}>
          {t('Cancel')}
        </Button>
        <Button type="submit" disabled={save.isPending}>
          {piggyBank ? t('Save changes') : t('Create goal')}
        </Button>
      </div>
    </form>
  )
}
