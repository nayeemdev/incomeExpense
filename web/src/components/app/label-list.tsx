import { useQuery } from '@tanstack/react-query'
import { Link } from '@tanstack/react-router'
import { PlusIcon, SearchIcon } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/components/ui/input-group'
import { Skeleton } from '@/components/ui/skeleton'
import { useCanEdit, useLedger } from '@/hooks/use-ledger'
import { useDebouncedValue } from '@/hooks/use-debounced-value'
import { categoriesQuery, tagsQuery } from '@/lib/api/queries'
import { LabelFormDialog } from './label-form-dialog'
import { PageHeader } from './page-header'

export function LabelList({ kind }: { kind: 'categories' | 'tags' }) {
  const { t } = useTranslation()
  const ledger = useLedger()
  const canEdit = useCanEdit()
  const [creating, setCreating] = useState(false)
  const [search, setSearch] = useState('')
  const debounced = useDebouncedValue(search)
  const query = kind === 'tags' ? tagsQuery(ledger.id, debounced) : categoriesQuery(ledger.id, debounced)
  const { data } = useQuery(query as ReturnType<typeof categoriesQuery>)
  const ledgerId = String(ledger.id)
  const isTags = kind === 'tags'

  return (
    <>
      <PageHeader
        title={isTags ? t('Tags') : t('Categories')}
        description={
          isTags
            ? t('Tags group transactions across categories, like a trip or a project.')
            : t('Categories describe what money was spent on or earned from.')
        }
        actions={
          canEdit && (
            <Button onClick={() => setCreating(true)}>
              <PlusIcon />
              {isTags ? t('New tag') : t('New category')}
            </Button>
          )
        }
      />

      <InputGroup className="mb-4 max-w-sm">
        <InputGroupAddon>
          <SearchIcon />
        </InputGroupAddon>
        <InputGroupInput placeholder={t('Search…')} value={search} onChange={(event) => setSearch(event.target.value)} />
      </InputGroup>

      {!data && <Skeleton className="h-48 w-full" />}

      {data?.data.length === 0 && (
        <Empty className="surface-card">
          <EmptyHeader>
            <EmptyTitle>{search ? t('Nothing matches your search') : isTags ? t('No tags yet') : t('No categories yet')}</EmptyTitle>
            <EmptyDescription>{t('You can also create them while adding a transaction.')}</EmptyDescription>
          </EmptyHeader>
          {canEdit && !search && (
            <EmptyContent>
              <Button onClick={() => setCreating(true)}>{isTags ? t('New tag') : t('New category')}</Button>
            </EmptyContent>
          )}
        </Empty>
      )}

      {data && data.data.length > 0 && (
        <ul className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
          {data.data.map((item) => (
            <li key={item.id}>
              <Link
                to={isTags ? '/$ledgerId/tags/$tagId' : '/$ledgerId/categories/$categoryId'}
                params={isTags ? { ledgerId, tagId: String(item.id) } : { ledgerId, categoryId: String(item.id) }}
                className="hover:bg-muted/50 flex items-center justify-between gap-3 surface-card px-4 py-3 transition-shadow hover:shadow-card-hover"
              >
                <span className="truncate font-medium">{item.name}</span>
                <span className="text-muted-foreground shrink-0 text-xs">
                  {t('{{count}} transactions', { count: item.transactions_count ?? 0 })}
                </span>
              </Link>
            </li>
          ))}
        </ul>
      )}

      <LabelFormDialog kind={kind} open={creating} onOpenChange={setCreating} />
    </>
  )
}
