import { Link, useNavigate } from '@tanstack/react-router'
import { ArrowDownLeftIcon, ArrowLeftRightIcon, ArrowUpRightIcon, MoreHorizontalIcon, ScaleIcon } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Skeleton } from '@/components/ui/skeleton'
import { useCanEdit, useLedger, useLocale } from '@/hooks/use-ledger'
import { useDeleteTransaction } from '@/lib/api/mutations'
import type { Transaction } from '@/lib/api/types'
import { formatDate } from '@/lib/format'
import { notifyError } from '@/lib/forms'
import { accountDisplayName } from '@/lib/transactions'
import { cn } from '@/lib/utils'
import { Money } from './money'

type TransactionListProps = {
  transactions: Transaction[] | undefined
  accountId?: number
  showRunningBalance?: boolean
  empty?: React.ReactNode
  selected?: Set<number>
  onToggle?: (id: number) => void
}

const icons = {
  withdrawal: ArrowUpRightIcon,
  deposit: ArrowDownLeftIcon,
  transfer: ArrowLeftRightIcon,
  opening_balance: ScaleIcon,
  reconciliation: ScaleIcon,
}

function signedAmount(transaction: Transaction, accountId?: number) {
  if (accountId) return transaction.source.id === accountId ? `-${transaction.amount}` : transaction.amount
  if (transaction.type === 'withdrawal') return `-${transaction.amount}`

  return transaction.amount
}

export function TransactionList({ transactions, accountId, showRunningBalance, empty, selected, onToggle }: TransactionListProps) {
  const locale = useLocale()

  if (!transactions) {
    return (
      <div className="space-y-2">
        {Array.from({ length: 6 }, (_, index) => (
          <Skeleton key={index} className="h-14 w-full" />
        ))}
      </div>
    )
  }

  if (transactions.length === 0) return <>{empty}</>

  const days = new Map<string, Transaction[]>()
  transactions.forEach((transaction) => days.set(transaction.date, [...(days.get(transaction.date) ?? []), transaction]))

  return (
    <div className="space-y-6">
      {[...days.entries()].map(([date, items]) => (
        <section key={date}>
          <h3 className="text-muted-foreground mb-1 px-2 text-xs font-medium tracking-wide uppercase">
            {formatDate(date, locale, { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' })}
          </h3>
          <ul className="divide-y rounded-lg border">
            {items.map((transaction) => (
              <TransactionRow
                key={transaction.id}
                transaction={transaction}
                accountId={accountId}
                showRunningBalance={showRunningBalance}
                selected={selected?.has(transaction.id)}
                onToggle={onToggle}
              />
            ))}
          </ul>
        </section>
      ))}
    </div>
  )
}

function TransactionRow({
  transaction,
  accountId,
  showRunningBalance,
  selected,
  onToggle,
}: {
  transaction: Transaction
  accountId?: number
  showRunningBalance?: boolean
  selected?: boolean
  onToggle?: (id: number) => void
}) {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const ledger = useLedger()
  const canEdit = useCanEdit()
  const remove = useDeleteTransaction(ledger.id)
  const Icon = icons[transaction.type]
  const ledgerId = String(ledger.id)
  const groupId = String(transaction.group_id)
  const editable = ['withdrawal', 'deposit', 'transfer'].includes(transaction.type)
  const amount = signedAmount(transaction, accountId)

  return (
    <li className={cn('hover:bg-muted/50 group relative flex items-center gap-3 px-3 py-2.5', selected && 'bg-muted/60')}>
      {onToggle && editable && (
        <Checkbox
          className="relative z-10"
          aria-label={t('Select {{description}}', { description: transaction.description })}
          checked={selected}
          onCheckedChange={() => onToggle(transaction.id)}
        />
      )}
      <span
        className={cn(
          'grid size-8 shrink-0 place-items-center rounded-full',
          transaction.type === 'withdrawal' && 'bg-destructive/10 text-destructive',
          transaction.type === 'deposit' && 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
          !['withdrawal', 'deposit'].includes(transaction.type) && 'bg-muted text-muted-foreground',
        )}
      >
        <Icon className="size-4" />
      </span>
      <div className="min-w-0 flex-1">
        <Link
          to="/$ledgerId/transactions/$groupId"
          params={{ ledgerId, groupId }}
          className="block truncate font-medium after:absolute after:inset-0"
        >
          {transaction.description}
        </Link>
        <p className="text-muted-foreground flex min-w-0 items-center gap-2 text-xs">
          <span className="truncate">
            {accountDisplayName(transaction.source)} → {accountDisplayName(transaction.destination)}
          </span>
          {transaction.category && (
            <Badge variant="secondary" className="hidden sm:inline-flex">
              {transaction.category.name}
            </Badge>
          )}
          {transaction.group_title && <span className="hidden truncate md:inline">· {transaction.group_title}</span>}
        </p>
      </div>
      <div className="text-right">
        <Money
          amount={amount}
          currency={transaction.currency.code}
          tone={transaction.type === 'transfer' && !accountId ? 'none' : amount.startsWith('-') ? 'negative' : 'positive'}
          className="font-medium"
        />
        {showRunningBalance && transaction.running_balance !== undefined && (
          <p className="text-muted-foreground text-xs">
            <Money amount={transaction.running_balance} currency={transaction.currency.code} tone="none" />
          </p>
        )}
      </div>
      {canEdit && editable && (
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="ghost" size="icon" className="relative z-10 size-8" aria-label={t('Actions')}>
              <MoreHorizontalIcon />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            <DropdownMenuItem
              onSelect={() => navigate({ to: '/$ledgerId/transactions/$groupId/edit', params: { ledgerId, groupId } })}
            >
              {t('Edit')}
            </DropdownMenuItem>
            <DropdownMenuItem
              variant="destructive"
              onSelect={() => remove.mutate(transaction.group_id, { onError: notifyError })}
            >
              {t('Delete')}
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      )}
    </li>
  )
}
