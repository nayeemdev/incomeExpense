import { useMutation, useQuery } from '@tanstack/react-query'
import { createFileRoute, useNavigate } from '@tanstack/react-router'
import { PencilIcon, PlusIcon, ScaleIcon, Trash2Icon } from 'lucide-react'
import { toast } from 'sonner'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'
import { AccountForm } from '@/components/app/account-form'
import { BalanceChart } from '@/components/charts/balance-chart'
import { Attachments } from '@/components/app/attachments'
import { ConfirmDialog } from '@/components/app/confirm-dialog'
import { DatePicker } from '@/components/app/date-picker'
import { AmountDialog } from '@/components/planning/amount-dialog'
import { Money } from '@/components/app/money'
import { PageHeader } from '@/components/app/page-header'
import { Pagination } from '@/components/app/pagination'
import { useQuickAdd } from '@/components/app/quick-add-context'
import { TransactionList } from '@/components/app/transaction-list'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader } from '@/components/ui/card'
import { FormDialog } from '@/components/app/form-dialog'
import { Skeleton } from '@/components/ui/skeleton'
import { useCanEdit, useLedger } from '@/hooks/use-ledger'
import { api } from '@/lib/api/client'
import { useInvalidateLedger } from '@/lib/api/mutations'
import { accountQuery, balanceHistoryQuery, transactionsQuery } from '@/lib/api/queries'
import { rangePresets } from '@/lib/period'
import { todayIso } from '@/lib/format'
import { notifyError } from '@/lib/forms'
import { accountTypeSingular, assetRoleLabels, liabilityDirectionLabels, liabilityTypeLabels } from '@/lib/labels'

export const Route = createFileRoute('/_app/$ledgerId/accounts/$accountId')({
  validateSearch: z.object({ page: z.number().int().positive().optional().catch(undefined) }),
  component: AccountDetail,
})

