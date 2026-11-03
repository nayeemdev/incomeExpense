import { useMutation, useQuery } from '@tanstack/react-query'
import { createFileRoute, useNavigate } from '@tanstack/react-router'
import { BookmarkIcon, BookmarkPlusIcon, DownloadIcon, PlusIcon, SearchIcon, XIcon } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'
import { BulkActions } from '@/components/app/bulk-actions'
import { DatePicker } from '@/components/app/date-picker'
import { PageHeader } from '@/components/app/page-header'
import { Pagination } from '@/components/app/pagination'
import { useQuickAdd } from '@/components/app/quick-add-context'
import { SearchHelp } from '@/components/app/search-help'
import { TransactionList } from '@/components/app/transaction-list'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { Button } from '@/components/ui/button'
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/components/ui/input-group'
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group'
import { useCanEdit, useLedger } from '@/hooks/use-ledger'
import { api, downloadUrl } from '@/lib/api/client'
import { useInvalidateLedger } from '@/lib/api/mutations'
import { savedSearchesQuery, transactionsQuery } from '@/lib/api/queries'
import { notifyError } from '@/lib/forms'
import { transactionTypeLabels } from '@/lib/labels'

const searchSchema = z.object({
  query: z.string().optional().catch(undefined),
  type: z.enum(['withdrawal', 'deposit', 'transfer']).optional().catch(undefined),
  start: z.string().optional().catch(undefined),
  end: z.string().optional().catch(undefined),
  page: z.number().int().positive().optional().catch(undefined),
})

export const Route = createFileRoute('/_app/$ledgerId/transactions/')({
  validateSearch: searchSchema,
  component: Transactions,
})

