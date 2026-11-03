import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation } from '@tanstack/react-query'
import { useForm, useWatch } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'
import { CurrencySelect } from '@/components/app/currency-select'
import { FormField } from '@/components/app/form-field'
import { MoneyInput } from '@/components/app/money-input'
import { SelectField } from '@/components/app/select-field'
import { Button } from '@/components/ui/button'
import { DialogFooter } from '@/components/ui/dialog'
import { FormDialog } from '@/components/app/form-dialog'
import { FieldGroup } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
import { useLedger } from '@/hooks/use-ledger'
import { api } from '@/lib/api/client'
import { useInvalidateLedger } from '@/lib/api/mutations'
import type { Budget } from '@/lib/api/types'
import { handleFormError } from '@/lib/forms'
import { autoBudgetLabels, frequencyLabels } from '@/lib/labels'

const schema = z.object({
  name: z.string().trim().min(1).max(255),
  auto: z.string(),
  auto_budget_amount: z.string(),
  auto_budget_period: z.string(),
  auto_budget_currency_id: z.number().nullable(),
  active: z.boolean(),
})

type BudgetDialogProps = { open: boolean; onOpenChange: (open: boolean) => void; budget?: Budget }

export function BudgetDialog({ open, onOpenChange, budget }: BudgetDialogProps) {
  const { t } = useTranslation()
  const ledger = useLedger()
  const invalidate = useInvalidateLedger(ledger.id)
  const form = useForm({
    resolver: zodResolver(schema),
    values: {
      name: budget?.name ?? '',
      auto: budget?.auto_budget_type ?? 'none',
      auto_budget_amount: budget?.auto_budget_amount ?? '',
      auto_budget_period: budget?.auto_budget_period ?? 'monthly',
      auto_budget_currency_id: budget?.auto_budget_currency?.id ?? ledger.primary_currency.id,
      active: budget?.active ?? true,
    },
  })
  const auto = useWatch({ control: form.control, name: 'auto' })

  const save = useMutation({
    mutationFn: (values: z.infer<typeof schema>) => {
      const automatic = values.auto !== 'none'
      const body = {
        name: values.name,
        active: values.active,
        auto_budget_type: automatic ? values.auto : null,
        auto_budget_amount: automatic ? values.auto_budget_amount : null,
        auto_budget_period: automatic ? values.auto_budget_period : null,
        auto_budget_currency_id: automatic ? values.auto_budget_currency_id : null,
      }

      return budget ? api.patch(`/ledgers/${ledger.id}/budgets/${budget.id}`, body) : api.post(`/ledgers/${ledger.id}/budgets`, body)
    },
    onSuccess: async () => {
      await invalidate()
      onOpenChange(false)
    },
    onError: (error) => handleFormError(error, form.setError),
  })

  return (
    <FormDialog open={open} onOpenChange={onOpenChange} title={budget ? t('Edit {{name}}', { name: budget.name }) : t('New budget')}>
        <form onSubmit={form.handleSubmit((values) => save.mutate(values))} className="space-y-6">
          <FieldGroup>
            <FormField control={form.control} name="name" required label={t('Name')}>
              {(field) => <Input autoFocus placeholder={t('e.g. Groceries')} {...field} />}
            </FormField>
            <FormField
              control={form.control}
              name="auto"
              label={t('Automatic amount')}
              description={t('Set the amount once and LedgerFly fills in every period for you.')}
            >
              {(field) => (
                <SelectField id={field.id} value={field.value} onChange={field.onChange} options={{ none: t('Off, I set it myself'), ...autoBudgetLabels() }} />
              )}
            </FormField>
            {auto !== 'none' && (
              <div className="grid gap-4 sm:grid-cols-2">
                <FormField control={form.control} name="auto_budget_amount" required label={t('Amount')}>
                  {(field) => <MoneyInput {...field} />}
                </FormField>
                <FormField control={form.control} name="auto_budget_period" required label={t('Every')}>
                  {(field) => <SelectField id={field.id} value={field.value} onChange={field.onChange} options={frequencyLabels()} />}
                </FormField>
                <FormField control={form.control} name="auto_budget_currency_id" required label={t('Currency')} className="sm:col-span-2">
                  {(field) => <CurrencySelect id={field.id} value={field.value} onChange={field.onChange} />}
                </FormField>
              </div>
            )}
            {budget && (
              <FormField control={form.control} name="active" label={t('Active')} orientation="horizontal">
                {(field) => <Switch id={field.id} checked={field.value} onCheckedChange={field.onChange} />}
              </FormField>
            )}
          </FieldGroup>
          <DialogFooter>
            <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
              {t('Cancel')}
            </Button>
            <Button type="submit" disabled={save.isPending}>
              {t('Save')}
            </Button>
          </DialogFooter>
        </form>
    </FormDialog>
  )
}
