import { useQuery } from '@tanstack/react-query'
import { Link, createFileRoute, useNavigate } from '@tanstack/react-router'
import { PencilIcon, Trash2Icon } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Attachments } from '@/components/app/attachments'
import { TransactionLinks } from '@/components/app/transaction-links'
import { Money } from '@/components/app/money'
import { useQuickAdd } from '@/components/app/quick-add-context'
import { PageHeader } from '@/components/app/page-header'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { useCanEdit, useLedger, useLocale } from '@/hooks/use-ledger'
import { useDeleteTransaction } from '@/lib/api/mutations'
import { transactionGroupQuery } from '@/lib/api/queries'
import type { AccountRef } from '@/lib/api/types'
import { formatDate } from '@/lib/format'
import { notifyError } from '@/lib/forms'
import { transactionTypeLabels } from '@/lib/labels'
import { accountDisplayName } from '@/lib/transactions'

export const Route = createFileRoute('/_app/$ledgerId/transactions/$groupId/')({
  component: TransactionDetail,
})

function TransactionDetail() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const locale = useLocale()
  const ledger = useLedger()
  const canEdit = useCanEdit()
  const groupId = Route.useParams().groupId
  const ledgerId = ledger.id
  const { data: group } = useQuery(transactionGroupQuery(ledger.id, groupId))
  const remove = useDeleteTransaction(ledger.id)
  const quickAdd = useQuickAdd()

  if (!group) return <Skeleton className="h-64 w-full" />

  const first = group.transactions[0]
  const editable = ['withdrawal', 'deposit', 'transfer'].includes(first.type)
  const total = group.transactions.reduce((sum, transaction) => sum + Number(transaction.amount), 0)

  const accountLink = (account: AccountRef) =>
    ['asset', 'expense', 'revenue', 'liability'].includes(account.type) ? (
      <Link
        to="/$ledgerId/accounts/$accountId"
        params={{ ledgerId, accountId: account.id }}
        className="font-medium hover:underline"
      >
        {account.name}
      </Link>
    ) : (
      <span className="font-medium">{accountDisplayName(account)}</span>
    )

  return (
    <>
      <PageHeader
        title={group.title ?? first.description}
        description={
          <span className="flex flex-wrap items-center gap-2">
            <Badge variant="secondary">{transactionTypeLabels()[first.type]}</Badge>
            {formatDate(first.date, locale, { dateStyle: 'full' })}
            {group.created_by && <span>· {t('Added by {{name}}', { name: group.created_by.name })}</span>}
          </span>
        }
        actions={
          canEdit &&
          editable && (
            <>
              <Button variant="outline" onClick={() => quickAdd({ groupId })}>
                <PencilIcon />
                {t('Edit')}
              </Button>
              <Button
                variant="outline"
                size="icon"
                aria-label={t('Delete')}
                onClick={() =>
                  remove.mutate(groupId, {
                    onSuccess: () => navigate({ to: '/$ledgerId/transactions', params: { ledgerId } }),
                    onError: notifyError,
                  })
                }
              >
                <Trash2Icon />
              </Button>
            </>
          )
        }
      />

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_20rem]">
        <div className="space-y-4">
          {group.transactions.map((transaction) => (
            <Card key={transaction.id}>
              <CardHeader>
                <CardTitle className="flex items-start justify-between gap-4">
                  <span>{transaction.description}</span>
                  <Money
                    amount={transaction.amount}
                    currency={transaction.currency.code}
                    tone={transaction.type === 'withdrawal' ? 'negative' : transaction.type === 'deposit' ? 'positive' : 'none'}
                    className="text-xl"
                  />
                </CardTitle>
              </CardHeader>
              <CardContent>
                <dl className="grid grid-cols-1 gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                  <div>
                    <dt className="text-muted-foreground">{t('From')}</dt>
                    <dd>{accountLink(transaction.source)}</dd>
                  </div>
                  <div>
                    <dt className="text-muted-foreground">{t('To')}</dt>
                    <dd>{accountLink(transaction.destination)}</dd>
                  </div>
                  {transaction.foreign_amount && transaction.foreign_currency && (
                    <div>
                      <dt className="text-muted-foreground">{t('Foreign amount')}</dt>
                      <dd>
                        <Money amount={transaction.foreign_amount} currency={transaction.foreign_currency.code} tone="none" />
                      </dd>
                    </div>
                  )}
                  {transaction.category && (
                    <div>
                      <dt className="text-muted-foreground">{t('Category')}</dt>
                      <dd>
                        <Link
                          to="/$ledgerId/categories/$categoryId"
                          params={{ ledgerId, categoryId: transaction.category.id }}
                          className="hover:underline"
                        >
                          {transaction.category.name}
                        </Link>
                      </dd>
                    </div>
                  )}
                  {transaction.budget && (
                    <div>
                      <dt className="text-muted-foreground">{t('Budget')}</dt>
                      <dd>
                        <Link to="/$ledgerId/budgets/$budgetId" params={{ ledgerId, budgetId: transaction.budget.id }} search={{}} className="hover:underline">
                          {transaction.budget.name}
                        </Link>
                      </dd>
                    </div>
                  )}
                  {transaction.bill && (
                    <div>
                      <dt className="text-muted-foreground">{t('Bill')}</dt>
                      <dd>
                        <Link to="/$ledgerId/bills/$billId" params={{ ledgerId, billId: transaction.bill.id }} className="hover:underline">
                          {transaction.bill.name}
                        </Link>
                      </dd>
                    </div>
                  )}
                  {transaction.tags && transaction.tags.length > 0 && (
                    <div>
                      <dt className="text-muted-foreground">{t('Tags')}</dt>
                      <dd className="flex flex-wrap gap-1">
                        {transaction.tags.map((tag) => (
                          <Badge key={tag.id} variant="outline" asChild>
                            <Link to="/$ledgerId/tags/$tagId" params={{ ledgerId, tagId: tag.id }}>
                              {tag.name}
                            </Link>
                          </Badge>
                        ))}
                      </dd>
                    </div>
                  )}
                  {transaction.notes && (
                    <div className="sm:col-span-2">
                      <dt className="text-muted-foreground">{t('Notes')}</dt>
                      <dd className="whitespace-pre-line">{transaction.notes}</dd>
                    </div>
                  )}
                </dl>
              </CardContent>
            </Card>
          ))}
          {group.transactions.length > 1 && (
            <p className="text-right text-sm">
              {t('Total')}: <Money amount={String(total)} currency={first.currency.code} tone="none" className="font-semibold" />
            </p>
          )}
        </div>
        <div className="space-y-4">
          <Attachments type="transaction_group" id={group.id} />
          <TransactionLinks groupId={group.id} />
        </div>
      </div>
    </>
  )
}
