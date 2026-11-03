import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { FieldGroup } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { useLedger } from '@/hooks/use-ledger'
import { api } from '@/lib/api/client'
import { useInvalidateLedger } from '@/lib/api/mutations'
import type { Resource } from '@/lib/api/types'
import { handleFormError } from '@/lib/forms'
import { FormField } from './form-field'

const schema = z.object({ name: z.string().trim().min(1).max(255), notes: z.string() })

type LabelFormDialogProps = {
  kind: 'categories' | 'tags'
  open: boolean
  onOpenChange: (open: boolean) => void
  item?: { id: number; name: string; notes?: string | null; description?: string | null }
  onSaved?: (id: number) => void
}

/**
 * Create or rename a category or tag. Tags call their free text "description", categories "notes".
 */
export function LabelFormDialog({ kind, open, onOpenChange, item, onSaved }: LabelFormDialogProps) {
  const { t } = useTranslation()
  const ledger = useLedger()
  const invalidate = useInvalidateLedger(ledger.id)
  const textField = kind === 'tags' ? 'description' : 'notes'
  const form = useForm({
    resolver: zodResolver(schema),
    values: { name: item?.name ?? '', notes: (kind === 'tags' ? item?.description : item?.notes) ?? '' },
  })

  const save = useMutation({
    mutationFn: (values: z.infer<typeof schema>) => {
      const body = { name: values.name, [textField]: values.notes || null }
      const path = `/ledgers/${ledger.id}/${kind}`

      return (item ? api.patch<Resource<{ id: number }>>(`${path}/${item.id}`, body) : api.post<Resource<{ id: number }>>(path, body)).then(
        (response) => response.data,
      )
    },
    onSuccess: async (saved) => {
      await invalidate()
      onOpenChange(false)
      onSaved?.(saved.id)
    },
    onError: (error) => handleFormError(error, form.setError),
  })

  const title = item
    ? t('Edit {{name}}', { name: item.name })
    : kind === 'tags'
      ? t('New tag')
      : t('New category')

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{title}</DialogTitle>
        </DialogHeader>
        <form onSubmit={form.handleSubmit((values) => save.mutate(values))} className="space-y-6">
          <FieldGroup>
            <FormField control={form.control} name="name" label={t('Name')}>
              {(field) => <Input autoFocus {...field} />}
            </FormField>
            <FormField control={form.control} name="notes" label={kind === 'tags' ? t('Description') : t('Notes')}>
              {(field) => <Textarea rows={3} {...field} />}
            </FormField>
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
      </DialogContent>
    </Dialog>
  )
}
