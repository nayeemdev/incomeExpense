import { createFileRoute, redirect } from '@tanstack/react-router'
import { ledgersQuery } from '@/lib/api/queries'
import { storage } from '@/lib/storage'

export const Route = createFileRoute('/_app/')({
  beforeLoad: async ({ context }) => {
    const ledgers = await context.queryClient.fetchQuery(ledgersQuery)

    if (ledgers.length === 0) throw redirect({ to: '/onboarding' })

    const last = storage.get('ledger')
    const ledger = ledgers.find((item) => item.id === last) ?? ledgers[0]

    throw redirect({ to: '/$ledgerId', params: { ledgerId: ledger.id } })
  },
})
