import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import i18n from '@/i18n'
import { api } from './client'
import { ledgerKey } from './queries'
import type { Resource, TransactionGroup, TransactionGroupInput } from './types'

export function useInvalidateLedger(ledgerId: number) {
  const queryClient = useQueryClient()

  return () => queryClient.invalidateQueries({ queryKey: ledgerKey(ledgerId) })
}

export function useSaveTransaction(ledgerId: number, groupId?: number) {
  const invalidate = useInvalidateLedger(ledgerId)

  return useMutation({
    mutationFn: (input: TransactionGroupInput) =>
      (groupId
        ? api.put<Resource<TransactionGroup>>(`/ledgers/${ledgerId}/transactions/${groupId}`, input)
        : api.post<Resource<TransactionGroup>>(`/ledgers/${ledgerId}/transactions`, input)
      ).then((response) => response.data),
    onSuccess: () => invalidate(),
  })
}

/**
 * Deletes right away and offers an undo in the toast instead of asking for confirmation first.
 */
export function useDeleteTransaction(ledgerId: number) {
  const invalidate = useInvalidateLedger(ledgerId)

  return useMutation({
    mutationFn: (groupId: number) => api.delete(`/ledgers/${ledgerId}/transactions/${groupId}`).then(() => groupId),
    onSuccess: async (groupId) => {
      await invalidate()
      toast(i18n.t('Transaction deleted'), {
        action: {
          label: i18n.t('Undo'),
          onClick: () => void api.post(`/ledgers/${ledgerId}/transactions/${groupId}/restore`).then(invalidate),
        },
      })
    },
  })
}
