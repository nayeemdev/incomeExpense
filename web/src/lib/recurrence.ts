import i18n from '@/i18n'
import type { Recurrence } from './api/types'

const ordinals = () => [i18n.t('first'), i18n.t('second'), i18n.t('third'), i18n.t('fourth'), i18n.t('fifth')]

export function weekdayNames(locale?: string) {
  return Array.from({ length: 7 }, (_, index) =>
    new Intl.DateTimeFormat(locale, { weekday: 'long' }).format(new Date(2024, 0, 1 + index)),
  )
}

export function describeSchedule(recurrence: Pick<Recurrence, 'repetition'>, locale?: string) {
  const repetition = recurrence.repetition

  if (!repetition) return ''

  const days = weekdayNames(locale)
  const count = repetition.skip + 1
  const plural = repetition.skip > 0

  switch (repetition.type) {
    case 'daily':
      return plural ? i18n.t('Every {{count}} days', { count }) : i18n.t('Every day')
    case 'weekly':
      return plural
        ? i18n.t('Every {{count}} weeks on {{day}}', { count, day: days[Number(repetition.moment) - 1] })
        : i18n.t('Every week on {{day}}', { day: days[Number(repetition.moment) - 1] })
    case 'monthly':
      return plural
        ? i18n.t('Every {{count}} months on day {{day}}', { count, day: repetition.moment })
        : i18n.t('Every month on day {{day}}', { day: repetition.moment })
    case 'ndom': {
      const [nth, weekday] = repetition.moment.split(',').map(Number)

      const values = { count, nth: ordinals()[nth - 1], day: days[weekday - 1] }

      return plural ? i18n.t('Every {{count}} months on the {{nth}} {{day}}', values) : i18n.t('Every month on the {{nth}} {{day}}', values)
    }
    case 'yearly':
      return i18n.t('Every year on {{date}}', {
        date: new Intl.DateTimeFormat(locale, { day: 'numeric', month: 'long' }).format(new Date(`2024-${repetition.moment}T00:00:00`)),
      })
  }
}

export { ordinals }
