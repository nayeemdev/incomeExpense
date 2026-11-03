import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation, useQuery } from '@tanstack/react-query'
import { CheckIcon } from 'lucide-react'
import { createElement } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'
import { Button } from '@/components/ui/button'
import { DialogFooter } from '@/components/ui/dialog'
import { FormDialog } from '@/components/app/form-dialog'
import { FieldGroup } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { useLedger } from '@/hooks/use-ledger'
import { api } from '@/lib/api/client'
import { useInvalidateLedger } from '@/lib/api/mutations'
import type { Resource } from '@/lib/api/types'
import { handleFormError } from '@/lib/forms'
import { categoryStylesQuery } from '@/lib/api/queries'
import { categoryColor, categoryIcon } from '@/lib/category-style'
import { cn } from '@/lib/utils'
import { CategoryIcon } from './category-icon'
import { FormField } from './form-field'

const schema = z.object({ name: z.string().trim().min(1).max(255), notes: z.string(), icon: z.string().nullable(), color: z.string().nullable() })

type LabelFormDialogProps = {
  kind: 'categories' | 'tags'
  open: boolean
  onOpenChange: (open: boolean) => void
  item?: { id: string; name: string; notes?: string | null; description?: string | null; icon?: string | null; color?: string | null }
  onSaved?: (id: string) => void
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
    values: { name: item?.name ?? '', notes: (kind === 'tags' ? item?.description : item?.notes) ?? '', icon: item?.icon ?? null, color: item?.color ?? null },
  })

  const [icon, color] = useWatch({ control: form.control, name: ['icon', 'color'] })

  const save = useMutation({
    mutationFn: (values: z.infer<typeof schema>) => {
      const body = {
        name: values.name,
        [textField]: values.notes || null,
        ...(kind === 'categories' && values.icon ? { icon: values.icon } : {}),
        ...(kind === 'categories' && values.color ? { color: values.color } : {}),
      }
      const path = `/ledgers/${ledger.id}/${kind}`

      return (item ? api.patch<Resource<{ id: string }>>(`${path}/${item.id}`, body) : api.post<Resource<{ id: string }>>(path, body)).then(
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
    <FormDialog open={open} onOpenChange={onOpenChange} title={title}>
        <form onSubmit={form.handleSubmit((values) => save.mutate(values))} className="space-y-6">
          <FieldGroup>
            <FormField control={form.control} name="name" required label={t('Name')}>
              {(field) => <Input autoFocus {...field} />}
            </FormField>
            {kind === 'categories' && <StylePicker icon={icon} color={color} onChange={(key, value) => form.setValue(key, value, { shouldDirty: true })} />}
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
    </FormDialog>
  )
}

type StylePickerProps = { icon: string | null; color: string | null; onChange: (key: 'icon' | 'color', value: string) => void }

/**
 * The predefined icons and colors a category can use. Leaving them unset lets the server suggest a style from the name.
 */
function StylePicker({ icon, color, onChange }: StylePickerProps) {
  const { t } = useTranslation()
  const { data: styles } = useQuery(categoryStylesQuery)
  if (!styles) return null
  const tint = categoryColor(color)

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-3">
        <CategoryIcon icon={icon} color={color} className="size-12 rounded-xl" />
        <p className="text-muted-foreground text-sm">{t('Pick an icon and color, or leave them and one is chosen from the name.')}</p>
      </div>
      <div className="space-y-2">
        <p className="text-sm font-medium">{t('Color')}</p>
        <div role="radiogroup" aria-label={t('Color')} className="flex flex-wrap gap-2">
          {styles.colors.map((name) => (
            <button
              key={name}
              type="button"
              role="radio"
              aria-checked={color === name}
              aria-label={name}
              onClick={() => onChange('color', name)}
              className={cn('grid size-7 place-items-center rounded-full ring-offset-2 ring-offset-background transition', color === name && 'ring-foreground ring-2')}
              style={{ backgroundColor: categoryColor(name) }}
            >
              {color === name && <CheckIcon className="size-3.5 text-white" />}
            </button>
          ))}
        </div>
      </div>
      <div className="space-y-2">
        <p className="text-sm font-medium">{t('Icon')}</p>
        <div role="radiogroup" aria-label={t('Icon')} className="grid max-h-48 grid-cols-8 gap-1.5 overflow-y-auto rounded-lg border p-2">
          {styles.icons.map((name) => (
            <button
              key={name}
              type="button"
              role="radio"
              aria-checked={icon === name}
              aria-label={name}
              onClick={() => onChange('icon', name)}
              className={cn('hover:bg-muted grid aspect-square place-items-center rounded-md border border-transparent transition-colors', icon !== name && 'text-muted-foreground')}
              style={icon === name ? { color: tint, borderColor: tint, backgroundColor: `color-mix(in srgb, ${tint} 14%, transparent)` } : undefined}
            >
              {createElement(categoryIcon(name), { className: 'size-4' })}
            </button>
          ))}
        </div>
      </div>
    </div>
  )
}
