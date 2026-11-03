import { Outlet, createFileRoute, redirect } from '@tanstack/react-router'
import { Logo } from '@/components/app/logo'
import { userQuery } from '@/lib/api/queries'

export const Route = createFileRoute('/_guest')({
  beforeLoad: async ({ context }) => {
    const user = await context.queryClient.fetchQuery(userQuery).catch(() => null)

    if (user) throw redirect({ to: '/' })
  },
  component: GuestLayout,
})

function GuestLayout() {
  return (
    <div className="bg-muted/40 flex min-h-svh flex-col items-center justify-center gap-6 p-4">
      <Logo className="text-lg" />
      <div className="w-full max-w-sm">
        <Outlet />
      </div>
    </div>
  )
}
