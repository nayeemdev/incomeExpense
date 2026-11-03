import { useQuery } from '@tanstack/react-query'
import { Link, createFileRoute, useNavigate } from '@tanstack/react-router'
import { DownloadIcon, PrinterIcon, TriangleAlertIcon } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'
import { CategoryIcon } from '@/components/app/category-icon'
import { Money } from '@/components/app/money'
import { PageHeader } from '@/components/app/page-header'
import { ProgressBar } from '@/components/app/progress-bar'
import { RangePicker } from '@/components/app/range-picker'
import { BalanceChart } from '@/components/charts/balance-chart'
import { BreakdownChart } from '@/components/charts/breakdown-chart'
import { CategoryDonut } from '@/components/charts/category-donut'
import { donutSlices } from '@/components/charts/donut-slices'
import { bucketLabel } from '@/components/charts/format'
import { IncomeExpenseChart } from '@/components/charts/income-expense-chart'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group'
import { useLedger, useLocale } from '@/hooks/use-ledger'
import { cn } from '@/lib/utils'
import { downloadUrl } from '@/lib/api/client'
import { breakdownReportQuery, categoriesQuery, overviewReportQuery } from '@/lib/api/queries'
import { rangePresets } from '@/lib/period'
import type { BreakdownRow, ReportMeta } from '@/lib/api/types'

const tabs = ['overview', 'category', 'tag', 'counterparty', 'budget'] as const

export const Route = createFileRoute('/_app/$ledgerId/reports/')({
  validateSearch: z.object({
    tab: z.enum(tabs).optional().catch(undefined),
    start: z.string().optional().catch(undefined),
    end: z.string().optional().catch(undefined),
    type: z.enum(['withdrawal', 'deposit']).optional().catch(undefined),
  }),
  component: Reports,
})

function Incomplete({ meta }: { meta?: ReportMeta }) {
  const { t } = useTranslation()
  const ledger = useLedger()

  if (!meta || meta.complete) return null

  return (
    <Alert className="mb-4">
      <TriangleAlertIcon />
      <AlertDescription>
        <span>
          {t('Some amounts are in currencies without an exchange rate and are left out.')}{' '}
          <Link to="/$ledgerId/settings" params={{ ledgerId: String(ledger.id) }} search={{ tab: 'exchange-rates' }} className="underline">
            {t('Add exchange rates')}
          </Link>
        </span>
      </AlertDescription>
    </Alert>
  )
}

function Reports() {
  const { t } = useTranslation()
  const navigate = useNavigate({ from: Route.fullPath })
  const ledger = useLedger()
  const [today] = useState(() => new Date())
  const search = Route.useSearch()
  const fallback = rangePresets(today).this_year
  const tab = search.tab ?? 'overview'
  const start = search.start ?? fallback.start
  const end = search.end ?? fallback.end
  const labels: Record<(typeof tabs)[number], string> = {
    overview: t('Overview'),
    category: t('Categories'),
    tag: t('Tags'),
    counterparty: t('Payees'),
    budget: t('Budgets'),
  }

  const update = (patch: Partial<z.infer<typeof Route.types.searchSchema>>) =>
    navigate({ search: (previous) => ({ ...previous, ...patch }), replace: true })

  return (
    <>
      <PageHeader
        title={t('Reports')}
        description={t('Where your money comes from and where it goes, over any period. All amounts in {{currency}}.', { currency: ledger.primary_currency.code })}
        actions={
          <div className="flex flex-wrap items-center gap-2 print:hidden">
            <RangePicker start={start} end={end} onChange={(range) => update(range)} />
            <Button variant="outline" size="icon" aria-label={t('Print or save as PDF')} onClick={() => window.print()}>
              <PrinterIcon />
            </Button>
            <Button variant="outline" size="icon" aria-label={t('Download transactions as CSV')} asChild>
              <a href={downloadUrl(`/ledgers/${ledger.id}/export/transactions.csv?start=${start}&end=${end}`)}>
                <DownloadIcon />
              </a>
            </Button>
          </div>
        }
      />
      <Tabs value={tab} onValueChange={(value) => update({ tab: value as (typeof tabs)[number] })} className="mb-4 print:hidden">
        <TabsList className="max-w-full overflow-x-auto">
          {tabs.map((value) => (
            <TabsTrigger key={value} value={value}>
              {labels[value]}
            </TabsTrigger>
          ))}
        </TabsList>
      </Tabs>
      {tab === 'overview' ? (
        <Overview start={start} end={end} />
      ) : (
        <Breakdown groupBy={tab} start={start} end={end} type={search.type ?? 'withdrawal'} onTypeChange={(type) => update({ type })} />
      )}
    </>
  )
}

