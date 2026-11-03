import { useQuery } from '@tanstack/react-query'
import { Outlet, createFileRoute, redirect } from '@tanstack/react-router'
import { useTheme } from '@/hooks/use-theme'
import { useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import { ApiError } from '@/lib/api/client'
import { userQuery } from '@/lib/api/queries'

export const Route = createFileRoute('/_app')({
  beforeLoad: async ({ context, location }) => {
    try {
      return { user: await context.queryClient.fetchQuery(userQuery) }
    } catch (error) {
      if (error instanceof ApiError && error.isUnauthenticated) {
        throw redirect({ to: '/login', search: { redirect: location.href } })
      }

      throw error
    }
  },
  component: AppLayout,
})

function AppLayout() {
  const { data: user } = useQuery(userQuery)
  const { setTheme } = useTheme()
  const { i18n } = useTranslation()

  useEffect(() => {
    if (!user) return

    setTheme(user.preferences.theme)

    if (i18n.language !== user.preferences.locale && i18n.hasResourceBundle(user.preferences.locale, 'translation')) {
      void i18n.changeLanguage(user.preferences.locale)
    }
  }, [user, setTheme, i18n])

  return <Outlet />
}
