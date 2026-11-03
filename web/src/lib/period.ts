import { addMonths, endOfMonth, endOfYear, format, parse, startOfMonth, startOfYear, subMonths, subYears } from 'date-fns'
import { toIsoDate } from './format'

export function monthRange(month: string | undefined, today: Date) {
  const current = month ? parse(month, 'yyyy-MM', today) : today

  return {
    current,
    start: toIsoDate(startOfMonth(current)),
    end: toIsoDate(endOfMonth(current)),
    shift: (offset: number) => format(addMonths(current, offset), 'yyyy-MM'),
  }
}

export function rangePresets(today: Date) {
  return {
    this_month: { start: toIsoDate(startOfMonth(today)), end: toIsoDate(endOfMonth(today)) },
    last_month: { start: toIsoDate(startOfMonth(subMonths(today, 1))), end: toIsoDate(endOfMonth(subMonths(today, 1))) },
    last_3_months: { start: toIsoDate(startOfMonth(subMonths(today, 2))), end: toIsoDate(endOfMonth(today)) },
    this_year: { start: toIsoDate(startOfYear(today)), end: toIsoDate(endOfYear(today)) },
    last_year: { start: toIsoDate(startOfYear(subYears(today, 1))), end: toIsoDate(endOfYear(subYears(today, 1))) },
    last_12_months: { start: toIsoDate(startOfMonth(subMonths(today, 11))), end: toIsoDate(endOfMonth(today)) },
  }
}
