import { format, parseISO } from 'date-fns'

const currencyFormatters = new Map<string, Intl.NumberFormat>()

export function formatMoney(amount: string | number | null | undefined, currency: string, locale?: string) {
  if (amount === null || amount === undefined || amount === '') return ''

  const key = `${locale ?? ''}|${currency}`
  let formatter = currencyFormatters.get(key)

  if (!formatter) {
    formatter = new Intl.NumberFormat(locale, { style: 'currency', currency })
    currencyFormatters.set(key, formatter)
  }

  return formatter.format(Number(amount))
}

export function formatDate(date: string, locale?: string, options: Intl.DateTimeFormatOptions = { dateStyle: 'medium' }) {
  return new Intl.DateTimeFormat(locale, options).format(parseISO(date))
}

export function toIsoDate(date: Date) {
  return format(date, 'yyyy-MM-dd')
}

export function todayIso() {
  return toIsoDate(new Date())
}

export function isNegative(amount: string | null | undefined) {
  return !!amount && amount.trim().startsWith('-')
}
