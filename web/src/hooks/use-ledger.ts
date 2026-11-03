import { useQuery, useSuspenseQuery } from '@tanstack/react-query'
import { useParams } from '@tanstack/react-router'
import { userQuery } from '@/lib/api/queries'
import { ledgerQuery } from '@/lib/api/queries'

export function useLedgerId() {
  const { ledgerId } = useParams({ from: '/_app/$ledgerId' })

  return Number(ledgerId)
}

export function useLedger() {
  return useSuspenseQuery(ledgerQuery(useLedgerId())).data
}

export function useCanEdit() {
  return useLedger().role !== 'viewer'
}

export function useLocale() {
  return useQuery(userQuery).data?.preferences.locale
}
