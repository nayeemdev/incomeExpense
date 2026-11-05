import { useQuery } from '@tanstack/react-query'
import { Link } from '@tanstack/react-router'
import { ArrowRightIcon } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Money } from '@/components/app/money'
import { Button } from '@/components/ui/button'
import { Card, CardAction, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { useLedger, useLocale } from '@/hooks/use-ledger'
import { investmentsQuery } from '@/lib/api/queries'
import { formatDate } from '@/lib/format'
import { percent } from '@/lib/investments'
import { cn } from '@/lib/utils'

/**
 * The dashboard's view of the portfolio: what it is worth, how it is doing and the next payout to expect.
 */
export function PortfolioCard() {
  const { t } = useTranslation()
  const locale = useLocale()
  const ledger = useLedger()
  const { data } = useQuery(investmentsQuery(ledger.id))
  const open = data?.data.filter((investment) => !investment.closed_at) ?? []

  if (!data || open.length === 0) return null

  const summary = data.summary
  const next = open
    .flatMap((investment) => investment.upcoming_payouts.map((payout) => ({ ...payout, name: investment.name, currency: investment.currency.code })))
    .sort((a, b) => a.date.localeCompare(b.date))[0]

  return (
    <Card>
      <CardHeader>
        <CardTitle>{t('Investments')}</CardTitle>
        <CardAction>
          <Button variant="ghost" size="sm" asChild>
            <Link to="/$ledgerId/investments" params={{ ledgerId: ledger.id }}>
              {t('View all')}
              <ArrowRightIcon />
            </Link>
          </Button>
        </CardAction>
      </CardHeader>
      <CardContent className="space-y-3 text-sm">
        <div className="flex items-baseline justify-between gap-2">
          <Money amount={summary.value} currency={summary.currency} tone="none" className="text-xl font-semibold" />
          <span className={cn('font-medium', summary.gain.startsWith('-') ? 'text-destructive' : 'text-emerald-600 dark:text-emerald-400')}>
            {percent(summary.gain_percent, locale)}
          </span>
        </div>
        <div className="text-muted-foreground flex justify-between gap-2">
          <span>{t('Invested')}</span>
          <Money amount={summary.cost} currency={summary.currency} tone="none" />
        </div>
        {next && (
          <div className="text-muted-foreground flex justify-between gap-2">
            <span className="min-w-0 truncate">{t('Next payout {{date}}', { date: formatDate(next.date, locale) })}</span>
            {next.amount && <Money amount={next.amount} currency={next.currency} tone="none" />}
          </div>
        )}
      </CardContent>
    </Card>
  )
}
