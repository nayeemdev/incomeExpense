import { addMonths, endOfMonth, format, parse, startOfMonth } from 'date-fns'
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
