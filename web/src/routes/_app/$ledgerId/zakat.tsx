import { useQuery } from '@tanstack/react-query'
import { createFileRoute } from '@tanstack/react-router'
import { CircleCheckIcon, InfoIcon } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Money } from '@/components/app/money'
import { MoneyInput } from '@/components/app/money-input'
import { PageHeader } from '@/components/app/page-header'
import { StatCard } from '@/components/app/stat-card'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Field, FieldDescription, FieldLabel } from '@/components/ui/field'
import { Skeleton } from '@/components/ui/skeleton'
import { useLedger } from '@/hooks/use-ledger'
import { zakatQuery } from '@/lib/api/queries'
import type { Zakat } from '@/lib/api/types'
import { storage } from '@/lib/storage'

export const Route = createFileRoute('/_app/$ledgerId/zakat')({
  component: ZakatPage,
})

const GOLD_PRICE_KEY = 'zakat.gold-price'

function ZakatPage() {
  const { t } = useTranslation()
  const ledger = useLedger()
  const currency = ledger.primary_currency.code
  const [goldPrice, setGoldPrice] = useState(() => storage.get(GOLD_PRICE_KEY) ?? '')
  const { data, isFetching } = useQuery(zakatQuery(ledger.id, goldPrice))
  const groups: Record<Zakat['items'][number]['group'], string> = {
    cash: t('Cash and bank'),
    investments: t('Investments'),
    gold: t('Gold'),
    receivables: t('Money others owe you'),
    debts: t('Debts you owe'),
  }

  return (
    <>
      <PageHeader title={t('Zakat')} description={t('An estimate from your balances and investments today. The home you live in is left out.')} />
      <div className="grid grid-cols-1 gap-4 xl:grid-cols-[20rem_minmax(0,1fr)]">
        <Card className="h-fit">
          <CardContent>
            <Field>
              <FieldLabel htmlFor="gold-price">{t('Gold price per gram')}</FieldLabel>
              <MoneyInput
                id="gold-price"
                autoFocus
                currency={currency}
                value={goldPrice}
                onChange={(value) => {
                  setGoldPrice(value)
                  storage.set(GOLD_PRICE_KEY, value)
                }}
              />
              <FieldDescription>{t('Today\'s 24 carat price. The nisab is the value of {{grams}} grams.', { grams: data?.nisab_gold_grams ?? 87.48 })}</FieldDescription>
            </Field>
          </CardContent>
        </Card>
        <div className="min-w-0 space-y-4">
          {!goldPrice && (
            <Alert>
              <InfoIcon />
              <AlertTitle>{t('Enter the gold price to begin')}</AlertTitle>
              <AlertDescription>{t('It sets the nisab, the minimum wealth on which zakat is due.')}</AlertDescription>
            </Alert>
          )}
          {goldPrice && !data && isFetching && <Skeleton className="h-48 w-full" />}
          {data && (
            <>
              <div className="stat-grid">
                <StatCard label={t('Zakatable wealth')} value={<Money amount={data.total} currency={data.currency} tone="none" />} />
                <StatCard label={t('Nisab')} value={<Money amount={data.nisab} currency={data.currency} tone="none" />} />
                <StatCard
                  label={t('Zakat due')}
                  tone={data.due ? 'brand' : 'default'}
                  icon={CircleCheckIcon}
                  value={<Money amount={data.zakat} currency={data.currency} tone="none" />}
                  hint={data.due ? t('{{rate}}% of your zakatable wealth.', { rate: data.rate }) : t('Your wealth is below the nisab.')}
                />
              </div>
              {!data.complete && <p className="text-muted-foreground text-sm">{t('Some currencies have no exchange rate and are left out.')}</p>}
              <Card>
                <CardHeader>
                  <CardTitle>{t('What is counted')}</CardTitle>
                </CardHeader>
                <CardContent className="space-y-5">
                  {(Object.keys(groups) as Array<keyof typeof groups>).map((group) => {
                    const items = data.items.filter((item) => item.group === group)
                    if (items.length === 0) return null

                    return (
                      <section key={group}>
                        <h4 className="text-muted-foreground mb-1 text-xs font-medium tracking-wide uppercase">{groups[group]}</h4>
                        <ul className="divide-y text-sm">
                          {items.map((item) => (
                            <li key={item.name} className="flex justify-between gap-3 py-2">
                              <span className="min-w-0 truncate">{item.name}</span>
                              <Money amount={item.amount} currency={data.currency} />
                            </li>
                          ))}
                        </ul>
                      </section>
                    )
                  })}
                </CardContent>
              </Card>
            </>
          )}
        </div>
      </div>
    </>
  )
}
