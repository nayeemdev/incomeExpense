import { createFileRoute } from '@tanstack/react-router'

export const Route = createFileRoute('/')({
  component: Home,
})

function Home() {
  return (
    <main className="flex min-h-svh items-center justify-center">
      <h1 className="text-2xl font-semibold tracking-tight">LedgerFly</h1>
    </main>
  )
}
