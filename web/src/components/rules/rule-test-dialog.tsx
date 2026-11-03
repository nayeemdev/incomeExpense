import { useMutation, useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { DatePicker } from '@/components/app/date-picker'
import { TransactionList } from '@/components/app/transaction-list'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { useCanEdit, useLedger } from '@/hooks/use-ledger'
import { api } from '@/lib/api/client'
import { ledgerKey } from '@/lib/api/queries'
import { useInvalidateLedger } from '@/lib/api/mutations'
import type { Resource, Rule, Transaction } from '@/lib/api/types'
import { notifyError } from '@/lib/forms'

export function RuleTestDialog({ rule, onClose }: { rule: Rule; onClose: () => void }) {
  const { t } = useTranslation()
  const ledger = useLedger()
  const canEdit = useCanEdit()
  const invalidate = useInvalidateLedger(ledger.id)
  const [start, setStart] = useState<string | null>(null)
  const [end, setEnd] = useState<string | null>(null)
  const { data: matches } = useQuery({
    queryKey: [...ledgerKey(ledger.id), 'rules', rule.id, 'test', start, end],
    queryFn: () => api.post<Resource<Transaction[]>>(`/ledgers/${ledger.id}/rules/${rule.id}/test`, { start, end }).then((r) => r.data),
  })

  const apply = useMutation({
    mutationFn: () => api.post<{ data: { applied: number } }>(`/ledgers/${ledger.id}/rules/${rule.id}/apply`, { start, end }),
    onSuccess: async (response) => {
      await invalidate()
      toast.success(t('Rule applied to {{count}} transactions', { count: response.data.applied }))
      onClose()
    },
    onError: notifyError,
  })

  return (
    <Dialog open onOpenChange={(open) => !open && onClose()}>
      <DialogContent className="sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle>{t('Test "{{name}}"', { name: rule.title })}</DialogTitle>
          <DialogDescription>{t('Existing transactions this rule matches. Nothing changes until you apply it.')}</DialogDescription>
        </DialogHeader>
        <div className="flex gap-2">
          <DatePicker value={start} onChange={setStart} placeholder={t('From the start')} />
          <DatePicker value={end} onChange={setEnd} placeholder={t('Until today')} />
        </div>
        <div className="max-h-96 overflow-y-auto">
          <TransactionList
            transactions={matches}
            empty={<p className="text-muted-foreground py-8 text-center text-sm">{t('No transactions match.')}</p>}
          />
        </div>
        {canEdit && (
          <DialogFooter>
            <Button disabled={!matches?.length || apply.isPending} onClick={() => apply.mutate()}>
              {t('Apply to {{count}} transactions', { count: matches?.length ?? 0 })}
            </Button>
          </DialogFooter>
        )}
      </DialogContent>
    </Dialog>
  )
}
