import { useQuery } from '@tanstack/react-query'
import { Link } from '@tanstack/react-router'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Money } from '@/components/app/money'
import { SelectField } from '@/components/app/select-field'
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Table, TableBody, TableCell, TableFooter, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { useLedger } from '@/hooks/use-ledger'
import { investmentIncomeQuery } from '@/lib/api/queries'
import { todayIso } from '@/lib/format'

const YEARS_SHOWN = 6

/**
 * Income and realised gains per investment for a year, the numbers a tax return asks for.
 */
export function IncomeCard() {
  const { t } = useTranslation()
  const ledger = useLedger()
  const current = Number(todayIso().slice(0, 4))
  const [year, setYear] = useState(String(current))
  const { data } = useQuery(investmentIncomeQuery(ledger.id, `${year}-01-01`, `${year}-12-31`))
  const years = Object.fromEntries(Array.from({ length: YEARS_SHOWN }, (_, index) => [String(current - index), String(current - index)]))

  return (
    <Card className="min-w-0">
      <CardHeader>
        <CardTitle>{t('Income report')}</CardTitle>
        <CardDescription>{t('Dividends, interest, profit and gains from sales in the year.')}</CardDescription>
        <CardAction>
          <SelectField value={year} onChange={setYear} options={years} className="w-28" />
        </CardAction>
      </CardHeader>
      <CardContent className="px-0">
        {!data || data.rows.length === 0 ? (
          <p className="text-muted-foreground px-6 text-sm">{t('No investment income in {{year}}.', { year })}</p>
        ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className="pl-6">{t('Investment')}</TableHead>
                <TableHead className="text-right">{t('Income')}</TableHead>
                <TableHead className="pr-6 text-right">{t('From sales')}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {data.rows.map((row) => (
                <TableRow key={row.id}>
                  <TableCell className="pl-6">
                    <Link to="/$ledgerId/investments/$investmentId" params={{ ledgerId: ledger.id, investmentId: row.id }} className="hover:underline">
                      {row.name}
                    </Link>
                  </TableCell>
                  <TableCell className="text-right">
                    <Money amount={row.income} currency={row.currency} tone="none" />
                  </TableCell>
                  <TableCell className="pr-6 text-right">
                    <Money amount={row.realized} currency={row.currency} />
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
            <TableFooter>
              <TableRow>
                <TableCell className="pl-6 font-medium">{t('Total')}</TableCell>
                <TableCell className="text-right font-medium">
                  <Money amount={data.income} currency={data.currency} tone="none" />
                </TableCell>
                <TableCell className="pr-6 text-right font-medium">
                  <Money amount={data.realized} currency={data.currency} />
                </TableCell>
              </TableRow>
            </TableFooter>
          </Table>
        )}
      </CardContent>
    </Card>
  )
}
