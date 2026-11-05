import { useQuery } from '@tanstack/react-query'
import { Link, createFileRoute } from '@tanstack/react-router'
import { BanknoteArrowUpIcon, CalculatorIcon, CoinsIcon, PercentIcon, PlusIcon, TrendingUpIcon, WalletIcon } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { FormDialog } from '@/components/app/form-dialog'
import { Money } from '@/components/app/money'
import { PageHeader } from '@/components/app/page-header'
import { StatCard } from '@/components/app/stat-card'
import { AllocationChart } from '@/components/investments/allocation-chart'
import { ComingUpCard } from '@/components/investments/coming-up-card'
import { IncomeCard } from '@/components/investments/income-card'
import { TargetsDialog } from '@/components/investments/targets-dialog'
import { InvestmentForm } from '@/components/investments/investment-form'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardAction, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Skeleton } from '@/components/ui/skeleton'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { useCanEdit, useLedger, useLocale } from '@/hooks/use-ledger'
import type { Investment } from '@/lib/api/types'
import { investmentsQuery } from '@/lib/api/queries'
import { formatDate } from '@/lib/format'
import { percent, units } from '@/lib/investments'
import { investmentKindLabels } from '@/lib/labels'
import { cn } from '@/lib/utils'

export const Route = createFileRoute('/_app/$ledgerId/investments/')({
  component: Investments,
})

function Investments() {
  const { t } = useTranslation()
  const locale = useLocale()
  const ledger = useLedger()
  const canEdit = useCanEdit()
  const [creating, setCreating] = useState(false)
  const [editingTargets, setEditingTargets] = useState(false)
  const { data } = useQuery(investmentsQuery(ledger.id))
  const open = data?.data.filter((investment) => !investment.closed_at) ?? []
  const closed = data?.data.filter((investment) => investment.closed_at) ?? []
  const summary = data?.summary
  const nextPayout = open
    .flatMap((investment) => investment.upcoming_payouts.map((payout) => ({ ...payout, investment })))
    .sort((a, b) => a.date.localeCompare(b.date))[0]

  return (
    <>
      <PageHeader
        title={t('Investments')}
        description={t('Shares, funds, deposits, gold, property and private investments, valued in one place.')}
        actions={
          <>
            <Button variant="outline" asChild>
              <Link to="/$ledgerId/investments/calculator" params={{ ledgerId: ledger.id }}>
                <CalculatorIcon />
                {t('What if')}
              </Link>
            </Button>
            {canEdit && (
              <Button onClick={() => setCreating(true)}>
                <PlusIcon />
                {t('New investment')}
              </Button>
            )}
          </>
        }
      />
      {!data && <Skeleton className="h-64 w-full" />}
      {data?.data.length === 0 && (
        <Empty className="surface-card">
          <EmptyHeader>
            <EmptyTitle>{t('No investments yet')}</EmptyTitle>
            <EmptyDescription>
              {t('Track what you own and what it earns: shares, a fixed deposit, gold, or money put into a business that pays you every quarter.')}
            </EmptyDescription>
          </EmptyHeader>
          {canEdit && (
            <EmptyContent>
              <Button onClick={() => setCreating(true)}>{t('New investment')}</Button>
            </EmptyContent>
          )}
        </Empty>
      )}
      {summary && data.data.length > 0 && (
        <>
          <div className="stat-grid mb-6">
            <StatCard
              label={t('Current value')}
              icon={WalletIcon}
              tone="brand"
              value={<Money amount={summary.value} currency={summary.currency} tone="none" />}
              hint={!summary.complete ? t('Some currencies have no exchange rate and are left out.') : undefined}
            />
            <StatCard
              label={t('Invested')}
              icon={CoinsIcon}
              value={<Money amount={summary.cost} currency={summary.currency} tone="none" />}
            />
            <StatCard
              label={t('Unrealised gain')}
              icon={TrendingUpIcon}
              tone={summary.gain.startsWith('-') ? 'danger' : 'success'}
              value={<Money amount={summary.gain} currency={summary.currency} />}
              hint={percent(summary.gain_percent, locale)}
            />
            <StatCard
              label={t('Yearly return')}
              icon={PercentIcon}
              value={summary.annual_return === null ? t('Too early') : percent(String(summary.annual_return), locale)}
              hint={t('Across all investments, counting income and sales.')}
            />
            <StatCard
              label={t('Income received')}
              icon={BanknoteArrowUpIcon}
              value={<Money amount={summary.income} currency={summary.currency} tone="none" />}
              hint={
                nextPayout
                  ? t('Next payout {{date}} from {{name}}', { date: formatDate(nextPayout.date, locale), name: nextPayout.investment.name })
                  : undefined
              }
            />
          </div>
          <div className="mb-6 grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_20rem]">
            <HoldingsCard title={t('Holdings')} investments={open} />
            <Card>
              <CardHeader>
                <CardTitle>{t('Allocation')}</CardTitle>
                {canEdit && (
                  <CardAction>
                    <Button variant="ghost" size="sm" onClick={() => setEditingTargets(true)}>
                      {summary.targets.length > 0 ? t('Edit targets') : t('Set targets')}
                    </Button>
                  </CardAction>
                )}
              </CardHeader>
              <CardContent>
                {summary.allocation.length > 0 || summary.targets.length > 0 ? (
                  <AllocationChart allocation={summary.allocation} targets={summary.targets} currency={summary.currency} />
                ) : (
                  <p className="text-muted-foreground text-sm">{t('Nothing held right now.')}</p>
                )}
              </CardContent>
            </Card>
          </div>
          <div className="mb-6 grid grid-cols-1 gap-4 xl:grid-cols-2">
            <ComingUpCard />
            <IncomeCard />
          </div>
          {closed.length > 0 && <HoldingsCard title={t('Closed')} investments={closed} />}
          <TargetsDialog targets={summary.targets} open={editingTargets} onClose={() => setEditingTargets(false)} />
        </>
      )}
      <FormDialog open={creating} onOpenChange={setCreating} title={t('New investment')} size="lg">
        <InvestmentForm onCancel={() => setCreating(false)} onSaved={() => setCreating(false)} />
      </FormDialog>
    </>
  )
}

