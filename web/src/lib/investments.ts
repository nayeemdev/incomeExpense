import i18n from '@/i18n'
import type { InvestmentEventType, InvestmentKind } from './api/types'

const t = (key: string) => i18n.t(key)

const unitKinds: InvestmentKind[] = ['stock', 'etf', 'mutual_fund', 'gold']
const interestKinds: InvestmentKind[] = ['deposit', 'savings_scheme', 'private']

export const tracksUnits = (kind: InvestmentKind) => unitKinds.includes(kind)

export const earnsInterest = (kind: InvestmentKind) => interestKinds.includes(kind)

/**
 * The words for each kind of activity, so a fixed deposit says "Add money" and "Withdraw" where shares say "Buy" and "Sell".
 */
export function investmentEventLabels(kind: InvestmentKind): Record<InvestmentEventType, string> {
  const units = tracksUnits(kind)
  const interest = earnsInterest(kind)

  return {
    buy: interest ? t('Add money') : t('Buy'),
    sell: interest ? t('Withdraw') : t('Sell'),
    income: units && kind !== 'gold' ? t('Dividend') : interest ? t('Payout') : t('Income'),
    valuation: units ? t('Update price') : t('Update value'),
  }
}

export const percent = (value: string | null, locale?: string) =>
  value === null ? null : new Intl.NumberFormat(locale, { style: 'percent', maximumFractionDigits: 2, signDisplay: 'exceptZero' }).format(Number(value) / 100)

export const units = (value: string, locale?: string) => new Intl.NumberFormat(locale, { maximumFractionDigits: 6 }).format(Number(value))