function Transactions() {
  const { t } = useTranslation()
  const navigate = useNavigate({ from: Route.fullPath })
  const ledger = useLedger()
  const canEdit = useCanEdit()
  const quickAdd = useQuickAdd()
  const labels = transactionTypeLabels()
  const filters = Route.useSearch()
  const { data } = useQuery(
    transactionsQuery(ledger.id, { ...filters, type: filters.type ? [filters.type] : undefined }),
  )
  const filtered = !!(filters.query || filters.type || filters.start || filters.end)
  const invalidate = useInvalidateLedger(ledger.id)
  const [selected, setSelected] = useState<Set<number>>(new Set())
  const [draft, setDraft] = useState(filters.query ?? '')
  const { data: saved = [] } = useQuery(savedSearchesQuery(ledger.id))
  const toggle = (id: number) =>
    setSelected((current) => {
      const next = new Set(current)
      if (next.has(id)) next.delete(id)
      else next.add(id)

      return next
    })

  const saveSearch = useMutation({
    mutationFn: (name: string) => api.post(`/ledgers/${ledger.id}/saved-searches`, { name, query: filters.query }),
    onSuccess: () => invalidate(),
    onError: notifyError,
  })

  const update = (patch: Partial<z.infer<typeof searchSchema>>) =>
    navigate({ search: (previous) => ({ ...previous, ...patch, page: undefined }), replace: true })

  return (
    <>
      <PageHeader
        title={t('Transactions')}
        description={t('Every expense, income and transfer. Search with filters, or select several to change them at once.')}
        actions={
          canEdit && (
            <>
              <Button variant="outline" onClick={() => quickAdd({ split: true })}>
                {t('Split transaction')}
              </Button>
              <Button onClick={() => quickAdd()}>
                <PlusIcon />
                {t('Add')}
              </Button>
            </>
          )
        }
      />

      <div className="mb-6 flex flex-col gap-2 lg:flex-row lg:items-center">
        <form
          className="flex flex-1 items-center gap-1 lg:max-w-md"
          onSubmit={(event) => {
            event.preventDefault()
            update({ query: draft.trim() || undefined })
          }}
        >
          <InputGroup>
            <InputGroupAddon>
              <SearchIcon />
            </InputGroupAddon>
            <InputGroupInput
              aria-label={t('Search')}
              placeholder={t('Search, e.g. coffee category:Food -tag:work')}
              value={draft}
              onChange={(event) => setDraft(event.target.value)}
              onBlur={() => update({ query: draft.trim() || undefined })}
            />
          </InputGroup>
          <SearchHelp onInsert={(snippet) => setDraft((current) => `${current} ${snippet}`.trim())} />
          {(saved.length > 0 || filters.query) && (
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" aria-label={t('Saved searches')}>
                  <BookmarkIcon />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" className="min-w-56">
                {saved.map((item) => (
                  <DropdownMenuItem
                    key={item.id}
                    onSelect={() => {
                      setDraft(item.query)
                      update({ query: item.query })
                    }}
                  >
                    {item.name}
                  </DropdownMenuItem>
                ))}
                {filters.query && (
                  <>
                    {saved.length > 0 && <DropdownMenuSeparator />}
                    <DropdownMenuItem
                      onSelect={() => {
                        const name = window.prompt(t('Name this search'), filters.query)
                        if (name) saveSearch.mutate(name)
                      }}
                    >
                      <BookmarkPlusIcon />
                      {t('Save this search')}
                    </DropdownMenuItem>
                  </>
                )}
              </DropdownMenuContent>
            </DropdownMenu>
          )}
        </form>
        <ToggleGroup
          type="single"
          variant="outline"
          value={filters.type ?? ''}
          onValueChange={(value) => update({ type: (value || undefined) as z.infer<typeof searchSchema>['type'] })}
        >
          {(['withdrawal', 'deposit', 'transfer'] as const).map((type) => (
            <ToggleGroupItem key={type} value={type} className="px-3">
              {labels[type]}
            </ToggleGroupItem>
          ))}
        </ToggleGroup>
        <div className="flex items-center gap-2">
          <DatePicker value={filters.start ?? null} onChange={(start) => update({ start: start ?? undefined })} placeholder={t('From')} className="lg:w-40" />
          <DatePicker value={filters.end ?? null} onChange={(end) => update({ end: end ?? undefined })} placeholder={t('To')} className="lg:w-40" />
        </div>
        {filtered && (
          <Button variant="outline" size="icon" aria-label={t('Download as CSV')} asChild>
            <a href={downloadUrl(`/ledgers/${ledger.id}/export/transactions.csv?${new URLSearchParams(Object.entries({ query: filters.query ?? '', start: filters.start ?? '', end: filters.end ?? '' }).filter(([, value]) => value))}`)}>
              <DownloadIcon />
            </a>
          </Button>
        )}
        {filtered && (
          <Button
            variant="ghost"
            onClick={() => {
              setDraft('')
              void navigate({ search: {}, replace: true })
            }}
          >
            <XIcon />
            {t('Clear')}
          </Button>
        )}
      </div>

      <TransactionList
        transactions={data?.data}
        selected={selected}
        onToggle={canEdit ? toggle : undefined}
        empty={
          <Empty className="surface-card">
            <EmptyHeader>
              <EmptyTitle>{filtered ? t('No transactions match these filters') : t('No transactions yet')}</EmptyTitle>
              <EmptyDescription>
                {filtered ? t('Try a wider date range or a different search.') : t('Record your first expense or income to see it here.')}
              </EmptyDescription>
            </EmptyHeader>
            {canEdit && !filtered && (
              <EmptyContent>
                <Button onClick={() => quickAdd()}>{t('Add a transaction')}</Button>
              </EmptyContent>
            )}
          </Empty>
        }
      />
      {selected.size > 0 && <BulkActions selected={[...selected]} onDone={() => setSelected(new Set())} />}
      {data && (
        <Pagination
          page={data.meta.current_page}
          lastPage={data.meta.last_page}
          onChange={(page) => navigate({ search: (previous) => ({ ...previous, page }) })}
        />
      )}
    </>
  )
}
