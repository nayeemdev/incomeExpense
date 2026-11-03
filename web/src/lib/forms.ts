import type { FieldValues, Path, UseFormSetError } from 'react-hook-form'
import { toast } from 'sonner'
import i18n from '@/i18n'
import { ApiError } from './api/client'

/**
 * Shows server-side validation errors on the matching fields and anything else as a toast.
 */
export function handleFormError<T extends FieldValues>(error: unknown, setError: UseFormSetError<T>) {
  if (error instanceof ApiError && error.isValidation) {
    for (const [field, messages] of Object.entries(error.errors)) {
      setError((field === 'transactions' ? 'root' : field) as Path<T>, { message: messages[0] })
    }

    return
  }

  notifyError(error)
}

export function notifyError(error: unknown) {
  toast.error(error instanceof Error && error.message ? error.message : i18n.t('Something went wrong. Please try again.'))
}
