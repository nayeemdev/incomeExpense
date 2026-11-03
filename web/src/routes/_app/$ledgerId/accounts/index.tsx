import { useQuery } from '@tanstack/react-query'
import { Link, createFileRoute, useNavigate } from '@tanstack/react-router'
import { PlusIcon, SearchIcon } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'
import { AccountForm } from '@/components/app/account-form'
import { Money } from '@/components/app/money'
import { PageHeader } from '@/components/app/page-header'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/components/ui/input-group'
import { FormDialog } from '@/components/app/form-dialog'
import { Skeleton } from '@/components/ui/skeleton'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { useCanEdit, useLedger } from '@/hooks/use-ledger'
import { accountsQuery } from '@/lib/api/queries'
import { accountTypeDescriptions, accountTypeLabels, accountTypeSingular, assetRoleLabels, liabilityTypeLabels } from '@/lib/labels'

export const Route = createFileRoute('/_app/$ledgerId/accounts/')({
  validateSearch: z.object({
    type: z.enum(['asset', 'expense', 'revenue', 'liability']).catch('asset'),
    search: z.string().optional().catch(undefined),
    create: z.boolean().optional().catch(undefined),
  }),
  component: Accounts,
})

function Accounts() {
  const { t } = useTranslation()
  const navigate = useNavigate({ from: Route.fullPath })
  const ledger = useLedger()
  const canEdit = useCanEdit()
  const { type, search = '', create } = Route.useSearch()
  const { data } = useQuery(accountsQuery(ledger.id, { type: [type], search }))
  const own = type === 'asset' || type === 'liability'
  const roles = assetRoleLabels()
  const liabilityTypes = liabilityTypeLabels()
  const ledgerId = String(ledger.id)

  const setSearch = (patch: { search?: string; create?: boolean }) =>
    navigate({ search: (previous) => ({ ...previous, ...patch }), replace: true })

  return (
    <>
      <PageHeader
        title={accountTypeLabels()[type]}
        description={accountTypeDescriptions()[type]}
        actions={
          canEdit && (
            <Button onClick={() => setSearch({ create: true })}>
              <PlusIcon />
              {t('New {{type}}', { type: accountTypeSingular()[type].toLowerCase() })}
            </Button>
          )
        }
      />

      <InputGroup className="mb-4 max-w-sm">
        <InputGroupAddon>
          <SearchIcon />
        </InputGroupAddon>
        <InputGroupInput
          placeholder={t('Search accounts…')}
          defaultValue={search}
          onChange={(event) => setSearch({ search: event.target.value || undefined })}
        />
      </InputGroup>

      {!data && <Skeleton className="h-48 w-full" />}

      {data?.data.length === 0 && (
        <Empty className="surface-card">
          <EmptyHeader>
            <EmptyTitle>{search ? t('No accounts match your search') : t('No accounts yet')}</EmptyTitle>
            <EmptyDescription>
              {type === 'expense' || type === 'revenue'
                ? t('These are created for you when you record transactions.')
                : t('Add one to start tracking its balance.')}
            </EmptyDescription>
          </EmptyHeader>
          {canEdit && !search && (
            <EmptyContent>
              <Button onClick={() => setSearch({ create: true })}>{t('Add account')}</Button>
            </EmptyContent>
          )}
        </Empty>
      )}

      {data && data.data.length > 0 && (
        <div className="surface-card overflow-hidden">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>{t('Name')}</TableHead>
                {own && <TableHead className="hidden sm:table-cell">{t('Kind')}</TableHead>}
                {own && <TableHead className="text-right">{t('Balance')}</TableHead>}
              </TableRow>
            </TableHeader>
            <TableBody>
              {data.data.map((account) => (
                <TableRow key={account.id} className="relative">
                  <TableCell className="font-medium">
                    <Link
                      to="/$ledgerId/accounts/$accountId"
                      params={{ ledgerId, accountId: String(account.id) }}
                      className="after:absolute after:inset-0"
                    >
                      {account.name}
                    </Link>
                    {!account.active && (
                      <Badge variant="outline" className="ml-2">
                        {t('Inactive')}
                      </Badge>
                    )}
                  </TableCell>
                  {own && (
                    <TableCell className="text-muted-foreground hidden sm:table-cell">
                      {account.asset_role ? roles[account.asset_role] : account.liability_type ? liabilityTypes[account.liability_type] : ''}
                    </TableCell>
                  )}
                  {own && (
                    <TableCell className="text-right font-medium">
                      <Money amount={account.balance} currency={account.currency?.code ?? ledger.primary_currency.code} />
                    </TableCell>
                  )}
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>
      )}

      <FormDialog open={!!create} onOpenChange={(open) => setSearch({ create: open || undefined })} title={t('New {{type}}', { type: accountTypeSingular()[type].toLowerCase() })} description={t('You can change everything later.')} size="lg">
        <AccountForm
              type={type}
              onCancel={() => setSearch({ create: undefined })}
              onSaved={(account) =>
                navigate({ to: '/$ledgerId/accounts/$accountId', params: { ledgerId, accountId: String(account.id) } })
              }
            />
      </FormDialog>
    </>
  )
}