function AccountDetail() {
  const { t } = useTranslation()
  const navigate = useNavigate({ from: Route.fullPath })
  const ledger = useLedger()
  const canEdit = useCanEdit()
  const quickAdd = useQuickAdd()
  const invalidate = useInvalidateLedger(ledger.id)
  const [editing, setEditing] = useState(false)
  const [reconciling, setReconciling] = useState(false)
  const [reconcileDate, setReconcileDate] = useState(todayIso())
  const accountId = Route.useParams().accountId
  const { page = 1 } = Route.useSearch()
  const { data: account } = useQuery(accountQuery(ledger.id, accountId))
  const { data: transactions } = useQuery(transactionsQuery(ledger.id, { account_id: accountId, page }))

  const remove = useMutation({
    mutationFn: () => api.delete(`/ledgers/${ledger.id}/accounts/${accountId}`),
    onSuccess: async () => {
      await navigate({ to: '/$ledgerId/accounts', params: { ledgerId: ledger.id }, search: { type: account?.type ?? 'asset' } })
      await invalidate()
    },
    onError: notifyError,
  })

  if (!account) return <Skeleton className="h-64 w-full" />

  const own = account.type === 'asset' || account.type === 'liability'
  const currency = account.currency?.code ?? ledger.primary_currency.code
  const subtitle = [
    accountTypeSingular()[account.type],
    account.asset_role && assetRoleLabels()[account.asset_role],
    account.liability_type && liabilityTypeLabels()[account.liability_type],
    account.liability_direction && liabilityDirectionLabels()[account.liability_direction],
    account.currency?.code,
  ]
    .filter(Boolean)
    .join(' · ')

  return (
    <>
      <PageHeader
        title={
          <span className="flex items-center gap-2">
            {account.name}
            {!account.active && <Badge variant="outline">{t('Inactive')}</Badge>}
          </span>
        }
        description={subtitle}
        actions={
          canEdit && (
            <>
              {own && (
                <Button onClick={() => quickAdd({ accountId: account.id, type: account.type === 'asset' ? 'withdrawal' : 'deposit' })}>
                  <PlusIcon />
                  {t('Add transaction')}
                </Button>
              )}
              {own && (
                <Button variant="outline" onClick={() => setReconciling(true)}>
                  <ScaleIcon />
                  {t('Reconcile')}
                </Button>
              )}
              <Button variant="outline" onClick={() => setEditing(true)}>
                <PencilIcon />
                {t('Edit')}
              </Button>
              <ConfirmDialog
                trigger={
                  <Button variant="outline" size="icon" aria-label={t('Delete account')}>
                    <Trash2Icon />
                  </Button>
                }
                title={t('Delete {{name}}?', { name: account.name })}
                description={t('All transactions that involve this account are deleted as well. This cannot be undone.')}
                onConfirm={() => remove.mutate()}
              />
            </>
          )
        }
      />

      {own && (
        <div className="stat-grid mb-6">
          <Card>
            <CardHeader>
              <CardDescription>{t('Current balance')}</CardDescription>
            </CardHeader>
            <CardContent>
              <Money amount={account.balance} currency={currency} className="text-3xl font-semibold" />
            </CardContent>
          </Card>
          {account.opening_balance && account.opening_balance_date && (
            <Card>
              <CardHeader>
                <CardDescription>{t('Opening balance')}</CardDescription>
              </CardHeader>
              <CardContent className="space-y-1">
                <Money amount={account.opening_balance} currency={currency} className="text-xl font-medium" />
                <p className="text-muted-foreground text-xs">{account.opening_balance_date}</p>
              </CardContent>
            </Card>
          )}
          {(account.iban || account.account_number) && (
            <Card>
              <CardHeader>
                <CardDescription>{t('Bank details')}</CardDescription>
              </CardHeader>
              <CardContent className="space-y-1 text-sm">
                {account.iban && <p className="font-mono">{account.iban}</p>}
                {account.bic && <p className="text-muted-foreground font-mono">{account.bic}</p>}
                {account.account_number && <p className="font-mono">{account.account_number}</p>}
              </CardContent>
            </Card>
          )}
        </div>
      )}

      {own && <AccountBalanceCard accountId={account.id} currency={currency} />}

      {account.notes && <p className="text-muted-foreground mb-6 text-sm whitespace-pre-line">{account.notes}</p>}

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_20rem]">
        <div>
          <TransactionList
            transactions={transactions?.data}
            accountId={account.id}
            showRunningBalance={own}
            empty={<p className="text-muted-foreground surface-card py-10 text-center text-sm">{t('No transactions yet.')}</p>}
          />
          {transactions && (
            <Pagination
              page={transactions.meta.current_page}
              lastPage={transactions.meta.last_page}
              onChange={(next) => navigate({ search: { page: next } })}
            />
          )}
        </div>
        <div>
          <Attachments type="account" id={account.id} />
        </div>
      </div>

      <AmountDialog
        open={reconciling}
        onOpenChange={setReconciling}
        title={t('Reconcile {{name}}', { name: account.name })}
        description={t('Enter the balance from your statement. Any difference is recorded as a reconciliation.')}
        currency={currency}
        initial={account.balance ?? ''}
        allowNegative
        onSave={async (balance) => {
          await api.post(`/ledgers/${ledger.id}/accounts/${account.id}/reconcile`, { date: reconcileDate, balance })
          await invalidate()
          toast.success(t('Account reconciled'))
        }}
      >
        <DatePicker value={reconcileDate} onChange={(date) => setReconcileDate(date ?? todayIso())} />
      </AmountDialog>

      <FormDialog open={editing} onOpenChange={setEditing} title={t('Edit {{name}}', { name: account.name })} size="lg">
        <AccountForm type={account.type} account={account} onCancel={() => setEditing(false)} onSaved={() => setEditing(false)} />
      </FormDialog>
    </>
  )
}

function AccountBalanceCard({ accountId, currency }: { accountId: string; currency: string }) {
  const { t } = useTranslation()
  const ledger = useLedger()
  const [range] = useState(() => rangePresets(new Date()).last_12_months)
  const { data } = useQuery(balanceHistoryQuery(ledger.id, accountId, { ...range, interval: 'week' }))

  if (!data || data.data.points.length < 2) return null

  return (
    <Card className="mb-6">
      <CardHeader>
        <CardDescription>{t('Balance over the last 12 months')}</CardDescription>
      </CardHeader>
      <CardContent>
        <BalanceChart points={data.data.points} currency={data.meta.currency === currency ? currency : data.meta.currency} label={t('Balance')} />
      </CardContent>
    </Card>
  )
}
