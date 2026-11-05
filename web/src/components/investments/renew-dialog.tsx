import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation } from '@tanstack/react-query'
import { addDays, differenceInCalendarDays, parseISO } from 'date-fns'
import { useEffect } from 'react'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'
import { DatePicker } from '@/components/app/date-picker'
import { FormDialog } from '@/components/app/form-dialog'
import { FormField } from '@/components/app/form-field'
import { MoneyInput } from '@/components/app/money-input'
import { Button } from '@/components/ui/button'
import { useLedger, useLocale } from '@/hooks/use-ledger'
import { api } from '@/lib/api/client'
import { useInvalidateLedger } from '@/lib/api/mutations'
import type { Investment } from '@/lib/api/types'
import { formatMoney, toIsoDate, todayIso } from '@/lib/format'
import { handleFormError } from '@/lib/forms'

const schema = z.object({
  date: z.string().nullable(),
  maturity_date: z.string().nullable(),
  interest_rate: z.string(),
  amount: z.string(),
})

type Values = z.infer<typeof schema>
type RenewDialogProps = { investment: Investment; open: boolean; onClose: () => void }

/**
 * Starts a new term for a matured deposit or scheme. The new term defaults to the same length as the last one.
 */
export function RenewDialog({ investment, open, onClose }: RenewDialogProps) {
  const { t } = useTranslation()
  const locale = useLocale()
  const ledger = useLedger()
  const invalidate = useInvalidateLedger(ledger.id)
  const form = useForm<Values>({ resolver: zodResolver(schema) })

  useEffect(() => {
    if (!open) return
    const date = investment.maturity_date ?? todayIso()
    const term =
      investment.start_date && investment.maturity_date
        ? differenceInCalendarDays(parseISO(investment.maturity_date), parseISO(investment.start_date))
        : null
    form.reset({
      date,
      maturity_date: term ? toIsoDate(addDays(parseISO(date), term)) : null,
      interest_rate: investment.interest_rate ? String(Number(investment.interest_rate)) : '',
      amount: '',
    })
  }, [open, investment, form])

  const save = useMutation({
    mutationFn: (values: Values) =>
      api.post(`/ledgers/${ledger.id}/investments/${investment.id}/renew`, {
        date: values.date,
        maturity_date: values.maturity_date,
        interest_rate: values.interest_rate || null,
        amount: values.amount || null,
      }),
    onSuccess: async () => {
      await invalidate()
      onClose()
    },
    onError: (error) => handleFormError(error, form.setError),
  })

  return (
    <FormDialog
      open={open}
      onOpenChange={(next) => !next && onClose()}
      title={t('Renew {{name}}', { name: investment.name })}
      description={t('The interest earned stays invested and a new term starts.')}
      size="md"
    >
      <form onSubmit={form.handleSubmit((values) => save.mutate(values))} className="space-y-5">
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <FormField control={form.control} name="date" required label={t('Renewed on')}>
            {(field) => <DatePicker id={field.id} value={field.value} onChange={field.onChange} />}
          </FormField>
          <FormField control={form.control} name="maturity_date" label={t('New maturity date')}>
            {(field) => <DatePicker id={field.id} value={field.value} onChange={field.onChange} placeholder={t('Optional')} />}
          </FormField>
        </div>
        <FormField control={form.control} name="interest_rate" label={t('Yearly return (%)')}>
          {(field) => <MoneyInput {...field} />}
        </FormField>
        <FormField
          control={form.control}
          name="amount"
          label={t('Amount renewed')}
          description={t('Leave empty to use the current value of {{value}}.', { value: formatMoney(investment.value, investment.currency.code, locale) })}
        >
          {(field) => <MoneyInput currency={investment.currency.code} {...field} placeholder={t('Optional')} />}
        </FormField>
        <div className="flex justify-end gap-2">
          <Button type="button" variant="ghost" onClick={onClose}>
            {t('Cancel')}
          </Button>
          <Button type="submit" disabled={save.isPending}>
            {t('Renew')}
          </Button>
        </div>
      </form>
    </FormDialog>
  )
}
