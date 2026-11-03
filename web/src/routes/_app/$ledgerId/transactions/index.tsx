import { useQuery } from '@tanstack/react-query'
import { Link, createFileRoute, useNavigate } from '@tanstack/react-router'
import { PlusIcon, SearchIcon, XIcon } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'
import { DatePicker } from '@/components/app/date-picker'
import { PageHeader } from '@/components/app/page-header'
import { Pagination } from '@/components/app/pagination'
import { useQuickAdd } from '@/components/app/quick-add-context'
import { TransactionList } from '@/components/app/transaction-list'
import { Button } from '@/components/ui/button'
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/components/ui/input-group'
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group'
import { useCanEdit, useLedger } from '@/hooks/use-ledger'
import { transactionsQuery } from '@/lib/api/queries'
import { transactionTypeLabels } from '@/lib/labels'

const searchSchema = z.object({
  search: z.string().optional().catch(undefined),
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
  const filtered = !!(filters.search || filters.type || filters.start || filters.end)

  const update = (patch: Partial<z.infer<typeof searchSchema>>) =>
    navigate({ search: (previous) => ({ ...previous, ...patch, page: undefined }), replace: true })

  return (
    <>
      <PageHeader
        title={t('Transactions')}
        actions={
          canEdit && (
            <>
              <Button variant="outline" asChild>
                <Link to="/$ledgerId/transactions/new" params={{ ledgerId: String(ledger.id) }}>
                  {t('Split transaction')}
                </Link>
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
        <InputGroup className="lg:max-w-xs">
          <InputGroupAddon>
            <SearchIcon />
          </InputGroupAddon>
          <InputGroupInput
            placeholder={t('Search description or notes…')}
            defaultValue={filters.search}
            onChange={(event) => update({ search: event.target.value || undefined })}
          />
        </InputGroup>
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
          <Button variant="ghost" onClick={() => navigate({ search: {}, replace: true })}>
            <XIcon />
            {t('Clear')}
          </Button>
        )}
      </div>

      <TransactionList
        transactions={data?.data}
        empty={
          <Empty className="border">
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
