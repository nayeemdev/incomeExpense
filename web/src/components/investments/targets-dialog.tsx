import { useMutation } from '@tanstack/react-query'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { FormDialog } from '@/components/app/form-dialog'
import { MoneyInput } from '@/components/app/money-input'
import { Button } from '@/components/ui/button'
import { Field, FieldError, FieldLabel } from '@/components/ui/field'
import { useLedger } from '@/hooks/use-ledger'
import { api, ApiError } from '@/lib/api/client'
import { useInvalidateLedger } from '@/lib/api/mutations'
import type { InvestmentKind, PortfolioSummary } from '@/lib/api/types'
import { notifyError } from '@/lib/forms'
import { investmentKindLabels } from '@/lib/labels'

type TargetsDialogProps = { targets: PortfolioSummary['targets']; open: boolean; onClose: () => void }

export function TargetsDialog({ targets, open, onClose }: TargetsDialogProps) {
  const { t } = useTranslation()
  const ledger = useLedger()
  const invalidate = useInvalidateLedger(ledger.id)
  const kinds = investmentKindLabels()
  const initial = () => Object.fromEntries(targets.map((target) => [target.kind, String(target.percent)]))
  const [values, setValues] = useState<Record<string, string>>(initial)
  const [error, setError] = useState<string | null>(null)
  const total = Object.values(values).reduce((sum, value) => sum + (Number(value) || 0), 0)

  const save = useMutation({
    mutationFn: () =>
      api.put(`/ledgers/${ledger.id}/investment-targets`, {
        targets: Object.entries(values)
          .filter(([, value]) => Number(value) > 0)
          .map(([kind, percent]) => ({ kind, percent: Number(percent) })),
      }),
    onSuccess: async () => {
      await invalidate()
      onClose()
    },
    onError: (caught) => (caught instanceof ApiError && caught.isValidation ? setError(Object.values(caught.errors)[0]?.[0] ?? caught.message) : notifyError(caught)),
  })

  return (
    <FormDialog
      open={open}
      onOpenChange={(next) => {
        if (next) {
          setValues(initial())
          setError(null)
        } else {
          onClose()
        }
      }}
      title={t('Target mix')}
      description={t('The share of your portfolio you want in each kind of investment.')}
      size="md"
    >
      <form
        className="space-y-5"
        onSubmit={(event) => {
          event.preventDefault()
          save.mutate()
        }}
      >
        <div className="grid grid-cols-2 gap-3">
          {(Object.keys(kinds) as InvestmentKind[]).map((kind) => (
            <Field key={kind}>
              <FieldLabel htmlFor={`target-${kind}`}>{kinds[kind]}</FieldLabel>
              <MoneyInput
                id={`target-${kind}`}
                value={values[kind] ?? ''}
                onChange={(value) => setValues((current) => ({ ...current, [kind]: value }))}
                currency="%"
                placeholder="0"
              />
            </Field>
          ))}
        </div>
        <Field data-invalid={total > 100 || !!error}>
          <p className="text-sm">{t('Total {{total}}%', { total: Math.round(total * 100) / 100 })}</p>
          {(total > 100 || error) && <FieldError>{error ?? t('The targets add up to more than 100%.')}</FieldError>}
        </Field>
        <div className="flex justify-end gap-2">
          <Button type="button" variant="ghost" onClick={onClose}>
            {t('Cancel')}
          </Button>
          <Button type="submit" disabled={save.isPending || total > 100}>
            {t('Save')}
          </Button>
        </div>
      </form>
    </FormDialog>
  )
}