function Overview({ start, end }: { start: string; end: string }) {
  const { t } = useTranslation()
  const ledger = useLedger()
  const { data } = useQuery(overviewReportQuery(ledger.id, { start, end }))

  if (!data) return <Skeleton className="h-96 w-full" />

  const { data: report, meta } = data
  const currency = meta.currency
  const income = report.income_expense.reduce((sum, row) => sum + Number(row.income), 0)
  const expense = report.income_expense.reduce((sum, row) => sum + Number(row.expense), 0)

  return (
    <div className="space-y-4">
      <Incomplete meta={meta} />
      <div className="grid gap-4 sm:grid-cols-3">
        {[
          [t('Income'), income, 'none'],
          [t('Expenses'), expense, 'none'],
          [t('Saved'), income - expense, 'auto'],
        ].map(([label, amount, tone]) => (
          <Card key={String(label)}>
            <CardHeader>
              <CardDescription>{label}</CardDescription>
            </CardHeader>
            <CardContent>
              <Money amount={String(amount)} currency={currency} tone={tone as 'none' | 'auto'} className="text-2xl font-semibold" />
            </CardContent>
          </Card>
        ))}
      </div>
      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>{t('Income and expenses')}</CardTitle>
          </CardHeader>
          <CardContent>
            <IncomeExpenseChart data={report.income_expense} currency={currency} interval={meta.interval} />
          </CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle>{t('Net worth')}</CardTitle>
          </CardHeader>
          <CardContent>
            <BalanceChart points={report.net_worth} currency={currency} label={t('Net worth')} className="aspect-auto h-64 w-full" />
          </CardContent>
        </Card>
      </div>
      <div className="grid gap-4 lg:grid-cols-2">
        <CategoryList title={t('Where the money went')} rows={report.expense_categories} currency={currency} />
        <CategoryList title={t('Where the money came from')} rows={report.income_categories} currency={currency} />
      </div>
      <Card>
        <CardHeader>
          <CardTitle>{t('Accounts')}</CardTitle>
        </CardHeader>
        <CardContent className="overflow-x-auto">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>{t('Account')}</TableHead>
                <TableHead className="text-right">{t('Start')}</TableHead>
                <TableHead className="text-right">{t('End')}</TableHead>
                <TableHead className="text-right">{t('Change')}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {report.accounts.map((account) => (
                <TableRow key={account.id}>
                  <TableCell className="font-medium">{account.name}</TableCell>
                  <TableCell className="text-right"><Money amount={account.start} currency={account.currency ?? currency} /></TableCell>
                  <TableCell className="text-right"><Money amount={account.end} currency={account.currency ?? currency} /></TableCell>
                  <TableCell className="text-right"><Money amount={account.change} currency={account.currency ?? currency} /></TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
      {report.budgets.length > 0 && (
        <Card>
          <CardHeader>
            <CardTitle>{t('Budgets')}</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            {report.budgets.map((budget) => (
              <div key={budget.id} className="space-y-1">
                <div className="flex justify-between gap-2 text-sm">
                  <span>{budget.name}</span>
                  <span className="text-muted-foreground">
                    <Money amount={budget.spent} currency={currency} tone="none" /> / <Money amount={budget.budgeted} currency={currency} tone="none" />
                  </span>
                </div>
                <ProgressBar value={Number(budget.spent)} max={Number(budget.budgeted)} />
              </div>
            ))}
          </CardContent>
        </Card>
      )}
    </div>
  )
}

function CategoryList({ title, rows, currency }: { title: string; rows: BreakdownRow[]; currency: string }) {
  const { t } = useTranslation()
  const ledger = useLedger()
  const { data: categories } = useQuery(categoriesQuery(ledger.id))
  const styles = new Map((categories?.data ?? []).map((category) => [String(category.id), category]))
  const slices = donutSlices(rows, t('Other'), t('Uncategorised'))
  const [selected, setSelected] = useState<string | null>(null)
  const total = slices.reduce((sum, slice) => sum + slice.value, 0)

  return (
    <Card>
      <CardHeader>
        <CardTitle>{title}</CardTitle>
      </CardHeader>
      <CardContent className="space-y-5">
        {slices.length === 0 ? (
          <p className="text-muted-foreground text-sm">{t('Nothing in this period.')}</p>
        ) : (
          <>
            <CategoryDonut slices={slices} currency={currency} selected={selected} onSelect={setSelected} />
            <ul className="space-y-1">
              {slices.map((slice) => (
                <li key={slice.key}>
                  <button
                    type="button"
                    onClick={() => setSelected(selected === slice.key ? null : slice.key)}
                    aria-pressed={selected === slice.key}
                    className={cn(
                      'hover:bg-muted/50 w-full space-y-1 rounded-lg px-2 py-1.5 text-left transition-opacity',
                      selected && selected !== slice.key && 'opacity-50',
                    )}
                  >
                  <div className="flex items-center gap-2 text-sm">
                    <span className="size-2.5 shrink-0 rounded-full" style={{ background: slice.color }} aria-hidden />
                    {slice.key !== 'other' && <CategoryIcon icon={styles.get(slice.key)?.icon} color={styles.get(slice.key)?.color} className="size-7" />}
                    <span className="flex-1 truncate">{slice.name}</span>
                    <span className="text-muted-foreground tabular-nums">{Math.round((slice.value / total) * 100)}%</span>
                    <Money amount={String(slice.value)} currency={currency} tone="none" className="w-24 text-right" />
                  </div>
                  <div className="bg-muted h-1.5 overflow-hidden rounded-full">
                    <div className="h-full rounded-full" style={{ width: `${(slice.value / total) * 100}%`, background: slice.color }} />
                  </div>
                  </button>
                </li>
              ))}
            </ul>
          </>
        )}
      </CardContent>
    </Card>
  )
}

function Breakdown({ groupBy, start, end, type, onTypeChange }: { groupBy: string; start: string; end: string; type: 'withdrawal' | 'deposit'; onTypeChange: (type: 'withdrawal' | 'deposit') => void }) {
  const { t } = useTranslation()
  const locale = useLocale()
  const ledger = useLedger()
  const { data } = useQuery(breakdownReportQuery(ledger.id, groupBy, type, { start, end }))

  return (
    <div className="space-y-4">
      {groupBy !== 'budget' && (
        <ToggleGroup type="single" variant="outline" value={type} onValueChange={(value) => value && onTypeChange(value as typeof type)} className="print:hidden">
          <ToggleGroupItem value="withdrawal" className="px-4">{t('Expenses')}</ToggleGroupItem>
          <ToggleGroupItem value="deposit" className="px-4">{t('Income')}</ToggleGroupItem>
        </ToggleGroup>
      )}
      {!data ? (
        <Skeleton className="h-96 w-full" />
      ) : (
        <>
          <Incomplete meta={data.meta} />
          {data.data.rows.length === 0 ? (
            <p className="text-muted-foreground surface-card py-10 text-center text-sm">{t('Nothing in this period.')}</p>
          ) : (
            <>
              <Card>
                <CardContent>
                  <BreakdownChart rows={data.data.rows} currency={data.meta.currency} interval={data.meta.interval} />
                </CardContent>
              </Card>
              <Card>
                <CardContent className="overflow-x-auto">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>{t('Name')}</TableHead>
                        {data.data.buckets.map((bucket) => (
                          <TableHead key={bucket.start} className="text-right whitespace-nowrap">
                            {bucketLabel(bucket.start, data.meta.interval, locale)}
                          </TableHead>
                        ))}
                        <TableHead className="text-right">{t('Total')}</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {data.data.rows.map((row) => (
                        <TableRow key={row.id ?? 'none'}>
                          <TableCell className="font-medium whitespace-nowrap">{row.name ?? t('None')}</TableCell>
                          {data.data.buckets.map((bucket) => (
                            <TableCell key={bucket.start} className="text-right">
                              <Money amount={row.buckets[bucket.start]} currency={data.meta.currency} tone="none" />
                            </TableCell>
                          ))}
                          <TableCell className="text-right font-medium">
                            <Money amount={row.total} currency={data.meta.currency} tone="none" />
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </CardContent>
              </Card>
            </>
          )}
        </>
      )}
    </div>
  )
}
