import { createFileRoute, redirect } from '@tanstack/react-router'
import { ledgersQuery } from '@/lib/api/queries'
import { storage } from '@/lib/storage'

export const Route = createFileRoute('/_app/quick-add')({
  beforeLoad: async ({ context }) => {
    const ledgers = await context.queryClient.fetchQuery(ledgersQuery)

    if (ledgers.length === 0) throw redirect({ to: '/onboarding' })

    const ledger = ledgers.find((item) => item.id === Number(storage.get('ledger'))) ?? ledgers[0]

    throw redirect({ to: '/$ledgerId', params: { ledgerId: String(ledger.id) }, search: { add: true } })
  },
})
