import { createContext, useContext } from 'react'
import type { TransactionType } from '@/lib/api/types'

export type QuickAddDefaults = { type?: TransactionType; accountId?: string; groupId?: string; split?: boolean }

export const QuickAddContext = createContext<(defaults?: QuickAddDefaults) => void>(() => {})

export function useQuickAdd() {
  return useContext(QuickAddContext)
}
