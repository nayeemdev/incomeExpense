import i18n from '@/i18n'
import type { Account, AccountRef, TransactionType } from './api/types'

export type AccountChoice = { id: string } | { name: string } | null

export function allowedAccountTypes(type: TransactionType, side: 'source' | 'destination'): Account['type'][] {
  if (type === 'transfer') return ['asset']
  if (type === 'withdrawal') return side === 'source' ? ['asset', 'liability'] : ['expense', 'liability']

  return side === 'source' ? ['revenue', 'liability'] : ['asset', 'liability']
}

export function isCounterparty(type: TransactionType, side: 'source' | 'destination') {
  return (type === 'withdrawal' && side === 'destination') || (type === 'deposit' && side === 'source')
}

export function accountDisplayName(account: AccountRef) {
  if (account.type === 'initial_balance') return i18n.t('Starting balance')
  if (account.type === 'reconciliation') return i18n.t('Reconciliation')

  return account.name
}
