import { useLocale } from '@/hooks/use-ledger'
import { formatMoney, isNegative } from '@/lib/format'
import { cn } from '@/lib/utils'

type MoneyProps = {
  amount: string | null | undefined
  currency: string
  tone?: 'auto' | 'none' | 'negative' | 'positive'
  className?: string
}

export function Money({ amount, currency, tone = 'auto', className }: MoneyProps) {
  const locale = useLocale()
  const negative = tone === 'negative' || (tone === 'auto' && isNegative(amount))

  return (
    <span
      className={cn(
        'tabular-nums',
        negative && 'text-destructive',
        tone === 'positive' && 'text-emerald-600 dark:text-emerald-400',
        className,
      )}
    >
      {formatMoney(amount, currency, locale)}
    </span>
  )
}
