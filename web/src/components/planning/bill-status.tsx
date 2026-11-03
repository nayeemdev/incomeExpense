import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { useLocale } from '@/hooks/use-ledger'
import type { Bill } from '@/lib/api/types'
import { formatDate } from '@/lib/format'

export function BillStatus({ bill, today }: { bill: Bill; today: string }) {
  const { t } = useTranslation()
  const locale = useLocale()
  const expected = bill.expected_dates ?? []
  const paid = (bill.payments?.length ?? 0) >= expected.length && expected.length > 0

  if (!bill.active) return <Badge variant="outline">{t('Inactive')}</Badge>
  if (paid) return <Badge className="bg-emerald-600 text-white">{t('Paid')}</Badge>
  if (expected.length === 0) return <Badge variant="outline">{t('Not due this month')}</Badge>

  const due = expected[bill.payments?.length ?? 0]

  return due < today ? (
    <Badge variant="destructive">{t('Overdue since {{date}}', { date: formatDate(due, locale, { day: 'numeric', month: 'short' }) })}</Badge>
  ) : (
    <Badge variant="secondary">{t('Due {{date}}', { date: formatDate(due, locale, { day: 'numeric', month: 'short' }) })}</Badge>
  )
}
