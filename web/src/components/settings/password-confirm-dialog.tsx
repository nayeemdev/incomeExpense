import { useMutation } from '@tanstack/react-query'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Field, FieldError, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { ApiError, api } from '@/lib/api/client'

type PasswordConfirmDialogProps = { open: boolean; onOpenChange: (open: boolean) => void; onConfirmed: () => void }

export function PasswordConfirmDialog({ open, onOpenChange, onConfirmed }: PasswordConfirmDialogProps) {
  const { t } = useTranslation()
  const [password, setPassword] = useState('')

  const confirm = useMutation({
    mutationFn: () => api.post('/auth/user/confirm-password', { password }),
    onSuccess: () => {
      setPassword('')
      onOpenChange(false)
      onConfirmed()
    },
  })

  const error = confirm.error instanceof ApiError ? confirm.error.errors.password?.[0] ?? confirm.error.message : null

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <form
          onSubmit={(event) => {
            event.preventDefault()
            confirm.mutate()
          }}
          className="space-y-6"
        >
          <DialogHeader>
            <DialogTitle>{t('Confirm your password')}</DialogTitle>
            <DialogDescription>{t('For your security, enter your password to continue.')}</DialogDescription>
          </DialogHeader>
          <Field data-invalid={!!error}>
            <FieldLabel htmlFor="confirm-password">{t('Password')}</FieldLabel>
            <Input
              id="confirm-password"
              type="password"
              autoComplete="current-password"
              autoFocus
              value={password}
              onChange={(event) => setPassword(event.target.value)}
            />
            {error && <FieldError>{error}</FieldError>}
          </Field>
          <DialogFooter>
            <Button type="submit" disabled={confirm.isPending}>
              {t('Confirm')}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
