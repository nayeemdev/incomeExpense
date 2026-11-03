import { useMutation, useQuery } from '@tanstack/react-query'
import { useNavigate } from '@tanstack/react-router'
import { PencilIcon, Trash2Icon } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { useCanEdit, useLedger } from '@/hooks/use-ledger'
import { api } from '@/lib/api/client'
import { useInvalidateLedger } from '@/lib/api/mutations'
import { ledgerKey, transactionsQuery } from '@/lib/api/queries'
import type { Category, Resource, Tag } from '@/lib/api/types'
import { notifyError } from '@/lib/forms'
import { ConfirmDialog } from './confirm-dialog'
import { LabelFormDialog } from './label-form-dialog'
import { PageHeader } from './page-header'
import { Pagination } from './pagination'
import { TransactionList } from './transaction-list'

type LabelDetailProps = { kind: 'categories' | 'tags'; id: number; page: number; onPageChange: (page: number) => void }

export function LabelDetail({ kind, id, page, onPageChange }: LabelDetailProps) {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const ledger = useLedger()
  const canEdit = useCanEdit()
  const invalidate = useInvalidateLedger(ledger.id)
  const [editing, setEditing] = useState(false)
  const isTags = kind === 'tags'
  const { data: item } = useQuery({
    queryKey: [...ledgerKey(ledger.id), kind, 'detail', id],
    queryFn: () => api.get<Resource<Category | Tag>>(`/ledgers/${ledger.id}/${kind}/${id}`).then((r) => r.data),
  })
  const { data: transactions } = useQuery(
    transactionsQuery(ledger.id, isTags ? { tag_id: id, page } : { category_id: id, page }),
  )

  const remove = useMutation({
    mutationFn: () => api.delete(`/ledgers/${ledger.id}/${kind}/${id}`),
    onSuccess: async () => {
      await navigate({ to: isTags ? '/$ledgerId/tags' : '/$ledgerId/categories', params: { ledgerId: String(ledger.id) } })
      await invalidate()
    },
    onError: notifyError,
  })

  if (!item) return <Skeleton className="h-64 w-full" />

  const text = 'description' in item ? item.description : item.notes

  return (
    <>
      <PageHeader
        title={item.name}
        description={text}
        actions={
          canEdit && (
            <>
              <Button variant="outline" onClick={() => setEditing(true)}>
                <PencilIcon />
                {t('Edit')}
              </Button>
              <ConfirmDialog
                trigger={
                  <Button variant="outline" size="icon" aria-label={t('Delete')}>
                    <Trash2Icon />
                  </Button>
                }
                title={t('Delete {{name}}?', { name: item.name })}
                description={t('Transactions are kept, they just lose this label.')}
                onConfirm={() => remove.mutate()}
              />
            </>
          )
        }
      />
      <TransactionList
        transactions={transactions?.data}
        empty={<p className="text-muted-foreground rounded-lg border py-10 text-center text-sm">{t('No transactions yet.')}</p>}
      />
      {transactions && (
        <Pagination page={transactions.meta.current_page} lastPage={transactions.meta.last_page} onChange={onPageChange} />
      )}
      <LabelFormDialog kind={kind} open={editing} onOpenChange={setEditing} item={item} />
    </>
  )
}
