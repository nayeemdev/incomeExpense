import { useMutation, useQuery } from '@tanstack/react-query'
import { Link } from '@tanstack/react-router'
import { LinkIcon, PlusIcon, XIcon } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Card, CardAction, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Field, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { useDebouncedValue } from '@/hooks/use-debounced-value'
import { useCanEdit, useLedger, useLocale } from '@/hooks/use-ledger'
import { api } from '@/lib/api/client'
import { useInvalidateLedger } from '@/lib/api/mutations'
import { linkTypesQuery, transactionLinksQuery, transactionsQuery } from '@/lib/api/queries'
import { formatDate } from '@/lib/format'
import { notifyError } from '@/lib/forms'
import { Money } from './money'
import { SelectField } from './select-field'

export function TransactionLinks({ groupId }: { groupId: number }) {
  const { t } = useTranslation()
  const locale = useLocale()
  const ledger = useLedger()
  const canEdit = useCanEdit()
  const invalidate = useInvalidateLedger(ledger.id)
  const [adding, setAdding] = useState(false)
  const [search, setSearch] = useState('')
  const debounced = useDebouncedValue(search)
  const { data: links = [] } = useQuery(transactionLinksQuery(ledger.id, groupId))
  const { data: types = [] } = useQuery(linkTypesQuery(ledger.id))
  const [choice, setChoice] = useState('')
  const { data: results } = useQuery({
    ...transactionsQuery(ledger.id, { search: debounced }),
    enabled: adding && debounced.length > 1,
    select: (page) => page.data.filter((item) => item.group_id !== groupId).slice(0, 8),
  })
  const options = Object.fromEntries(types.flatMap((type) => [[`${type.id}:outward`, type.outward], ...(type.outward !== type.inward ? [[`${type.id}:inward`, type.inward]] : [])]))

  const add = useMutation({
    mutationFn: (otherId: number) => {
      const [typeId, direction] = (choice || Object.keys(options)[0]).split(':')

      return api.post(`/ledgers/${ledger.id}/transactions/${groupId}/links`, { link_type_id: Number(typeId), group_id: otherId, direction })
    },
    onSuccess: async () => {
      setAdding(false)
      setSearch('')
      await invalidate()
    },
    onError: notifyError,
  })

  const remove = useMutation({
    mutationFn: (id: number) => api.delete(`/ledgers/${ledger.id}/links/${id}`),
    onSuccess: () => invalidate(),
    onError: notifyError,
  })

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <LinkIcon className="size-4" />
          {t('Linked transactions')}
        </CardTitle>
        {canEdit && (
          <CardAction>
            <Button variant="outline" size="sm" onClick={() => setAdding(true)}>
              <PlusIcon />
              {t('Link')}
            </Button>
          </CardAction>
        )}
      </CardHeader>
      <CardContent>
        {links.length === 0 ? (
          <p className="text-muted-foreground text-sm">{t('Connect a refund to its purchase, or a repayment to what it paid for.')}</p>
        ) : (
          <ul className="divide-y">
            {links.map((link) => (
              <li key={link.id} className="flex items-center gap-2 py-2 text-sm">
                <span className="text-muted-foreground">{link.label}</span>
                <Link
                  to="/$ledgerId/transactions/$groupId"
                  params={{ ledgerId: String(ledger.id), groupId: String(link.group.id) }}
                  className="min-w-0 flex-1 truncate font-medium hover:underline"
                >
                  {link.group.title}
                </Link>
                {canEdit && (
                  <Button variant="ghost" size="icon" className="size-7" aria-label={t('Remove link')} onClick={() => remove.mutate(link.id)}>
                    <XIcon />
                  </Button>
                )}
              </li>
            ))}
          </ul>
        )}
      </CardContent>
      <Dialog open={adding} onOpenChange={setAdding}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t('Link a transaction')}</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <Field>
              <FieldLabel>{t('This transaction')}</FieldLabel>
              <SelectField value={choice || Object.keys(options)[0] || ''} onChange={setChoice} options={options} />
            </Field>
            <Field>
              <FieldLabel htmlFor="link-search">{t('Find the other transaction')}</FieldLabel>
              <Input id="link-search" autoFocus placeholder={t('Search by description…')} value={search} onChange={(event) => setSearch(event.target.value)} />
            </Field>
            <ul className="divide-y rounded-md border empty:hidden">
              {results?.map((item) => (
                <li key={item.id}>
                  <button
                    type="button"
                    className="hover:bg-muted flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm"
                    onClick={() => add.mutate(item.group_id)}
                  >
                    <span className="min-w-0">
                      <span className="block truncate font-medium">{item.description}</span>
                      <span className="text-muted-foreground text-xs">{formatDate(item.date, locale)}</span>
                    </span>
                    <Money amount={item.amount} currency={item.currency.code} tone="none" />
                  </button>
                </li>
              ))}
            </ul>
          </div>
        </DialogContent>
      </Dialog>
    </Card>
  )
}
