import { useMutation, useQuery } from '@tanstack/react-query'
import { RefreshCwIcon, Trash2Icon } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { CurrencySelect } from '@/components/app/currency-select'
import { DatePicker } from '@/components/app/date-picker'
import { MoneyInput } from '@/components/app/money-input'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Field, FieldLabel } from '@/components/ui/field'
import { useCanEdit, useLedger, useLocale } from '@/hooks/use-ledger'
import { api } from '@/lib/api/client'
import { useInvalidateLedger } from '@/lib/api/mutations'
import { exchangeRatesQuery } from '@/lib/api/queries'
import { formatDate, todayIso } from '@/lib/format'
import { notifyError } from '@/lib/forms'

export function ExchangeRateSettings() {
  const { t } = useTranslation()
  const locale = useLocale()
  const ledger = useLedger()
  const canEdit = useCanEdit()
  const invalidate = useInvalidateLedger(ledger.id)
  const { data: rates = [] } = useQuery(exchangeRatesQuery(ledger.id))
  const [draft, setDraft] = useState<{ from: number | null; to: number | null; date: string; rate: string }>({
    from: null,
    to: ledger.primary_currency.id,
    date: todayIso(),
    rate: '',
  })

  const fetchNow = useMutation({
    mutationFn: () => api.post<{ data: { fetched: number } }>(`/ledgers/${ledger.id}/exchange-rates/fetch`),
    onSuccess: async (response) => {
      await invalidate()
      toast.success(t('{{count}} rates updated', { count: response.data.fetched }))
    },
    onError: notifyError,
  })

  const add = useMutation({
    mutationFn: () =>
      api.post(`/ledgers/${ledger.id}/exchange-rates`, { from_currency_id: draft.from, to_currency_id: draft.to, date: draft.date, rate: draft.rate }),
    onSuccess: async () => {
      setDraft({ ...draft, rate: '' })
      await invalidate()
    },
    onError: notifyError,
  })

  const remove = useMutation({
    mutationFn: (id: number) => api.delete(`/ledgers/${ledger.id}/exchange-rates/${id}`),
    onSuccess: () => invalidate(),
    onError: notifyError,
  })

  return (
    <div className="space-y-6">
      <Card>
        <CardHeader>
          <CardTitle>{t('Exchange rates')}</CardTitle>
          <CardDescription>
            {t('Rates for {{currency}} are fetched every day. Rates you add here win over fetched ones for this ledger.', {
              currency: ledger.primary_currency.code,
            })}
          </CardDescription>
          {canEdit && (
            <CardAction>
              <Button variant="outline" size="sm" disabled={fetchNow.isPending} onClick={() => fetchNow.mutate()}>
                <RefreshCwIcon />
                {t('Fetch now')}
              </Button>
            </CardAction>
          )}
        </CardHeader>
        <CardContent className="space-y-6">
          {canEdit && (
            <form
              className="grid gap-3 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_10rem_8rem_auto] lg:items-end"
              onSubmit={(event) => {
                event.preventDefault()
                add.mutate()
              }}
            >
              <Field>
                <FieldLabel>{t('1 unit of')}</FieldLabel>
                <CurrencySelect value={draft.from} onChange={(from) => setDraft({ ...draft, from })} />
              </Field>
              <Field>
                <FieldLabel>{t('equals, in')}</FieldLabel>
                <CurrencySelect value={draft.to} onChange={(to) => setDraft({ ...draft, to })} />
              </Field>
              <Field>
                <FieldLabel>{t('On')}</FieldLabel>
                <DatePicker value={draft.date} onChange={(date) => setDraft({ ...draft, date: date ?? todayIso() })} />
              </Field>
              <Field>
                <FieldLabel>{t('Rate')}</FieldLabel>
                <MoneyInput value={draft.rate} onChange={(rate) => setDraft({ ...draft, rate })} />
              </Field>
              <Button type="submit" disabled={!draft.from || !draft.to || !draft.rate || add.isPending}>
                {t('Add')}
              </Button>
            </form>
          )}
          {rates.length === 0 ? (
            <p className="text-muted-foreground text-sm">{t('No rates yet. Fetch them now or add one by hand.')}</p>
          ) : (
            <ul className="max-h-96 divide-y overflow-y-auto">
              {rates.map((rate) => (
                <li key={rate.id} className="flex items-center gap-3 py-2 text-sm">
                  <span className="font-medium tabular-nums">
                    1 {rate.from} = {rate.rate} {rate.to}
                  </span>
                  {rate.manual && <Badge variant="secondary">{t('Manual')}</Badge>}
                  <span className="text-muted-foreground ml-auto text-xs">{formatDate(rate.date, locale)}</span>
                  {canEdit && rate.manual && (
                    <Button variant="ghost" size="icon" className="size-7" aria-label={t('Delete')} onClick={() => remove.mutate(rate.id)}>
                      <Trash2Icon />
                    </Button>
                  )}
                </li>
              ))}
            </ul>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
