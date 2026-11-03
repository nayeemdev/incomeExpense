import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { currenciesQuery } from '@/lib/api/queries'
import { Combobox } from './combobox'

type CurrencySelectProps = {
  id?: string
  value: string | null | undefined
  onChange: (value: string | null) => void
  'aria-invalid'?: boolean
}

export function CurrencySelect({ id, value, onChange, ...props }: CurrencySelectProps) {
  const { t } = useTranslation()
  const { data: currencies = [] } = useQuery(currenciesQuery)

  return (
    <Combobox
      id={id}
      aria-invalid={props['aria-invalid']}
      options={currencies.map((currency) => ({
        value: currency.id,
        label: `${currency.code} · ${currency.name}`,
        keywords: [currency.code, currency.name],
      }))}
      value={value ?? null}
      onChange={(next) => onChange(next ?? null)}
      placeholder={t('Choose a currency')}
      searchPlaceholder={t('Search currencies…')}
    />
  )
}
