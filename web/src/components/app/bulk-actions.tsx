import { useMutation, useQuery } from '@tanstack/react-query'
import { XIcon } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { useLedger } from '@/hooks/use-ledger'
import { api } from '@/lib/api/client'
import { useInvalidateLedger } from '@/lib/api/mutations'
import { categoriesQuery } from '@/lib/api/queries'
import { notifyError } from '@/lib/forms'
import { Combobox } from './combobox'
import { ConfirmDialog } from './confirm-dialog'
import { SelectField } from './select-field'

type BulkActionsProps = { selected: number[]; onDone: () => void }

export function BulkActions({ selected, onDone }: BulkActionsProps) {
  const { t } = useTranslation()
  const ledger = useLedger()
  const invalidate = useInvalidateLedger(ledger.id)
  const [action, setAction] = useState('set_category')
  const [value, setValue] = useState('')
  const { data: categories = [] } = useQuery({ ...categoriesQuery(ledger.id), select: (page) => page.data })
  const actions: Record<string, string> = {
    set_category: t('Set category'),
    clear_category: t('Clear category'),
    add_tag: t('Add tag'),
    remove_tag: t('Remove tag'),
    set_budget: t('Set budget'),
    clear_budget: t('Clear budget'),
    append_notes: t('Add to notes'),
  }
  const needsValue = !action.startsWith('clear_')

  const apply = useMutation({
    mutationFn: (type: string) => api.post<{ data: { updated: number } }>(`/ledgers/${ledger.id}/transactions/bulk`, { transaction_ids: selected, action: type, value }),
    onSuccess: async (response) => {
      await invalidate()
      toast.success(t('{{count}} transactions updated', { count: response.data.updated }))
      setValue('')
      onDone()
    },
    onError: notifyError,
  })

  return (
    <div className="bg-background/95 sticky bottom-4 z-20 mx-auto mt-4 flex max-w-3xl flex-wrap items-center gap-2 rounded-xl border p-2 shadow-lg backdrop-blur">
      <span className="px-2 text-sm font-medium">{t('{{count}} selected', { count: selected.length })}</span>
      <SelectField className="w-40" value={action} onChange={setAction} options={actions} />
      {needsValue &&
        (action === 'set_category' ? (
          <Combobox
            className="w-44"
            options={categories.map((category) => ({ value: category.name, label: category.name }))}
            value={value || null}
            displayValue={value || undefined}
            onChange={(next) => setValue(next ?? '')}
            onCreate={setValue}
            placeholder={t('Category')}
          />
        ) : (
          <Input className="w-40" value={value} onChange={(event) => setValue(event.target.value)} placeholder={t('Value')} />
        ))}
      <Button size="sm" disabled={apply.isPending || (needsValue && !value)} onClick={() => apply.mutate(action)}>
        {t('Apply')}
      </Button>
      <ConfirmDialog
        trigger={
          <Button size="sm" variant="destructive">
            {t('Delete')}
          </Button>
        }
        title={t('Delete {{count}} transactions?', { count: selected.length })}
        description={t('Split transactions are deleted as a whole.')}
        onConfirm={() => apply.mutate('delete_transaction')}
      />
      <Button size="icon" variant="ghost" className="ml-auto" aria-label={t('Clear selection')} onClick={onDone}>
        <XIcon />
      </Button>
    </div>
  )
}
