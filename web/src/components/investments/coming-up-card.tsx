import { useQuery } from '@tanstack/react-query'
import { Link } from '@tanstack/react-router'
import { useTranslation } from 'react-i18next'
import { Money } from '@/components/app/money'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { useLedger, useLocale } from '@/hooks/use-ledger'
import { investmentCalendarQuery } from '@/lib/api/queries'
import type { InvestmentCalendarItem } from '@/lib/api/types'
import { formatDate } from '@/lib/format'

/**
 * Payouts, installments and maturities over the next year, grouped by month.
 */
export function ComingUpCard() {
  const { t } = useTranslation()
  const locale = useLocale()
  const ledger = useLedger()
  const { data: items = [] } = useQuery(investmentCalendarQuery(ledger.id))
  const types: Record<InvestmentCalendarItem['type'], string> = { payout: t('Payout'), installment: t('Installment'), maturity: t('Matures') }
  const months = Object.entries(
    items.reduce<Record<string, InvestmentCalendarItem[]>>((groups, item) => {
      const month = item.date.slice(0, 7)
      ;(groups[month] ??= []).push(item)
      return groups
    }, {}),
  )

  return (
    <Card className="min-w-0">
      <CardHeader>
        <CardTitle>{t('Coming up')}</CardTitle>
        <CardDescription>{t('Payouts, installments and maturities in the next 12 months.')}</CardDescription>
      </CardHeader>
      <CardContent>
        {months.length === 0 ? (
          <p className="text-muted-foreground text-sm">{t('Nothing scheduled.')}</p>
        ) : (
          <div className="max-h-96 space-y-4 overflow-y-auto pr-1">
            {months.map(([month, entries]) => (
              <section key={month}>
                <h4 className="text-muted-foreground mb-1 text-xs font-medium tracking-wide uppercase">
                  {formatDate(`${month}-01`, locale, { month: 'long', year: 'numeric' })}
                </h4>
                <ul className="divide-y">
                  {entries.map((item) => (
                    <li key={`${item.investment_id}-${item.type}-${item.date}`} className="flex items-center gap-3 py-2 text-sm">
                      <span className="text-muted-foreground w-12 shrink-0 tabular-nums">{formatDate(item.date, locale, { day: 'numeric', month: 'short' })}</span>
                      <div className="min-w-0 flex-1">
                        <Link
                          to="/$ledgerId/investments/$investmentId"
                          params={{ ledgerId: ledger.id, investmentId: item.investment_id }}
                          className="block truncate font-medium hover:underline"
                        >
                          {item.name}
                        </Link>
                        <span className="text-muted-foreground text-xs">{types[item.type]}</span>
                        {item.overdue && (
                          <Badge variant="destructive" className="ml-1.5">
                            {t('Not recorded yet')}
                          </Badge>
                        )}
                      </div>
                      {item.amount && (
                        <Money amount={item.amount} currency={item.currency} tone={item.type === 'installment' ? 'none' : 'positive'} className="shrink-0" />
                      )}
                    </li>
                  ))}
                </ul>
              </section>
            ))}
          </div>
        )}
      </CardContent>
    </Card>
  )
}
