import { useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { MoneyInput } from '@/components/app/money-input'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Field, FieldError } from '@/components/ui/field'
import { ApiError } from '@/lib/api/client'
import { notifyError } from '@/lib/forms'

type AmountDialogProps = {
  open: boolean
  onOpenChange: (open: boolean) => void
  title: ReactNode
  description?: ReactNode
  currency: string
  initial?: string
  allowNegative?: boolean
  children?: ReactNode
  onSave: (amount: string) => Promise<unknown>
}

/**
 * A small dialog that asks for one amount, used for budget limits, available budget and piggy bank moves.
 */
export function AmountDialog({ open, onOpenChange, title, description, currency, initial = '', allowNegative, children, onSave }: AmountDialogProps) {
  const { t } = useTranslation()
  const [amount, setAmount] = useState(initial)
  const [error, setError] = useState<string | null>(null)
  const [saving, setSaving] = useState(false)

  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        if (next) {
          setAmount(initial)
          setError(null)
        }
        onOpenChange(next)
      }}
    >
      <DialogContent className="sm:max-w-sm">
        <form
          className="space-y-6"
          onSubmit={async (event) => {
            event.preventDefault()
            setSaving(true)

            try {
              await onSave(amount)
              onOpenChange(false)
            } catch (caught) {
              if (caught instanceof ApiError && caught.isValidation) {
                setError(Object.values(caught.errors)[0]?.[0] ?? caught.message)
              } else {
                notifyError(caught)
              }
            } finally {
              setSaving(false)
            }
          }}
        >
          <DialogHeader>
            <DialogTitle>{title}</DialogTitle>
            {description && <DialogDescription>{description}</DialogDescription>}
          </DialogHeader>
          {children}
          <Field data-invalid={!!error}>
            <MoneyInput autoFocus value={amount} onChange={setAmount} currency={currency} allowNegative={allowNegative} className="h-11 text-lg" />
            {error && <FieldError>{error}</FieldError>}
          </Field>
          <DialogFooter>
            <Button type="submit" disabled={saving || amount === ''}>
              {t('Save')}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