function HoldingsCard({ title, investments }: { title: string; investments: Investment[] }) {
  const { t } = useTranslation()
  const locale = useLocale()
  const ledger = useLedger()
  const kinds = investmentKindLabels()

  return (
    <Card className="min-w-0">
      <CardHeader>
        <CardTitle>{title}</CardTitle>
      </CardHeader>
      <CardContent className="px-0">
        {investments.length === 0 ? (
          <p className="text-muted-foreground px-6 text-sm">{t('Nothing held right now.')}</p>
        ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className="pl-6">{t('Name')}</TableHead>
                <TableHead className="hidden text-right sm:table-cell">{t('Invested')}</TableHead>
                <TableHead className="text-right">{t('Value')}</TableHead>
                <TableHead className="pr-6 text-right">{t('Return')}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {investments.map((investment) => {
                const currency = investment.currency.code
                const returned = Number(investment.gain) + Number(investment.income) + Number(investment.realized)

                return (
                  <TableRow key={investment.id} className="relative">
                    <TableCell className="pl-6">
                      <Link
                        to="/$ledgerId/investments/$investmentId"
                        params={{ ledgerId: ledger.id, investmentId: investment.id }}
                        className="font-medium after:absolute after:inset-0"
                      >
                        {investment.name}
                      </Link>
                      <div className="text-muted-foreground mt-0.5 flex flex-wrap items-center gap-1.5 text-xs">
                        <Badge variant="secondary">{kinds[investment.kind]}</Badge>
                        {investment.tracks_units && Number(investment.units) > 0 && <span>{t('{{units}} units', { units: units(investment.units, locale) })}</span>}
                        {investment.upcoming_payouts.some((payout) => payout.overdue) && <Badge variant="destructive">{t('Payout overdue')}</Badge>}
                      </div>
                    </TableCell>
                    <TableCell className="hidden text-right sm:table-cell">
                      <Money amount={investment.closed_at ? investment.invested : investment.cost} currency={currency} tone="none" />
                    </TableCell>
                    <TableCell className="text-right">
                      <Money amount={investment.value} currency={currency} tone="none" className="font-medium" />
                    </TableCell>
                    <TableCell className="pr-6 text-right">
                      <Money amount={String(returned)} currency={currency} tone={returned < 0 ? 'negative' : returned > 0 ? 'positive' : 'none'} />
                      {investment.gain_percent && (
                        <div className={cn('text-xs', Number(investment.gain_percent) < 0 ? 'text-destructive' : 'text-muted-foreground')}>
                          {percent(investment.gain_percent, locale)}
                        </div>
                      )}
                    </TableCell>
                  </TableRow>
                )
              })}
            </TableBody>
          </Table>
        )}
      </CardContent>
    </Card>
  )
}
