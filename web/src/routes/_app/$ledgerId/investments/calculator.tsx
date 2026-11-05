import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation } from '@tanstack/react-query'
import { createFileRoute } from '@tanstack/react-router'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'
import { DatePicker } from '@/components/app/date-picker'
import { FormField } from '@/components/app/form-field'
import { MoneyInput } from '@/components/app/money-input'
import { PageHeader } from '@/components/app/page-header'
import { SelectField } from '@/components/app/select-field'
import { StatCard } from '@/components/app/stat-card'
import { ReturnRoadmap } from '@/components/investments/return-roadmap'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { FieldGroup } from '@/components/ui/field'
import { useLedger, useLocale } from '@/hooks/use-ledger'
import { api } from '@/lib/api/client'
import type { InvestmentRoadmap, Resource } from '@/lib/api/types'
import { formatDate, formatMoney, todayIso } from '@/lib/format'
import { handleFormError } from '@/lib/forms'
import { frequencyLabels } from '@/lib/labels'

export const Route = createFileRoute('/_app/$ledgerId/investments/calculator')({
  component: Calculator,
})

const schema = z.object({
  amount: z.string().min(1),
  interest_rate: z.string().min(1),
  payout_frequency: z.string(),
  tax_rate: z.string(),
  start_date: z.string().nullable(),
  maturity_date: z.string().nullable(),
  contribution_amount: z.string(),
  contribution_frequency: z.string(),
})

type Values = z.infer<typeof schema>
type Projection = { payout_amount: string | null; roadmap: InvestmentRoadmap }

function Calculator() {
  const { t } = useTranslation()
  const locale = useLocale()
  const ledger = useLedger()
  const currency = ledger.primary_currency.code
  const form = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: {
      amount: '',
      interest_rate: '',
      payout_frequency: 'quarterly',
      tax_rate: '',
      start_date: todayIso(),
      maturity_date: null,
      contribution_amount: '',
      contribution_frequency: 'monthly',
    },
  })

  const project = useMutation({
    mutationFn: (values: Values) =>
      api
        .post<Resource<Projection>>(`/ledgers/${ledger.id}/investments/projection`, {
          amount: values.amount,
          interest_rate: values.interest_rate,
          payout_frequency: values.payout_frequency === 'none' ? null : values.payout_frequency,
          tax_rate: values.tax_rate || null,
          start_date: values.start_date,
          maturity_date: values.maturity_date,
          contribution_amount: values.contribution_amount || null,
          contribution_frequency: values.contribution_amount ? values.contribution_frequency : null,
        })
        .then((r) => r.data),
    onError: (error) => handleFormError(error, form.setError),
  })
  const result = project.data

  return (
    <>
      <PageHeader title={t('What if')} description={t('See what an offer would pay before you put money in.')} />
      <div className="grid grid-cols-1 gap-4 xl:grid-cols-[22rem_minmax(0,1fr)]">
        <Card>
          <CardContent>
            <form onSubmit={form.handleSubmit((values) => project.mutate(values))} className="space-y-6">
              <FieldGroup>
                <FormField control={form.control} name="amount" required label={t('Amount to put in')}>
                  {(field) => <MoneyInput autoFocus currency={currency} {...field} />}
                </FormField>
                <div className="grid grid-cols-2 gap-4">
                  <FormField control={form.control} name="interest_rate" required label={t('Yearly return (%)')}>
                    {(field) => <MoneyInput {...field} placeholder="10" />}
                  </FormField>
                  <FormField control={form.control} name="tax_rate" label={t('Tax (%)')}>
                    {(field) => <MoneyInput {...field} placeholder={t('Optional')} />}
                  </FormField>
                </div>
                <FormField control={form.control} name="payout_frequency" label={t('Pays out')}>
                  {(field) => <SelectField id={field.id} value={field.value} onChange={field.onChange} options={{ none: t('At maturity'), ...frequencyLabels() }} />}
                </FormField>
                <div className="grid grid-cols-2 gap-4">
                  <FormField control={form.control} name="start_date" label={t('Start')}>
                    {(field) => <DatePicker id={field.id} value={field.value} onChange={field.onChange} />}
                  </FormField>
                  <FormField control={form.control} name="maturity_date" label={t('Maturity')}>
                    {(field) => <DatePicker id={field.id} value={field.value} onChange={field.onChange} placeholder={t('Optional')} />}
                  </FormField>
                </div>
                <div className="grid grid-cols-2 gap-4">
                  <FormField control={form.control} name="contribution_amount" label={t('Installment')}>
                    {(field) => <MoneyInput {...field} placeholder={t('Optional')} />}
                  </FormField>
                  <FormField control={form.control} name="contribution_frequency" label={t('Every')}>
                    {(field) => <SelectField id={field.id} value={field.value} onChange={field.onChange} options={frequencyLabels()} />}
                  </FormField>
                </div>
              </FieldGroup>
              <Button type="submit" className="w-full" disabled={project.isPending}>
                {t('Work it out')}
              </Button>
            </form>
          </CardContent>
        </Card>
        <div className="min-w-0 space-y-4">
          {result ? (
            <>
              <div className="stat-grid">
                {result.payout_amount && <StatCard label={t('Each payout')} value={formatMoney(result.payout_amount, currency, locale)} />}
                {result.roadmap.maturity_value && <StatCard label={t('Value at maturity')} value={formatMoney(result.roadmap.maturity_value, currency, locale)} />}
                <StatCard label={t('Paid out by {{date}}', { date: formatDate(result.roadmap.end, locale) })} value={formatMoney(result.roadmap.total_return, currency, locale)} />
              </div>
              <Card>
                <CardHeader>
                  <CardTitle>{t('Return roadmap')}</CardTitle>
                </CardHeader>
                <CardContent>
                  <ReturnRoadmap roadmap={result.roadmap} invested={form.getValues('amount')} currency={currency} />
                </CardContent>
              </Card>
            </>
          ) : (
            <Card>
              <CardContent className="text-muted-foreground py-16 text-center text-sm">
                {t('Fill in the offer, for example 5,00,000 at 10% a year paid every quarter, to see when your money is back.')}
              </CardContent>
            </Card>
          )}
        </div>
      </div>
    </>
  )
}
