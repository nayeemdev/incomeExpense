export function compactMoney(value: number, currency: string, locale?: string) {
  return new Intl.NumberFormat(locale, { style: 'currency', currency, notation: 'compact', maximumFractionDigits: 1 }).format(value)
}

export function bucketLabel(start: string, interval: string, locale?: string) {
  const date = new Date(`${start}T00:00:00`)
  const options: Intl.DateTimeFormatOptions =
    interval === 'year' ? { year: 'numeric' } : interval === 'month' ? { month: 'short', year: '2-digit' } : { day: 'numeric', month: 'short' }

  return new Intl.DateTimeFormat(locale, options).format(date)
}
